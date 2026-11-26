<?php

declare(strict_types=1);

namespace Botforge\Storage;

final class Session
{
    private array $data;

    public function __construct(
        private readonly StorageInterface $storage,
        private readonly string $key,
        array $initialData = [],
        private readonly ?int $ttl = null,
    ) {
        $stored = $this->storage->get($this->key);

        $this->data = is_array($stored)
            ? $stored
            : $initialData;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;

        $this->save();
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function delete(string $key): void
    {
        unset($this->data[$key]);

        $this->save();
    }

    public function all(): array
    {
        return $this->data;
    }

    public function clear(): void
    {
        $this->data = [];

        $this->save();
    }

    private function save(): void
    {
        $this->storage->set(
            $this->key,
            $this->data,
            $this->ttl,
        );
    }
}
