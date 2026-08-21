<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Enum\UserStatus;
use App\Message\SendVerificationEmail;
use App\Util\IdGenerator;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * IMPORTANT: orchestrates the registration side-effects — creating the
 * User, persisting it and dispatching an async message to send the
 * verification e-mail. NOTE: the e-mail is dispatched (not sent inline)
 * so the user is registered "right away" as the task requires.
 */
final readonly class RegistrationService
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * IMPORTANT: persists a new {@see User} with status Unverified and
     * dispatches the verification e-mail asynchronously.
     */
    public function register(
        string $name,
        string $email,
        string $plainPassword,
    ): User {
        $user = new User();

        $user->setName($name);
        $user->setEmail($email);
        $user->setStatus(UserStatus::Unverified);
        $user->setCreatedAt(new DateTimeImmutable());

        // NOTE: a fresh verification token is generated right before persisting.
        $user->setEmailVerificationToken(IdGenerator::getVerificationToken());

        $user->setPassword(
            $this->passwordHasher->hashPassword($user, $plainPassword)
        );

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // IMPORTANT: dispatching the async message is the last side-effect;
        // the user is registered even if e-mail delivery fails.
        $this->messageBus->dispatch(new SendVerificationEmail($user->getId()));

        return $user;
    }
}