<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class LocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly string $defaultLocale = 'en',
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Try to get locale from the URL
        if (!$request->hasPreviousSession()) {
            return;
        }

        // If no explicit locale has been set on this request, use the session locale
        if (!$request->attributes->get('_locale')) {
            $request->setLocale($request->getSession()->get('_locale', $this->defaultLocale));
        } else {
            // Store locale in session for future requests
            $request->getSession()->set('_locale', $request->getLocale());
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
        ];
    }
}
