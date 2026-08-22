<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ConsumeMessengerMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Process\Process;

#[AsMessageHandler]
final readonly class ConsumeMessengerHandler
{
    public function __invoke(ConsumeMessengerMessage $msg): void
    {
        $process = new Process([
            'php', 'bin/console', 'messenger:consume',
            $msg->transport,
            '--time-limit=' . $msg->timeLimit,
            '--limit=' . $msg->messageLimit,
        ]);
        $process->setTimeout($msg->timeLimit + 30);
        $process->run();
    }
}