<?php

declare(strict_types=1);

namespace Botforge\Keyboard;

/**
 * Makes the user's app open the "reply" box for our message, as if they pressed "Reply".
 * Handy when the bot asks a question.
 */
final class ForceReply implements ReplyMarkup
{
    public function __construct(private readonly ?string $placeholder = null)
    {
    }

    public function toArray(): array
    {
        $markup = ['force_reply' => true];

        if ($this->placeholder !== null) {
            $markup['input_field_placeholder'] = $this->placeholder;
        }

        return $markup;
    }
}