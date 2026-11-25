<?php

declare(strict_types=1);

namespace Botforge\Middleware;

use Botforge\Context\Context;
use Botforge\Entities\Chat;
use Closure;
use InvalidArgumentException;

/**
 * Lets an update through only if it comes from an allowed kind of chat.
 * new ChatTypeMiddleware('private')  or  new ChatTypeMiddleware('group', 'channel')
 *
 * Other updates are ignored silently (no reply), so the bot does not spam groups.
 * Updates without a chat (inline queries) are blocked too.
 */
final class ChatTypeMiddleware implements Middleware
{
    private const ALLOWED = ['private', 'group', 'channel'];

    /** @var list<string> */
    private readonly array $types;

    public function __construct(string ...$types)
    {
        if ($types === []) {
            throw new InvalidArgumentException('Give at least one chat type: ' . implode(', ', self::ALLOWED) . '.');
        }

        foreach ($types as $type) {
            if (!in_array($type, self::ALLOWED, true)) {
                throw new InvalidArgumentException(
                    sprintf('Unknown chat type "%s". Use: %s.', $type, implode(', ', self::ALLOWED))
                );
            }
        }

        $this->types = array_values($types);
    }

    public function process(Context $ctx, Closure $next): mixed
    {
        $chat = $ctx->chat();

        if ($chat === null || !$this->isAllowed($chat)) {
            return null; // stop here: the handler never runs
        }

        return $next($ctx);
    }

    private function isAllowed(Chat $chat): bool
    {
        foreach ($this->types as $type) {
            if (
                ($type === 'private' && $chat->isPrivate())
                || ($type === 'group' && $chat->isGroup())
                || ($type === 'channel' && $chat->isChannel())
            ) {
                return true;
            }
        }

        return false;
    }
}