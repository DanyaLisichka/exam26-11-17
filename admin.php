<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $bookingId = filter_var($_POST['booking_id'] ?? null, FILTER_VALIDATE_INT);
    $action = request_string($_POST, 'action');

    if (!$bookingId || $bookingId < 1 || !in_array($action, ['approve', 'reject', 'delete'], true)) {
        flash('danger', 'Не удалось обработать заявку: некорректные данные.');
        redirect('admin.php');
    }

    if ($action === 'delete') {
        $delete = $pdo->prepare('DELETE FROM bookings WHERE id = :id');
        $delete->execute(['id' => $bookingId]);
        if ($delete->rowCount() === 1) {
            flash('success', 'Заявка №' . $bookingId . ' удалена.');
        } else {
            flash('warning', 'Заявка не найдена или уже удалена.');
        }
        redirect('admin.php');
    }

    if ($action === 'reject') {
        $update = $pdo->prepare('UPDATE bookings SET status = \'rejected\' WHERE id = :id AND status = \'new\'');
        $update->execute(['id' => $bookingId]);
        if ($update->rowCount() === 1) {
            flash('success', 'Заявка №' . $bookingId . ' отклонена.');
        } else {
            flash('warning', 'Заявка не найдена или уже обработана.');
        }
        redirect('admin.php');
    }

    // Одобрение выполняется в транзакции. Сначала блокируем строку номера,
    // чтобы два администратора не смогли одновременно одобрить пересекающиеся заявки.
    try {
        $roomLookup = $pdo->prepare('SELECT room_id FROM bookings WHERE id = :id');
        $roomLookup->execute(['id' => $bookingId]);
        $roomId = $roomLookup->fetchColumn();

        if (!$roomId) {
            flash('warning', 'Заявка не найдена или уже удалена.');
            redirect('admin.php');
        }

        $pdo->beginTransaction();
        $lockRoom = $pdo->prepare('SELECT id FROM rooms WHERE id = :id FOR UPDATE');
        $lockRoom->execute(['id' => (int)$roomId]);

        $bookingLookup = $pdo->prepare(
            'SELECT id, room_id, check_in, check_out, status FROM bookings WHERE id = :id FOR UPDATE'
        );
        $bookingLookup->execute(['id' => $bookingId]);
        $booking = $bookingLookup->fetch();

        if (!$booking || $booking['status'] !== 'new') {
            $pdo->rollBack();
            flash('warning', 'Заявка не найдена или уже обработана.');
            redirect('admin.php');
        }

        if (has_approved_booking_conflict(
            $pdo,
            (int)$booking['room_id'],
            $booking['check_in'],
            $booking['check_out'],
            (int)$booking['id']
        )) {
            $pdo->rollBack();
            flash('danger', 'Нельзя одобрить заявку №' . $bookingId . ': на этот номер уже одобрено бронирование на пересекающиеся даты.');
            redirect('admin.php');
        }

        $update = $pdo->prepare('UPDATE bookings SET status = \'approved\' WHERE id = :id AND status = \'new\'');
        $update->execute(['id' => $bookingId]);
        $pdo->commit();
        flash('success', 'Заявка №' . $bookingId . ' одобрена. Даты теперь недоступны для других гостей.');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Booking approval failed: ' . $exception->getMessage());
        flash('danger', 'Не удалось изменить статус заявки. Попробуйте ещё раз.');
    }
    redirect('admin.php');
}

$filterRaw = request_string($_GET, 'status');
$filter = in_array($filterRaw, ['new', 'approved', 'rejected'], true) ? $filterRaw : '';
$sql = 'SELECT b.*, r.category, r.price_min, r.price_max
        FROM bookings b INNER JOIN rooms r ON r.id = b.room_id';
$params = [];
if ($filter !== '') {
    $sql .= ' WHERE b.status = :status';
    $params['status'] = $filter;
}
$sql .= ' ORDER BY CASE b.status WHEN \'new\' THEN 0 WHEN \'approved\' THEN 1 ELSE 2 END, b.created_at DESC';
$statement = $pdo->prepare($sql);
$statement->execute($params);
$bookings = $statement->fetchAll();

$pageTitle = 'Заявки — «Сладкие сны»';
require __DIR__ . '/includes/header.php';
?>
<main>
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div><p class="eyebrow mb-2">Управление бронированиями</p><h1 class="mb-0">Заявки гостей</h1></div>
        <span class="text-secondary small">Администратор: <?= e((string)($_SESSION['admin_username'] ?? '')) ?></span>
    </div>
    <form method="get" action="admin.php" class="filter-panel row g-2 align-items-end mb-4">
        <div class="col-sm-8 col-md-4">
            <label for="status" class="form-label">Фильтр по статусу</label>
            <select class="form-select" name="status" id="status">
                <option value="">Все заявки</option>
                <option value="new" <?= $filter === 'new' ? 'selected' : '' ?>>Новые</option>
                <option value="approved" <?= $filter === 'approved' ? 'selected' : '' ?>>Одобренные</option>
                <option value="rejected" <?= $filter === 'rejected' ? 'selected' : '' ?>>Отклонённые</option>
            </select>
        </div>
        <div class="col-auto"><button class="btn btn-primary" type="submit">Показать</button></div>
        <div class="col-auto"><a class="btn btn-outline-secondary" href="admin.php">Сбросить</a></div>
    </form>

    <?php if (!$bookings): ?>
        <div class="alert alert-info">Заявок по выбранному фильтру пока нет.</div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($bookings as $booking): ?>
                <div class="col-lg-6">
                    <article class="booking-card h-100">
                        <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                            <h2 class="h5 mb-0">Заявка №<?= (int)$booking['id'] ?></h2>
                            <span class="badge text-bg-<?= e(booking_status_badge($booking['status'])) ?>"><?= e(booking_status_label($booking['status'])) ?></span>
                        </div>
                        <dl class="booking-details">
                            <dt>Гость</dt><dd><?= e($booking['last_name'] . ' ' . $booking['first_name']) ?></dd>
                            <dt>Телефон</dt><dd><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $booking['phone'])) ?>"><?= e($booking['phone']) ?></a></dd>
                            <dt>Email</dt><dd><a href="mailto:<?= e($booking['email']) ?>"><?= e($booking['email']) ?></a></dd>
                            <dt>Категория</dt><dd><?= e(room_category_label($booking['category'])) ?> (<?= number_format((int)$booking['price_min'], 0, ',', ' ') ?>–<?= number_format((int)$booking['price_max'], 0, ',', ' ') ?> ₽/сутки)</dd>
                            <dt>Заезд</dt><dd><?= date('d.m.Y', strtotime($booking['check_in'])) ?></dd>
                            <dt>Выезд</dt><dd><?= date('d.m.Y', strtotime($booking['check_out'])) ?></dd>
                            <dt>Создана</dt><dd><?= date('d.m.Y H:i', strtotime($booking['created_at'])) ?></dd>
                        </dl>
                        <div class="d-flex flex-wrap gap-2">
                            <?php if ($booking['status'] === 'new'): ?>
                                <form method="post" action="admin.php" class="m-0">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button class="btn btn-success" type="submit">Одобрить</button>
                                </form>
                                <form method="post" action="admin.php" class="m-0">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button class="btn btn-outline-danger" type="submit">Отклонить</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="admin.php" class="m-0" onsubmit="return confirm('Удалить заявку №<?= (int)$booking['id'] ?> без возможности восстановления?');">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">
                                <input type="hidden" name="action" value="delete">
                                <button class="btn btn-outline-secondary" type="submit">Удалить</button>
                            </form>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
