<?php
//تعریف یک مرحله-سوالات اون مرحله-اعتبارسنجی
//


declare(strict_types=1);

namespace Botforge\Conversation;

use Botforge\Context\Context;
use InvalidArgumentException;
use UnexpectedValueException;

final readonly class Step
{
    /**
     * @param callable(string, Context, array<string, mixed>): bool|string|null $validator
     */
    public function __construct(
        public string $key,
        public string $prompt,
        public mixed $validator = null,
        public string $invalidMessage = 'Invalid input. Please try again.',
    ) {
        if ($this->key === '') {
            throw new InvalidArgumentException('Step key cannot be empty.');
        }

        if (trim($this->prompt) === '') {
            throw new InvalidArgumentException('Step prompt cannot be empty.');
        }

        if ($this->validator !== null && !is_callable($this->validator)) {
            throw new InvalidArgumentException('Step validator must be callable.');
        }
    }

    /**
     * Validates the user's input.
     *
     * @param array<string, mixed> $data
     */
    public function validate(
        string $input,
        Context $context,
        array $data,
    ): bool|string {
        if ($this->validator === null) {
            return true;
        }

        $result = ($this->validator)($input, $context, $data);

        if (!is_bool($result) && !is_string($result)) {
            throw new UnexpectedValueException(
                'Step validator must return bool or string.'
            );
        }

        return $result;
    }
}