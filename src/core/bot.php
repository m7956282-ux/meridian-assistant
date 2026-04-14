<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/logger.php';

/**
 * AI Bot — генерация ответов
 * Поддерживаемые модели: OpenAI, DeepSeek, Qwen (DashScope)
 */
class Bot {
    private static $companyName = 'Меридиан';
    private static $companyPhone = '+7 (951) 196-14-20';
    private static $companyEmail = 'info@meridian18.ru';
    private static $companyCity = 'Самара';

    /**
     * Системный промпт для AI
     */
    private static function getSystemPrompt(): string {
        return "Ты — AI-ассистент и продавец компании \"" . self::$companyName . "\". " .
               "Компания занимается логистикой и доставкой грузов. " .
               "Твоя задача: отвечать на вопросы клиентов, рассчитывать стоимость доставки, " .
               "консультировать по услугам. Отвечай вежливо, кратко и по делу. " .
               "Если не знаешь точного ответа — предложи передать вопрос менеджеру. " .
               "Контакты компании: телефон " . self::$companyPhone . ", email " . self::$companyEmail . ", город " . self::$companyCity;
    }

    /**
     * Получить активные каналы
     */
    public static function getActiveChannels(): array {
        $active = [];
        foreach (Config::CHANNELS as $name => $cfg) {
            if ($cfg['enabled']) {
                $active[] = $name;
            }
        }
        return $active;
    }

    /**
     * Генерация ответа (rule-based fallback)
     * TODO: Заменить на вызов OpenAI / YandexGPT / Claude
     */
    public static function generateResponse(string $message, string $userName, string $channel = ''): string {
        $lower = mb_strtolower($message, 'UTF-8');

        // Приветствия
        if (self::matchesAny($lower, ['привет', 'здравствуй', 'добрый', 'хай', 'hello', 'hi'])) {
            return "Привет, {$userName}! 👋\n\nЯ AI-ассистент компании <b>" . self::$companyName . "</b>. Чем могу помочь?";
        }

        // Доставка / логистика
        if (self::matchesAny($lower, ['доставк', 'перевозк', 'груз', 'логист', 'отправк'])) {
            return "🚚 <b>Расчёт доставки</b>\n\nДля точного расчёта мне нужно:\n\n1️⃣ Что везём?\n2️⃣ Откуда → куда?\n3️⃣ Вес и объём?\n4️⃣ Когда нужно?\n\nНаш логист свяжется с вами!";
        }

        // Цена / стоимость
        if (self::matchesAny($lower, ['цен', 'стоим', 'сколько стоит', 'прайс', 'тариф'])) {
            return "💰 Стоимость зависит от маршрута и типа груза.\n\nНапишите детали — рассчитаю!";
        }

        // Контакты
        if (self::matchesAny($lower, ['контакт', 'телефон', 'адрес', 'как связаться'])) {
            return "📞 <b>Контакты " . self::$companyName . ":</b>\n\n📱 Телефон: " . self::$companyPhone . "\n📧 Email: " . self::$companyEmail . "\n📍 Адрес: г. " . self::$companyCity . "\n\nЧем ещё могу помочь?";
        }

        // Благодарность
        if (self::matchesAny($lower, ['спасибо', 'благодар'])) {
            return "Пожалуйста, {$userName}! 😊 Обращайтесь в любое время!";
        }

        // Прощание
        if (self::matchesAny($lower, ['пока', 'до свидания', 'прощай', 'bye'])) {
            return "До встречи, {$userName}! 👋 Хорошего дня!";
        }

        // Вызов AI API
        return self::callAiApi($message, $userName);
    }

    /**
     * Проверка совпадений (любое из списка)
     */
    private static function matchesAny(string $text, array $keywords): bool {
        foreach ($keywords as $kw) {
            if (strpos($text, $kw) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Вызов AI API (OpenAI / DeepSeek / Qwen)
     */
    private static function callAiApi(string $message, string $userName): string {
        $model = Config::getAiModel();

        switch ($model) {
            case 'deepseek':
                return self::callDeepSeek($message, $userName);
            case 'qwen':
                return self::callQwen($message, $userName);
            case 'openai':
            default:
                return self::callOpenAI($message, $userName);
        }
    }

    /**
     * OpenAI API
     */
    private static function callOpenAI(string $message, string $userName): string {
        $apiKey = Config::getAiApiKey();
        if (!$apiKey) {
            Logger::entry('ai_error', ['model' => 'openai', 'reason' => 'No API key']);
            return self::fallbackResponse($userName);
        }

        $payload = json_encode([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => self::getSystemPrompt()],
                ['role' => 'user', 'content' => $message],
            ],
            'max_tokens' => 500,
            'temperature' => 0.7,
        ]);

        return self::request(Config::AI_API_URL, $apiKey, $payload, 'openai');
    }

    /**
     * DeepSeek API
     */
    private static function callDeepSeek(string $message, string $userName): string {
        $apiKey = Config::getDeepSeekApiKey();
        if (!$apiKey) {
            Logger::entry('ai_error', ['model' => 'deepseek', 'reason' => 'No API key']);
            return self::fallbackResponse($userName);
        }

        $payload = json_encode([
            'model' => Config::DEEPSEEK_MODEL,
            'messages' => [
                ['role' => 'system', 'content' => self::getSystemPrompt()],
                ['role' => 'user', 'content' => $message],
            ],
            'max_tokens' => 1000,
            'temperature' => 0.7,
        ]);

        return self::request(Config::DEEPSEEK_API_URL, $apiKey, $payload, 'deepseek');
    }

    /**
     * Qwen API (DashScope / Alibaba)
     * Поддерживает модели: qwen-plus, qwen-max, qwen-turbo
     */
    private static function callQwen(string $message, string $userName): string {
        $apiKey = Config::getQwenApiKey();
        if (!$apiKey) {
            Logger::entry('ai_error', ['model' => 'qwen', 'reason' => 'No API key']);
            return self::fallbackResponse($userName);
        }

        $payload = json_encode([
            'model' => Config::QWEN_MODEL,
            'messages' => [
                ['role' => 'system', 'content' => self::getSystemPrompt()],
                ['role' => 'user', 'content' => $message],
            ],
            'max_tokens' => 1000,
            'temperature' => 0.7,
        ]);

        return self::request(Config::QWEN_API_URL, $apiKey, $payload, 'qwen');
    }

    /**
     * Универсальный HTTP-запрос к AI API
     */
    private static function request(string $url, string $apiKey, string $payload, string $model): string {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Logger::entry('ai_error', ['model' => $model, 'curl_error' => $error]);
            return 'Извините, произошла ошибка. Попробуйте позже.';
        }

        if ($httpCode !== 200) {
            Logger::entry('ai_error', ['model' => $model, 'http_code' => $httpCode, 'response' => $result]);
            return 'Извините, временно не могу ответить. Попробуйте позже.';
        }

        $data = json_decode($result, true);
        $reply = $data['choices'][0]['message']['content'] ?? null;

        if (!$reply) {
            Logger::entry('ai_error', ['model' => $model, 'response' => $result]);
            return 'Извините, не удалось обработать запрос.';
        }

        Logger::entry('ai_response', ['model' => $model, 'reply_length' => strlen($reply)]);
        return $reply;
    }

    /**
     * Fallback-ответ при отсутствии AI
     */
    private static function fallbackResponse(string $userName): string {
        return "Спасибо за обращение! 🤖\n\nВаш вопрос принят. Наш менеджер ответит в ближайшее время.\n\nА пока могу помочь с:\n• Расчётом доставки\n• Информацией о контактах\n• Статусом заявки";
    }
}
