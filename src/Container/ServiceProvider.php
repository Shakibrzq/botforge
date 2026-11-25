<?php

declare(strict_types=1);

namespace Botforge\Container;

/**
 * A "setup box": one class that registers a group of related things in the container.
 */
interface ServiceProvider
{
    public function register(Container $container): void;
}