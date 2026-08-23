<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * IMPORTANT: HTTP-triggered messenger consumer.
 *
 * External cron (cron-job.org, UptimeRobot, GitHub Actions…) hits this
 * endpoint once a minute. The endpoint runs `messenger:consume` for a
 * short, bounded time and returns.
 *
 * Auth: requires header `X-Cron-Token: <CRON_TOKEN>`. Without it, returns
 * 401. This keeps random visitors from draining your queue.
 */
final class MessengerConsumeController
{
    public function __construct(private readonly KernelInterface $kernel) {}

    #[Route('/messenger/consume', name: 'messenger_consume', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        // 1) Auth — token from header, compared in constant time
        $expected = (string) ($_ENV['CRON_TOKEN'] ?? '');
        $provided = (string) $request->headers->get('X-Cron-Token', '');
        if ($expected === '' || !hash_equals($expected, $provided)) {
            return new JsonResponse(['error' => 'unauthorized'], 401);
        }

        // 2) Close the HTTP response immediately, keep working in background.
        //    fastcgi_finish_request() exists only under PHP-FPM, which is what
        //    Render + our Dockerfile use, so this is safe.
        ignore_user_abort(true);
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        // 3) Run messenger:consume async with the same bounds as before
        $application = new Application($this->kernel);
        $application->setAutoExit(false);

        $input = new ArrayInput([
            'receivers'      => ['async'],
            '--limit'        => 20,
            '--time-limit'   => 50,
            '--memory-limit' => '128M',
            '-v'             => true,
        ]);
        $output = new BufferedOutput();
        $exitCode = $application->run($input, $output);

        // After fastcgi_finish_request() this won't reach the client, but
        // it lands in Render's logs / var/log/messenger.log for debugging.
        error_log(sprintf(
            '[messenger:consume] exit=%d output=%s',
            $exitCode,
            $output->fetch()
        ));

        return new JsonResponse(['status' => 'started']);
    }
}
