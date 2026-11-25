<?php

declare(strict_types=1);

namespace Botforge\Api;

use Botforge\Config;
use Botforge\Keyboard\ReplyMarkup;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Utils;
use InvalidArgumentException;

/**
 * Talks to the Telegram Bot API.
 *
 * Every method returns what Telegram answered, or throws a TelegramApiException
 * (or one of its children: RateLimitException, ForbiddenException).
 */
final class TelegramClient
{
    private const API_URL = 'https://api.telegram.org';
    private const TIMEOUT_SECONDS = 30;
    private const MAX_RETRY_WAIT_SECONDS = 30;

    private readonly ClientInterface $http;

    /**
     * @param int $maxRetries How many times to retry after a "429 Too Many Requests".
     * @param ClientInterface|null $http Custom Guzzle client (proxy, or a fake one in tests).
     */
    public function __construct(
        private readonly string $token,
        private readonly ?string $defaultParseMode = null,
        private readonly int $maxRetries = 3,
        ?ClientInterface $http = null,
    ) {
        $this->http = $http ?? new Client();
    }

    public static function fromConfig(Config $config, ?ClientInterface $http = null): self
    {
        return new self($config->token, $config->parseMode, 3, $http);
    }

    /** Hides the token if someone var_dump()s this object. */
    public function __debugInfo(): array
    {
        return [
            'defaultParseMode' => $this->defaultParseMode,
            'maxRetries' => $this->maxRetries,
        ];
    }

    // ------------------------------------------------------------------
    // The generic method: works for ANY Telegram method, even ones we did not wrap.
    // ------------------------------------------------------------------

    /**
     * Calls any Bot API method. On "429 Too Many Requests" it waits and tries again.
     *
     * @param array<string, mixed> $params
     *
     * @throws TelegramApiException
     */
    public function call(string $method, array $params = []): mixed
    {
        $retries = 0;

        while (true) {
            try {
                return $this->send($method, $params);
            } catch (RateLimitException $e) {
                $tooManyTries = $retries >= $this->maxRetries;
                $waitIsTooLong = $e->retryAfter > self::MAX_RETRY_WAIT_SECONDS;

                if ($tooManyTries || $waitIsTooLong) {
                    throw $e;
                }

                $retries++;
                sleep(max(1, $e->retryAfter)); // Telegram told us how long to wait
            }
        }
    }

