<?php

declare(strict_types=1);

namespace Botforge;

use Dotenv\Dotenv;

/**
 * Holds all bot settings in one place.
 *
 * Create it with Config::fromEnv() (reads the .env file and real
 * environment variables) or Config::fromArray() (handy for tests).
 * Every value is checked when the object is created, so a wrong
 * setting fails immediately with a clear message.
 */
final class Config
{
    /** The setting names we read from the environment. */
    private const KEYS = [
        'BOT_TOKEN',
        'WEBHOOK_URL',
        'WEBHOOK_SECRET',
        'ADMIN_IDS',
        'STORAGE_DRIVER',
        'STORAGE_PATH',
        'PARSE_MODE',
        'LOG_LEVEL',
    ];

    public readonly string $token;
    public readonly ?string $webhookUrl;
    public readonly ?string $secretToken;

    /** @var list<int> */
    public readonly array $adminIds;

    public readonly string $storageDriver;
    public readonly string $storagePath;
    public readonly ?string $parseMode;
    public readonly string $logLevel;

    /**
     * @param array<string, string|null> $values Raw settings, keyed by name (e.g. BOT_TOKEN).
     */
    private function __construct(array $values)
    {
        $this->token = self::parseToken($values['BOT_TOKEN'] ?? null);
        $this->webhookUrl = self::parseWebhookUrl($values['WEBHOOK_URL'] ?? null);
        $this->secretToken = self::emptyToNull($values['WEBHOOK_SECRET'] ?? null);
        $this->adminIds = self::parseAdminIds($values['ADMIN_IDS'] ?? null);
        $this->storageDriver = self::parseChoice(
            'STORAGE_DRIVER',
            $values['STORAGE_DRIVER'] ?? null,
            'file',
            ['file', 'redis', 'pdo']
        );
        $this->storagePath = self::emptyToNull($values['STORAGE_PATH'] ?? null) ?? 'storage';

        $parseMode = self::parseChoice(
            'PARSE_MODE',
            $values['PARSE_MODE'] ?? null,
            'HTML',
            ['HTML', 'MarkdownV2', 'none']
        );
        $this->parseMode = $parseMode === 'none' ? null : $parseMode;

        $this->logLevel = self::parseChoice(
            'LOG_LEVEL',
            $values['LOG_LEVEL'] ?? null,
            'info',
            ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency']
        );
    }

    /**
     * Build the config from a .env file (inside $directory) and real environment variables.
     * Real environment variables win over the .env file (useful in Docker/servers).
     *
     * @throws ConfigException
     */
    public static function fromEnv(string $directory): self
    {
        // Reads the .env file and returns its values. Does not touch $_ENV or putenv().
        // If there is no .env file, it simply returns an empty array.
        $fromFile = Dotenv::createArrayBacked($directory)->safeLoad();

        $values = [];
        foreach (self::KEYS as $key) {
            $real = getenv($key);
            $values[$key] = $real !== false ? $real : ($fromFile[$key] ?? null);
        }

        // Default storage folder: <project>/storage
        //لاگ ها توش قرار میگیره
        // coreserviceprovider -> تعیین میکنه لاگ ها تو این قرار بگیره
        if (self::emptyToNull($values['STORAGE_PATH']) === null) {
            $values['STORAGE_PATH'] = $directory . '/storage';
        }

        return new self($values);
    }

    /**
     * Build the config from a plain array (keys are the same names as in .env).
     *
     * @param array<string, string|null> $values
     *
     * @throws ConfigException
     */
    public static function fromArray(array $values): self
    {
        return new self($values);
    }

    /**
     * Used by var_dump()/print_r(): hides secrets so they never leak into logs or screens.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        $vars = get_object_vars($this);
        $vars['token'] = '***hidden***';
        $vars['secretToken'] = $this->secretToken === null ? null : '***hidden***';

        return $vars;
    }

    private static function parseToken(?string $value): string
    {
        $value = self::emptyToNull($value);

        if ($value === null) {
            throw new ConfigException('BOT_TOKEN is missing. Set it in your .env file.');
        }

        // Real tokens look like "123456:ABC-def_123". We do not print the value on purpose.
        if (preg_match('/^\d+:[A-Za-z0-9_-]+$/', $value) !== 1) {
            throw new ConfigException('BOT_TOKEN has an invalid format. Expected something like 123456789:AAExample.');
        }

        return $value;
    }

    private static function parseWebhookUrl(?string $value): ?string
    {
        $value = self::emptyToNull($value);

        if ($value === null) {
            return null;
        }

        if (!str_starts_with($value, 'https://') || filter_var($value, FILTER_VALIDATE_URL) === false) {
            throw new ConfigException('WEBHOOK_URL must be a valid URL starting with https://');
        }

        return $value;
    }

    /**
     * Turns "111, 222" into [111, 222].
     *
     * @return list<int>
     */
    private static function parseAdminIds(?string $value): array
    {
        $value = self::emptyToNull($value);

        if ($value === null) {
            return [];
        }

        $ids = [];
        foreach (explode(',', $value) as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            if (!ctype_digit($part)) {
                throw new ConfigException('ADMIN_IDS must be numbers separated by commas, e.g. 111,222.');
            }

            $ids[] = (int) $part;
        }

        return $ids;
    }

    /**
     * Returns the value if it is one of the allowed choices, or the default if empty.
     *
     * @param list<string> $allowed
     */
    private static function parseChoice(string $name, ?string $value, string $default, array $allowed): string
    {
        $value = self::emptyToNull($value) ?? $default;

        if (!in_array($value, $allowed, true)) {
            throw new ConfigException(sprintf('%s must be one of: %s.', $name, implode(', ', $allowed)));
        }

        return $value;
    }

    /** Treats empty or spaces-only text as "not set". */
    private static function emptyToNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
