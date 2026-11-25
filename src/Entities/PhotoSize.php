<?php

declare(strict_types=1);

namespace Botforge\Entities;

/**
 * One size of a photo. Telegram sends every photo in several sizes.
 */
final readonly class PhotoSize
{
    public function __construct(
        public string $fileId,
        public string $fileUniqueId,
        public int $width,
        public int $height,
        public ?int $fileSize = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            fileId: $data['file_id'],
            fileUniqueId: $data['file_unique_id'],
            width: $data['width'],
            height: $data['height'],
            fileSize: $data['file_size'] ?? null,
        );
    }
}