<?php

declare(strict_types=1);

namespace Botforge\Middleware;

use Botforge\Context\Context;
use Closure;

/**
 * "Join our channel first": lets the user through only if they are a member of the channel.
 * The bot must be an administrator of that channel, otherwise Telegram refuses to answer
 * (the error is not hidden: it travels up, so you notice the mistake in your logs).
 */
final class ChannelMembershipMiddleware implements Middleware
{
    /**
     * @param int|string $channel '@mychannel' or the numeric id
     * @param string|null $message Text shown to people who have not joined (null = a default text)
     */
    public function __construct(
        private readonly int|string $channel,
        private readonly ?string $message = null,
    ) {
    }

    public function process(Context $ctx, Closure $next): mixed
    {
        $user = $ctx->from();

        if ($user === null) {
            return $next($ctx); // nobody to check (for example a channel post)
        }

        $member = $ctx->telegram()->getChatMember($this->channel, $user->id);
        $status = $member['status'] ?? 'left';

        $isMember = in_array($status, ['creator', 'administrator', 'member'], true)
            || ($status === 'restricted' && ($member['is_member'] ?? false) === true);

        if ($isMember) {
            return $next($ctx);
        }

        $ctx->notify($this->message ?? $this->defaultMessage());

        return null;
    }

    private function defaultMessage(): string
    {
        if (is_string($this->channel) && str_starts_with($this->channel, '@')) {
            return sprintf('Please join %s first, then try again.', $this->channel);
        }

        return 'Please join our channel first, then try again.';
    }
}