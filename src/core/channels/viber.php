<?php
namespace Channels;

require_once __DIR__ . '/../core/logger.php';

class Viber {
    public static function sendMessage(string $chatId, string $text): void {
        Logger::entry('viber_sent', ['chat_id' => $chatId, 'text' => $text]);
    }
}
