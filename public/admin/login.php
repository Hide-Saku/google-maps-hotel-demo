<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';
Auth::start();
if (Auth::check()) {
    redirect('/admin/');
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::requireValid();
    $u = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
    $p = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    if ($u !== '' && $p !== '' && Auth::login($u, $p)) {
        redirect('/admin/');
    }
    $error = 'ユーザー名またはパスワードが正しくありません';
}
View::header('ログイン');
View::errors($error !== '' ? [$error] : []);
?>
<form method="post" class="card narrow">
  <?= Csrf::field() ?>
  <label>ユーザー名<input name="username" autocomplete="username" required maxlength="50"></label>
  <label>パスワード<input name="password" type="password" autocomplete="current-password" required></label>
  <button type="submit" class="btn">ログイン</button>
</form>
<?php View::footer();
