<?php

declare(strict_types=1);

namespace Botforge\Routing;

use Botforge\Container\Container;
use Botforge\Context\Context;
use Botforge\Entities\Update;
use Botforge\Middleware\Middleware;
use Botforge\Middleware\Pipeline;
use Closure;
use InvalidArgumentException;

/**
 * Decides which handler gets an update.
 *
 * Routes are checked in the order they were registered. The FIRST one that matches wins.
 * If none matches, the fallback route (if any) is used.
 */
final class Router
{
    private const MESSAGE_TYPES = ['text', 'photo', 'document', 'voice', 'contact', 'location'];

    /** @var list<Route> */
    private array $routes = [];

    private ?Route $fallbackRoute = null;

    /** @var list<RouteGroup> The groups we are inside of right now (while group() is running) */
    private array $groups = [];

    public function __construct(
        private readonly Container $container,
        private readonly Pipeline $pipeline,
    ) {
    }

    // ------------------------------------------------------------------
    // Registering routes
    // The handler can be a closure, an invokable class (Foo::class) or [Foo::class, 'method'].
    // ------------------------------------------------------------------

    /**
     * /start, /help ... The deep-link payload ("/start ref_123") arrives as $payload.
     *
     * @param callable|array<int, mixed>|string $handler
     */
    public function command(string $name, callable|array|string $handler): Route
    {
        return $this->add(Matchers::command($name), $handler);
    }

    /** @param callable|array<int, mixed>|string $handler The whole text must be exactly $text */
    public function text(string $text, callable|array|string $handler): Route
    {
        return $this->add(Matchers::text($text), $handler);
    }

    /** @param callable|array<int, mixed>|string $handler The text must contain $needle (any letter case) */
    public function contains(string $needle, callable|array|string $handler): Route
    {
        return $this->add(Matchers::contains($needle), $handler);
    }

    /** @param callable|array<int, mixed>|string $handler Named groups arrive as parameters of the same name */
    public function regex(string $pattern, callable|array|string $handler): Route
    {
        return $this->add(Matchers::regex($pattern), $handler);
    }

    /** @param callable|array<int, mixed>|string $handler Pattern like 'menu' or 'product:{id}' */
    public function callback(string $pattern, callable|array|string $handler): Route
    {
        return $this->add(Matchers::callback($pattern), $handler);
    }

    /**
     * A message of a kind: text, photo, document, voice, contact, location.
     *
     * @param callable|array<int, mixed>|string $handler
     */
    public function on(string $messageType, callable|array|string $handler): Route
    {
        if (!in_array($messageType, self::MESSAGE_TYPES, true)) {
            throw new InvalidArgumentException(sprintf(
                'Unknown message type "%s". Use one of: %s.',
                $messageType,
                implode(', ', self::MESSAGE_TYPES)
            ));
        }

        return $this->add(Matchers::messageType($messageType), $handler);
    }

    /** @param callable|array<int, mixed>|string $handler Receives string $query */
    public function inlineQuery(callable|array|string $handler): Route
    {
        return $this->add(Matchers::inlineQuery(), $handler);
    }

    /** @param callable|array<int, mixed>|string $handler Receives ChatMemberUpdated $change (the bot's own status) */
    public function myChatMember(callable|array|string $handler): Route
    {
        return $this->add(Matchers::myChatMember(), $handler);
    }

    /** @param callable|array<int, mixed>|string $handler Receives ChatMemberUpdated $change (someone else's status) */
    public function chatMember(callable|array|string $handler): Route
    {
        return $this->add(Matchers::chatMember(), $handler);
    }

    /**
     * Used when no other route matched. There is only one fallback; a new one replaces the old.
     *
     * @param callable|array<int, mixed>|string $handler
     */
    public function fallback(callable|array|string $handler): Route
    {
        $this->fallbackRoute = $this->makeRoute(Matchers::always(), $handler);

        return $this->fallbackRoute;
    }

