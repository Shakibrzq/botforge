<?php

declare(strict_types=1);

namespace Botforge\Routing;

use Botforge\Context\Context;
use Botforge\Middleware\Middleware;
use Closure;

/**
 * Settings shared by all routes defined inside one $router->group(...) block.
 */
final readonly class RouteGroup
{
    /**
     * @param list<Middleware|Closure|string> $middleware Added to every route of the group
     * @param Closure(Context): bool|null $condition Every route of the group only matches when this returns true
     */
    public function __construct(
        public array $middleware = [],
        public ?Closure $condition = null,
    ) {
    }
}