<?php
//ساخت مکالمه

declare(strict_types=1);

namespace Botforge\Conversation;

use Botforge\Context\Context;

abstract class Conversation
{
    /**
     * Returns the steps of this conversation in execution order.
     *
     * @return array<int, Step>
     */
    abstract public function steps(): array;

    /**
     * Returns the conversation timeout in seconds.
     */
    public function timeout(): int
    {
        return 1800;
    }

    /**
     * Called when all conversation steps are completed.
     *
     * @param array<string, mixed> $data
     */
    public function onComplete(Context $context, array $data): void
    {
    }

    /**
     * Called when the conversation is cancelled.
     */
    public function onCancel(Context $context): void
    {
    }
}