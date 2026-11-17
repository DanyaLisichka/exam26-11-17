<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$allowedCategories = ['standard', 'studio', 'lux'];
$categoryRaw = request_string($_GET, 'category');
$category = in_array($categoryRaw, $allowedCategories, true) ? $categoryRaw : '';
$minPriceRaw = request_string($_GET, 'min_price');
$maxPriceRaw = request_string($_GET, 'max_price');
$capacityRaw = request_string($_GET, 'capacity');
$errors = [];
$minPrice = null;
$maxPrice = null;
$capacity = null;

if ($minPriceRaw !== '') {
    if (!ctype_digit($minPriceRaw) || (int)$minPriceRaw > 1000000) {
        $errors[] = 'Минимальная цена должна быть целым числом от 0 до 1 000 000.';
    } else {
        $minPrice = (int)$minPriceRaw;
    }
}
if ($maxPriceRaw !== '') {
    if (!ctype_digit($maxPriceRaw) || (int)$maxPriceRaw > 1000000) {
        $errors[] = 'Максимальная цена должна быть целым числом от 0 до 1 000 000.';
    } else {
        $maxPrice = (int)$maxPriceRaw;
    }
}
if ($minPrice !== null && $maxPrice !== null && $minPrice > $maxPrice) {
    $errors[] = 'Минимальная цена не может превышать максимальную.';
}
if ($capacityRaw !== '') {
    if (!ctype_digit($capacityRaw) || (int)$capacityRaw < 1 || (int)$capacityRaw > 20) {
        $errors[] = 'Количество мест должно быть от 1 до 20.';
    } else {
        $capacity = (int)$capacityRaw;
    }
}

$sql = 'SELECT r.*, GROUP_CONCAT(a.name ORDER BY a.name SEPARATOR \'||\') AS amenity_names
        FROM rooms r
        LEFT JOIN room_amenities ra ON ra.room_id = r.id
        LEFT JOIN amenities a ON a.id = ra.amenity_id
        WHERE r.is_active = 1';
$params = [];
if ($category !== '') {
    $sql .= ' AND r.category = :category';
    $params['category'] = $category;
}
if ($minPrice !== null) {
    $sql .= ' AND r.price_max >= :min_price';
    $params['min_price'] = $minPrice;
}
if ($maxPrice !== null) {
    $sql .= ' AND r.price_min <= :max_price';
    $params['max_price'] = $maxPrice;
}
if ($capacity !== null) {
    $sql .= ' AND r.capacity >= :capacity';
    $params['capacity'] = $capacity;
}
$sql .= ' GROUP BY r.id, r.category, r.price_min, r.price_max, r.capacity, r.area, r.description, r.image, r.is_active, r.created_at ORDER BY FIELD(r.category, \'standard\', \'studio\', \'lux\'), r.price_min';

$rooms = [];
if (!$errors) {
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $rooms = $statement->fetchAll();
}

$pageTitle = 'Каталог номеров — «Сладкие сны»';
require __DIR__ . '/includes/header.php';
?>
<main>
    <section class="intro mb-4">
        <p class="eyebrow mb-2">Гостиница в Москве</p>
        <h1>Найдите номер для комфортного отдыха</h1>
        <p class="text-secondary mb-0">Выберите подходящую категорию и отправьте заявку на бронирование.</p>
    </section>

    <section class="filter-panel mb-4" aria-labelledby="filter-title">
        <h2 id="filter-title" class="h5 mb-3">Поиск номера</h2>
        <form method="get" action="index.php" class="row g-3 align-items-end" novalidate>
            <div class="col-md-3">
                <label class="form-label" for="category">Категория</label>
                <select class="form-select" name="category" id="category">
                    <option value="">Все категории</option>
                    <option value="standard" <?= $category === 'standard' ? 'selected' : '' ?>>Стандартный</option>
                    <option value="studio" <?= $category === 'studio' ? 'selected' : '' ?>>Студия</option>
                    <option value="lux" <?= $category === 'lux' ? 'selected' : '' ?>>Люкс</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="min_price">Цена от, ₽</label>
                <input class="form-control" type="number" min="0" max="1000000" name="min_price" id="min_price" value="<?= e($minPriceRaw) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="max_price">Цена до, ₽</label>
                <input class="form-control" type="number" min="0" max="1000000" name="max_price" id="max_price" value="<?= e($maxPriceRaw) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="capacity">Количество мест</label>
                <input class="form-control" type="number" min="1" max="20" name="capacity" id="capacity" value="<?= e($capacityRaw) ?>" placeholder="Любое">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-primary flex-grow-1" type="submit">Найти</button>
                <a class="btn btn-outline-secondary" href="index.php">Сбросить</a>
            </div>
        </form>
        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger mt-3 mb-0" role="alert"><?= e($error) ?></div>
        <?php endforeach; ?>
    </section>

    <section aria-labelledby="catalog-title">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <h2 id="catalog-title" class="h3 mb-0">Каталог номеров</h2>
            <?php if (!$errors): ?><span class="text-secondary small">Найдено: <?= count($rooms) ?></span><?php endif; ?>
        </div>
        <?php if ($errors): ?>
            <div class="alert alert-warning">Исправьте параметры фильтра и повторите поиск.</div>
        <?php elseif (!$rooms): ?>
            <div class="alert alert-info">По заданным параметрам номера не найдены. Измените фильтры.</div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($rooms as $room): ?>
                    <?php $amenities = $room['amenity_names'] ? explode('||', $room['amenity_names']) : []; ?>
                    <div class="col-md-6 col-xl-4">
                        <article class="card room-card h-100">
                            <img src="<?= e($room['image']) ?>" class="card-img-top room-image" alt="Номер категории <?= e(room_category_label($room['category'])) ?>">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <h3 class="h4 card-title"><?= e(room_category_label($room['category'])) ?></h3>
                                    <span class="price-tag"><?= number_format((int)$room['price_min'], 0, ',', ' ') ?>–<?= number_format((int)$room['price_max'], 0, ',', ' ') ?> ₽</span>
                                </div>
                                <?php if (!empty($room['description'])): ?><p class="text-secondary"><?= e($room['description']) ?></p><?php endif; ?>
                                <div class="room-facts small text-secondary mb-3">
                                    <span>Мест: <?= $room['capacity'] !== null ? (int)$room['capacity'] : 'не указано' ?></span>
                                    <span>Площадь: <?= $room['area'] !== null ? e((string)$room['area']) . ' м²' : 'не указана' ?></span>
                                </div>
                                <h4 class="h6">Характеристики</h4>
                                <?php if ($amenities): ?>
                                    <ul class="list-group list-group-flush mb-3">
                                        <?php foreach ($amenities as $amenity): ?><li class="list-group-item px-0 py-1"><?= e($amenity) ?></li><?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="text-secondary small">Дополнительные характеристики не указаны.</p>
                                <?php endif; ?>
                                <a href="order.php?room_id=<?= (int)$room['id'] ?>" class="btn btn-success mt-auto">Забронировать</a>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
