<?php

declare(strict_types=1);

namespace Botforge\Middleware;

use Botforge\Config;
use Botforge\Context\Context;
use Closure;

/**
 * Lets only the admins (ADMIN_IDS in .env) through. Everybody else gets a short notice.
 * Pass null as the message to ignore non-admins silently.
 */
final class AdminOnlyMiddleware implements Middleware
{
    public function __construct(
        private readonly Config $config,
        private readonly ?string $denyMessage = 'This action is for administrators only.',
    ) {
    }

    public function process(Context $ctx, Closure $next): mixed
    {
        $userId = $ctx->from()?->id;

        if ($userId === null || !in_array($userId, $this->config->adminIds, true)) {
            if ($this->denyMessage !== null) {
                $ctx->notify($this->denyMessage);
            }

            return null;
        }

        return $next($ctx);
    }
}