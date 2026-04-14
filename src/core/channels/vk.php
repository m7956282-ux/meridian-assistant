<?php
namespace Channels;
require_once __DIR__ . '/../core/logger.php';

class Vk {
    public static function sendMessage(string $chatId, string $text): void {
        \Logger::entry('vk_sent', ['chat_id' => $chatId]);
    }
}
