<?php

declare(strict_types=1);

namespace App\Api\Security;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class ApiTokenAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    private const TOKEN_EXPIRY = 3600 * 24; // 24 hours

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly string $secret,
    ) {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        $message = 'Authentication required. Please provide a valid Bearer token.';
        if ($authException !== null) {
            $message = $authException->getMessage();
        }

        return new JsonResponse([
            'error' => $message,
        ], Response::HTTP_UNAUTHORIZED);
    }

    public function supports(Request $request): ?bool
    {
        // Check for Authorization header (case-insensitive)
        $authHeader = $request->headers->get('Authorization')
            ?? $request->headers->get('authorization')
            ?? '';

        // Support both "Bearer " and "bearer " prefixes
        return !empty($authHeader) && stripos($authHeader, 'Bearer ') === 0;
    }

    public function authenticate(Request $request): Passport
    {
        $authHeader = $request->headers->get('Authorization')
            ?? $request->headers->get('authorization')
            ?? '';

        // Remove 'Bearer ' prefix (case-insensitive) and trim
        $token = trim(substr($authHeader, 7));

        if (empty($token)) {
            throw new CustomUserMessageAuthenticationException('Empty token provided');
        }

        $decoded = base64_decode($token, true);
        if ($decoded === false) {
            throw new CustomUserMessageAuthenticationException('Invalid token encoding');
        }

        $parts = explode(':', $decoded);
        if (count($parts) !== 3) {
            throw new CustomUserMessageAuthenticationException('Invalid token structure');
        }

        [$userId, $timestamp, $hash] = $parts;

        // Verify token hasn't expired
        if ((time() - (int) $timestamp) > self::TOKEN_EXPIRY) {
            throw new CustomUserMessageAuthenticationException('Token has expired');
        }

        // Verify token hash
        $expectedHash = hash_hmac('sha256', $userId.':'.$timestamp, $this->secret);
        if (!hash_equals($expectedHash, $hash)) {
            throw new CustomUserMessageAuthenticationException('Invalid token signature');
        }

        return new SelfValidatingPassport(
            new UserBadge($userId, function (string $userId) {
                $user = $this->userRepository->find((int) $userId);
                if (!$user) {
                    throw new CustomUserMessageAuthenticationException('User not found');
                }
                if (!$user->isActive()) {
                    throw new CustomUserMessageAuthenticationException('Account is disabled');
                }

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Continue to the controller
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'error' => $exception->getMessage(),
        ], Response::HTTP_UNAUTHORIZED);
    }
}
