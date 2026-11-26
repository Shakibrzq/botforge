<?php

declare(strict_types=1);

namespace Botforge\Conversation;

use Botforge\Context\Context;
use InvalidArgumentException;

final class ConversationManager
{
    private const SESSION_KEY = 'conversation_state';

    /**
     * Starts a new conversation.
     */
    public function start(
        Context $context,
        Conversation $conversation,
    ): void {
        $steps = $conversation->steps();

        if ($steps === []) {
            throw new InvalidArgumentException(
                'A conversation must contain at least one step.'
            );
        }

        $session = $context->session();

        $session->set(self::SESSION_KEY, [
            'class' => $conversation::class,
            'current_step' => 0,
            'data' => [],
            'last_activity' => time(),
        ]);

        $context->reply($steps[0]->prompt);
    }

    /**
     * Handles an incoming user message.
     *
     * Commands must be left for the Router, so they cannot accidentally
     * become answers to a registration step. The /cancel command is the
     * only command handled by an active conversation.
     *
     * Returns true if an active conversation handled the message.
     */
    public function handle(Context $context, string $input): bool
    {
        $command = $context->command();

        // Important: don't consume /start, /help, /products, /register, etc.
        // Let the normal Router dispatch those commands.
        if ($command !== null && $command !== 'cancel') {
            return false;
        }

        $session = $context->session();

        if (!$session->has(self::SESSION_KEY)) {
            return false;
        }

        $state = $session->get(self::SESSION_KEY);

        if (!is_array($state) || !isset($state['class'])) {
            $session->delete(self::SESSION_KEY);

            return false;
        }

        $class = $state['class'];

        if (!is_string($class) || !class_exists($class)) {
            $session->delete(self::SESSION_KEY);

            return false;
        }

        $conversation = new $class();

        if (!$conversation instanceof Conversation) {
            $session->delete(self::SESSION_KEY);

            return false;
        }

        // Handle cancellation, including /cancel@BotName.
        if ($command === 'cancel') {
            $conversation->onCancel($context);
            $session->delete(self::SESSION_KEY);

            return true;
        }

        // Check conversation timeout.
        $lastActivity = $state['last_activity'] ?? 0;

        if (
            !is_int($lastActivity)
            || time() - $lastActivity > $conversation->timeout()
        ) {
            $session->delete(self::SESSION_KEY);
            $context->reply('Your conversation has expired.');

            return true;
        }

        $steps = $conversation->steps();
        $currentStep = $state['current_step'] ?? null;

        if (
            !is_int($currentStep)
            || !isset($steps[$currentStep])
            || !$steps[$currentStep] instanceof Step
        ) {
            $session->delete(self::SESSION_KEY);

            return false;
        }

        $step = $steps[$currentStep];
        $data = $state['data'] ?? [];

        if (!is_array($data)) {
            $data = [];
        }

        // Validate the user's input.
        $result = $step->validate($input, $context, $data);

        if ($result !== true) {
            $message = is_string($result)
                ? $result
                : $step->invalidMessage;

            $context->reply($message);

            return true;
        }

        // Save the valid answer.
        $data[$step->key] = $input;

        $nextStep = $currentStep + 1;

        // Save updated state.
        $state['data'] = $data;
        $state['current_step'] = $nextStep;
        $state['last_activity'] = time();

        // Check whether the conversation is completed.
        if (!isset($steps[$nextStep])) {
            $conversation->onComplete($context, $data);
            $session->delete(self::SESSION_KEY);

            return true;
        }

        $session->set(self::SESSION_KEY, $state);

        // Ask the next question.
        $context->reply($steps[$nextStep]->prompt);

        return true;
    }
}