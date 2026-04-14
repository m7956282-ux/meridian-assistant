<?php
/**
 * Конфигурация приложения
 */
class Config {
    // Bitrix24
    public const BX_PORTAL = 'https://meridian18.bitrix24.ru';
    public const CONNECTOR_ID = 'meridian_assistant';
    public const CONNECTOR_NAME = 'Меридиан Ассистент';

    // AI Model (настраивается)
    public const AI_MODEL = 'openai'; // openai | yandex | claude
    public const AI_API_KEY = ''; // Установить через env
    public const AI_API_URL = 'https://api.openai.com/v1/chat/completions';

    // Каналы
    public const CHANNELS = [
        'telegram' => [
            'enabled' => true,
            'token' => '', // Установить через env
        ],
        'whatsapp' => [
            'enabled' => false,
            'token' => '',
            'phone_number_id' => '',
        ],
        'viber' => [
            'enabled' => false,
            'token' => '',
        ],
        'max' => [
            'enabled' => false,
            'token' => '',
        ],
        'vk' => [
            'enabled' => false,
            'token' => '',
            'group_id' => '',
        ],
        'instagram' => [
            'enabled' => false,
            'token' => '',
        ],
        'avito' => [
            'enabled' => false,
            'client_id' => '',
            'client_secret' => '',
        ],
        'youla' => [
            'enabled' => false,
            'token' => '',
        ],
        'webchat' => [
            'enabled' => false,
        ],
        'email' => [
            'enabled' => false,
            'host' => '',
            'port' => 993,
            'user' => '',
            'password' => '',
        ],
        'sms' => [
            'enabled' => false,
            'provider' => 'smsc', // smsc | sms.ru
            'login' => '',
            'password' => '',
        ],
        'yandex_dialogs' => [
            'enabled' => false,
            'token' => '',
        ],
        'google_business' => [
            'enabled' => false,
            'token' => '',
        ],
    ];

    /**
     * Получить токен канала
     */
    public static function getChannelConfig(string $channel): ?array {
        return self::CHANNELS[$channel] ?? null;
    }

    /**
     * Загрузить AUTH_ID из файла
     */
    public static function getAuthId(): ?string {
        $tokenFile = __DIR__ . '/bitrix_token.json';
        if (file_exists($tokenFile)) {
            $data = json_decode(file_get_contents($tokenFile), true);
            return $data['access_token'] ?? null;
        }
        return null;
    }

    /**
     * Получить AI API Key из env
     */
    public static function getAiApiKey(): string {
        return getenv('AI_API_KEY') ?: self::AI_API_KEY;
    }
}
