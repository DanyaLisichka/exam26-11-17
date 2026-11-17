<?php
declare(strict_types=1);

// По возможности задавайте параметры подключения через переменные окружения.
// Для локального XAMPP можно создать config.local.php (он исключён из Git).
$localConfig = __DIR__ . '/config.local.php';
if (is_file($localConfig)) {
    require $localConfig;
}

return [
    'db' => [
        'host' => getenv('DB_HOST') ?: ($DB_HOST ?? '127.0.0.1'),
        'port' => getenv('DB_PORT') ?: ($DB_PORT ?? '3306'),
        'name' => getenv('DB_NAME') ?: ($DB_NAME ?? 'hotel_sladkie_sny'),
        'user' => getenv('DB_USER') ?: ($DB_USER ?? 'root'),
        'pass' => getenv('DB_PASS') !== false ? getenv('DB_PASS') : ($DB_PASS ?? ''),
        'charset' => 'utf8mb4',
    ],
];
