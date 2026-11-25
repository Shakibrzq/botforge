<?php

declare(strict_types=1);

namespace Botforge\Entities;

/**
 * What arrives when a user presses an inline button.
 */
final readonly class CallbackQuery
{
    public function __construct(
        public string $id,
        public User $from,
        public ?Message $message = null, // the message that holds the button
        public ?string $data = null, // the text we put inside the button
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $message = $data['message'] ?? null;

        return new self(
            id: $data['id'],
            from: User::fromArray($data['from']),
            message: is_array($message) ? Message::fromArray($message) : null,
            data: $data['data'] ?? null,
        );
    }
}