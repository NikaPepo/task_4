<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\User;
use App\Message\SendVerificationEmail;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment;

/**
 * IMPORTANT: handles the {@see SendVerificationEmail} message asynchronously
 * (via messenger consumer). NOTE: looks up the user fresh from the DB to
 * avoid using a stale entity from the request scope.
 */
#[AsMessageHandler]
final readonly class SendVerificationEmailHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private Environment $twig,
    ) {
    }

    public function __invoke(SendVerificationEmail $message): void
    {
        /** @var User|null $user */
        $user = $this->entityManager->getRepository(User::class)->find($message->userId);
        if ($user === null) {
            // NOTE: user was deleted before the message was consumed — drop quietly.
            return;
        }

        $token = $user->getEmailVerificationToken();
        if ($token === null) {
            // NOTE: already verified, nothing to do.
            return;
        }

        // IMPORTANT: resolve env vars at invocation time (so they stay dynamic).
        $baseUrl = $_SERVER['APP_BASE_URL'] ?? $_ENV['APP_BASE_URL'] ?? 'http://localhost:8000';
        $verifyUrl = rtrim($baseUrl, '/').'/verify?token='.urlencode($token);

        $fromEmail = $_SERVER['MAILER_FROM_EMAIL'] ?? $_ENV['MAILER_FROM_EMAIL'] ?? 'noreply@example.com';
        $fromName = $_SERVER['MAILER_FROM_NAME'] ?? $_ENV['MAILER_FROM_NAME'] ?? 'User Management';

        $html = $this->twig->render('emails/verification.html.twig', [
            'name' => $user->getName(),
            'url' => $verifyUrl,
        ]);

        $email = (new Email())
            ->from(new Address($fromEmail, $fromName))
            ->to($user->getEmail())
            ->subject('Confirm your e-mail address')
            ->html($html);

        $this->mailer->send($email);
    }
}