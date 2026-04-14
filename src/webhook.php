<?php
/**
 * Meridian Assistant — Bitrix24 Open Lines Connector
 * Мост: Telegram ↔ Bitrix24 Open Lines
 */

// =================== CONFIG ===================

$BOT_TOKEN = '544078024:AAEZ8nXm5jJRIqHbAhx_sg-KgzmyE1ugO44';
$BX_PORTAL = 'https://meridian18.bitrix24.ru';
$CONNECTOR = 'meridian_assistant';

// Читаем токен из файла (автоматически обновляется через onAppUpdate)
$BX_AUTH = '';
$tokenFile = __DIR__ . '/bitrix_token.json';
if (file_exists($tokenFile)) {
    $tokenData = json_decode(file_get_contents($tokenFile), true);
    $BX_AUTH = $tokenData['access_token'] ?? '';
}

$logFile = __DIR__ . '/webhook.log';
$chatFile = __DIR__ . '/tg_chats.json';

// =================== INCOMING ===================

$input = file_get_contents('php://input');
$parsed = parse_input($input);

if ($parsed === null) {
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
    exit;
}

log_entry('incoming', $parsed);

// =================== ROUTING ===================

// Тип 1: Bitrix24 → установка приложения (AUTH_ID) или обновление токена (onAppUpdate)
if (isset($parsed['post']['AUTH_ID'])) {
    handle_app_install($parsed);
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
    exit;
}

// Тип 1b: Bitrix24 → обновление токена (onAppUpdate event)
if (isset($parsed['post']['event']) && $parsed['post']['event'] === 'onAppUpdate') {
    handle_token_refresh($parsed);
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
    exit;
}

// Тип 2: Bitrix24 → событие OnImConnectorMessageAdd / OnImConnectorDialogStart
if (isset($parsed['post']['event'])) {
    handle_bitrix_event($parsed);
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
    exit;
}

// Тип 3: Telegram → incoming message
if (isset($parsed['post']['update_id']) || isset($parsed['post']['message'])) {
    handle_telegram($parsed);
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
    exit;
}

http_response_code(200);
echo json_encode(['status' => 'ok']);

// =================== HANDLERS ===================

