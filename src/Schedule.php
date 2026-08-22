<?php

namespace App;

use App\Message\ConsumeMessengerMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule]
class Schedule implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
    ) {
    }

    public function getSchedule(): SymfonySchedule
    {
        return (new SymfonySchedule())
            ->stateful($this->cache) // ensure missed tasks are executed
            ->processOnlyLastMissedRun(true) // ensure only last missed task is run

            // IMPORTANT: every minute, dispatch ConsumeMessengerMessage.
            // Its handler runs `messenger:consume async` as a short
            // subprocess. The Symfony 8.1 scheduler integrates with
            // Messenger — DispatchSchedulerEventListener fires on every
            // consumed message and dispatches RecurringMessage to the
            // queue automatically.
            ->add(RecurringMessage::every('1 minute', new ConsumeMessengerMessage()))
        ;
    }
}