<?php
declare(strict_types=1);

/** 管理画面の共通の枠。 */
final class View
{
    public static function flash(string $msg): void
    {
        Auth::start();
        $_SESSION['flash'] = $msg;
    }

    public static function header(string $title): void
    {
        send_security_headers();
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        $flash = '';
        if (!empty($_SESSION['flash'])) {
            $flash = '<p class="flash">' . h((string)$_SESSION['flash']) . '</p>';
            unset($_SESSION['flash']);
        }
        echo '<!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . h($title) . ' | 管理画面（デモ）</title><link rel="stylesheet" href="/assets/css/app.css"></head><body class="admin">'
            . '<header class="bar"><a href="/admin/">管理画面（デモ）</a><span class="grow"></span><a href="/">地図を見る</a>';
        if (Auth::check()) {
            echo '<form method="post" action="/admin/logout.php" class="inline">' . Csrf::field()
                . '<button type="submit" class="link">ログアウト（' . h((string)($_SESSION['admin_name'] ?? '')) . '）</button></form>';
        }
        echo '</header><main><h1>' . h($title) . '</h1>' . $flash;
    }

    public static function errors(array $errors): void
    {
        if ($errors) {
            echo '<div class="errors" role="alert"><ul>';
            foreach ($errors as $e) {
                echo '<li>' . h($e) . '</li>';
            }
            echo '</ul></div>';
        }
    }

    public static function footer(): void
    {
        echo '</main></body></html>';
    }
}
