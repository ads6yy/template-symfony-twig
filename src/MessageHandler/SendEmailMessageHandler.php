<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SendEmailMessage;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
final readonly class SendEmailMessageHandler
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendEmailMessage $message): void
    {
        $email = (new Email())
            ->from($message->getFrom())
            ->to($message->getTo())
            ->subject($message->getSubject())
            ->html($message->getHtmlContent());

        try {
            $this->mailer->send($email);
            $this->logger->info('Email sent successfully', [
                'to' => $message->getTo(),
                'subject' => $message->getSubject(),
            ]);
        } catch (Exception|TransportExceptionInterface $e) {
            $this->logger->error('Failed to send email', [
                'to' => $message->getTo(),
                'subject' => $message->getSubject(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
