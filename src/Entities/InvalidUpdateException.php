<?php

declare(strict_types=1);

namespace Botforge\Entities;

use InvalidArgumentException;

/**
 * Thrown when the text we received is not a valid Telegram update.
 */
final class InvalidUpdateException extends InvalidArgumentException
{
}