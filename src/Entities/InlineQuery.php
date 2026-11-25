<?php

declare(strict_types=1);

namespace Botforge\Entities;

/**
 * What arrives when a user types "@yourbot something" in any chat.
 */
final readonly class InlineQuery
{
    public function __construct(
        public string $id,
        public User $from,
        public string $query,
        public string $offset,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            from: User::fromArray($data['from']),
            query: $data['query'],
            offset: $data['offset'],
        );
    }
}