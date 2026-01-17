<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api')]
class AuthApiController extends AbstractController
{
    #[Route('/login', name: 'api_login', methods: ['POST'])]
    #[OA\Post(
        path: '/api/login',
        summary: 'Login to create a session',
        tags: ['Authentication']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OA\Property(property: 'email', type: 'string', example: 'admin@example.com'),
                new OA\Property(property: 'password', type: 'string', example: 'password'),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Login successful - session created',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Login successful'),
                new OA\Property(property: 'user', properties: [
                    new OA\Property(property: 'id', type: 'integer', example: 1),
                    new OA\Property(property: 'email', type: 'string', example: 'admin@example.com'),
                    new OA\Property(property: 'firstName', type: 'string', example: 'John'),
                    new OA\Property(property: 'lastName', type: 'string', example: 'Doe'),
                    new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
                ], type: 'object'),
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Invalid credentials'
    )]
    public function login(#[CurrentUser] ?User $user): JsonResponse
    {
        // This method is handled by json_login authenticator
        // If we reach here, the user is authenticated
        if (null === $user) {
            return $this->json([
                'error' => 'Invalid credentials',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'message' => 'Login successful',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'roles' => $user->getRoles(),
            ],
        ]);
    }

    #[Route('/me', name: 'api_me', methods: ['GET'])]
    #[OA\Get(
        path: '/api/me',
        summary: 'Get current authenticated user',
        tags: ['Authentication']
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns current user info',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'id', type: 'integer', example: 1),
                new OA\Property(property: 'email', type: 'string', example: 'admin@example.com'),
                new OA\Property(property: 'firstName', type: 'string', example: 'John'),
                new OA\Property(property: 'lastName', type: 'string', example: 'Doe'),
                new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Not authenticated'
    )]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'roles' => $user->getRoles(),
        ]);
    }

    #[Route('/logout', name: 'api_logout', methods: ['POST'])]
    #[OA\Post(
        path: '/api/logout',
        summary: 'Logout and destroy session',
        tags: ['Authentication']
    )]
    #[OA\Response(
        response: 200,
        description: 'Logout successful'
    )]
    public function logout(): JsonResponse
    {
        // This will be handled by the logout handler
        // But we can also just return success since session will be invalidated
        return $this->json(['message' => 'Logout successful']);
    }
}
