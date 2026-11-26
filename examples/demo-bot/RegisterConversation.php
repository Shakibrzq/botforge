<?php

declare(strict_types=1);

namespace DemoBot;

use Botforge\Context\Context;
use Botforge\Conversation\Conversation;
use Botforge\Conversation\Step;

final class RegisterConversation extends Conversation
{
    /**
     * Defines the registration steps.
     *
     * @return array<int, Step>
     */
    public function steps(): array
    {
        return [
            new Step(
                key: 'name',
                prompt: 'Please enter your name:',
                validator: fn (string $input): bool =>
                    trim($input) !== '',
                invalidMessage: 'Name cannot be empty. Please try again.',
            ),

            new Step(
                key: 'phone',
                prompt: 'Please enter your phone number:',
                validator: fn (string $input): bool =>
                    preg_match('/^\+?[0-9]{10,15}$/', trim($input)) === 1,
                invalidMessage: 'Please enter a valid phone number.',
            ),

            new Step(
                key: 'confirm',
                prompt: 'Do you confirm your registration? (yes/no)',
                validator: fn (string $input): bool =>
                    in_array(strtolower(trim($input)), ['yes', 'no'], true),
                invalidMessage: 'Please answer with yes or no.',
            ),
        ];
    }

    /**
     * Called after all steps are completed.
     *
     * @param array<string, mixed> $data
     */
    public function onComplete(Context $context, array $data): void
    {
        if (strtolower(trim((string) $data['confirm'])) === 'no') {
            $context->reply('Registration cancelled.');

            return;
        }

        $name = $data['name'];
        $phone = $data['phone'];

        $context->reply(
            "Registration completed successfully!\n"
            . "Name: {$name}\n"
            . "Phone: {$phone}"
        );
    }

    /**
     * Called when the user sends /cancel.
     */
    public function onCancel(Context $context): void
    {
        $context->reply('Registration cancelled.');
    }
}