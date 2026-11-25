<?php

declare(strict_types=1);

// Tests the HTML and MarkdownV2 helpers. No internet, no real token.
// Run it with:  php examples/format-test.php

require __DIR__ . '/../vendor/autoload.php';

use Botforge\Support\Html;
use Botforge\Support\MarkdownV2;

// Shows "\n" as the two letters \n, so every result fits on one line.
$line = function (string $label, string $result): void {
    echo '   ' . str_pad($label, 22) . ' => ' . str_replace("\n", '\n', $result) . PHP_EOL;
};

echo '1) HTML' . PHP_EOL;
$line('escape', Html::escape('Tom & <Jerry> "ok"'));
$line('bold (user text)', 'Hello, ' . Html::bold('Ali <script>'));
$line('fake link attack', Html::escape('<a href="evil">click</a>'));
$line('code', Html::code('a < b'));
$line('pre with language', Html::pre('x = 1 < 2', 'php'));
$line('pre, bad language', Html::pre('x', '"><b>'));
$line('link', Html::link('Docs "v2"', 'https://example.com/?a=1&b="2"'));
$line('mention', Html::mention(12345, 'Ali & Sara'));
$line('spoiler', Html::spoiler('secret <3'));
$line('broken UTF-8', Html::escape("ok \xFF end"));

echo PHP_EOL . '2) MarkdownV2' . PHP_EOL;
$line('escape', MarkdownV2::escape('Price: 5.99 (cheap)! #1'));
$line('bold', MarkdownV2::bold('a_b*c'));
$line('italic', MarkdownV2::italic('x.y'));
$line('underline', MarkdownV2::underline('x'));
$line('strike', MarkdownV2::strike('x'));
$line('spoiler', MarkdownV2::spoiler('x'));
$line('code', MarkdownV2::code('x`y\z'));
$line('pre with language', MarkdownV2::pre('a = 1', 'php'));
$line('link', MarkdownV2::link('Docs (v2)', 'https://x.com/a(b)'));
$line('mention', MarkdownV2::mention(12345, 'Ali.K'));