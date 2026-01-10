<?php

declare(strict_types=1);

namespace App\Twig;

use Symfony\Contracts\Translation\LocaleAwareInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class DateExtension extends AbstractExtension
{
    private const DATE_FORMATS = [
        'en' => [
            'date' => 'm/d/Y',
            'time' => 'g:i A',
            'datetime' => 'm/d/Y g:i A',
            'datetime_short' => 'm/d/Y H:i',
        ],
        'fr' => [
            'date' => 'd/m/Y',
            'time' => 'H:i',
            'datetime' => 'd/m/Y H:i',
            'datetime_short' => 'd/m/Y H:i',
        ],
    ];

    public function __construct(
        private readonly LocaleAwareInterface $translator
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('localized_date', $this->formatDate(...)),
            new TwigFilter('localized_datetime', $this->formatDateTime(...)),
        ];
    }

    public function formatDate(?\DateTimeInterface $date): string
    {
        if (null === $date) {
            return '—';
        }

        $locale = $this->translator->getLocale();
        $format = self::DATE_FORMATS[$locale]['date'] ?? self::DATE_FORMATS['en']['date'];

        return $date->format($format);
    }

    public function formatDateTime(?\DateTimeInterface $date, bool $includeSeconds = false): string
    {
        if (null === $date) {
            return '—';
        }

        $locale = $this->translator->getLocale();
        $format = self::DATE_FORMATS[$locale]['datetime_short'] ?? self::DATE_FORMATS['en']['datetime_short'];

        if ($includeSeconds) {
            $format .= ':s';
        }

        return $date->format($format);
    }
}

