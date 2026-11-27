<?php

declare(strict_types=1);

namespace Botforge\Middleware;

use Botforge\Context\Context;
use Closure;

/**
 * Allows only users whose session contains a truthy registration flag.
 *
 * Register the flag after a successful registration with:
 * $ctx->session()->set('registered', true);
 *
 * The middleware is intended for private-user routes. Updates without both a
 * user and a chat are stopped because they cannot have a user session.
 */
final class RegisteredUserMiddleware implements Middleware
{
    public function __construct(
        private readonly string $sessionKey = 'registered',
        private readonly string $denyMessage = 'Please register first using /register.',
    ) {
        if ($this->sessionKey === '') {
            throw new \InvalidArgumentException('sessionKey cannot be empty.');
        }
    }

    public function process(Context $ctx, Closure $next): mixed
    {
        if ($ctx->from() === null || $ctx->chat() === null) {
            return null;
        }

        if ($ctx->session()->get($this->sessionKey, false) === true) {
            return $next($ctx);
        }

        if ($this->denyMessage !== '') {
            $ctx->reply($this->denyMessage);
        }

        return null;
    }
}