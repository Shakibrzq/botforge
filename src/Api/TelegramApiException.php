<?php

declare(strict_types=1);

namespace Botforge\Api;

use RuntimeException;

/**
 * Base error for everything that goes wrong while talking to Telegram.
 *
 * getMessage() is a readable description.
 * getCode() is Telegram's error code (400, 401, 404, ...) or 0 for network problems.
 */
class TelegramApiException extends RuntimeException
{
}
