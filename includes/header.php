<?php
$pageTitle = $pageTitle ?? 'Гостиница «Сладкие сны»';
$flashMessage = take_flash();
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Гостиница «Сладкие сны»: каталог номеров и онлайн-бронирование.">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/index.css">
</head>
<body class="container">
<header class="site-header d-flex flex-wrap align-items-center justify-content-between py-3 px-3">
    <a href="index.php" class="brand fs-4 fw-semibold text-decoration-none">Сладкие сны</a>
    <nav class="nav align-items-center">
        <a class="nav-link" href="index.php">Каталог</a>
        <?php if (!empty($_SESSION['admin_id'])): ?>
            <a class="nav-link" href="admin.php">Заявки</a>
            <form action="logout.php" method="post" class="m-0">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button class="btn btn-outline-secondary btn-sm" type="submit">Выйти</button>
            </form>
        <?php else: ?>
            <a class="nav-link" href="login.php">Вход администратора</a>
        <?php endif; ?>
    </nav>
</header>
<?php if ($flashMessage): ?>
    <div class="alert alert-<?= e($flashMessage['type']) ?> alert-dismissible fade show" role="alert">
        <?= e($flashMessage['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>
    </div>
<?php endif; ?>
