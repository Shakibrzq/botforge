<?php

declare(strict_types=1);

namespace Botforge\Entities;

/**
 * A message in a chat. Only the fields that match its kind are filled; the others stay null.
 */
final readonly class Message
{
    /**
     * @param list<PhotoSize> $photo All sizes of the photo (empty when the message has no photo)
     */
    public function __construct(
        public int $messageId,
        public int $date, // Unix time
        public Chat $chat,
        public ?User $from = null, // null for channel posts
        public ?string $text = null,
        public ?string $caption = null, // the text under a photo/document
        public array $photo = [],
        public ?Document $document = null,
        public ?Voice $voice = null,
        public ?Contact $contact = null,
        public ?Location $location = null,
        public ?Message $replyToMessage = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $from = $data['from'] ?? null;
        $document = $data['document'] ?? null;
        $voice = $data['voice'] ?? null;
        $contact = $data['contact'] ?? null;
        $location = $data['location'] ?? null;
        $reply = $data['reply_to_message'] ?? null;

        $photos = [];
        $rawPhotos = $data['photo'] ?? [];
        if (is_array($rawPhotos)) {
            foreach ($rawPhotos as $size) {
                $photos[] = PhotoSize::fromArray($size);
            }
        }

        return new self(
            messageId: $data['message_id'],
            date: $data['date'],
            chat: Chat::fromArray($data['chat']),
            from: is_array($from) ? User::fromArray($from) : null,
            text: $data['text'] ?? null,
            caption: $data['caption'] ?? null,
            photo: $photos,
            document: is_array($document) ? Document::fromArray($document) : null,
            voice: is_array($voice) ? Voice::fromArray($voice) : null,
            contact: is_array($contact) ? Contact::fromArray($contact) : null,
            location: is_array($location) ? Location::fromArray($location) : null,
            replyToMessage: is_array($reply) ? self::fromArray($reply) : null,
        );
    }

    /** What kind of message is this? text | photo | document | voice | contact | location | unknown */
    public function type(): string
    {
        return match (true) {
            $this->text !== null => 'text',
            $this->photo !== [] => 'photo',
            $this->document !== null => 'document',
            $this->voice !== null => 'voice',
            $this->contact !== null => 'contact',
            $this->location !== null => 'location',
            default => 'unknown',
        };
    }

    /** Telegram lists photo sizes from small to big, so the biggest one is the last. */
    public function largestPhoto(): ?PhotoSize
    {
        if ($this->photo === []) {
            return null;
        }

        return $this->photo[array_key_last($this->photo)];
    }
}