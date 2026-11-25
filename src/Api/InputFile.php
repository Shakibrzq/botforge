<?php

declare(strict_types=1);

namespace Botforge\Api;

use InvalidArgumentException;

/**
 * A file from your computer that we want to upload to Telegram.
 *
 * Example: $telegram->sendPhoto($chatId, InputFile::fromPath('/path/cat.jpg'));
 */
final class InputFile
{
    private function __construct(
        public readonly string $path,
        public readonly string $filename,
    ) {
    }

    public static function fromPath(string $path, ?string $filename = null): self
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException(sprintf('File not found or not readable: %s', $path));
        }

        return new self($path, $filename ?? basename($path));
    }
}