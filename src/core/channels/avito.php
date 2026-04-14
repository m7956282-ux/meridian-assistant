<?php
namespace Channels;
require_once __DIR__ . '/../core/logger.php';

class Avito {
    public static function sendMessage(string $chatId, string $text): void {
        \Logger::entry('avito_sent', ['chat_id' => $chatId]);
    }
}
