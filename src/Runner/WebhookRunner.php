<?php

declare(strict_types=1);

namespace Botforge\Runner;

use Botforge\Config;
use Botforge\Entities\InvalidUpdateException;
use Botforge\Entities\Update;
use Closure;
use Psr\Log\LoggerInterface;
use TypeError;

/**
 * Webhook mode: Telegram calls OUR web address once for every update.
 * This class checks the request, answers "200 OK" quickly, and then hands the update to the bot.
 */
final class WebhookRunner
{
    /**
     * @param Closure(Update): void $handle What to do with a valid update (usually Bot::handle)
     */
    public function __construct(
        private readonly Config $config,
        private readonly LoggerInterface $logger,
        private readonly Closure $handle,
    ) {
    }

    /** Reads the current HTTP request, answers it, and handles the update. */
    public function run(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $secretHeader = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? null;
        $body = (string) file_get_contents('php://input');

        $result = $this->parse($method, is_string($secretHeader) ? $secretHeader : null, $body);

        if (is_int($result)) {
            http_response_code($result); // rejected: answer with the error code and stop

            return;
        }

        http_response_code(200);
        $this->finishResponse(); // Telegram gets its answer NOW, before we do the real work

        ($this->handle)($result);
    }

    /**
     * Checks a request WITHOUT touching PHP's global variables (so it is easy to test).
     *
     * @return Update|int The update if the request is fine, otherwise the HTTP status code to answer with
     */
    public function parse(string $method, ?string $secretHeader, string $body): Update|int
    {
        if ($method !== 'POST') {
            return 405;
        }

        // Check WHO is calling before we even look at what they sent.
        $expected = $this->config->secretToken;

        if ($expected !== null && ($secretHeader === null || !hash_equals($expected, $secretHeader))) {
            $this->logger->warning('Webhook request rejected: wrong or missing secret token');

            return 403;
        }

        try {
            return Update::fromJson($body);
        } catch (InvalidUpdateException | TypeError $e) {
            $this->logger->warning('Webhook request rejected: the body is not a valid update', [
                'exception' => $e::class,
            ]);

            return 400;
        }
    }

    /** Sends the HTTP answer to Telegram and lets our script keep working in the background. */
    private function finishResponse(): void
    {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request(); // PHP-FPM only
        }
        // Other servers (Apache, the built-in server) simply answer when the script ends.
    }
}