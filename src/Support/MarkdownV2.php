<?php

declare(strict_types=1);

namespace Botforge\Support;

/**
 * Safe building blocks for messages sent with parse mode MarkdownV2.
 * Same method names as Html, so switching between the two is easy.
 *
 * MarkdownV2 is much stricter than HTML: a single unescaped "." or "!" makes Telegram reject
 * the whole message. That is why every method escapes the text you give it.
 */
final class MarkdownV2
{
    /** Characters that Telegram wants a backslash in front of (and the backslash itself). */
    private const SPECIAL = '_*[]()~`>#+-=|{}.!\\';

    private function __construct()
    {
        // Only static helpers here.
    }

    public static function escape(string $text): string
    {
        return addcslashes($text, self::SPECIAL);
    }

    public static function bold(string $text): string
    {
        return '*' . self::escape($text) . '*';
    }

    public static function italic(string $text): string
    {
        return '_' . self::escape($text) . '_';
    }

    public static function underline(string $text): string
    {
        return '__' . self::escape($text) . '__';
    }

    public static function strike(string $text): string
    {
        return '~' . self::escape($text) . '~';
    }

    public static function spoiler(string $text): string
    {
        return '||' . self::escape($text) . '||';
    }

    public static function code(string $text): string
    {
        return '`' . self::escapeInsideCode($text) . '`';
    }

    public static function pre(string $text, ?string $language = null): string
    {
        $name = ($language !== null && preg_match('/^[A-Za-z0-9_+\-]+$/', $language) === 1) ? $language : '';

        return "```" . $name . "\n" . self::escapeInsideCode($text) . "\n```";
    }

    public static function link(string $text, string $url): string
    {
        // Inside the (...) part only ")" and "\" must be escaped.
        return '[' . self::escape($text) . '](' . addcslashes($url, ')\\') . ')';
    }

    public static function mention(int $userId, string $name): string
    {
        return self::link($name, 'tg://user?id=' . $userId);
    }

    /** Inside `code` and ```blocks``` only the backtick and the backslash are special. */
    private static function escapeInsideCode(string $text): string
    {
        return addcslashes($text, '`\\');
    }
}