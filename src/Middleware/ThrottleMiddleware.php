<?php

declare(strict_types=1);

namespace Botforge\Middleware;

use Botforge\Context\Context;
use Botforge\Storage\StorageInterface;
use Closure;
use InvalidArgumentException;

/**
 * Limits how often one Telegram user may pass through a middleware layer.
 *
 * This uses a fixed window stored through the configured storage driver, so it
 * works across webhook requests and survives process restarts. Attach it to a
 * route or register it globally with Bot::middleware().
 */
final class ThrottleMiddleware implements Middleware
{
    public function __construct(
        private readonly StorageInterface $storage,
        private readonly int $maxAttempts = 3,
        private readonly int $windowSeconds = 60,
        private readonly string $keyPrefix = 'throttle',
        private readonly string $denyMessage = 'Please slow down and try again shortly.',
    ) {
        if ($this->maxAttempts < 1) {
            throw new InvalidArgumentException('maxAttempts must be at least 1.');
        }

        if ($this->windowSeconds < 1) {
            throw new InvalidArgumentException('windowSeconds must be at least 1.');
        }

        if ($this->keyPrefix === '') {
            throw new InvalidArgumentException('keyPrefix cannot be empty.');
        }
    }

    public function process(Context $ctx, Closure $next): mixed
    {
        $userId = $ctx->from()?->id;

        // Updates without a Telegram user (for example some channel updates)
        // cannot be throttled per-user, so they continue normally.
        if ($userId === null) {
            return $next($ctx);
        }

        $window = intdiv(time(), $this->windowSeconds);
        $key = sprintf('%s:user:%d:window:%d', $this->keyPrefix, $userId, $window);
        $attempts = $this->storage->get($key);
        $attempts = is_int($attempts) && $attempts >= 0 ? $attempts : 0;

        if ($attempts >= $this->maxAttempts) {
            if ($this->denyMessage !== '') {
                $ctx->reply($this->denyMessage);
            }

            return null;
        }

        $this->storage->set($key, $attempts + 1, $this->windowSeconds);

        return $next($ctx);
    }
}
