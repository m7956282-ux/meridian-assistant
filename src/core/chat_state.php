<?php
/**
 * Хранилище состояния чатов
 */
class ChatState {
    private static $chatFile = __DIR__ . '/../config/chats.json';

    /**
     * Найти чат по ID канала
     */
    public static function findByChannel(string $channel, string $channelChatId): ?array {
        $chats = self::load();
        $key = self::makeKey($channel, $channelChatId);
        return $chats[$key] ?? null;
    }

    /**
     * Найти чат по Bitrix24 dialog ID
     */
    public static function findByBitrixDialog(string $dialogId): ?array {
        $chats = self::load();
        foreach ($chats as $chat) {
            if (($chat['bx_dialog'] ?? '') === $dialogId) {
                return $chat;
            }
        }
        return null;
    }

    /**
     * Создать новый чат
     */
    public static function create(string $channel, string $channelChatId, string $userName, string $username, int $leadId, ?string $bxDialog = null): array {
        $chats = self::load();
        $key = self::makeKey($channel, $channelChatId);

        $chat = [
            'channel' => $channel,
            'channel_chat_id' => $channelChatId,
            'user_name' => $userName,
            'username' => $username,
            'lead_id' => $leadId,
            'bx_dialog' => $bxDialog,
            'created_at' => date('Y-m-d H:i:s'),
            'messages' => [],
        ];

        $chats[$key] = $chat;
        self::save($chats);
        return $chat;
    }

    /**
     * Добавить сообщение
     */
    public static function addMessage(string $channel, string $channelChatId, string $from, string $text): void {
        $chats = self::load();
        $key = self::makeKey($channel, $channelChatId);

        if (!isset($chats[$key])) return;

        $chats[$key]['messages'][] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'from' => $from,
            'text' => $text,
        ];

        self::save($chats);
    }

    /**
     * Обновить Bitrix24 dialog ID
     */
    public static function updateDialog(string $channel, string $channelChatId, string $dialogId): void {
        $chats = self::load();
        $key = self::makeKey($channel, $channelChatId);

        if (!isset($chats[$key])) return;

        $chats[$key]['bx_dialog'] = $dialogId;
        self::save($chats);
    }

    private static function load(): array {
        if (file_exists(self::$chatFile)) {
            return json_decode(file_get_contents(self::$chatFile), true) ?? [];
        }
        return [];
    }

    private static function save(array $chats): void {
        file_put_contents(self::$chatFile, json_encode($chats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private static function makeKey(string $channel, string $chatId): string {
        return "{$channel}:{$chatId}";
    }
}
