<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Enum\UserStatus;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * IMPORTANT: invoked by the security firewall right before authentication
 * succeeds. NOTE: a user with the {@see UserStatus::Blocked} status must
 * NOT be allowed to log in — the exception is caught by Symfony and
 * rendered on the login page.
 */
final readonly class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if ($user->getStatus() === UserStatus::Blocked) {
            throw new CustomUserMessageAuthenticationException(
                'Your account has been blocked. Please contact the administrator.'
            );
        }
    }

    public function checkPostAuth(UserInterface $user, TokenInterface|null $token = null): void
    {
        // NOTE: nothing to do after successful authentication — the
        // UserActivitySubscriber / InactiveUserListener handles mid-session checks.
    }
}