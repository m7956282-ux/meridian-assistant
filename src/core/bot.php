<?php
/**
 * AI Bot — генерация ответов
 */
class Bot {
    private static $companyName = 'Меридиан';
    private static $companyPhone = '+7 (951) 196-14-20';
    private static $companyEmail = 'info@meridian18.ru';
    private static $companyCity = 'Самара';

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

        // TODO: Здесь будет вызов AI API (OpenAI / YandexGPT / Claude)
        // return self::callAiApi($message, $channel);

        return "Спасибо за обращение! 🤖\n\nВаш вопрос принят. Наш менеджер ответит в ближайшее время.\n\nА пока могу помочь с:\n• Расчётом доставки\n• Информацией о контактах\n• Статусом заявки";
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
     * TODO: Вызов AI API
     */
    private static function callAiApi(string $message, string $channel): string {
        $apiKey = Config::getAiApiKey();
        if (!$apiKey) {
            return self::generateResponse($message, 'User', $channel);
        }

        // OpenAI example:
        // $payload = json_encode([
        //     'model' => 'gpt-4',
        //     'messages' => [
        //         ['role' => 'system', 'content' => 'You are a sales assistant for ' . self::$companyName],
        //         ['role' => 'user', 'content' => $message],
        //     ],
        // ]);
        // $ch = curl_init();
        // curl_setopt($ch, CURLOPT_URL, Config::AI_API_URL);
        // curl_setopt($ch, CURLOPT_POST, true);
        // curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        // curl_setopt($ch, CURLOPT_HTTPHEADER, [
        //     'Content-Type: application/json',
        //     'Authorization: Bearer ' . $apiKey,
        // ]);
        // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // $result = json_decode(curl_exec($ch), true);
        // curl_close($ch);
        // return $result['choices'][0]['message']['content'] ?? 'Error';

        return 'AI response placeholder';
    }
}
