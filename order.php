<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$roomId = filter_input(INPUT_GET, 'room_id', FILTER_VALIDATE_INT);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roomId = filter_var($_POST['room_id'] ?? null, FILTER_VALIDATE_INT);
}
if (!$roomId || $roomId < 1) {
    flash('warning', 'Сначала выберите номер в каталоге.');
    redirect('index.php');
}
$roomStatement = $pdo->prepare('SELECT * FROM rooms WHERE id = :id AND is_active = 1');
$roomStatement->execute(['id' => $roomId]);
$room = $roomStatement->fetch();
if (!$room) {
    flash('danger', 'Выбранный номер не найден или временно недоступен.');
    redirect('index.php');
}

$values = [
    'first_name' => '', 'last_name' => '', 'phone' => '', 'email' => '',
    'check_in' => '', 'check_out' => '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach ($values as $field => $_) {
        $values[$field] = request_string($_POST, $field);
    }

    if (!preg_match('/^[\p{L}][\p{L}\s\-]{1,79}$/u', $values['first_name'])) {
        $errors[] = 'Введите имя: от 2 до 80 букв. Допускаются пробелы и дефис.';
    }
    if (!preg_match('/^[\p{L}][\p{L}\s\-]{1,79}$/u', $values['last_name'])) {
        $errors[] = 'Введите фамилию: от 2 до 80 букв. Допускаются пробелы и дефис.';
    }
    if (!preg_match('/^\+7\(\d{3}\)\d{3}-\d{2}-\d{2}$/', $values['phone'])) {
        $errors[] = 'Укажите телефон в формате +7(999)999-99-99.';
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || strlen($values['email']) > 254) {
        $errors[] = 'Укажите корректный адрес электронной почты.';
    }

    $checkIn = parse_ru_date($values['check_in']);
    $checkOut = parse_ru_date($values['check_out']);
    $today = new DateTimeImmutable('today');
    if (!$checkIn) {
        $errors[] = 'Выберите корректную дату заезда в календаре.';
    } elseif ($checkIn < $today) {
        $errors[] = 'Дата заезда не может быть раньше текущей даты.';
    }
    if (!$checkOut) {
        $errors[] = 'Выберите корректную дату выезда в календаре.';
    }
    if ($checkIn && $checkOut && $checkOut <= $checkIn) {
        $errors[] = 'Дата выезда должна быть позже даты заезда.';
    }

    if (!$errors && $checkIn && $checkOut) {
        try {
            // Блокировка строки номера сериализует создание/одобрение заявок для одного номера.
            $pdo->beginTransaction();
            $lockRoom = $pdo->prepare('SELECT id FROM rooms WHERE id = :id AND is_active = 1 FOR UPDATE');
            $lockRoom->execute(['id' => $roomId]);

            if (!$lockRoom->fetchColumn()) {
                $pdo->rollBack();
                $errors[] = 'Выбранный номер больше недоступен. Вернитесь в каталог.';
            } elseif (has_approved_booking_conflict(
                $pdo,
                $roomId,
                $checkIn->format('Y-m-d'),
                $checkOut->format('Y-m-d')
            )) {
                $pdo->rollBack();
                $errors[] = 'Выбранный период уже занят одобренным бронированием. Выберите другие даты.';
            } else {
                $insert = $pdo->prepare(
                    'INSERT INTO bookings (room_id, first_name, last_name, phone, email, check_in, check_out, status)
                     VALUES (:room_id, :first_name, :last_name, :phone, :email, :check_in, :check_out, \'new\')'
                );
                $insert->execute([
                    'room_id' => $roomId,
                    'first_name' => $values['first_name'],
                    'last_name' => $values['last_name'],
                    'phone' => $values['phone'],
                    'email' => $values['email'],
                    'check_in' => $checkIn->format('Y-m-d'),
                    'check_out' => $checkOut->format('Y-m-d'),
                ]);
                $pdo->commit();
                flash('success', 'Заявка отправлена. Администратор свяжется с вами для уточнения деталей.');
                redirect('index.php');
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Booking creation failed: ' . $exception->getMessage());
            $errors[] = 'Не удалось сохранить заявку. Проверьте данные и попробуйте ещё раз.';
        }
    }
}

$pageTitle = 'Бронирование — «Сладкие сны»';
$pageScripts = ['js/booking-calendar.js'];
require __DIR__ . '/includes/header.php';
?>
<main class="content-narrow">
    <div class="mb-4">
        <p class="eyebrow mb-2">Оформление заявки</p>
        <h1>Бронирование номера</h1>
        <p class="text-secondary">Вы выбрали категорию «<?= e(room_category_label($room['category'])) ?>» — <?= number_format((int)$room['price_min'], 0, ',', ' ') ?>–<?= number_format((int)$room['price_max'], 0, ',', ' ') ?> ₽ за сутки.</p>
    </div>
    <?php if ($errors): ?>
        <div class="alert alert-danger" role="alert">
            <strong>Не удалось отправить заявку:</strong>
            <ul class="mb-0 mt-2"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>
    <form class="form-panel row g-3" method="post" action="order.php?room_id=<?= (int)$room['id'] ?>" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="room_id" value="<?= (int)$room['id'] ?>">
        <div class="col-md-6">
            <label for="first_name" class="form-label">Имя</label>
            <input type="text" class="form-control" id="first_name" name="first_name" maxlength="80" value="<?= e($values['first_name']) ?>" required autocomplete="given-name">
        </div>
        <div class="col-md-6">
            <label for="last_name" class="form-label">Фамилия</label>
            <input type="text" class="form-control" id="last_name" name="last_name" maxlength="80" value="<?= e($values['last_name']) ?>" required autocomplete="family-name">
        </div>
        <div class="col-md-6">
            <label for="phone" class="form-label">Телефон</label>
            <input type="tel" class="form-control" id="validationCustomPhone" name="phone" placeholder="+7(999)999-99-99" value="<?= e($values['phone']) ?>" required autocomplete="tel">
        </div>
        <div class="col-md-6">
            <label for="email" class="form-label">Электронная почта</label>
            <input type="email" class="form-control" id="email" name="email" maxlength="254" value="<?= e($values['email']) ?>" required autocomplete="email">
        </div>
        <div class="col-md-6">
            <label for="check_in" class="form-label">Дата заезда</label>
            <input type="text" class="form-control booking-date-input" id="check_in" name="check_in" placeholder="Выберите дату в календаре" value="<?= e($values['check_in']) ?>" readonly required aria-describedby="calendarHelp">
        </div>
        <div class="col-md-6">
            <label for="check_out" class="form-label">Дата выезда</label>
            <input type="text" class="form-control booking-date-input" id="check_out" name="check_out" placeholder="Выберите дату в календаре" value="<?= e($values['check_out']) ?>" readonly required aria-describedby="calendarHelp">
        </div>
        <div class="col-12" id="bookingCalendar" data-room-id="<?= (int)$room['id'] ?>">
            <div class="calendar-panel">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div>
                        <h2 class="h5 mb-1">Календарь доступности</h2>
                        <p class="small text-secondary mb-0" id="calendarHelp">Сначала выберите заезд, затем выезд. Серые даты заняты одобренными бронированиями.</p>
                    </div>
                    <div class="calendar-nav d-flex align-items-center gap-2">
                        <button class="btn btn-outline-secondary btn-sm" type="button" id="calendarPrevious" aria-label="Предыдущий месяц">‹</button>
                        <strong id="calendarMonth" class="calendar-month" aria-live="polite"></strong>
                        <button class="btn btn-outline-secondary btn-sm" type="button" id="calendarNext" aria-label="Следующий месяц">›</button>
                    </div>
                </div>
                <div class="calendar-weekdays" aria-hidden="true">
                    <span>Пн</span><span>Вт</span><span>Ср</span><span>Чт</span><span>Пт</span><span>Сб</span><span>Вс</span>
                </div>
                <div class="calendar-grid" id="calendarDays" aria-label="Даты месяца"></div>
                <div class="calendar-legend mt-3">
                    <span><i class="legend-dot legend-free"></i> Свободно</span>
                    <span><i class="legend-dot legend-busy"></i> Занято</span>
                    <span><i class="legend-dot legend-selected"></i> Выбрано</span>
                </div>
                <div class="small mt-3" id="calendarMessage" role="status" aria-live="polite">Загрузка данных о занятости…</div>
                <div class="mt-2"><button type="button" class="btn btn-link btn-sm px-0" id="clearBookingDates">Очистить выбранные даты</button></div>
            </div>
        </div>
        <div class="col-12 d-flex flex-wrap gap-2">
            <button class="btn btn-primary" type="submit">Отправить заявку</button>
            <a href="index.php" class="btn btn-outline-secondary">Вернуться в каталог</a>
        </div>
        <p class="small text-secondary mb-0">Занятыми считаются периоды уже одобренных заявок. Отправленная заявка получит статус «Новая» и не блокирует даты до одобрения администратором.</p>
        <p class="small text-secondary mb-0">Отправляя заявку, вы соглашаетесь на обработку указанных контактных данных для связи по бронированию.</p>
    </form>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
