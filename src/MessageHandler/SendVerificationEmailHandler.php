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

        $email = (new Email())
            ->from(new Address($fromEmail, $fromName))
            ->to($user->getEmail())
            ->subject('Confirm your e-mail address')
            ->html($this->renderBody($user->getName(), $verifyUrl));

        $this->mailer->send($email);
    }

    /**
     * NOTE: small, deliberately boring HTML body for the verification e-mail.
     */
    private function renderBody(string $name, string $url): string
    {
        // IMPORTANT: htmlspecialchars-escape user-controlled fragments.
        $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html><body style="font-family:Arial,sans-serif;color:#222">
<h2 style="margin-bottom:0.25em">Welcome, {$safeName}!</h2>
<p>Please confirm your e-mail address by clicking the link below.</p>
<p style="margin:1.5em 0">
  <a href="{$url}" style="background:#0d6efd;color:#fff;padding:0.5em 1em;border-radius:4px;text-decoration:none">
    Confirm e-mail
  </a>
</p>
<p>If the button does not work, copy this link into your browser:</p>
<p style="word-break:break-all;color:#555">{$url}</p>
<p style="color:#888;font-size:0.85em">If you did not register, simply ignore this message.</p>
</body></html>
HTML;
    }
}