<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function request_string(array $source, string $key): string
{
    $value = $source[$key] ?? '';
    return is_string($value) || is_numeric($value) ? trim((string)$value) : '';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    $saved = $_SESSION['csrf_token'] ?? '';
    if (!is_string($sent) || !is_string($saved) || $saved === '' || !hash_equals($saved, $sent)) {
        http_response_code(419);
        exit('Срок действия формы истёк. Обновите страницу и попробуйте ещё раз.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($message) ? $message : null;
}

function redirect(string $url): never
{
    header('Location: ' . $url, true, 303);
    exit;
}

function require_admin(): void
{
    if (empty($_SESSION['admin_id'])) {
        flash('warning', 'Для доступа к этому разделу необходимо войти как администратор.');
        redirect('login.php');
    }
}

function room_category_label(string $category): string
{
    return match ($category) {
        'standard' => 'Стандартный',
        'studio' => 'Студия',
        'lux' => 'Люкс',
        default => 'Не указано',
    };
}

function booking_status_label(string $status): string
{
    return match ($status) {
        'new' => 'Новая',
        'approved' => 'Одобрена',
        'rejected' => 'Отклонена',
        default => 'Неизвестно',
    };
}

function booking_status_badge(string $status): string
{
    return match ($status) {
        'approved' => 'success',
        'rejected' => 'danger',
        default => 'warning',
    };
}

function parse_ru_date(string $value): ?DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('!d.m.Y', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        return null;
    }
    return $date->format('d.m.Y') === $value ? $date : null;
}

/**
 * Проверяет пересечение периода с уже одобренными бронированиями номера.
 * Периоды рассматриваются как [заезд, выезд): выезд в день следующего заезда допустим.
 * При вызове внутри транзакции FOR UPDATE защищает проверку от параллельного одобрения.
 */
function has_approved_booking_conflict(
    PDO $pdo,
    int $roomId,
    string $checkIn,
    string $checkOut,
    ?int $excludeBookingId = null
): bool {
    $sql = 'SELECT id
            FROM bookings
            WHERE room_id = :room_id
              AND status = \'approved\'
              AND check_in < :check_out
              AND check_out > :check_in';
    $params = [
        'room_id' => $roomId,
        'check_out' => $checkOut,
        'check_in' => $checkIn,
    ];

    if ($excludeBookingId !== null) {
        $sql .= ' AND id <> :exclude_id';
        $params['exclude_id'] = $excludeBookingId;
    }

    $sql .= ' LIMIT 1 FOR UPDATE';
    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    return (bool)$statement->fetchColumn();
}
