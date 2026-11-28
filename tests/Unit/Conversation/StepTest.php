<?php

declare(strict_types=1);

namespace BotForge\Tests\Unit\Conversation;

use Botforge\Context\Context;
use Botforge\Conversation\Conversation;
use Botforge\Conversation\Step;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use UnexpectedValueException;

final class StepTest extends TestCase
{
    private function context(): Context
    {
        return (new ReflectionClass(Context::class))->newInstanceWithoutConstructor();
    }

    public function testStepWithoutValidatorAcceptsInput(): void
    {
        $step = new Step('name', 'What is your name?');

        self::assertTrue($step->validate('Shakiba', $this->context(), []));
    }

    public function testStepAcceptsInputWhenValidatorReturnsTrue(): void
    {
        $step = new Step(
            'age',
            'How old are you?',
            static fn (string $input): bool => ctype_digit($input),
        );

        self::assertTrue($step->validate('24', $this->context(), []));
    }

    public function testStepReturnsValidatorErrorMessage(): void
    {
        $step = new Step(
            'phone',
            'Enter your phone number',
            static fn (string $input): bool|string => preg_match('/^09\d{9}$/', $input) === 1
                ? true
                : 'Invalid phone number.',
        );

        self::assertSame(
            'Invalid phone number.',
            $step->validate('123', $this->context(), [])
        );
    }

    public function testValidatorReceivesPreviouslyCollectedData(): void
    {
        $step = new Step(
            'confirm',
            'Confirm?',
            static fn (string $input, Context $context, array $data): bool =>
                $input === 'yes' && ($data['name'] ?? null) === 'Ali',
        );

        self::assertTrue(
            $step->validate('yes', $this->context(), ['name' => 'Ali'])
        );
    }

    public function testEmptyKeyIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Step('', 'Prompt');
    }

    public function testEmptyPromptIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Step('name', '   ');
    }

    public function testNonCallableValidatorIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Step('name', 'Prompt', 'not-callable');
    }

    public function testValidatorMustReturnBooleanOrString(): void
    {
        $step = new Step('name', 'Prompt', static fn (): null => null);

        $this->expectException(UnexpectedValueException::class);
        $step->validate('Ali', $this->context(), []);
    }

    public function testBaseConversationHasDefaultTimeout(): void
    {
        $conversation = new class extends Conversation {
            public function steps(): array
            {
                return [new Step('name', 'Name?')];
            }
        };

        self::assertSame(1800, $conversation->timeout());
    }
}
