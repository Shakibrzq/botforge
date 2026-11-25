<?php

declare(strict_types=1);

namespace Botforge\Entities;

/**
 * Someone's membership status changed (joined, left, was banned, blocked the bot, ...).
 *
 * Status values: creator | administrator | member | restricted | left | kicked.
 * In a private chat, newStatus "kicked" means the user blocked the bot.
 */
final readonly class ChatMemberUpdated
{
    public function __construct(
        public Chat $chat,
        public User $from, // who did the change
        public int $date,
        public User $member, // whose status changed
        public string $oldStatus,
        public string $newStatus,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            chat: Chat::fromArray($data['chat']),
            from: User::fromArray($data['from']),
            date: $data['date'],
            member: User::fromArray($data['new_chat_member']['user']),
            oldStatus: $data['old_chat_member']['status'],
            newStatus: $data['new_chat_member']['status'],
        );
    }
}