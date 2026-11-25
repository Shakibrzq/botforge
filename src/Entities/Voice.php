<?php

declare(strict_types=1);

namespace Botforge\Entities;

/**
 * A voice message.
 */
final readonly class Voice
{
    public function __construct(
        public string $fileId,
        public string $fileUniqueId,
        public int $duration, // in seconds
        public ?string $mimeType = null,
        public ?int $fileSize = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            fileId: $data['file_id'],
            fileUniqueId: $data['file_unique_id'],
            duration: $data['duration'],
            mimeType: $data['mime_type'] ?? null,
            fileSize: $data['file_size'] ?? null,
        );
    }
}