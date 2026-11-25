<?php

declare(strict_types=1);

namespace Botforge\Middleware;

use Botforge\Context\Context;
use Closure;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Writes a log line when an update arrives and another one when it is done (with the time it took).
 * If the handler fails, the error is logged and then thrown again.
 *
 * We log ids and the update type, never the message text or the bot token.
 */
final class LoggingMiddleware implements Middleware
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function process(Context $ctx, Closure $next): mixed
    {
        $update = $ctx->update();
        $info = [
            'update_id' => $update->updateId,
            'type' => $update->type(),
            'user_id' => $ctx->from()?->id,
            'chat_id' => $ctx->chat()?->id,
        ];

        $this->logger->info('Update received', $info);
        $start = hrtime(true);

        try {
            $result = $next($ctx);
        } catch (Throwable $e) {
            $this->logger->error('Update failed', $info + [
                'exception' => $e::class,
                'error' => $e->getMessage(),
                'duration_ms' => $this->elapsedMs($start),
            ]);

            throw $e; // not our job to hide it
        }

        $this->logger->info('Update handled', $info + ['duration_ms' => $this->elapsedMs($start)]);

        return $result;
    }

    private function elapsedMs(int $start): float
    {
        return round((hrtime(true) - $start) / 1_000_000, 1);
    }
}