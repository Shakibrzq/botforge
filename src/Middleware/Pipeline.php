<?php

declare(strict_types=1);

namespace Botforge\Middleware;

use Botforge\Container\Container;
use Botforge\Context\Context;
use Closure;
use InvalidArgumentException;

/**
 * Wraps a destination (usually a handler) in layers of middleware, like an onion.
 *
 * Middleware can be: an object, a closure function (Context $ctx, Closure $next), or a class name
 * (the container creates it, so it can have its own dependencies).
 */
final class Pipeline
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param list<Middleware|Closure|string> $middleware First item = outermost layer
     * @param Closure(Context): mixed $destination What runs in the very center
     */
    public function run(Context $ctx, array $middleware, Closure $destination): mixed
    {
        $next = $destination;

        // Build from the inside out: start with the center, wrap the LAST middleware around it,
        // then the one before it, and so on. That is why we walk the list backwards.
        foreach (array_reverse($middleware) as $layer) {
            $next = fn (Context $ctx): mixed => $this->callLayer($layer, $ctx, $next);
        }

        return $next($ctx);
    }

    /** @param Middleware|Closure|string $layer */
    private function callLayer(Middleware|Closure|string $layer, Context $ctx, Closure $next): mixed
    {
        if ($layer instanceof Closure) {
            return $layer($ctx, $next);
        }

        if (is_string($layer)) {
            $name = $layer;
            $layer = $this->container->get($name);

            if (!$layer instanceof Middleware) {
                throw new InvalidArgumentException(
                    sprintf('"%s" is not a middleware (it must implement %s).', $name, Middleware::class)
                );
            }
        }

        return $layer->process($ctx, $next);
    }
}