    /**
     * Defines several routes that share settings.
     *
     *   $router->group(['middleware' => [AdminOnlyMiddleware::class], 'when' => fn ($ctx) => ...], function (Router $r) {
     *       $r->command('stats', ...);
     *   });
     *
     * @param array{middleware?: list<Middleware|Closure|string>, when?: Closure(Context): bool} $options
     * @param Closure(Router): void $define
     */
    public function group(array $options, Closure $define): void
    {
        $this->groups[] = new RouteGroup($options['middleware'] ?? [], $options['when'] ?? null);

        try {
            $define($this);
        } finally {
            array_pop($this->groups); // leave the group even if $define throws
        }
    }

    // ------------------------------------------------------------------
    // Dispatching
    // ------------------------------------------------------------------

    /**
     * Finds the route for this update and runs it (through its middleware).
     *
     * @return bool true if a route (or the fallback) handled the update
     */
    public function dispatch(Context $ctx): bool
    {
        // Make the Context available to handlers by type-hint (function (Context $ctx) {...}).
        $this->container->instance(Context::class, $ctx);
        $this->container->instance(Update::class, $ctx->update());

        foreach ($this->routes as $route) {
            $parameters = $route->tryMatch($ctx);

            if ($parameters !== null) {
                $this->run($route, $ctx, $parameters);

                return true;
            }
        }

        if ($this->fallbackRoute !== null) {
            $parameters = $this->fallbackRoute->tryMatch($ctx); // a group condition may still say no

            if ($parameters !== null) {
                $this->run($this->fallbackRoute, $ctx, $parameters);

                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------------
    // Inner workings
    // ------------------------------------------------------------------

    /** @param callable|array<int, mixed>|string $handler */
    private function add(Closure $matcher, callable|array|string $handler): Route
    {
        $route = $this->makeRoute($matcher, $handler);
        $this->routes[] = $route;

        return $route;
    }

    /**
     * Builds a Route and applies the settings of the groups we are inside of.
     *
     * @param callable|array<int, mixed>|string $handler
     */
    private function makeRoute(Closure $matcher, callable|array|string $handler): Route
    {
        $this->assertHandler($handler);

        $conditions = [];
        $middleware = [];

        foreach ($this->groups as $group) { // outer group first, inner group last
            if ($group->condition !== null) {
                $conditions[] = $group->condition;
            }

            array_push($middleware, ...$group->middleware);
        }

        if ($conditions !== []) {
            $inner = $matcher;

            // The new matcher first checks the group conditions, then asks the original matcher.
            $matcher = static function (Context $ctx) use ($conditions, $inner): ?array {
                foreach ($conditions as $condition) {
                    if (!$condition($ctx)) {
                        return null;
                    }
                }

                return $inner($ctx);
            };
        }

        return new Route($matcher, $handler, $middleware);
    }

    /** Fails NOW (when registering) instead of later (when a user writes to the bot). */
    private function assertHandler(mixed $handler): void
    {
        $valid = match (true) {
            is_object($handler) => $handler instanceof Closure || method_exists($handler, '__invoke'),
            is_string($handler) => class_exists($handler) && method_exists($handler, '__invoke'),
            is_array($handler) => count($handler) === 2
                && (is_object($handler[0]) || (is_string($handler[0]) && class_exists($handler[0])))
                && is_string($handler[1])
                && method_exists($handler[0], $handler[1]),
            default => false,
        };

        if (!$valid) {
            throw new InvalidArgumentException(
                'Invalid handler. Use a closure, an invokable class name, or [Class::class, \'method\'].'
            );
        }
    }

    /** @param array<string, mixed> $parameters */
    private function run(Route $route, Context $ctx, array $parameters): void
    {
        $this->pipeline->run(
            $ctx,
            $route->getMiddleware(),
            function (Context $ctx) use ($route, $parameters): mixed {
                $this->container->instance(Context::class, $ctx); // in case a middleware passed a different one

                return $this->container->call($route->handler, $parameters);
            }
        );
    }
}