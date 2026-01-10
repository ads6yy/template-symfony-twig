<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordType;
use App\Form\UserType;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/users', name: 'app_user_')]
final class UserController extends AbstractController
{
    public function __construct(
        protected UserRepository $userRepository,
        protected EntityManagerInterface $entityManager,
        protected UserPasswordHasherInterface $passwordHasher,
        protected LoggerInterface $logger,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $this->logger->info('User index accessed');

        $users = $this->userRepository->findAll();

        return $this->render('user/index.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(User $user): Response
    {
        $currentUser = $this->getUser();

        if ($currentUser !== $user && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('error.access_denied.edit_profile');
        }

        return $this->render('user/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $this->logger->info('New user creation form accessed');

        $user = new User();
        $form = $this->createForm(UserType::class, $user, [
            'is_admin' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('password')->getData();
            $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->logger->info('User created', ['email' => $user->getEmail()]);
            $this->addFlash('success', 'User created successfully!');

            return $this->redirectToRoute('app_user_index');
        }

        return $this->render('user/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, User $user): Response
    {
        $currentUser = $this->getUser();

        if ($currentUser !== $user && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('You can only edit your own profile.');
        }

        $this->logger->info('User edit form accessed', ['id' => $user->getId()]);

        $form = $this->createForm(UserType::class, $user, [
            'is_admin' => $this->isGranted('ROLE_ADMIN'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // In UserType the password field is normally added only when creating a new user.
            // This check ensures we handle it safely if it is present during edit (e.g. edge cases or config changes).
            if ($form->has('password')) {
                $plainPassword = $form->get('password')->getData();
                if ($plainPassword) {
                    $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
                    $user->setPassword($hashedPassword);
                }
            }

            $user->setUpdatedAt(new DateTimeImmutable());
            $this->entityManager->flush();

            $this->logger->info('User updated', ['id' => $user->getId()]);
            $this->addFlash('success', 'User updated successfully!');

            return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
        }

        return $this->render('user/edit.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, User $user): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $this->logger->info('User deletion requested', ['id' => $user->getId()]);

        if ((is_string($request->request->get('_token')) || null === $request->request->get('_token'))
            && $this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($user);
            $this->entityManager->flush();

            $this->logger->info('User deleted', ['email' => $user->getEmail()]);
            $this->addFlash('success', 'User deleted successfully!');
        } else {
            $this->addFlash('error', 'flash.user.invalid_csrf');
        }

        return $this->redirectToRoute('app_user_index');
    }

    #[Route('/{id}/toggle-active', name: 'toggle_active', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleActive(Request $request, User $user): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ((is_string($request->request->get('_token')) || null === $request->request->get('_token'))
            && $this->isCsrfTokenValid('toggle'.$user->getId(), $request->request->get('_token'))) {
            $user->setIsActive(!$user->isActive());
            $this->entityManager->flush();

            $this->logger->info('User active status toggled', ['id' => $user->getId(), 'active' => $user->isActive()]);
            $this->addFlash('success', 'flash.user.status_updated');
        }

        return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
    }

    #[Route('/{id}/change-password', name: 'change_password', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function changePassword(Request $request, User $user): Response
    {
        $currentUser = $this->getUser();

        // A user can change their password, admins can change any user's password
        if ($currentUser !== $user && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('error.access_denied.change_password');
        }

        $this->logger->info('Change password form accessed', ['id' => $user->getId()]);

        // Admins don't need to enter the old password
        $requireOldPassword = $currentUser === $user;

        $form = $this->createForm(ChangePasswordType::class, [], [
            'require_old_password' => $requireOldPassword,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Access unmapped form data
            $oldPassword = $form->has('oldPassword') ? $form->get('oldPassword')->getData() : null;
            $newPassword = $form->get('newPassword')->getData();
            $confirmPassword = $form->get('confirmPassword')->getData();

            // Check old password if user is changing their own password
            if ($requireOldPassword && $oldPassword) {
                if (!$this->passwordHasher->isPasswordValid($user, $oldPassword)) {
                    $form->get('oldPassword')->addError(new \Symfony\Component\Form\FormError('The old password is incorrect.'));

                    return $this->render('user/change_password.html.twig', [
                        'form' => $form,
                        'user' => $user,
                        'require_old_password' => $requireOldPassword,
                    ]);
                }
            }

            // Check that both passwords match
            if ($newPassword !== $confirmPassword) {
                $form->get('confirmPassword')->addError(
                    new \Symfony\Component\Form\FormError(
                        '',
                        'validation.password.mismatch',
                        [],
                        null,
                        'validators'
                    )
                );

                return $this->render('user/change_password.html.twig', [
                    'form' => $form,
                    'user' => $user,
                    'require_old_password' => $requireOldPassword,
                ]);
            }

            $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);
            $user->setUpdatedAt(new DateTimeImmutable());
            $this->entityManager->flush();

            $this->logger->info('User password changed', ['id' => $user->getId()]);
            $this->addFlash('success', 'flash.user.password_changed');

            return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
        }

        return $this->render('user/change_password.html.twig', [
            'form' => $form,
            'user' => $user,
            'require_old_password' => $requireOldPassword,
        ]);
    }
}
