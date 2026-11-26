<?php

declare(strict_types=1);

namespace Botforge\Storage;

use InvalidArgumentException;
use RuntimeException;
use UnexpectedValueException;

final class FileStorage implements StorageInterface
{
    public function __construct(
        private readonly string $directory,
    ) {
        if (!is_dir($this->directory)) {
            if (!mkdir($this->directory, 0770, true) && !is_dir($this->directory)) {
                throw new RuntimeException(
                    "Could not create storage directory: {$this->directory}"
                );
            }
        }

        if (!is_writable($this->directory)) {
            throw new RuntimeException(
                "Storage directory is not writable: {$this->directory}"
            );
        }
    }

    public function get(string $key): mixed
    {
        $record = $this->readRecord($key);

        return $record['value'] ?? null;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        $this->validateKey($key);

        if ($ttl !== null && $ttl < 0) {
            throw new InvalidArgumentException(
                'TTL cannot be negative.'
            );
        }

        $expiresAt = $ttl === null ? null : time() + $ttl;

        $record = [
            'expires_at' => $expiresAt,
            'value' => $value,
        ];

        $contents = serialize($record);

        $path = $this->pathFor($key);
        $lock = $this->lockFor($key);

        $this->withLock($lock, LOCK_EX, function () use ($path, $contents): void {
            $handle = fopen($path, 'wb');

            if ($handle === false) {
                throw new RuntimeException(
                    "Could not open storage file: {$path}"
                );
            }

            try {
                $written = fwrite($handle, $contents);

                if ($written !== strlen($contents)) {
                    throw new RuntimeException(
                        "Could not write storage file: {$path}"
                    );
                }

                fflush($handle);
            } finally {
                fclose($handle);
            }

            @chmod($path, 0600);
        });
    }

    public function has(string $key): bool
    {
        return $this->readRecord($key) !== null;
    }

    public function delete(string $key): void
    {
        $this->validateKey($key);

        $path = $this->pathFor($key);
        $lock = $this->lockFor($key);

        $this->withLock($lock, LOCK_EX, function () use ($path): void {
            if (is_file($path) && !unlink($path)) {
                throw new RuntimeException(
                    "Could not delete storage file: {$path}"
                );
            }
        });
    }

    /**
     * @return array{expires_at: int|null, value: mixed}|null
     */
    private function readRecord(string $key): ?array
    {
        $this->validateKey($key);

        $path = $this->pathFor($key);
        $lock = $this->lockFor($key);

        if (!is_file($path)) {
            return null;
        }

        $record = $this->withLock(
            $lock,
            LOCK_SH,
            function () use ($path): ?array {
                if (!is_file($path)) {
                    return null;
                }

                $contents = file_get_contents($path);

                if ($contents === false) {
                    throw new RuntimeException(
                        "Could not read storage file: {$path}"
                    );
                }

                $record = unserialize(
                    $contents,
                    ['allowed_classes' => false]
                );

                if (
                    !is_array($record)
                    || !array_key_exists('value', $record)
                    || !array_key_exists('expires_at', $record)
                ) {
                    throw new UnexpectedValueException(
                        "Invalid storage record: {$path}"
                    );
                }

                return $record;
            }
        );

        if ($record === null) {
            return null;
        }

        $expiresAt = $record['expires_at'];

        if ($expiresAt !== null && $expiresAt <= time()) {
            $this->delete($key);

            return null;
        }

        return $record;
    }

    private function pathFor(string $key): string
    {
        return $this->directory . '/' . hash('sha256', $key) . '.dat';
    }

    private function lockFor(string $key): string
    {
        return $this->directory . '/' . hash('sha256', $key) . '.lock';
    }

    private function validateKey(string $key): void
    {
        if ($key === '') {
            throw new InvalidArgumentException(
                'Storage key cannot be empty.'
            );
        }
    }

    /**
     * Execute an operation while holding a file lock.
     */
    private function withLock(
        string $lockPath,
        int $mode,
        callable $callback,
    ): mixed {
        $handle = fopen($lockPath, 'c');

        if ($handle === false) {
            throw new RuntimeException(
                "Could not open lock file: {$lockPath}"
            );
        }

        try {
            if (!flock($handle, $mode)) {
                throw new RuntimeException(
                    "Could not lock storage file: {$lockPath}"
                );
            }

            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
