<?php

declare(strict_types=1);

namespace Botforge\Middleware;

use Botforge\Context\Context;
use Closure;

/**
 * "Maintenance mode" switched by a file: create the flag file to turn it ON, delete it to turn it OFF.
 * No restart or redeploy is needed. Admins in $bypassUserIds can still use the bot.
 */
final class MaintenanceMiddleware implements Middleware
{
    /**
     * @param string $flagFile Path of the flag file, e.g. storage/maintenance.flag
     * @param list<int> $bypassUserIds
     */
    public function __construct(
        private readonly string $flagFile,
        private readonly array $bypassUserIds = [],
        private readonly string $message = 'The bot is under maintenance. Please try again later.',
    ) {
    }

    public function process(Context $ctx, Closure $next): mixed
    {
        clearstatcache(true, $this->flagFile); // a long-running bot must not remember an old answer

        if (!is_file($this->flagFile)) {
            return $next($ctx);
        }

        $userId = $ctx->from()?->id;

        if ($userId !== null && in_array($userId, $this->bypassUserIds, true)) {
            return $next($ctx);
        }

        $ctx->notify($this->message);

        return null;
    }
}