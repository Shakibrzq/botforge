<?php

declare(strict_types=1);

namespace Botforge\Middleware;

use Botforge\Context\Context;
use Closure;

/**
 * A "gate" around a handler.
 *
 * Do something BEFORE, then call $next($ctx) to let the update continue inward
 * (or do NOT call it to stop here), and optionally do something AFTER.
 */
interface Middleware
{
    /**
     * @param Closure(Context): mixed $next The rest of the onion (the next middleware, or finally the handler)
     */
    public function process(Context $ctx, Closure $next): mixed;
}