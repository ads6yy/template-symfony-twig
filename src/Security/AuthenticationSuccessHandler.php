<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use LogicException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

final readonly class AuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private RouterInterface $router,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): RedirectResponse
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            throw new LogicException('L\'utilisateur authentifié doit être une instance de App\Entity\User.');
        }

        // Les admins vont à la liste des utilisateurs
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return new RedirectResponse($this->router->generate('app_user_index'));
        }

        // Les utilisateurs réguliers vont à leur profil
        return new RedirectResponse($this->router->generate('app_user_show', ['id' => $user->id]));
    }
}
