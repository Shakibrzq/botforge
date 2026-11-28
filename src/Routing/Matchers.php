<?php

declare(strict_types=1);

namespace Botforge\Routing;

use Botforge\Context\Context;
use Closure;
use InvalidArgumentException;

final class Matchers
{
    private function __construct()
    {
    }

    /**
     * Matches commands such as /start or /start ref_123.
     */
    public static function command(string $name): Closure
    {
        $name = strtolower(ltrim($name, '/'));

        return static function (Context $ctx) use ($name): ?array {
            if ($ctx->command() !== $name) {
                return null;
            }

            $args = $ctx->args();

            return $args === [] ? [] : ['payload' => implode(' ', $args)];
        };
    }

    /**
     * Matches the whole message text exactly.
     */
    public static function text(string $expected): Closure
    {
        return static fn (Context $ctx): ?array =>
            $ctx->text() === $expected ? [] : null;
    }

    /**
     * Matches text containing the given string.
     */
    public static function contains(string $needle): Closure
    {
        if ($needle === '') {
            throw new InvalidArgumentException(
                'contains() needs a non-empty text.'
            );
        }

        return static function (Context $ctx) use ($needle): ?array {
            $text = $ctx->text();

            return $text !== null && mb_stripos($text, $needle) !== false
                ? []
                : null;
        };
    }

    /**
     * Matches text using a regular expression.
     */
    public static function regex(string $pattern): Closure
    {
        if (@preg_match($pattern, '') === false) {
            throw new InvalidArgumentException(
                sprintf('Invalid regular expression: %s', $pattern)
            );
        }

        return static function (Context $ctx) use ($pattern): ?array {
            $text = $ctx->text();

            if ($text === null || preg_match($pattern, $text, $matches) !== 1) {
                return null;
            }

            $named = array_filter(
                $matches,
                'is_string',
                ARRAY_FILTER_USE_KEY
            );

            return $named + ['matches' => $matches];
        };
    }

    /**
     * Matches callback data exactly or extracts named parameters.
     */
    public static function callback(string $pattern): Closure
    {
        if (!str_contains($pattern, '{')) {
            return static fn (Context $ctx): ?array =>
                $ctx->callbackData() === $pattern ? [] : null;
        }

        $regex = self::compilePattern($pattern);

        return static function (Context $ctx) use ($regex): ?array {
            $data = $ctx->callbackData();

            if ($data === null || preg_match($regex, $data, $matches) !== 1) {
                return null;
            }

            return array_filter(
                $matches,
                'is_string',
                ARRAY_FILTER_USE_KEY
            );
        };
    }

    /**
     * Matches a specific message type.
     */
    public static function messageType(string $type): Closure
    {
        return static fn (Context $ctx): ?array =>
            $ctx->update()->message?->type() === $type ? [] : null;
    }

    /**
     * Matches inline queries.
     */
    public static function inlineQuery(): Closure
    {
        return static function (Context $ctx): ?array {
            $inline = $ctx->update()->inlineQuery;

            return $inline === null ? null : ['query' => $inline->query];
        };
    }

    /**
     * Matches changes to the bot's own chat membership.
     */
    public static function myChatMember(): Closure
    {
        return static function (Context $ctx): ?array {
            $change = $ctx->update()->myChatMember;

            return $change === null ? null : ['change' => $change];
        };
    }

    /**
     * Matches changes to another member's chat status.
     */
    public static function chatMember(): Closure
    {
        return static function (Context $ctx): ?array {
            $change = $ctx->update()->chatMember;

            return $change === null ? null : ['change' => $change];
        };
    }

    /**
     * Always matches.
     */
    public static function always(): Closure
    {
        return static fn (Context $ctx): ?array => [];
    }

    /**
     * Converts callback patterns into regular expressions.
     */
    private static function compilePattern(string $pattern): string
    {
        $parts = preg_split(
            '/(\{[A-Za-z_]\w*\})/',
            $pattern,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        if ($parts === false) {
            throw new InvalidArgumentException(
                sprintf('Invalid callback pattern: %s', $pattern)
            );
        }

        $regex = '';

        foreach ($parts as $part) {
            if (preg_match('/^\{([A-Za-z_]\w*)\}$/', $part, $placeholder) === 1) {
                $regex .= '(?<' . $placeholder[1] . '>[^:]+)';
            } else {
                $regex .= preg_quote($part, '#');
            }
        }

        return '#^' . $regex . '$#';
    }
}