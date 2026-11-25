<?php

declare(strict_types=1);

namespace Botforge\Keyboard;

use InvalidArgumentException;

/**
 * The entry point for building keyboards:
 *
 *   Keyboard::inline()->row(Button::callback('Profile', 'profile'))
 *   Keyboard::reply()->row(Button::requestContact('Share my number'))
 *   Keyboard::remove()
 *   Keyboard::forceReply('Your name?')
 *   Keyboard::paginate($items, $page, 5, 'products')
 */
final class Keyboard
{
    private function __construct()
    {
        // Only static helpers here.
    }

    public static function inline(): InlineKeyboard
    {
        return new InlineKeyboard();
    }

    public static function reply(): ReplyKeyboard
    {
        return new ReplyKeyboard();
    }

    public static function remove(): RemoveKeyboard
    {
        return new RemoveKeyboard();
    }

    public static function forceReply(?string $placeholder = null): ForceReply
    {
        return new ForceReply($placeholder);
    }

    /**
     * An inline keyboard that shows ONE page of a long list, with "Prev" and "Next" buttons.
     *
     * Item buttons send  "<prefix>:item:<id>"   -> $bot->callback('products:item:{id}', ...)
     * Page buttons send  "<prefix>:page:<n>"    -> $bot->callback('products:page:{page}', ...)
     *
     * The result is a normal InlineKeyboard, so you can add more rows after it.
     *
     * @param array<int|string, string> $items id => label (a simple list works too: the id is the position)
     * @param int $page Wanted page, starting at 1 (too small/too big numbers are corrected)
     * @param int $columns Item buttons per row
     */
    public static function paginate(array $items, int $page, int $perPage, string $prefix, int $columns = 1): InlineKeyboard
    {
        if ($perPage < 1 || $columns < 1) {
            throw new InvalidArgumentException('perPage and columns must be at least 1.');
        }

        $totalPages = max(1, (int) ceil(count($items) / $perPage));
        $page = min(max($page, 1), $totalPages);

        // preserve_keys = true: each item keeps its own id.
        $pageItems = array_slice($items, ($page - 1) * $perPage, $perPage, true);

        $buttons = [];
        foreach ($pageItems as $id => $label) {
            $buttons[] = Button::callback((string) $label, $prefix . ':item:' . $id);
        }

        $keyboard = new InlineKeyboard();

        foreach (array_chunk($buttons, $columns) as $row) {
            $keyboard->row(...$row);
        }

        $navigation = [];
        if ($page > 1) {
            $navigation[] = Button::callback('« Prev', $prefix . ':page:' . ($page - 1));
        }
        if ($page < $totalPages) {
            $navigation[] = Button::callback('Next »', $prefix . ':page:' . ($page + 1));
        }
        if ($navigation !== []) {
            $keyboard->row(...$navigation);
        }

        return $keyboard;
    }
}