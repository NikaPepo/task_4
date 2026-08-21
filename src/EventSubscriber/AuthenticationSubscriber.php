<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * IMPORTANT: on every successful login, store the current timestamp in
 * the user record. NOTE: this is what powers the "last login time"
 * column on the admin table — without this subscriber the column would
 * always be null and the default sort would be a no-op.
 */
final readonly class AuthenticationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $user->setLastLoginAt(new DateTimeImmutable());
        $this->entityManager->flush();
    }
}