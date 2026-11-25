<?php

declare(strict_types=1);

namespace Botforge\Support;

/**
 * Safe building blocks for messages sent with parse mode HTML.
 *
 * Every method escapes the text you give it, so a user name like "<b>Hack" can never break
 * your message or inject tags. Text you write yourself between the pieces is NOT escaped.
 *
 *   'Hello, ' . Html::bold($user->firstName) . '!'
 */
final class Html
{
    private function __construct()
    {
        // Only static helpers here.
    }

    /** Makes any text safe: only &, < and > need escaping in Telegram's HTML. */
    public static function escape(string $text): string
    {
        // ENT_SUBSTITUTE: broken UTF-8 becomes "?" instead of making the WHOLE result empty.
        return htmlspecialchars($text, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function bold(string $text): string
    {
        return '<b>' . self::escape($text) . '</b>';
    }

    public static function italic(string $text): string
    {
        return '<i>' . self::escape($text) . '</i>';
    }

    public static function underline(string $text): string
    {
        return '<u>' . self::escape($text) . '</u>';
    }

    public static function strike(string $text): string
    {
        return '<s>' . self::escape($text) . '</s>';
    }

    public static function spoiler(string $text): string
    {
        return '<tg-spoiler>' . self::escape($text) . '</tg-spoiler>';
    }

    public static function code(string $text): string
    {
        return '<code>' . self::escape($text) . '</code>';
    }

    /** A code block, optionally with a language name (php, json, ...). */
    public static function pre(string $text, ?string $language = null): string
    {
        $code = self::escape($text);

        // Only simple language names are accepted, so nobody can sneak other attributes in.
        if ($language !== null && preg_match('/^[A-Za-z0-9_+\-]+$/', $language) === 1) {
            return '<pre><code class="language-' . $language . '">' . $code . '</code></pre>';
        }

        return '<pre>' . $code . '</pre>';
    }

    public static function link(string $text, string $url): string
    {
        // Inside an attribute, quotes must be escaped too (ENT_QUOTES).
        $safeUrl = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<a href="' . $safeUrl . '">' . self::escape($text) . '</a>';
    }

    /** A clickable name that notifies/opens the user with this id. */
    public static function mention(int $userId, string $name): string
    {
        return self::link($name, 'tg://user?id=' . $userId);
    }
}