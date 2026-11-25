<?php

declare(strict_types=1);

namespace Botforge\Keyboard;

use InvalidArgumentException;

/**
 * Buttons that sit under a message. Build it row by row:
 * Keyboard::inline()->row($a, $b)->row($c)
 */
final class InlineKeyboard implements ReplyMarkup
{
    private const MAX_BUTTONS_PER_ROW = 8; // Telegram's limit

    /** @var list<list<Button>> */
    private array $rows = [];

    /** Adds one row of buttons. */
    public function row(Button ...$buttons): self
    {
        if ($buttons === []) {
            throw new InvalidArgumentException('A row needs at least one button.');
        }

        if (count($buttons) > self::MAX_BUTTONS_PER_ROW) {
            throw new InvalidArgumentException(sprintf(
                'A row can hold at most %d buttons (got %d).',
                self::MAX_BUTTONS_PER_ROW,
                count($buttons)
            ));
        }

        $this->rows[] = array_values($buttons);

        return $this;
    }

    public function toArray(): array
    {
        return [
            'inline_keyboard' => array_map(
                static fn (array $row): array => array_map(
                    static fn (Button $button): array => $button->toArray(),
                    $row
                ),
                $this->rows
            ),
        ];
    }
}