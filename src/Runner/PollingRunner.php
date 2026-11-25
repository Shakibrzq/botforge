<?php

declare(strict_types=1);

namespace Botforge\Runner;

use Botforge\Api\TelegramApiException;
use Botforge\Api\TelegramClient;
use Botforge\Entities\Update;
use Closure;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Long polling mode: we keep asking Telegram "anything new?" in an endless loop.
 * Good for development on your own computer (no public web address needed).
 */
final class PollingRunner
{
    private bool $running = true;

    /**
     * @param Closure(Update): void $handle What to do with each update (usually Bot::handle)
     * @param int $timeout Seconds Telegram may wait for news before answering "nothing". Kept short on
     *                     purpose: a stop request is noticed after the current wait ends.
     */
    public function __construct(
        private readonly TelegramClient $telegram,
        private readonly Closure $handle,
        private readonly LoggerInterface $logger,
        private readonly int $timeout = 10,
    ) {
    }

    /** Asks the loop to stop after the current round. */
    public function stop(): void
    {
        $this->running = false;
    }

    /**
     * @param int|null $maxRounds Stop after this many rounds (used by tests). null = run until stopped.
     */
    public function run(?int $maxRounds = null): void
    {
        // Telegram refuses getUpdates while a webhook is set, so we remove it first.
        $this->telegram->deleteWebhook();
        $this->listenForStopSignals();
        $this->logger->info('Long polling started');

        $offset = null;
        $rounds = 0;

        while ($this->running && ($maxRounds === null || $rounds < $maxRounds)) {
            ++$rounds;

            try {
                $updates = $this->telegram->getUpdates($offset, $this->timeout);
            } catch (TelegramApiException $e) {
                $this->logger->warning('getUpdates failed, trying again soon', ['error' => $e->getMessage()]);
                sleep(3); // do not hammer Telegram (or the network) while something is wrong

                continue;
            }

            foreach ($updates as $raw) {
                if (!is_array($raw) || !isset($raw['update_id'])) {
                    continue;
                }

                // Confirm this update BEFORE handling it. Otherwise one update that always
                // crashes would come back forever.
                $offset = $raw['update_id'] + 1;

                try {
                    $update = Update::fromArray($raw);
                } catch (Throwable $e) {
                    $this->logger->warning('Skipped an update that could not be read', [
                        'exception' => $e::class,
                    ]);

                    continue;
                }

                try {
                    ($this->handle)($update);
                } catch (Throwable $e) {
                    $this->logger->error('A handler crashed', ['exception' => $e::class, 'error' => $e->getMessage()]);
                }
            }
        }

        $this->logger->info('Long polling stopped');
    }

    /** On Linux/Mac/Docker: Ctrl+C and "docker stop" (SIGTERM) end the loop politely. */
    private function listenForStopSignals(): void
    {
        if (!function_exists('pcntl_async_signals')) {
            return; // Windows, or PHP without pcntl: use Ctrl+C (the process just ends)
        }

        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, fn () => $this->stop());
        pcntl_signal(SIGINT, fn () => $this->stop());
    }
}