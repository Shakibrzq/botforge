<?php

declare(strict_types=1);

namespace Botforge\Keyboard;

use InvalidArgumentException;

/**
 * A keyboard that replaces the phone's normal keyboard.
 * Keyboard::reply()->row(Button::plain('Yes'), Button::plain('No'))->oneTime()
 */
final class ReplyKeyboard implements ReplyMarkup
{
    /** @var list<list<Button>> */
    private array $rows = [];

    private bool $resize = true;
    private bool $oneTime = false;
    private ?string $placeholder = null;

    public function row(Button ...$buttons): self
    {
        if ($buttons === []) {
            throw new InvalidArgumentException('A row needs at least one button.');
        }

        $this->rows[] = array_values($buttons);

        return $this;
    }

    /** Make the keyboard as small as its buttons (on by default; Telegram's own default is a huge keyboard). */
    public function resize(bool $resize = true): self
    {
        $this->resize = $resize;

        return $this;
    }

    /** Hide the keyboard after one press. */
    public function oneTime(bool $oneTime = true): self
    {
        $this->oneTime = $oneTime;

        return $this;
    }

    /** Gray hint text shown in the input field while the keyboard is visible. */
    public function placeholder(string $text): self
    {
        $this->placeholder = $text;

        return $this;
    }

    public function toArray(): array
    {
        $markup = [
            'keyboard' => array_map(
                static fn (array $row): array => array_map(
                    static fn (Button $button): array => $button->toArray(),
                    $row
                ),
                $this->rows
            ),
            'resize_keyboard' => $this->resize,
            'one_time_keyboard' => $this->oneTime,
        ];

        if ($this->placeholder !== null) {
            $markup['input_field_placeholder'] = $this->placeholder;
        }

        return $markup;
    }
}