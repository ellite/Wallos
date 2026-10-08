<?php

/**
 * Whether a chat can have topics. Positive numeric IDs are direct messages with
 * a user, which have no topics; groups and supergroups have negative IDs.
 *
 * @param string|int $chatId
 * @return bool
 */
function wallos_telegram_chat_supports_topics($chatId)
{
    return !preg_match('/^\\s*\\+?\\d+\\s*$/', (string) $chatId);
}

/**
 * Builds the sendMessage payload for the Telegram Bot API.
 *
 * message_thread_id is only included for a positive numeric topic ID, which
 * routes the message to that topic inside a group chat; empty or invalid
 * values fall back to the general chat. Direct-message chats never get one.
 *
 * @param string          $chatId
 * @param string          $text
 * @param string|int|null $messageThreadId
 * @return array
 */
function wallos_telegram_payload($chatId, $text, $messageThreadId = null)
{
    $payload = ['chat_id' => $chatId, 'text' => $text];

    $threadId = trim((string) $messageThreadId);
    if ($threadId !== '' && wallos_telegram_chat_supports_topics($chatId) && ctype_digit($threadId) && (int) $threadId > 0) {
        $payload['message_thread_id'] = (int) $threadId;
    }

    return $payload;
}
