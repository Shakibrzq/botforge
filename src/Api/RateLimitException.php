<?php

declare(strict_types=1);

namespace Botforge\Api;

/**
 * Telegram says "too many requests" (HTTP 429).
 * $retryAfter is how many seconds Telegram wants us to wait.
 */
final class RateLimitException extends TelegramApiException
{
    public function __construct(string $message, public readonly int $retryAfter)
    {
        parent::__construct($message, 429);
    }
}