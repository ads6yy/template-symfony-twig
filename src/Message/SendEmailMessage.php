<?php

declare(strict_types=1);

namespace App\Message;

final readonly class SendEmailMessage
{
    public function __construct(
        private string $from,
        private string $to,
        private string $subject,
        private string $htmlContent,
    ) {
    }

    public function getFrom(): string
    {
        return $this->from;
    }

    public function getTo(): string
    {
        return $this->to;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getHtmlContent(): string
    {
        return $this->htmlContent;
    }
}
