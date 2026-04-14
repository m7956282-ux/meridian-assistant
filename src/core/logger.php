<?php
/**
 * Логирование
 */
class Logger {
    private static $logFile = __DIR__ . '/../logs/webhook.log';
    private static $maxSize = 2 * 1024 * 1024; // 2MB

    public static function entry(string $type, mixed $data): void {
        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => $type,
            'data' => $data,
        ];

        $log = json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

        // Ротация
        if (file_exists(self::$logFile) && filesize(self::$logFile) > self::$maxSize) {
            $lines = file(self::$logFile);
            $lines = array_slice($lines, -500);
            file_put_contents(self::$logFile, implode('', $lines));
        }

        file_put_contents(self::$logFile, $log, FILE_APPEND);
    }
}