    // ------------------------------------------------------------------
    // Ready-made wrappers for the most used methods.
    // $extra holds optional Telegram parameters, e.g. ['reply_markup' => [...]].
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<mixed> The sent message
     */
    public function sendMessage(int|string $chatId, string $text, array $extra = []): array
    {
        return $this->callArray('sendMessage', $this->withParseMode(['chat_id' => $chatId, 'text' => $text] + $extra));
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<mixed> The edited message
     */
    public function editMessageText(int|string $chatId, int $messageId, string $text, array $extra = []): array
    {
        return $this->callArray('editMessageText', $this->withParseMode([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
        ] + $extra));
    }

    public function deleteMessage(int|string $chatId, int $messageId): bool
    {
        return $this->callBool('deleteMessage', ['chat_id' => $chatId, 'message_id' => $messageId]);
    }

    /**
     * @param InputFile|string $photo A file to upload, or a file_id / URL
     * @param array<string, mixed> $extra
     *
     * @return array<mixed>
     */
    public function sendPhoto(int|string $chatId, InputFile|string $photo, array $extra = []): array
    {
        return $this->callArray('sendPhoto', $this->withParseMode(['chat_id' => $chatId, 'photo' => $photo] + $extra));
    }

    /**
     * @param InputFile|string $document A file to upload, or a file_id / URL
     * @param array<string, mixed> $extra
     *
     * @return array<mixed>
     */
    public function sendDocument(int|string $chatId, InputFile|string $document, array $extra = []): array
    {
        return $this->callArray(
            'sendDocument',
            $this->withParseMode(['chat_id' => $chatId, 'document' => $document] + $extra)
        );
    }

    /** @param array<string, mixed> $extra */
    public function answerCallbackQuery(string $callbackQueryId, array $extra = []): bool
    {
        return $this->callBool('answerCallbackQuery', ['callback_query_id' => $callbackQueryId] + $extra);
    }

    /** Shows "typing..." (or "upload_photo", etc.) at the top of the chat. */
    public function sendChatAction(int|string $chatId, string $action = 'typing'): bool
    {
        return $this->callBool('sendChatAction', ['chat_id' => $chatId, 'action' => $action]);
    }

    /** @return array<mixed> */
    public function getChatMember(int|string $chatId, int $userId): array
    {
        return $this->callArray('getChatMember', ['chat_id' => $chatId, 'user_id' => $userId]);
    }

    /** @return array<mixed> Information about the bot itself */
    public function getMe(): array
    {
        return $this->callArray('getMe');
    }

    /** @return array<mixed> */
    public function getFile(string $fileId): array
    {
        return $this->callArray('getFile', ['file_id' => $fileId]);
    }

    /**
     * Long polling: asks Telegram for new updates.
     * $timeout > 0 makes Telegram wait up to that many seconds for news (saves requests).
     *
     * @return array<mixed> A list of raw updates
     */
    public function getUpdates(?int $offset = null, int $timeout = 0, int $limit = 100): array
    {
        return $this->callArray('getUpdates', ['offset' => $offset, 'timeout' => $timeout, 'limit' => $limit]);
    }

    /** @param array<string, mixed> $extra e.g. ['secret_token' => '...'] */
    public function setWebhook(string $url, array $extra = []): bool
    {
        return $this->callBool('setWebhook', ['url' => $url] + $extra);
    }

    public function deleteWebhook(bool $dropPendingUpdates = false): bool
    {
        return $this->callBool('deleteWebhook', ['drop_pending_updates' => $dropPendingUpdates]);
    }

    /** @return array<mixed> */
    public function getWebhookInfo(): array
    {
        return $this->callArray('getWebhookInfo');
    }

    /**
     * Downloads a file that a user sent to the bot and saves it at $destination.
     *
     * @throws TelegramApiException
     */
    public function downloadFile(string $fileId, string $destination): void
    {
        $file = $this->getFile($fileId);
        $filePath = $file['file_path'] ?? null;

        if (!is_string($filePath)) {
            throw new TelegramApiException('Telegram gave no file path (bots can only download files up to 20 MB).');
        }

        if (!is_dir(dirname($destination))) {
            throw new TelegramApiException(sprintf('Folder does not exist: %s', dirname($destination)));
        }

        $url = self::API_URL . '/file/bot' . $this->token . '/' . $filePath;

        try {
            $response = $this->http->request('GET', $url, [
                'sink' => $destination, // write the answer straight into this file
                'http_errors' => false,
                'connect_timeout' => 10,
                'timeout' => 60,
            ]);
        } catch (GuzzleException $e) {
            throw new TelegramApiException('Network error while downloading a file: ' . $this->hideToken($e->getMessage()));
        }

        if ($response->getStatusCode() !== 200) {
            if (is_file($destination)) {
                unlink($destination); // do not keep a broken file
            }

            throw new TelegramApiException(
                sprintf('Could not download the file (HTTP %d).', $response->getStatusCode()),
                $response->getStatusCode()
            );
        }
    }

    // ------------------------------------------------------------------
    // Inner workings
    // ------------------------------------------------------------------

    /**
     * Sends ONE request to Telegram and checks the answer.
     *
     * @param array<string, mixed> $params
     */
    private function send(string $method, array $params): mixed
    {
        // Friendly option: 'keyboard' => Keyboard::inline()... becomes Telegram's 'reply_markup'.
        if (array_key_exists('keyboard', $params)) {
            $keyboard = $params['keyboard'];
            unset($params['keyboard']);

            if ($keyboard instanceof ReplyMarkup) {
                $params['reply_markup'] = $keyboard->toArray();
            } elseif ($keyboard !== null) {
                throw new InvalidArgumentException('The "keyboard" option must be a Botforge\\Keyboard\\ReplyMarkup object.');
            }
        }

        // Optional parameters that are null are simply left out.
        $params = array_filter($params, static fn (mixed $value): bool => $value !== null);

        $url = self::API_URL . '/bot' . $this->token . '/' . $method;

        $options = $this->buildRequestOptions($params);
        $options['http_errors'] = false; // we read error answers ourselves
        $options['connect_timeout'] = 10;
        // For long polling, the HTTP timeout must be longer than Telegram's waiting time.
        $options['timeout'] = self::TIMEOUT_SECONDS + (int) ($params['timeout'] ?? 0);

        try {
            $response = $this->http->request('POST', $url, $options);
        } catch (GuzzleException $e) {
            // The original message contains the URL (with our token), so we clean it
            // and do NOT pass the original exception along.
            throw new TelegramApiException(
                sprintf('Network error while calling %s: %s', $method, $this->hideToken($e->getMessage()))
            );
        }

        $data = json_decode((string) $response->getBody(), true);

        if (!is_array($data) || !array_key_exists('ok', $data)) {
            throw new TelegramApiException(
                sprintf('Telegram sent an invalid answer to %s (HTTP %d).', $method, $response->getStatusCode()),
                $response->getStatusCode()
            );
        }

        if ($data['ok'] !== true) {
            throw $this->buildException($method, $data);
        }

        return $data['result'] ?? null;
    }

    /**
     * Normal parameters are sent as JSON. If a file is included, we must use "multipart" instead.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function buildRequestOptions(array $params): array
    {
        if ($params === []) {
            return [];
        }

        $hasFile = false;
        foreach ($params as $value) {
            if ($value instanceof InputFile) {
                $hasFile = true;
                break;
            }
        }

        if (!$hasFile) {
            return ['json' => $params];
        }

        $parts = [];
        foreach ($params as $name => $value) {
            if ($value instanceof InputFile) {
                $parts[] = [
                    'name' => (string) $name,
                    'contents' => Utils::tryFopen($value->path, 'r'),
                    'filename' => $value->filename,
                ];
                continue;
            }

            $parts[] = ['name' => (string) $name, 'contents' => $this->toFormValue($value)];
        }

        return ['multipart' => $parts];
    }

    /** Multipart can only carry text, so arrays become JSON text and booleans become true/false. */
    private function toFormValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        return (string) $value;
    }

