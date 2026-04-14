<?php
/**
 * Installer — URL установки приложения
 * Вызывается при установке приложения на портал Bitrix24
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/bitrix24.php';
require_once __DIR__ . '/../core/logger.php';

$input = file_get_contents('php://input');

$parsed = [
    'get' => $_GET ?? [],
    'post' => $_POST ?? [],
    'raw' => $input,
];

if (empty($parsed['post'])) {
    $json = json_decode($input, true);
    if ($json) {
        $parsed['post'] = $json;
    }
}

// Установка приложения
if (isset($parsed['post']['AUTH_ID'])) {
    Bitrix24::handleInstall($parsed);

    // Регистрируем коннектор в Open Lines
    $auth = $parsed['post']['AUTH_ID'];
    $domain = $parsed['get']['domain'] ?? 'meridian18.bitrix24.ru';
    $portalUrl = "https://{$domain}";

    // Регистрация коннектора
    $payload = http_build_query([
        'auth' => $auth,
        'connector_id' => Config::CONNECTOR_ID,
        'name' => Config::CONNECTOR_NAME,
        'url' => "https://{$domain}/webhook/index.php",
        'check_url' => "https://{$domain}/webhook/index.php?health=1",
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $portalUrl . '/rest/imconnector.register');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $result = curl_exec($ch);
    curl_close($ch);

    Logger::entry('connector_registered', [
        'domain' => $domain,
        'result' => $result,
    ]);

    http_response_code(200);
    echo json_encode(['status' => 'ok']);
    exit;
}

// Удаление приложения
if (isset($parsed['get']['event']) && $parsed['get']['event'] === 'ON_APP_UNINSTALL') {
    Logger::entry('app_uninstalled', ['domain' => $parsed['get']['domain'] ?? '']);

    http_response_code(200);
    echo json_encode(['status' => 'ok']);
    exit;
}

http_response_code(200);
echo json_encode(['status' => 'ok']);
