<?php

declare(strict_types=1);

namespace Botforge\Container;

use Closure;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Creates objects for us.
 *
 * It reads the constructor of a class (using PHP Reflection), sees which objects it needs,
 * creates those first, and passes them in. This is called "autowiring".
 */
final class Container implements ContainerInterface
{
    /** @var array<string, array{concrete: Closure|string, shared: bool}> */
    private array $definitions = [];

    /** @var array<string, mixed> Objects that already exist and are ready to be handed out */
    private array $instances = [];

    /** @var array<string, true> Names we are creating right now (used to detect loops) */
    private array $resolving = [];

    public function __construct()
    {
        // The container can also be asked for itself.
        $this->instance(self::class, $this);
        $this->instance(ContainerInterface::class, $this);
    }

    // ------------------------------------------------------------------
    // Registering
    // ------------------------------------------------------------------

    /**
     * "When someone asks for $id, build $concrete." A NEW object is made every time.
     * $concrete is a class name (e.g. map an interface to a class) or a closure that builds it.
     *
     * @param Closure(Container): mixed|string $concrete
     */
    public function bind(string $id, Closure|string $concrete): void
    {
        $this->definitions[$id] = ['concrete' => $concrete, 'shared' => false];
        unset($this->instances[$id]);
    }

    /**
     * Like bind(), but the object is built only ONCE and then reused.
     *
     * @param Closure(Container): mixed|string $concrete
     */
    public function singleton(string $id, Closure|string $concrete): void
    {
        $this->definitions[$id] = ['concrete' => $concrete, 'shared' => true];
        unset($this->instances[$id]);
    }

    /** Stores an object that already exists. Replaces anything registered before under $id. */
    public function instance(string $id, object $instance): void
    {
        $this->instances[$id] = $instance;
    }

    /** Lets a service provider register its things. */
    public function register(ServiceProvider $provider): void
    {
        $provider->register($this);
    }

    // ------------------------------------------------------------------
    // Getting things out (PSR-11)
    // ------------------------------------------------------------------

    public function has(string $id): bool
    {
        if (array_key_exists($id, $this->instances) || isset($this->definitions[$id])) {
            return true;
        }

        return class_exists($id) && (new ReflectionClass($id))->isInstantiable();
    }

    /**
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        // If we are already creating $id and it asks for itself again, we would loop forever.
        if (isset($this->resolving[$id])) {
            throw new ContainerException(sprintf('Circular dependency detected while creating %s.', $id));
        }

        $this->resolving[$id] = true;

        try {
            $object = $this->create($id);
        } finally {
            unset($this->resolving[$id]);
        }

        if (isset($this->definitions[$id]) && $this->definitions[$id]['shared']) {
            $this->instances[$id] = $object;
        }

        return $object;
    }

    // ------------------------------------------------------------------
    // Calling handlers
    // ------------------------------------------------------------------

    /**
     * Calls a handler and fills its parameters automatically.
     *
     * The handler can be: a closure, an invokable class (Foo::class), or [Foo::class, 'method'].
     * Parameters are filled in this order: from $parameters by NAME, then from the container
     * by TYPE (e.g. "Context $ctx"), then from the default value.
     *
     * @param callable|array<int, mixed>|string $handler
     * @param array<string, mixed> $parameters Extra values by parameter name, e.g. ['id' => '7']
     *
     * @throws ContainerException
     */
    public function call(callable|array|string $handler, array $parameters = []): mixed
    {
        if (is_string($handler)) {
            $handler = $this->get($handler); // a class name: create the object
        } elseif (is_array($handler) && is_string($handler[0])) {
            $handler = [$this->get($handler[0]), $handler[1]]; // [Class::class, 'method']
        }

        if (!is_callable($handler)) {
            throw new ContainerException('A handler must be a closure, an invokable class, or [Class::class, \'method\'].');
        }

        $reflection = match (true) {
            is_array($handler) => new ReflectionMethod($handler[0], $handler[1]),
            $handler instanceof Closure => new ReflectionFunction($handler),
            default => new ReflectionMethod($handler, '__invoke'),
        };

        $arguments = $this->resolveParameters($reflection->getParameters(), $parameters);

        return $handler(...$arguments);
    }

    // ------------------------------------------------------------------
    // Inner workings
    // ------------------------------------------------------------------

    private function create(string $id): mixed
    {
        if (!isset($this->definitions[$id])) {
            return $this->build($id); // not registered: try to autowire it
        }

        $concrete = $this->definitions[$id]['concrete'];

        if ($concrete instanceof Closure) {
            return $concrete($this);
        }

        // A class name: build it directly, or follow the chain if it points to another name.
        return $concrete === $id ? $this->build($concrete) : $this->get($concrete);
    }

    /** Creates an object of $class by reading its constructor. */
    private function build(string $class): object
    {
        if (interface_exists($class)) {
            throw new NotFoundException(sprintf('"%s" is an interface. Register a class for it with bind().', $class));
        }

        if (!class_exists($class)) {
            throw new NotFoundException(
                sprintf('Nothing is registered for "%s", and no class with that name exists.', $class)
            );
        }

        $reflection = new ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            throw new ContainerException(sprintf(
                '"%s" cannot be created directly (abstract class or private constructor). '
                . 'Register it with bind(), singleton() or instance().',
                $class
            ));
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $class(); // no constructor = nothing to fill
        }

        return $reflection->newInstanceArgs($this->resolveParameters($constructor->getParameters()));
    }

    /**
     * Finds a value for every parameter.
     *
     * @param list<ReflectionParameter> $parameters
     * @param array<string, mixed> $given
     *
     * @return list<mixed>
     */
    private function resolveParameters(array $parameters, array $given = []): array
    {
        $arguments = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();
            $type = $parameter->getType();

            // 1) A value given by name (e.g. from a route like "product:{id}")
            if (array_key_exists($name, $given)) {
                $arguments[] = $this->castGivenValue($given[$name], $type);
                continue;
            }

            if ($parameter->isVariadic()) {
                continue;
            }

            // 2) A class or interface type: ask the container
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin() && $this->has($type->getName())) {
                $arguments[] = $this->get($type->getName());
                continue;
            }

            // 3) A default value
            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            // 4) Nullable: pass null
            if ($type !== null && $type->allowsNull()) {
                $arguments[] = null;
                continue;
            }

            throw new ContainerException(sprintf(
                'Cannot resolve parameter $%s of %s. '
                . 'Register the class with bind(), singleton() or instance(), or give the parameter a default value.',
                $name,
                $this->describe($parameter->getDeclaringFunction())
            ));
        }

        return $arguments;
    }

    /**
     * Values from a message are always text ("7"). If the handler wants an int or a float,
     * we convert it, because strict types would refuse the text.
     */
    private function castGivenValue(mixed $value, ?\ReflectionType $type): mixed
    {
        if (!is_string($value) || !$type instanceof ReflectionNamedType || !$type->isBuiltin()) {
            return $value;
        }

        return match ($type->getName()) {
            'int' => filter_var($value, FILTER_VALIDATE_INT) === false ? $value : (int) $value,
            'float' => filter_var($value, FILTER_VALIDATE_FLOAT) === false ? $value : (float) $value,
            default => $value,
        };
    }

    private function describe(ReflectionFunctionAbstract $function): string
    {
        if ($function instanceof ReflectionMethod) {
            return $function->class . '::' . $function->getName();
        }

        return $function->getName();
    }
}