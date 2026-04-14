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
    // Поддерживаемые: openai, deepseek, qwen, yandex, claude
    public const AI_MODEL = 'openai';
    public const AI_API_KEY = '';
    public const AI_API_URL = 'https://api.openai.com/v1/chat/completions';

    // DeepSeek
    public const DEEPSEEK_API_URL = 'https://api.deepseek.com/v1/chat/completions';
    public const DEEPSEEK_MODEL = 'deepseek-chat';

    // Qwen (DashScope / Alibaba)
    public const QWEN_API_URL = 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions';
    public const QWEN_MODEL = 'qwen-plus';

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

    /**
     * Получить модель AI
     */
    public static function getAiModel(): string {
        return getenv('AI_MODEL') ?: self::AI_MODEL;
    }

    /**
     * Получить DeepSeek API Key
     */
    public static function getDeepSeekApiKey(): string {
        return getenv('DEEPSEEK_API_KEY') ?: '';
    }

    /**
     * Получить Qwen API Key
     */
    public static function getQwenApiKey(): string {
        return getenv('QWEN_API_KEY') ?: '';
    }
}
