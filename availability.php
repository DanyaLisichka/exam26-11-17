<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

function availability_json(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$roomId = filter_var($_GET['room_id'] ?? null, FILTER_VALIDATE_INT);
if (!$roomId || $roomId < 1) {
    availability_json(['error' => 'Не указан корректный номер.'], 400);
}

try {
    $roomStatement = $pdo->prepare('SELECT id FROM rooms WHERE id = :id AND is_active = 1 LIMIT 1');
    $roomStatement->execute(['id' => $roomId]);
    if (!$roomStatement->fetchColumn()) {
        availability_json(['error' => 'Номер не найден или временно недоступен.'], 404);
    }

    $statement = $pdo->prepare(
        'SELECT DATE_FORMAT(check_in, \'%Y-%m-%d\') AS check_in,
                DATE_FORMAT(check_out, \'%Y-%m-%d\') AS check_out
         FROM bookings
         WHERE room_id = :room_id
           AND status = \'approved\'
           AND check_out > CURRENT_DATE
         ORDER BY check_in'
    );
    $statement->execute(['room_id' => $roomId]);

    availability_json([
        'today' => (new DateTimeImmutable('today'))->format('Y-m-d'),
        'bookings' => $statement->fetchAll(),
    ]);
} catch (Throwable $exception) {
    error_log('Availability lookup failed: ' . $exception->getMessage());
    availability_json(['error' => 'Не удалось загрузить календарь. Обновите страницу или попробуйте позже.'], 500);
}
