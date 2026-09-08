<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationType;
use App\Message\SendEmailMessage;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Notifier\Message\DesktopMessage;
use Symfony\Component\Notifier\TexterInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelper;
use Throwable;

#[Route('/auth', name: 'app_auth_')]
final class AuthController extends AbstractController
{
    public function __construct(
        protected UserRepository $userRepository,
        protected EntityManagerInterface $entityManager,
        protected UserPasswordHasherInterface $passwordHasher,
        protected LoggerInterface $logger,
        protected TranslatorInterface $translator,
        protected MailerInterface $mailer,
        protected MessageBusInterface $messageBus,
        protected TexterInterface $texter,
        protected VerifyEmailHelper $verifyEmailHelper,
    ) {
    }

    #[Route('/login', name: 'login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_template');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('auth/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/login_check', name: 'login_check', methods: ['POST'])]
    public function loginCheck(): void
    {
        throw new LogicException('This method will be intercepted by the security firewall.');
    }

    #[Route('/logout', name: 'logout', methods: ['GET'])]
    public function logout(): void
    {
        throw new LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/register', name: 'register', methods: ['GET', 'POST'])]
    public function register(Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_template');
        }

        $form = $this->createForm(RegistrationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{email: string, firstName?: string, lastName?: string, password: string} $data */
            $data = $form->getData();

            // Check if email already exists
            $existingUser = $this->userRepository->findOneBy(['email' => $data['email']]);
            if ($existingUser) {
                $this->addFlash('error', 'flash.auth.email_in_use');

                return $this->render('auth/register.html.twig', ['form' => $form]);
            }

            $user = new User();
            $user->setEmail($data['email']);
            $user->setFirstName($data['firstName'] ?? '');
            $user->setLastName($data['lastName'] ?? '');
            $user->setRoles(['ROLE_USER']);

            $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
            $user->setPassword($hashedPassword);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Send the email verification link asynchronously
            $this->sendVerificationEmail($user, $request->getLocale());

            $this->logger->info('User registered', ['email' => $user->getEmail()]);
            $this->addFlash('success', 'flash.auth.verify_email_sent');

            try {
                $message = new DesktopMessage(
                    'You are now registered ! 🎉',
                    'Welcome to  Template Symfony + Twig application.'
                );
                $this->texter->send($message);
            } catch (Throwable $exception) {
                $this->logger->warning('Unable to send Desktop notification @exception', ['exception' => $exception->getMessage()]);
            }

            return $this->redirectToRoute('app_auth_login');
        }

        return $this->render('auth/register.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/verify/email', name: 'verify_email', methods: ['GET'])]
    public function verifyUserEmail(Request $request): Response
    {
        $userId = $request->query->get('id');
        if ($userId === null || $userId === '') {
            $this->addFlash('error', 'flash.auth.verify_email_error');

            return $this->redirectToRoute('app_auth_login');
        }

        $user = $this->userRepository->find((int) $userId);
        if (!$user instanceof User) {
            $this->addFlash('error', 'flash.auth.verify_email_error');

            return $this->redirectToRoute('app_auth_login');
        }

        if ($user->isVerified()) {
            $this->addFlash('info', 'flash.auth.verify_email_already');

            return $this->redirectToRoute('app_auth_login');
        }

        try {
            $this->verifyEmailHelper->validateEmailConfirmationFromRequest(
                $request,
                (string) $user->getId(),
                (string) $user->getEmail(),
            );
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->logger->info('Email verification failed', [
                'email' => $user->getEmail(),
                'reason' => $exception->getReason(),
            ]);
            $this->addFlash('error', 'flash.auth.verify_email_error');

            return $this->redirectToRoute('app_auth_login');
        }

        $user->setIsVerified(true);
        $this->entityManager->flush();

        $this->logger->info('Email verified', ['email' => $user->getEmail()]);
        $this->addFlash('success', 'flash.auth.verify_email_success');

        return $this->redirectToRoute('app_auth_login');
    }

    #[Route('/verify/resend', name: 'resend_verification', methods: ['GET', 'POST'])]
    public function resendVerification(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $submittedToken = (string) $request->request->get('_csrf_token');
            if (!$this->isCsrfTokenValid('resend_verification', $submittedToken)) {
                $this->addFlash('error', 'flash.user.invalid_csrf');

                return $this->redirectToRoute('app_auth_resend_verification');
            }

            $email = trim((string) $request->request->get('email'));
            $user = $this->userRepository->findOneBy(['email' => $email]);

            // Only actually send when the account exists and is not yet verified,
            // but always show the same message to avoid user enumeration.
            if ($user instanceof User && !$user->isVerified()) {
                $this->sendVerificationEmail($user, $request->getLocale());
            }

            $this->addFlash('success', 'flash.auth.verify_email_sent');

            return $this->redirectToRoute('app_auth_login');
        }

        return $this->render('auth/resend_verification.html.twig');
    }

    private function sendVerificationEmail(User $user, string $locale): void
    {
        $signature = $this->verifyEmailHelper->generateSignature(
            'app_auth_verify_email',
            (string) $user->getId(),
            (string) $user->getEmail(),
            ['id' => (string) $user->getId()],
        );

        $expiresText = $this->translator->trans(
            $signature->getExpirationMessageKey(),
            $signature->getExpirationMessageData(),
            'messages',
            $locale,
        );

        $htmlContent = $this->renderView('emails/email_verification.html.twig', [
            'user' => $user,
            'locale' => $locale,
            'signedUrl' => $signature->getSignedUrl(),
            'expiresText' => $expiresText,
        ]);

        $emailMessage = new SendEmailMessage(
            from: 'noreply@example.com',
            to: (string) $user->getEmail(),
            subject: $this->translator->trans('email.email_verification.subject', [], 'messages', $locale),
            htmlContent: $htmlContent
        );

        $this->messageBus->dispatch($emailMessage);
        $this->logger->info('Verification email queued for sending', ['email' => $user->getEmail()]);
    }
}
