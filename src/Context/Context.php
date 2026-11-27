<?php

declare(strict_types=1);

namespace Botforge\Context;

use Botforge\Api\InputFile;
use Botforge\Api\TelegramClient;
use Botforge\Entities\Chat;
use Botforge\Entities\Message;
use Botforge\Entities\Update;
use Botforge\Entities\User;
use LogicException;
use Botforge\Storage\Session;
use Botforge\Storage\StorageInterface;

/**
 * The toolbox handed to every handler. It wraps ONE update and the Telegram client,
 * so a handler can simply write $ctx->reply('Hello') without knowing chat ids.
 *
 * Methods that need something the update does not have (for example edit() on a
 * normal text message) throw a LogicException with a clear message.
 */
final class Context
{
    public function __construct(
        private readonly Update $update,
        private readonly TelegramClient $telegram,
        private readonly StorageInterface $storage,

    ) {
    }

    // ------------------------------------------------------------------
    // Reading: "what happened?"
    // ------------------------------------------------------------------

    public function update(): Update
    {
        return $this->update;
    }

    /** The raw API client, for anything the shortcuts below do not cover. */
    public function telegram(): TelegramClient
    {
        return $this->telegram;
    }
    public function session(): Session
    {
        $chat = $this->chat();
        $user = $this->from();

        if ($chat === null || $user === null) {
            throw new LogicException(
                'Session is only available for updates with a chat and a user.'
            );
        }

        $key = sprintf(
            'session:chat:%d:user:%d',
            $chat->id,
            $user->id
        );

        return new Session(
            storage: $this->storage,
            key: $key,
        );
}
    public function from(): ?User
    {
        return $this->update->from();
    }

    public function chat(): ?Chat
    {
        return $this->update->chat();
    }

    /**
     * The message this update is about: the new message, the edited one, the channel post,
     * or (for a button press) the message that holds the button.
     */
    public function message(): ?Message
    {
        return $this->incomingMessage() ?? $this->update->callbackQuery?->message;
    }

    /** The text the user wrote (null if the update is not a written message). */
    public function text(): ?string
    {
        return $this->incomingMessage()?->text;
    }

    /** The text hidden inside the button the user pressed (null if no button was pressed). */
    public function callbackData(): ?string
    {
        return $this->update->callbackQuery?->data;
    }

    /**
     * The command name without "/" and without "@BotName", in lowercase.
     * "/Start@MyBot ref_1" -> "start". Returns null if the text is not a command.
     */
    public function command(): ?string
    {
        $words = $this->words();

        if ($words === [] || !str_starts_with($words[0], '/')) {
            return null;
        }

        $name = explode('@', substr($words[0], 1))[0];

        return $name === '' ? null : strtolower($name);
    }

    /**
     * The words after the command. "/start ref_123" -> ['ref_123'].
     * Returns an empty list if the text is not a command.
     *
     * @return list<string>
     */

    //کارش جدا کردن ارگومان های دستوره
    // مثلا /start ref_123
    // خروجی [ref_123]
    public function args(): array
    {
        if ($this->command() === null) {
            return [];
        }

        return array_slice($this->words(), 1);
    }

    // ------------------------------------------------------------------
    // Acting: "do something"
    // ------------------------------------------------------------------

    /**
     * Sends a message to the current chat.
     *
     * @param array<string, mixed> $extra Optional Telegram parameters, e.g. ['reply_markup' => [...]]
     *
     * @return array<mixed> The sent message
     */
    public function reply(string $text, array $extra = []): array
    {
        return $this->telegram->sendMessage($this->requireChatId(), $text, $extra);
    }

    /**
     * Replaces the text of the message that holds the pressed button.
     *
     * @param array<string, mixed> $extra
     *
     * @return array<mixed>
     */
    public function edit(string $text, array $extra = []): array
    {
        $message = $this->update->callbackQuery?->message;

        if ($message === null) {
            throw new LogicException('edit() can only be used when a user pressed an inline button.');
        }

        return $this->telegram->editMessageText($message->chat->id, $message->messageId, $text, $extra);
    }

    /** Deletes the message of this update (for a button press: the message with the button). */
    public function delete(): bool
    {
        $message = $this->message();

        if ($message === null) {
            throw new LogicException('There is no message to delete in this update.');
        }

        return $this->telegram->deleteMessage($message->chat->id, $message->messageId);
    }

    /**
     * Tells Telegram "I got the button press". Always call this for button presses,
     * otherwise the user's button keeps showing a loading spinner.
     *
     * @param string|null $text A small notice shown to the user
     * @param bool $showAlert true = show it as a popup the user must close
     */
    public function answerCallback(?string $text = null, bool $showAlert = false): bool
    {
        $query = $this->update->callbackQuery;

        if ($query === null) {
            throw new LogicException('answerCallback() can only be used when a user pressed an inline button.');
        }

        return $this->telegram->answerCallbackQuery($query->id, ['text' => $text, 'show_alert' => $showAlert]);
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<mixed>
     */
    public function replyWithPhoto(InputFile|string $photo, ?string $caption = null, array $extra = []): array
    {
        return $this->telegram->sendPhoto($this->requireChatId(), $photo, ['caption' => $caption] + $extra);
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<mixed>
     */
    public function replyWithDocument(InputFile|string $document, ?string $caption = null, array $extra = []): array
    {
        return $this->telegram->sendDocument($this->requireChatId(), $document, ['caption' => $caption] + $extra);
    }

    /** Shows "typing..." in the chat. Telegram hides it after ~5 seconds or when we send a message. */
    public function typing(): bool
    {
        return $this->chatAction('typing');
    }

    /** Other actions: upload_photo, upload_document, record_voice, ... */
    public function chatAction(string $action): bool
    {
        return $this->telegram->sendChatAction($this->requireChatId(), $action);
    }

    // ------------------------------------------------------------------
    // Inner helpers
    // ------------------------------------------------------------------

    /** A message that a person wrote (not the message holding a pressed button). */
    private function incomingMessage(): ?Message
    {
        return $this->update->message
            ?? $this->update->editedMessage
            ?? $this->update->channelPost;
    }

    /**
     * The text split into words: "/buy  2   apples" -> ['/buy', '2', 'apples'].
     *
     * @return list<string>
     */
    private function words(): array
    {
        $text = $this->text();

        if ($text === null) {
            return [];
        }

        $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        return $words === false ? [] : $words;
    }

    private function requireChatId(): int
    {
        $chat = $this->chat();

        if ($chat === null) {
            throw new LogicException('This update has no chat, so there is nobody to send a message to.');
        }

        return $chat->id;
    }
}