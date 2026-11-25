<?php

declare(strict_types=1);

namespace Botforge\Keyboard;

/**
 * Removes a reply keyboard that was shown earlier.
 */
final class RemoveKeyboard implements ReplyMarkup
{
    public function toArray(): array
    {
        return ['remove_keyboard' => true];
    }
}
