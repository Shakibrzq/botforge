<?php

declare(strict_types=1);

namespace Botforge\Keyboard;

use InvalidArgumentException;

/**
 * One button. The same class is used in inline keyboards (callback, url, webApp)
 * and in reply keyboards (plain, requestContact, requestLocation, webApp).
 */
final readonly class Button
{
    private const MAX_CALLBACK_BYTES = 64; // Telegram's limit

    /** @param array<string, mixed> $extra The type-specific part, e.g. ['callback_data' => 'menu'] */
    private function __construct(
        public string $text,
        private array $extra = [],
    ) {
        if (trim($text) === '') {
            throw new InvalidArgumentException('A button needs a text.');
        }
    }

    /** Inline button: when pressed, the bot receives $data (see Router::callback). */
    public static function callback(string $text, string $data): self
    {
        $bytes = strlen($data); // BYTES, not letters: one Persian letter takes 2 bytes

        if ($bytes < 1 || $bytes > self::MAX_CALLBACK_BYTES) {
            throw new InvalidArgumentException(
                sprintf('Callback data must be between 1 and %d bytes (got %d).', self::MAX_CALLBACK_BYTES, $bytes)
            );
        }

        return new self($text, ['callback_data' => $data]);
    }

    /** Inline button that opens a link. */
    public static function url(string $text, string $url): self
    {
        if (preg_match('#^(https?|tg)://#i', $url) !== 1) {
            throw new InvalidArgumentException('A URL button needs a link starting with http://, https:// or tg://.');
        }

        return new self($text, ['url' => $url]);
    }

    /** Button that opens a Telegram Web App (a mini website inside Telegram). */
    public static function webApp(string $text, string $url): self
    {
        if (!str_starts_with(strtolower($url), 'https://')) {
            throw new InvalidArgumentException('A web app button needs an https:// link.');
        }

        return new self($text, ['web_app' => ['url' => $url]]);
    }

    /** Reply-keyboard button: pressing it just sends its text as a message. */
    public static function plain(string $text): self
    {
        return new self($text);
    }

    /** Reply-keyboard button: asks the user to share their phone number. */
    public static function requestContact(string $text): self
    {
        return new self($text, ['request_contact' => true]);
    }

    /** Reply-keyboard button: asks the user to share their location. */
    public static function requestLocation(string $text): self
    {
        return new self($text, ['request_location' => true]);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['text' => $this->text] + $this->extra;
    }
}