    /**
     * Turns Telegram's error answer into the right exception class.
     *
     * @param array<mixed> $data
     */
    private function buildException(string $method, array $data): TelegramApiException
    {
        $code = (int) ($data['error_code'] ?? 0);
        $description = (string) ($data['description'] ?? 'Unknown error');
        $message = sprintf('Telegram API error in %s: %s', $method, $description);

        if ($code === 429) {
            $parameters = $data['parameters'] ?? [];
            $retryAfter = is_array($parameters) ? (int) ($parameters['retry_after'] ?? 1) : 1;

            return new RateLimitException($message, $retryAfter);
        }

        if ($code === 403) {
            return new ForbiddenException($message);
        }

        return new TelegramApiException($message, $code);
    }

    /**
     * Adds our default parse_mode, unless the caller set one (even null = "no parse mode").
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function withParseMode(array $params): array
    {
        return $params + ['parse_mode' => $this->defaultParseMode];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<mixed>
     */
    private function callArray(string $method, array $params = []): array
    {
        $result = $this->call($method, $params);

        if (!is_array($result)) {
            throw new TelegramApiException(sprintf('Unexpected answer from %s: expected an object or a list.', $method));
        }

        return $result;
    }

    /** @param array<string, mixed> $params */
    private function callBool(string $method, array $params = []): bool
    {
        $result = $this->call($method, $params);

        if ($result !== true) {
            throw new TelegramApiException(sprintf('Unexpected answer from %s: expected true.', $method));
        }

        return true;
    }

    private function hideToken(string $text): string
    {
        return str_replace($this->token, '***hidden***', $text);
    }
}