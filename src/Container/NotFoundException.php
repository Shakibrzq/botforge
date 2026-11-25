<?php

declare(strict_types=1);

namespace Botforge\Container;

use Psr\Container\NotFoundExceptionInterface;

/**
 * Nothing is registered under that name, and it is not a class we could create.
 */
final class NotFoundException extends ContainerException implements NotFoundExceptionInterface
{
}