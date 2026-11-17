<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Запуск разрешён только из командной строки.\n");
}
require __DIR__ . '/../includes/bootstrap.php';

if ($argc !== 3) {
    fwrite(STDERR, "Использование: php scripts/create_admin.php <логин> <пароль>\nПароль должен содержать не менее 10 символов.\n");
    exit(1);
}
$username = trim($argv[1]);
$password = $argv[2];
if (!preg_match('/^[A-Za-z0-9_.-]{3,80}$/', $username)) {
    fwrite(STDERR, "Логин: 3–80 символов, латинские буквы, цифры, точка, дефис или подчёркивание.\n");
    exit(1);
}
if (strlen($password) < 10) {
    fwrite(STDERR, "Пароль должен содержать не менее 10 символов.\n");
    exit(1);
}
$hash = password_hash($password, PASSWORD_DEFAULT);
try {
    $statement = $pdo->prepare("INSERT INTO admins (username, password_hash) VALUES (:username, :password_hash) ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)");
    $statement->execute(['username' => $username, 'password_hash' => $hash]);
    fwrite(STDOUT, "Учётная запись администратора создана или обновлена: {$username}\n");
} catch (PDOException $exception) {
    fwrite(STDERR, "Не удалось создать администратора. Возможно, такой логин уже существует.\n");
    exit(1);
}
