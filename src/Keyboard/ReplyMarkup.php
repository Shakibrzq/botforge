<?php

declare(strict_types=1);

namespace Botforge\Keyboard;

/**
 * Anything that can be attached to a message as "reply_markup":
 * an inline keyboard, a reply keyboard, "remove keyboard" or "force reply".
 */
interface ReplyMarkup
{
    /**
     * The array exactly as Telegram wants it.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}