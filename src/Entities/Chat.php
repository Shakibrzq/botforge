<?php

declare(strict_types=1);

namespace Botforge\Entities;

/**
 * A conversation place: a private chat, a group, a supergroup or a channel.
 */
final readonly class Chat
{
    public function __construct(
        public int $id,
        public string $type, // private | group | supergroup | channel
        public ?string $title = null,
        public ?string $username = null,
        public ?string $firstName = null,
        public ?string $lastName = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            type: $data['type'],
            title: $data['title'] ?? null,
            username: $data['username'] ?? null,
            firstName: $data['first_name'] ?? null,
            lastName: $data['last_name'] ?? null,
        );
    }

    public function isPrivate(): bool
    {
        return $this->type === 'private';
    }

    /** True for both normal groups and supergroups. */
    public function isGroup(): bool
    {
        return in_array($this->type, ['group', 'supergroup'], true);
    }

    public function isChannel(): bool
    {
        return $this->type === 'channel';
    }
}