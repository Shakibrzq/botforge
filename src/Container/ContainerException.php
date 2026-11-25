<?php

declare(strict_types=1);

namespace Botforge\Container;

use Psr\Container\ContainerExceptionInterface;
use RuntimeException;

/**
 * The container found the thing but could not create it
 * (unknown constructor value, circular dependency, ...).
 */
class ContainerException extends RuntimeException implements ContainerExceptionInterface
{
}
