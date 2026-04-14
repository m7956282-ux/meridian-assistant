<?php
require_once __DIR__ . '/config.php';

class Bitrix24 {
    /**
     * Обработка установки приложения
     */
    public static function handleInstall($parsed): void {
        $auth_id = $parsed['post']['AUTH_ID'] ?? '';
        $refresh_id = $parsed['post']['REFRESH_ID'] ?? '';
        $member_id = $parsed['post']['member_id'] ?? '';
        $domain = $parsed['get']['domain'] ?? 'meridian18.bitrix24.ru';

        Logger::entry('app_installed', [
            'auth_id' => $auth_id,
            'domain' => $domain,
        ]);

        $tokenData = [
            'access_token' => $auth_id,
            'refresh_token' => $refresh_id,
            'member_id' => $member_id,
            'domain' => $domain,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        file_put_contents(__DIR__ . '/../config/bitrix_token.json', json_encode($tokenData, JSON_PRETTY_PRINT));
    }

    /**
     * Обработка событий от Open Lines
     */
    public static function handleEvent($parsed): void {
        $event = $parsed['post']['event'] ?? '';
        $data = $parsed['post']['data'] ?? [];

        Logger::entry('bitrix_event', ['event' => $event, 'data' => $data]);

        if ($event === 'OnImConnectorMessageAdd') {
            self::handleManagerReply($data);
        }
        if ($event === 'OnImConnectorDialogStart') {
            Logger::entry('dialog_start', $data);
        }
    }

    /**
     * Менеджер ответил в Open Lines → пересылаем в канал
     */
    private static function handleManagerReply($data): void {
        $dialogId = $data['dialogId'] ?? '';
        $message = $data['message'] ?? '';
        $senderId = $data['senderId'] ?? '';

        if (!$dialogId || !$message) return;

        $chatData = ChatState::findByBitrixDialog($dialogId);
        if ($chatData) {
            // Пересылаем ответ менеджера в канал
            Channels\ChannelRouter::sendMessage($chatData['channel'], $chatData['channel_chat_id'], $message);
            Logger::entry('manager_reply', [
                'channel' => $chatData['channel'],
                'chat_id' => $chatData['channel_chat_id'],
                'message' => $message,
            ]);
        }
    }

    /**
     * Создать диалог в Bitrix24 Open Lines
     */
    public static function createDialog(string $channel, string $channelChatId, string $userName, string $username, string $firstMessage): ?string {
        $auth = Config::getAuthId();
        if (!$auth) {
            Logger::entry('error', ['message' => 'No AUTH_ID available']);
            return null;
        }

        $payload = http_build_query([
            'auth' => $auth,
            'CONNECTOR' => Config::CONNECTOR_ID,
            'USER_ID' => "{$channel}{$channelChatId}",
            'MESSAGE' => $firstMessage,
            'PARAMS[USER_NAME]' => $userName,
            'PARAMS[CHANNEL]' => $channel,
            'PARAMS[CHANNEL_ID]' => $channelChatId,
            'PARAMS[USERNAME]' => $username,
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, Config::BX_PORTAL . '/rest/imconnector.message.add');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        Logger::entry('bx_create_dialog', ['result' => $result, 'http' => $httpCode]);

        $data = json_decode($result, true);
        if (isset($data['result']['result']['dialog'])) {
            return $data['result']['result']['dialog'];
        }
        if (isset($data['result']['result'])) {
            return $data['result']['result'];
        }
        if (isset($data['result'])) {
            return $data['result'];
        }

        return null;
    }

    /**
     * Отправить сообщение в диалог Bitrix24
     */
    public static function sendToDialog(string $dialog, string $message, string $userName, string $connector): void {
        $auth = Config::getAuthId();
        if (!$auth) return;

        $payload = http_build_query([
            'auth' => $auth,
            'CONNECTOR' => $connector,
            'DIALOG_ID' => $dialog,
            'MESSAGE' => $message,
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, Config::BX_PORTAL . '/rest/imconnector.message.add');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $result = curl_exec($ch);
        curl_close($ch);

        Logger::entry('bx_send_dialog', ['dialog' => $dialog, 'result' => $result]);
    }

    /**
     * Создать лид в CRM
     */
    public static function createLead(string $title, string $name, string $source, string $channelChatId, string $message): int {
        $auth = Config::getAuthId();
        if (!$auth) return 0;

        $payload = http_build_query([
            'auth' => $auth,
            'fields[TITLE]' => $title,
            'fields[NAME]' => $name,
            'fields[SOURCE_ID]' => $source,
            'fields[OPENED]' => 'Y',
            'fields[UF_CRM_CHANNEL_ID]' => $channelChatId,
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, Config::BX_PORTAL . '/rest/crm.lead.add');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $result = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($result, true);
        return (int)($data['result'] ?? 0);
    }

    /**
     * Добавить комментарий к лиду
     */
    public static function addComment(int $leadId, string $comment): void {
        $auth = Config::getAuthId();
        if (!$auth) return;

        $payload = http_build_query([
            'auth' => $auth,
            'ENTITY_TYPE' => 'LEAD',
            'ENTITY_ID' => $leadId,
            'COMMENT' => $comment,
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, Config::BX_PORTAL . '/rest/crm.timeline.comment.add');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_exec($ch);
        curl_close($ch);
    }
}
