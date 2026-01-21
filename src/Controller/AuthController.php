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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Contracts\Translation\TranslatorInterface;

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

            // Send welcome email asynchronously
            $locale = $request->getLocale();
            $htmlContent = $this->renderView('emails/user_registered.html.twig', [
                'user' => $user,
                'locale' => $locale,
            ]);

            $emailMessage = new SendEmailMessage(
                from: 'noreply@example.com',
                to: (string) $user->getEmail(),
                subject: $this->translator->trans('email.user_registered.subject', [], 'messages', $locale),
                htmlContent: $htmlContent
            );

            $this->messageBus->dispatch($emailMessage);
            $this->logger->info('Registration email queued for sending', ['email' => $user->getEmail()]);

            $this->logger->info('User registered', ['email' => $user->getEmail()]);
            $this->addFlash('success', 'flash.auth.registration_success');

            return $this->redirectToRoute('app_auth_login');
        }

        return $this->render('auth/register.html.twig', [
            'form' => $form,
        ]);
    }
}
