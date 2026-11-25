<?php

declare(strict_types=1);

namespace Botforge\Entities;

use JsonException;

/**
 * One "news item" from Telegram. Exactly one of the fields (message, callbackQuery, ...) is filled.
 */
final readonly class Update
{
    /**
     * @param array<string, mixed> $raw The original data, in case you need a field we did not model
     */
    public function __construct(
        public int $updateId,
        public ?Message $message = null,
        public ?Message $editedMessage = null,
        public ?Message $channelPost = null,
        public ?CallbackQuery $callbackQuery = null,
        public ?InlineQuery $inlineQuery = null,
        public ?ChatMemberUpdated $myChatMember = null, // the bot's own status changed
        public ?ChatMemberUpdated $chatMember = null, // someone else's status changed
        public array $raw = [],
    ) {
    }

    /**
     * Builds an Update from the JSON text Telegram sends.
     *
     * @throws InvalidUpdateException
     */
    public static function fromJson(string $json): self
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidUpdateException('The received text is not valid JSON.');
        }

        if (!is_array($data) || !isset($data['update_id'])) {
            throw new InvalidUpdateException('This does not look like a Telegram update (no update_id).');
        }

        return self::fromArray($data);
    }

    /**
     * Unknown fields are simply ignored, so new Telegram features never break us.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $message = $data['message'] ?? null;
        $editedMessage = $data['edited_message'] ?? null;
        $channelPost = $data['channel_post'] ?? null;
        $callbackQuery = $data['callback_query'] ?? null;
        $inlineQuery = $data['inline_query'] ?? null;
        $myChatMember = $data['my_chat_member'] ?? null;
        $chatMember = $data['chat_member'] ?? null;

        return new self(
            updateId: $data['update_id'],
            message: is_array($message) ? Message::fromArray($message) : null,
            editedMessage: is_array($editedMessage) ? Message::fromArray($editedMessage) : null,
            channelPost: is_array($channelPost) ? Message::fromArray($channelPost) : null,
            callbackQuery: is_array($callbackQuery) ? CallbackQuery::fromArray($callbackQuery) : null,
            inlineQuery: is_array($inlineQuery) ? InlineQuery::fromArray($inlineQuery) : null,
            myChatMember: is_array($myChatMember) ? ChatMemberUpdated::fromArray($myChatMember) : null,
            chatMember: is_array($chatMember) ? ChatMemberUpdated::fromArray($chatMember) : null,
            raw: $data,
        );
    }

    /** What kind of news is this? */
    public function type(): string
    {
        return match (true) {
            $this->message !== null => 'message',
            $this->editedMessage !== null => 'edited_message',
            $this->channelPost !== null => 'channel_post',
            $this->callbackQuery !== null => 'callback_query',
            $this->inlineQuery !== null => 'inline_query',
            $this->myChatMember !== null => 'my_chat_member',
            $this->chatMember !== null => 'chat_member',
            default => 'unknown',
        };
    }

    /** Who caused this update? (null for channel posts) */
    public function from(): ?User
    {
        return $this->message?->from
            ?? $this->editedMessage?->from
            ?? $this->callbackQuery?->from
            ?? $this->inlineQuery?->from
            ?? $this->myChatMember?->from
            ?? $this->chatMember?->from;
    }

    /** Where did this happen? (null for inline queries, which have no chat) */
    public function chat(): ?Chat
    {
        return $this->message?->chat
            ?? $this->editedMessage?->chat
            ?? $this->channelPost?->chat
            ?? $this->callbackQuery?->message?->chat
            ?? $this->myChatMember?->chat
            ?? $this->chatMember?->chat;
    }
}