<?php

declare(strict_types=1);

namespace Botforge\Storage;

use Redis;
use RuntimeException;

final class RedisStorage implements StorageInterface
{
    public function __construct(
        private readonly Redis $redis,
        private readonly string $prefix = 'botforge:',
    ) {
    }

    public function get(string $key): mixed
    {
        $value = $this->redis->get($this->formatKey($key));

        if ($value === false) {
            return null;
        }

        return unserialize($value, ['allowed_classes' => false]);
    }

    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        if ($ttl !== null && $ttl < 0) {
            throw new RuntimeException('TTL cannot be negative.');
        }

        $serialized = serialize($value);
        $redisKey = $this->formatKey($key);

        if ($ttl === null) {
            $result = $this->redis->set($redisKey, $serialized);
        } else {
            $result = $this->redis->setex($redisKey, $ttl, $serialized);
        }

        if ($result === false) {
            throw new RuntimeException('Failed to store value in Redis.');
        }
    }

    public function has(string $key): bool
    {
        return $this->redis->exists($this->formatKey($key)) > 0;
    }

    public function delete(string $key): void
    {
        $this->redis->del($this->formatKey($key));
    }

    private function formatKey(string $key): string
    {
        return $this->prefix . $key;
    }
}