<?php

declare(strict_types=1);

namespace Botforge\Routing;

use Botforge\Context\Context;
use Botforge\Middleware\Middleware;
use Closure;

/**
 * One rule: "if the update looks like THIS, run THAT handler (after these middleware)".
 */
final class Route
{
    /** @var list<Middleware|Closure|string> */
    private array $middleware;

    /**
     * @param Closure(Context): (array<string, mixed>|null) $matcher
     * @param callable|array<int, mixed>|string $handler
     * @param list<Middleware|Closure|string> $middleware Middleware inherited from groups
     */
    public function __construct(
        private readonly Closure $matcher,
        public readonly mixed $handler,
        array $middleware = [],
    ) {
        $this->middleware = $middleware;
    }

    /**
     * Asks the matcher. Returns null for "not for me", or the picked-out values (maybe an empty array).
     *
     * @return array<string, mixed>|null
     */
    public function tryMatch(Context $ctx): ?array
    {
        return ($this->matcher)($ctx);
    }

    /**
     * Adds middleware to this route only: $router->command('admin', ...)->middleware(AdminOnlyMiddleware::class)
     */
    public function middleware(Middleware|Closure|string ...$middleware): self
    {
        array_push($this->middleware, ...$middleware);

        return $this;
    }

    /** @return list<Middleware|Closure|string> */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }
}