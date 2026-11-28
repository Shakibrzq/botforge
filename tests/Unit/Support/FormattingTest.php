<?php

declare(strict_types=1);

namespace BotForge\Tests\Unit\Support;

use Botforge\Support\Html;
use Botforge\Support\MarkdownV2;
use PHPUnit\Framework\TestCase;

final class FormattingTest extends TestCase
{
    public function testHtmlEscapeProtectsMarkupCharacters(): void
    {
        self::assertSame('&lt;b&gt;Tom &amp; Jerry&lt;/b&gt;', Html::escape('<b>Tom & Jerry</b>'));
    }

    public function testHtmlFormattingMethodsEscapeUserText(): void
    {
        self::assertSame('<b>&lt;admin&gt;</b>', Html::bold('<admin>'));
        self::assertSame('<i>A &amp; B</i>', Html::italic('A & B'));
        self::assertSame('<u>name</u>', Html::underline('name'));
        self::assertSame('<s>old</s>', Html::strike('old'));
        self::assertSame('<tg-spoiler>secret</tg-spoiler>', Html::spoiler('secret'));
        self::assertSame('<code>&lt;tag&gt;</code>', Html::code('<tag>'));
    }

    public function testHtmlPreSupportsSafeLanguageName(): void
    {
        self::assertSame(
            '<pre><code class="language-php">&lt;?php</code></pre>',
            Html::pre('<?php', 'php')
        );
    }

    public function testHtmlPreIgnoresInvalidLanguageName(): void
    {
        self::assertSame('<pre>hello</pre>', Html::pre('hello', 'php" onload="x'));
    }

    public function testHtmlLinkEscapesTextAndAttribute(): void
    {
        self::assertSame(
            '<a href="https://example.com/?a=1&amp;b=&quot;x&quot;">&lt;site&gt;</a>',
            Html::link('<site>', 'https://example.com/?a=1&b="x"')
        );
    }

    public function testHtmlMentionBuildsTelegramUserLink(): void
    {
        self::assertSame(
            '<a href="tg://user?id=123">Ali &lt;Admin&gt;</a>',
            Html::mention(123, 'Ali <Admin>')
        );
    }

    public function testMarkdownV2EscapesSpecialCharacters(): void
    {
        self::assertSame('Hello\\.\\!', MarkdownV2::escape('Hello.!'));
    }

    public function testMarkdownV2FormattingMethods(): void
    {
        self::assertSame('*a\\.b*', MarkdownV2::bold('a.b'));
        self::assertSame('_a\\!_', MarkdownV2::italic('a!'));
        self::assertSame('__a\\!__', MarkdownV2::underline('a!'));
        self::assertSame('~a\\!~', MarkdownV2::strike('a!'));
        self::assertSame('||a\\!||', MarkdownV2::spoiler('a!'));
        self::assertSame('`a\\`b`', MarkdownV2::code('a`b'));
    }

    public function testMarkdownV2PreAndLanguage(): void
    {
        self::assertSame("```php\nhello\n```", MarkdownV2::pre('hello', 'php'));
        self::assertSame("```\nhello\n```", MarkdownV2::pre('hello', 'php"bad'));
    }

    public function testMarkdownV2LinkAndMention(): void
    {
        self::assertSame('[A\\!](https://example.com/a\\)b)', MarkdownV2::link('A!', 'https://example.com/a)b'));
        self::assertSame('[Ali](tg://user?id=123)', MarkdownV2::mention(123, 'Ali'));
    }
}
