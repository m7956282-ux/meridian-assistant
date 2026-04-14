<?php
/**
 * Роутер сообщений по каналам
 */
class ChannelRouter {
    /**
     * Отправить сообщение в нужный канал
     */
    public static function sendMessage(string $channel, string $channelChatId, string $text): void {
        switch ($channel) {
            case 'telegram':
                Channels\Telegram::sendMessage($channelChatId, $text);
                break;
            case 'whatsapp':
                Channels\WhatsApp::sendMessage($channelChatId, $text);
                break;
            case 'viber':
                Channels\Viber::sendMessage($channelChatId, $text);
                break;
            case 'vk':
                Channels\Vk::sendMessage($channelChatId, $text);
                break;
            case 'avito':
                Channels\Avito::sendMessage($channelChatId, $text);
                break;
            default:
                Logger::entry('channel_error', ['channel' => $channel, 'message' => 'Unsupported channel']);
        }
    }
}
