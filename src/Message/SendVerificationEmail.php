<?php

declare(strict_types=1);

namespace App\Message;

/**
 * IMPORTANT: messenger message dispatched after a successful registration.
 * The async transport (configured in messenger.yaml) ensures the e-mail is
 * NOT sent inline — the user is registered "right away", as required.
 */
final readonly class SendVerificationEmail
{
    public function __construct(
        public int $userId,
    ) {
    }
}