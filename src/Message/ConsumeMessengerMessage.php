<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Scheduled message dispatched by App\Schedule. Its handler
 * (App\MessageHandler\ConsumeMessengerHandler) runs `messenger:consume`
 * as a short subprocess, so we don't need a long-running worker.
 */
final readonly class ConsumeMessengerMessage
{
    public function __construct(
        public string $transport = 'async',
        public int $timeLimit = 50,
        public int $messageLimit = 20,
    ) {
    }
}