<?php

declare(strict_types=1);

namespace Botforge\Storage;

use PDO;
use RuntimeException;

final class PdoStorage implements StorageInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $table = 'botforge_storage',
    ) {
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $this->table) !== 1) {
            throw new RuntimeException('Invalid storage table name.');
        }

        $this->createTable();
    }

    public function get(string $key): mixed
    {
        $sql = "SELECT value, expires_at FROM {$this->table} WHERE storage_key = :key";

        $statement = $this->pdo->prepare($sql);
        $statement->execute(['key' => $key]);

        $record = $statement->fetch(PDO::FETCH_ASSOC);

        if ($record === false) {
            return null;
        }

        if (
            $record['expires_at'] !== null
            && (int) $record['expires_at'] <= time()
        ) {
            $this->delete($key);

            return null;
        }

        return unserialize(
            $record['value'],
            ['allowed_classes' => false]
        );
    }

    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        if ($ttl !== null && $ttl < 0) {
            throw new RuntimeException('TTL cannot be negative.');
        }

        $expiresAt = $ttl === null ? null : time() + $ttl;

        $this->pdo->beginTransaction();

        try {
            $delete = $this->pdo->prepare(
                "DELETE FROM {$this->table} WHERE storage_key = :key"
            );

            $delete->execute(['key' => $key]);

            $insert = $this->pdo->prepare(
                "INSERT INTO {$this->table} (storage_key, value, expires_at)
                 VALUES (:key, :value, :expires_at)"
            );

            $insert->execute([
                'key' => $key,
                'value' => serialize($value),
                'expires_at' => $expiresAt,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function has(string $key): bool
    {
        $sql = "SELECT expires_at FROM {$this->table} WHERE storage_key = :key";

        $statement = $this->pdo->prepare($sql);
        $statement->execute(['key' => $key]);

        $record = $statement->fetch(PDO::FETCH_ASSOC);

        if ($record === false) {
            return false;
        }

        if (
            $record['expires_at'] !== null
            && (int) $record['expires_at'] <= time()
        ) {
            $this->delete($key);

            return false;
        }

        return true;
    }

    public function delete(string $key): void
    {
        $statement = $this->pdo->prepare(
            "DELETE FROM {$this->table} WHERE storage_key = :key"
        );

        $statement->execute(['key' => $key]);
    }

    private function createTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS {$this->table} (
                storage_key VARCHAR(255) PRIMARY KEY,
                value TEXT NOT NULL,
                expires_at BIGINT NULL
            )
        ";

        $this->pdo->exec($sql);
    }
}