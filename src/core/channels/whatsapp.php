<?php
namespace Channels;

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/bot.php';
require_once __DIR__ . '/../core/bitrix24.php';
require_once __DIR__ . '/../core/chat_state.php';
require_once __DIR__ . '/../core/logger.php';

/**
 * WhatsApp Channel Handler (Cloud API / Business API)
 * TODO: Реализовать при подключении канала
 */
class WhatsApp {
    private static function getPhoneNumberId(): string {
        $cfg = Config::getChannelConfig('whatsapp');
        return $cfg['phone_number_id'] ?? '';
    }

    private static function getToken(): string {
        $cfg = Config::getChannelConfig('whatsapp');
        return getenv('WHATSAPP_TOKEN') ?: ($cfg['token'] ?? '');
    }

    private static function getApiUrl(): string {
        return 'https://graph.facebook.com/v17.0/' . self::getPhoneNumberId() . '/messages';
    }

    public static function handleIncoming($parsed): void {
        // TODO: Обработка входящих WhatsApp сообщений
        Logger::entry('whatsapp_incoming', ['data' => $parsed['post']]);
    }

    public static function sendMessage(string $chatId, string $text): void {
        // TODO: Отправка через WhatsApp Cloud API
        Logger::entry('whatsapp_sent', ['chat_id' => $chatId, 'text' => $text]);
    }
}
