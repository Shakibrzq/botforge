<?php

declare(strict_types=1);

namespace Botforge\Storage;

interface StorageInterface
{
    /**
     * Retrieve a value from storage.
     */
    public function get(string $key): mixed;

    /**
     * Store a value.
     *
     * @param int|null $ttl Time to live in seconds.
     */
    public function set(string $key, mixed $value, ?int $ttl = null): void;

    /**
     * Check whether a key exists.
     */
    public function has(string $key): bool;

    /**
     * Delete a key from storage.
     */
    public function delete(string $key): void;
}
