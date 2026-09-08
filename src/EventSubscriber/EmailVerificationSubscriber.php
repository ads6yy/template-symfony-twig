<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Prevents authenticated but unverified users from reaching protected web pages.
 *
 * Login succeeds, but every web request is redirected to the "resend verification"
 * notice page until the account email has been verified. API routes are left
 * untouched (email verification is web-only for now).
 */
final class EmailVerificationSubscriber implements EventSubscriberInterface
{
    /**
     * Routes an unverified user is still allowed to reach (to avoid a redirect loop).
     */
    private const ALLOWED_ROUTES = [
        'app_auth_login',
        'app_auth_login_check',
        'app_auth_logout',
        'app_auth_verify_email',
        'app_auth_resend_verification',
    ];

    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Email verification is web-only: never interfere with API requests.
        if (str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $route = $request->attributes->get('_route');
        if (!is_string($route) || in_array($route, self::ALLOWED_ROUTES, true)) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || $user->isVerified()) {
            return;
        }

        $event->setResponse(new RedirectResponse(
            $this->urlGenerator->generate('app_auth_resend_verification', [
                '_locale' => $request->getLocale(),
            ])
        ));
    }

    public static function getSubscribedEvents(): array
    {
        // Priority 6: after the firewall listener (8) so the security token is set.
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 6]],
        ];
    }
}
