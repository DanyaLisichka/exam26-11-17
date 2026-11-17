<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if (!empty($_SESSION['admin_id'])) {
    redirect('admin.php');
}
$error = '';
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = request_string($_POST, 'username');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    if ($username === '' || $password === '') {
        $error = 'Введите логин и пароль.';
    } else {
        $statement = $pdo->prepare('SELECT id, username, password_hash FROM admins WHERE username = :username LIMIT 1');
        $statement->execute(['username' => $username]);
        $admin = $statement->fetch();
        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            flash('success', 'Вы вошли в панель администратора.');
            redirect('admin.php');
        }
        $error = 'Неверный логин или пароль.';
    }
}

$pageTitle = 'Вход администратора — «Сладкие сны»';
require __DIR__ . '/includes/header.php';
?>
<main class="content-narrow">
    <div class="mb-4"><p class="eyebrow mb-2">Закрытый раздел</p><h1>Вход администратора</h1></div>
    <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form class="form-panel" method="post" action="login.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="mb-3">
            <label for="username" class="form-label">Логин</label>
            <input type="text" class="form-control" id="username" name="username" maxlength="80" value="<?= e($username) ?>" required autocomplete="username">
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Пароль</label>
            <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
        </div>
        <button class="btn btn-primary w-100" type="submit">Войти</button>
    </form>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
