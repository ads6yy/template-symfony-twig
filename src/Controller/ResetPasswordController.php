<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordType;
use App\Form\ResetPasswordRequestType;
use App\Message\SendEmailMessage;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelper;

#[Route('/auth/reset-password', name: 'app_')]
final class ResetPasswordController extends AbstractController
{
    use ResetPasswordControllerTrait;

    public function __construct(
        private readonly ResetPasswordHelper $resetPasswordHelper,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly MessageBusInterface $messageBus,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Display and process the "forgot password" request form.
     */
    #[Route('', name: 'forgot_password_request', methods: ['GET', 'POST'])]
    public function request(Request $request): Response
    {
        $form = $this->createForm(ResetPasswordRequestType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{email: string} $data */
            $data = $form->getData();

            return $this->processSendingPasswordResetEmail($data['email'], $request->getLocale());
        }

        return $this->render('reset_password/request.html.twig', [
            'requestForm' => $form,
        ]);
    }

    /**
     * Confirmation page shown after requesting a reset (no user enumeration).
     */
    #[Route('/check-email', name: 'check_email', methods: ['GET'])]
    public function checkEmail(): Response
    {
        // Prevent the real token from leaking: if it is not in the session,
        // show a fake one so this page reveals nothing about the account.
        $resetToken = $this->getTokenObjectFromSession();
        if ($resetToken === null) {
            $resetToken = $this->resetPasswordHelper->generateFakeResetToken();
        }

        return $this->render('reset_password/check_email.html.twig', [
            'resetToken' => $resetToken,
        ]);
    }

    /**
     * Validate the token and let the user choose a new password.
     */
    #[Route('/reset/{token}', name: 'reset_password', defaults: ['token' => null], methods: ['GET', 'POST'])]
    public function reset(Request $request, ?string $token = null): Response
    {
        if ($token !== null) {
            // Store the token in the session and remove it from the URL, to
            // avoid leaking it through the HTTP Referer header to third parties.
            $this->storeTokenInSession($token);

            return $this->redirectToRoute('app_reset_password');
        }

        $token = $this->getTokenFromSession();
        if ($token === null) {
            $this->addFlash('error', 'flash.auth.reset_no_token');

            return $this->redirectToRoute('app_forgot_password_request');
        }

        try {
            /** @var User $user */
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface $exception) {
            $this->logger->info('Reset password token validation failed', [
                'reason' => $exception->getReason(),
            ]);
            $this->addFlash('error', 'flash.auth.reset_invalid_token');

            return $this->redirectToRoute('app_forgot_password_request');
        }

        $form = $this->createForm(ChangePasswordType::class, [], [
            'require_old_password' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newPassword = (string) $form->get('newPassword')->getData();
            $confirmPassword = (string) $form->get('confirmPassword')->getData();

            if ($newPassword !== $confirmPassword) {
                $form->get('confirmPassword')->addError(new FormError(
                    $this->translator->trans('validation.password.mismatch', [], 'validators')
                ));

                return $this->render('reset_password/reset.html.twig', [
                    'resetForm' => $form,
                ]);
            }

            // A reset password token can only be used once: invalidate it.
            $this->resetPasswordHelper->removeResetRequest($token);

            $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
            $user->setUpdatedAt(new DateTimeImmutable());
            $this->entityManager->flush();

            $this->cleanSessionAfterReset();

            $this->logger->info('User password reset', ['email' => $user->getEmail()]);
            $this->addFlash('success', 'flash.auth.reset_success');

            return $this->redirectToRoute('app_auth_login');
        }

        return $this->render('reset_password/reset.html.twig', [
            'resetForm' => $form,
        ]);
    }

    private function processSendingPasswordResetEmail(string $emailFormData, string $locale): RedirectResponse
    {
        $user = $this->userRepository->findOneBy(['email' => $emailFormData]);

        // Do not reveal whether an account exists for the given email.
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_check_email');
        }

        try {
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
        } catch (ResetPasswordExceptionInterface $exception) {
            // Throttling or an existing pending request: stay silent, same UX.
            $this->logger->info('Reset password token not generated', [
                'reason' => $exception->getReason(),
            ]);

            return $this->redirectToRoute('app_check_email');
        }

        $resetUrl = $this->generateUrl(
            'app_reset_password',
            ['token' => $resetToken->getToken()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $expiresText = $this->translator->trans(
            $resetToken->getExpirationMessageKey(),
            $resetToken->getExpirationMessageData(),
            'messages',
            $locale,
        );

        $htmlContent = $this->renderView('emails/reset_password.html.twig', [
            'user' => $user,
            'locale' => $locale,
            'resetUrl' => $resetUrl,
            'expiresText' => $expiresText,
        ]);

        $this->messageBus->dispatch(new SendEmailMessage(
            from: 'noreply@example.com',
            to: (string) $user->getEmail(),
            subject: $this->translator->trans('email.reset_password.subject', [], 'messages', $locale),
            htmlContent: $htmlContent,
        ));

        $this->logger->info('Reset password email queued for sending', ['email' => $user->getEmail()]);

        $this->setTokenObjectInSession($resetToken);

        return $this->redirectToRoute('app_check_email');
    }
}
