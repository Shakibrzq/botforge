<?php
//this update is for me?

declare(strict_types=1);

namespace Botforge\Routing;

use Botforge\Context\Context;
use Closure;
use InvalidArgumentException;

/**
 * Small "questions" a route asks about an update.
 *
 * Every matcher is a closure: it receives the Context and answers
 *   - null  -> "no, this update is not for this route"
 *   - array -> "yes!" (the array holds the values we picked out, e.g. ['id' => '7'])
 */
final class Matchers
{
    private function __construct()
    {
        // Only static helpers here; nobody should create an object of this class.
    }

    /** Matches "/start", "/start ref_123", "/Start@MyBot". Gives ['payload' => ...] when there is text after it. */
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

    /** The whole text must be exactly $expected. */
    public static function text(string $expected): Closure
    {
        return static fn (Context $ctx): ?array => $ctx->text() === $expected ? [] : null;
    }

    /** The text must contain $needle somewhere (upper/lower case does not matter). */
    public static function contains(string $needle): Closure
    {
        if ($needle === '') {
            throw new InvalidArgumentException('contains() needs a non-empty text.');
        }

        return static function (Context $ctx) use ($needle): ?array {
            $text = $ctx->text();

            return $text !== null && mb_stripos($text, $needle) !== false ? [] : null;
        };
    }

    /**
     * The text must match a regular expression. Named groups become parameters:
     * '/^buy (?<qty>\d+)$/' gives ['qty' => '3', 'matches' => [...]].
     */
    public static function regex(string $pattern): Closure
    {
        // preg_match() returns false for a broken pattern. We check NOW, not when a user writes.
        // (The "@" hides PHP's own warning, because we throw our own clear error instead.)
        if (@preg_match($pattern, '') === false) {
            throw new InvalidArgumentException(sprintf('Invalid regular expression: %s', $pattern));
        }

        return static function (Context $ctx) use ($pattern): ?array {
            $text = $ctx->text();

            if ($text === null || preg_match($pattern, $text, $matches) !== 1) {
                return null;
            }

            $named = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            return $named + ['matches' => $matches];
        };
    }

    /**
     * Button data: exact ('menu') or with parameters ('product:{id}', 'page:{list}:{n}').
     * A parameter matches any text without a ":" inside.
     */
    public static function callback(string $pattern): Closure
    {
        if (!str_contains($pattern, '{')) {
            return static fn (Context $ctx): ?array => $ctx->callbackData() === $pattern ? [] : null;
        }

        $regex = self::compilePattern($pattern);

        return static function (Context $ctx) use ($regex): ?array {
            $data = $ctx->callbackData();

            if ($data === null || preg_match($regex, $data, $matches) !== 1) {
                return null;
            }

            return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        };
    }

    /** A message of one kind: photo, document, voice, contact, location, text. */
    public static function messageType(string $type): Closure
    {
        return static fn (Context $ctx): ?array => $ctx->update()->message?->type() === $type ? [] : null;
    }

    /** Someone typed "@yourbot something". Gives ['query' => 'something']. */
    public static function inlineQuery(): Closure
    {
        return static function (Context $ctx): ?array {
            $inline = $ctx->update()->inlineQuery;

            return $inline === null ? null : ['query' => $inline->query];
        };
    }

    /** The bot's OWN status changed (for example: a user blocked it). Gives ['change' => ChatMemberUpdated]. */
    public static function myChatMember(): Closure
    {
        return static function (Context $ctx): ?array {
            $change = $ctx->update()->myChatMember;

            return $change === null ? null : ['change' => $change];
        };
    }

    /** SOMEONE ELSE'S status changed in a chat. Gives ['change' => ChatMemberUpdated]. */
    public static function chatMember(): Closure
    {
        return static function (Context $ctx): ?array {
            $change = $ctx->update()->chatMember;

            return $change === null ? null : ['change' => $change];
        };
    }

    /** Matches everything (used by the fallback route). */
    public static function always(): Closure
    {
        return static fn (Context $ctx): ?array => [];
    }

    /** Turns 'product:{id}' into the regex '#^product:(?<id>[^:]+)$#'. */
    private static function compilePattern(string $pattern): string
    {
        // Cut the pattern into pieces, keeping the {placeholders} as separate pieces.
        $parts = preg_split('/(\{[A-Za-z_]\w*\})/', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        if ($parts === false) {
            throw new InvalidArgumentException(sprintf('Invalid callback pattern: %s', $pattern));
        }

        $regex = '';

        foreach ($parts as $part) {
            if (preg_match('/^\{([A-Za-z_]\w*)\}$/', $part, $placeholder) === 1) {
                $regex .= '(?<' . $placeholder[1] . '>[^:]+)'; // a named hole
            } else {
                $regex .= preg_quote($part, '#'); // normal text: must match literally
            }
        }

        return '#^' . $regex . '$#';
    }
}