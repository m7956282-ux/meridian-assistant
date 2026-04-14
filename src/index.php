<?php
/**
 * Meridian Assistant — Bitrix24 Contact Center Connector
 * Мульти-канальный AI-бот для Contact Center Битрикс24
 *
 * Поддерживаемые каналы:
 * - Telegram
 * - WhatsApp (Cloud API / Business API)
 * - Viber
 * - MAX (мессенджер Сбера)
 * - ВКонтакте
 * - Instagram
 * - Авито
 * - Юла
 * - Онлайн-чат
 * - Email
 * - SMS
 * - Яндекс.Диалоги
 * - Google Business Messages
 */

require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/bot.php';
require_once __DIR__ . '/core/channels/telegram.php';
require_once __DIR__ . '/core/bitrix24.php';
require_once __DIR__ . '/core/logger.php';

// =================== INCOMING ===================

$input = file_get_contents('php://input');
$parsed = parse_input($input);

if ($parsed === null) {
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
    exit;
}

Logger::entry('incoming', ['source' => 'webhook', 'parsed_keys' => array_keys($parsed['post'])]);

// =================== ROUTING ===================

// 1. Bitrix24 — установка приложения
if (isset($parsed['post']['AUTH_ID'])) {
    Bitrix24::handleInstall($parsed);
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
    exit;
}

// 2. Bitrix24 — событие от Open Lines
if (isset($parsed['post']['event'])) {
    Bitrix24::handleEvent($parsed);
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
    exit;
}

// 3. Telegram — входящее сообщение
if (isset($parsed['post']['update_id']) || isset($parsed['post']['message'])) {
    Channels\Telegram::handleIncoming($parsed);
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
    exit;
}

// 4. Health check
if (isset($parsed['get']['health'])) {
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'version' => '1.0.0', 'channels' => Bot::getActiveChannels()]);
    exit;
}

http_response_code(200);
echo json_encode(['status' => 'ok']);

// =================== UTILS ===================

function parse_input($input) {
    $parsed = [
        'get' => $_GET ?? [],
        'post' => $_POST ?? [],
        'raw' => $input,
        'headers' => function_exists('getallheaders') ? getallheaders() : [],
    ];

    // Если POST пустой, но есть raw JSON — парсим
    if (empty($parsed['post']) && $input) {
        $json = json_decode($input, true);
        if ($json) {
            $parsed['post'] = $json;
        }
    }

    return $parsed;
}
