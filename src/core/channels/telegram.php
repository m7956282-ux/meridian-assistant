<?php
namespace Channels;

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/bot.php';
require_once __DIR__ . '/../core/bitrix24.php';
require_once __DIR__ . '/../core/chat_state.php';
require_once __DIR__ . '/../core/logger.php';

/**
 * Telegram Channel Handler
 */
class Telegram {
    private static function getBotToken(): string {
        $cfg = Config::getChannelConfig('telegram');
        return getenv('TELEGRAM_BOT_TOKEN') ?: ($cfg['token'] ?? '');
    }

    private static function getApiUrl(): string {
        return 'https://api.telegram.org/bot' . self::getBotToken() . '/';
    }

    /**
     * Обработка входящего сообщения
     */
    public static function handleIncoming($parsed): void {
        $message = $parsed['post']['message'] ?? $parsed['post']['edited_message'] ?? null;
        if (!$message) return;

        $chatId = (string)$message['chat']['id'];
        $userId = (string)$message['from']['id'];
        $userName = $message['from']['first_name'] ?? 'User';
        $username = $message['from']['username'] ?? '';
        $text = $message['text'] ?? '';

        Logger::entry('telegram_incoming', [
            'chat_id' => $chatId,
            'user' => $userName,
            'text' => $text,
        ]);

        // Проверяем, есть ли уже чат
        $chatData = ChatState::findByChannel('telegram', $chatId);

        if (!$chatData) {
            // Первый контакт — создаём лид и диалог
            $title = "Telegram: {$userName}";
            if ($username) $title .= " (@{$username})";

            $leadId = Bitrix24::createLead($title, $userName, 'TELEGRAM', $chatId, $text);

            // Создаём диалог в Open Lines
            $bxDialog = Bitrix24::createDialog('telegram', $chatId, $userName, $username, $text);

            $chatData = ChatState::create('telegram', $chatId, $userName, $username, $leadId, $bxDialog);

            Logger::entry('chat_created', [
                'channel' => 'telegram',
                'chat_id' => $chatId,
                'lead_id' => $leadId,
                'bx_dialog' => $bxDialog,
            ]);
        }

        // Добавляем сообщение пользователя
        ChatState::addMessage('telegram', $chatId, 'user', $text);

        // Отправляем в Bitrix24
        if ($chatData['bx_dialog']) {
            Bitrix24::sendToDialog($chatData['bx_dialog'], $text, $userName, Config::CONNECTOR_ID);
        }

        // Добавляем комментарий к лиду
        Bitrix24::addComment($chatData['lead_id'] ?? 0, "💬 {$userName}: {$text}");

        // Генерируем AI-ответ
        $reply = Bot::generateResponse($text, $userName, 'telegram');

        // Добавляем ответ бота
        ChatState::addMessage('telegram', $chatId, 'bot', $reply);

        // Добавляем комментарий к лиду
        Bitrix24::addComment($chatData['lead_id'] ?? 0, "🤖 Бот: {$reply}");

        // Отправляем ответ в Telegram
        self::sendMessage($chatId, $reply);
    }

    /**
     * Отправить сообщение
     */
    public static function sendMessage(string $chatId, string $text): void {
        $data = json_encode([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::getApiUrl() . 'sendMessage');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $result = curl_exec($ch);
        curl_close($ch);

        Logger::entry('telegram_sent', ['chat_id' => $chatId, 'result' => $result]);
    }

    /**
     * Установить webhook
     */
    public static function setWebhook(string $webhookUrl): void {
        $data = json_encode(['url' => $webhookUrl]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::getApiUrl() . 'setWebhook');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $result = curl_exec($ch);
        curl_close($ch);

        Logger::entry('telegram_webhook_set', ['url' => $webhookUrl, 'result' => $result]);
    }
}