function handle_telegram($parsed) {
    global $BOT_TOKEN, $BX_PORTAL, $BX_AUTH, $CONNECTOR, $chatFile;
    
    $message = $parsed['post']['message'] ?? null;
    if (!$message) return;
    
    $chat_id = $message['chat']['id'];
    $user_id = $message['from']['id'];
    $user_name = $message['from']['first_name'] ?? 'User';
    $user_username = $message['from']['username'] ?? '';
    $text = $message['text'] ?? '';
    
    // Загружаем маппинг чатов
    $chats = [];
    if (file_exists($chatFile)) {
        $chats = json_decode(file_get_contents($chatFile), true) ?? [];
    }
    
    // Проверяем, есть ли уже Bitrix dialog для этого чата
    if (!isset($chats[$chat_id]) || empty($chats[$chat_id]['bx_dialog'])) {
        // Создаём новый dialog в Bitrix24 Open Lines
        $dialog = bx_create_dialog($chat_id, $user_name, $user_username, $text);
        
        if ($dialog) {
            $chats[$chat_id] = [
                'tg_chat_id' => $chat_id,
                'tg_user_id' => $user_id,
                'tg_user_name' => $user_name,
                'tg_username' => $user_username,
                'bx_dialog' => $dialog,
                'created_at' => date('Y-m-d H:i:s'),
            ];
            file_put_contents($chatFile, json_encode($chats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }
    
    $bx_dialog = $chats[$chat_id]['bx_dialog'] ?? null;
    
    if ($bx_dialog) {
        // Отправляем сообщение в Bitrix24 Open Lines
        bx_send_to_dialog($bx_dialog, $text, $user_name, $CONNECTOR);
        
        // AI-ответ (пока бот отвечает сам, менеджер видит в Open Lines)
        $reply = generate_ai_response($text, $user_name);
        
        // Отправляем AI-ответ в Telegram
        tg_send_message($chat_id, $reply);
        
        // Логируем
        log_entry('ai_reply', ['chat_id' => $chat_id, 'user' => $user_name, 'text' => $text, 'reply' => $reply]);
    } else {
        // Fallback — просто отвечаем в Telegram
        $reply = generate_ai_response($text, $user_name);
        tg_send_message($chat_id, $reply);
    }
}

function handle_bitrix_event($parsed) {
    global $BOT_TOKEN, $chatFile;
    
    $event = $parsed['post']['event'] ?? '';
    $data = $parsed['post']['data'] ?? [];
    
    log_entry('bitrix_event', ['event' => $event, 'data' => $data]);
    
    if ($event === 'OnImConnectorMessageAdd') {
        // Менеджер написал в Open Lines → пересылаем в Telegram
        $dialog_id = $data['dialogId'] ?? '';
        $message = $data['message'] ?? '';
        $sender_id = $data['senderId'] ?? '';
        
        if ($dialog_id && $message) {
            // Находим Telegram чат по Bitrix dialog
            $chats = file_exists($chatFile) ? json_decode(file_get_contents($chatFile), true) ?? [] : [];
            
            foreach ($chats as $chat_id => $chat_data) {
                if ($chat_data['bx_dialog'] === $dialog_id) {
                    tg_send_message($chat_id, $message);
                    log_entry('manager_reply', ['chat_id' => $chat_id, 'message' => $message]);
                    break;
                }
            }
        }
    }
    
    if ($event === 'OnImConnectorDialogStart') {
        log_entry('dialog_start', $data);
    }
}

function handle_app_install($parsed) {
    $auth_id = $parsed['post']['AUTH_ID'] ?? '';
    $refresh_id = $parsed['post']['REFRESH_ID'] ?? '';
    $member_id = $parsed['post']['member_id'] ?? '';
    $domain = $parsed['get']['DOMAIN'] ?? 'meridian18.bitrix24.ru';
    
    log_entry('app_installed', [
        'auth_id' => $auth_id,
        'domain' => $domain,
    ]);
    
    // Сохраняем токен
    $token_data = [
        'access_token' => $auth_id,
        'refresh_token' => $refresh_id,
        'member_id' => $member_id,
        'domain' => $domain,
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    file_put_contents(__DIR__ . '/bitrix_token.json', json_encode($token_data, JSON_PRETTY_PRINT));
}

/**
 * Обработка обновления токена (onAppUpdate от Bitrix24)
 */
function handle_token_refresh($parsed) {
    $auth_id = $parsed['post']['AUTH_ID'] ?? '';
    $refresh_id = $parsed['post']['REFRESH_ID'] ?? '';
    $member_id = $parsed['post']['member_id'] ?? '';
    $domain = $parsed['get']['DOMAIN'] ?? 'meridian18.bitrix24.ru';
    
    if ($auth_id) {
        log_entry('token_refreshed', [
            'auth_id' => $auth_id,
            'domain' => $domain,
        ]);
        
        $token_data = [
            'access_token' => $auth_id,
            'refresh_token' => $refresh_id,
            'member_id' => $member_id,
            'domain' => $domain,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        file_put_contents(__DIR__ . '/bitrix_token.json', json_encode($token_data, JSON_PRETTY_PRINT));
    }
}

// =================== Bitrix24 API ===================

function bx_create_dialog($tg_chat_id, $user_name, $username, $first_message) {
    global $BX_PORTAL, $BX_AUTH, $CONNECTOR;
    
    // Создаём через imconnector.message.add
    $payload = http_build_query([
        'auth' => $BX_AUTH,
        'CONNECTOR' => $CONNECTOR,
        'USER_ID' => "tg{$tg_chat_id}",
        'MESSAGE' => $first_message,
        'PARAMS[USER_NAME]' => $user_name,
        'PARAMS[TELEGRAM_ID]' => $tg_chat_id,
        'PARAMS[USERNAME]' => $username,
    ]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $BX_PORTAL . '/rest/imconnector.message.add');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    log_entry('bx_create_dialog', ['result' => $result, 'http' => $http_code]);
    
    $data = json_decode($result, true);
    if (isset($data['result']['result']['dialog'])) {
        return $data['result']['result']['dialog'];
    }
    // Альтернативные пути
    if (isset($data['result']['result'])) {
        return $data['result']['result'];
    }
    if (isset($data['result'])) {
        return $data['result'];
    }
    
    return null;
}

function bx_send_to_dialog($dialog, $message, $user_name, $connector) {
    global $BX_PORTAL, $BX_AUTH;
    
    // Отправляем как входящее сообщение от пользователя
    $payload = http_build_query([
        'auth' => $BX_AUTH,
        'CONNECTOR' => $connector,
        'DIALOG_ID' => $dialog,
        'MESSAGE' => $message,
    ]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $BX_PORTAL . '/rest/imconnector.message.add');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $result = curl_exec($ch);
    curl_close($ch);
    
    log_entry('bx_send_dialog', ['dialog' => $dialog, 'result' => $result]);
}

// =================== Telegram API ===================

function tg_send_message($chat_id, $text) {
    global $BOT_TOKEN;
    
    $data = json_encode([
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML',
    ]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot{$BOT_TOKEN}/sendMessage");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_exec($ch);
    curl_close($ch);
}

// =================== AI Response ===================

function generate_ai_response($message, $user_name) {
    $lower = mb_strtolower($message, 'UTF-8');
    
    if (strpos($lower, 'привет') !== false || strpos($lower, 'здравствуй') !== false || strpos($lower, 'добрый') !== false) {
        return "Привет, {$user_name}! 👋\n\nЯ AI-ассистент компании <b>Меридиан</b>. Чем могу помочь?";
    }
    
    if (strpos($lower, 'доставк') !== false || strpos($lower, 'перевозк') !== false || strpos($lower, 'груз') !== false) {
        return "🚚 <b>Расчёт доставки</b>\n\nДля точного расчёта мне нужно:\n\n1️⃣ Что везём?\n2️⃣ Откуда → куда?\n3️⃣ Вес и объём?\n4️⃣ Когда нужно?\n\nНаш логист свяжется с вами!";
    }
    
    if (strpos($lower, 'цен') !== false || strpos($lower, 'стоим') !== false || strpos($lower, 'сколько стоит') !== false) {
        return "💰 Стоимость зависит от маршрута и типа груза.\n\nНапишите детали — рассчитаю!";
    }
    
    if (strpos($lower, 'контакт') !== false || strpos($lower, 'телефон') !== false || strpos($lower, 'адрес') !== false) {
        return "📞 <b>Контакты Меридиан:</b>\n\n📱 Телефон: +7 (951) 196-14-20\n📧 Email: info@meridian18.ru\n📍 Адрес: г. Самара\n\nЧем ещё могу помочь?";
    }
    
    if (strpos($lower, 'спасибо') !== false) {
        return "Пожалуйста, {$user_name}! 😊 Обращайтесь в любое время!";
    }
    
    return "Спасибо за обращение! 🤖\n\nВаш вопрос принят. Наш менеджер ответит в ближайшее время.";
}

// =================== UTILS ===================

function parse_input($input) {
    $parsed = ['get' => $_GET ?? [], 'post' => $_POST ?? [], 'raw' => $input, 'headers' => getallheaders()];
    
    // Если POST пустой, но есть raw JSON — парсим
    if (empty($parsed['post']) && $input) {
        $json = json_decode($input, true);
        if ($json) {
            $parsed['post'] = $json;
        }
    }
    
    return $parsed;
}

function log_entry($type, $data) {
    global $logFile;
    
    $entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'type' => $type,
        'data' => $data,
    ];
    
    $log = json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    // Ротация — храним последние 1000 записей
    $max_size = 2 * 1024 * 1024; // 2MB
    if (file_exists($logFile) && filesize($logFile) > $max_size) {
        $lines = file($logFile);
        $lines = array_slice($lines, -500);
        file_put_contents($logFile, implode('', $lines));
    }
    
    file_put_contents($logFile, $log . "\n\n", FILE_APPEND);
}
