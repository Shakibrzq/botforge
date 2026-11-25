<?php

declare(strict_types=1);

namespace Botforge\Api;

/**
 * Telegram refuses the action (HTTP 403).
 * The most common reason: the user blocked the bot, or the bot was kicked from a group.
 */
final class ForbiddenException extends TelegramApiException
{
    public function __construct(string $message)
    {
        parent::__construct($message, 403);
    }

    /** True when the reason is "the user blocked the bot". */
    public function isBlockedByUser(): bool
    {
        return str_contains($this->getMessage(), 'bot was blocked by the user');
    }
}