<?php

declare(strict_types=1);

if (!function_exists('loadEnv')) {
    function loadEnv(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $value = trim($value, "\"'");

            if ($key !== '' && getenv($key) === false) {
                putenv(sprintf('%s=%s', $key, $value));
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

loadEnv(dirname(__DIR__) . '/.env');

return [
    'app_name' => getenv('APP_NAME') ?: 'RespondePro',
    'base_url' => rtrim((string) (getenv('APP_BASE_URL') ?: ''), '/'),
    'timezone' => getenv('APP_TIMEZONE') ?: 'America/Argentina/Buenos_Aires',
    'session_path' => getenv('SESSION_PATH') ?: dirname(__DIR__) . '/storage/sessions',
    'usage_limit_per_day' => (int) (getenv('USAGE_LIMIT_PER_DAY') ?: 25),
    'ai_provider' => getenv('AI_PROVIDER') ?: 'openai',
    'openai' => [
        'url' => getenv('OPENAI_API_URL') ?: 'https://api.openai.com/v1/chat/completions',
        'key' => getenv('OPENAI_API_KEY') ?: '',
        'model' => getenv('OPENAI_MODEL') ?: 'gpt-4o-mini',
        'timeout' => (int) (getenv('OPENAI_TIMEOUT') ?: 30),
    ],
    'gemini' => [
        'url' => getenv('GEMINI_API_URL') ?: 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
        'key' => getenv('GEMINI_API_KEY') ?: '',
        'model' => getenv('GEMINI_MODEL') ?: 'gemini-2.5-flash',
        'timeout' => (int) (getenv('GEMINI_TIMEOUT') ?: 30),
    ],
    'database' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'respondepro',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
        'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
    ],
];
