<?php

declare(strict_types=1);

namespace App\Twig;

use Exception;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class LocaleSwitcherExtension extends AbstractExtension
{
    private const string FALLBACK_ROUTE = 'app_template';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('locale_switcher_url', $this->getLocaleSwitcherUrl(...)),
        ];
    }

    /**
     * Generates a URL to switch locale while preserving the current route and parameters.
     * If the current route is not available or generates an error, returns the home page URL.
     */
    public function getLocaleSwitcherUrl(string $targetLocale): string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return $this->generateFallbackUrl($targetLocale);
        }

        // Get the current route
        $currentRoute = $request->attributes->get('_route');

        // If no usable route, or a system route (starting with "_"), use the fallback
        if (!is_string($currentRoute) || $currentRoute === '' || $currentRoute[0] === '_') {
            // Routes starting with _ are system routes (e.g. _wdt, _profiler)
            return $this->generateFallbackUrl($targetLocale);
        }

        // Get current route parameters
        $routeParams = $request->attributes->get('_route_params', []);

        // Merge with the new locale
        $routeParams['_locale'] = $targetLocale;

        try {
            // Attempt to generate the URL with the new locale
            return $this->urlGenerator->generate($currentRoute, $routeParams);
        } catch (Exception) {
            // If generation fails (invalid route, missing parameters, etc.)
            // Return the home page URL with the correct locale
            return $this->generateFallbackUrl($targetLocale);
        }
    }

    private function generateFallbackUrl(string $locale): string
    {
        try {
            return $this->urlGenerator->generate(self::FALLBACK_ROUTE, ['_locale' => $locale]);
        } catch (Exception) {
            // As a last resort, just return the root with the locale
            return '/'.$locale;
        }
    }
}
