<?php
declare(strict_types=1);

/** 管理画面のログイン。パスワードは password_hash / password_verify（平文は保存しない）。 */
final class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name('hotelmap');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => !empty($_SERVER['HTTPS']),
        ]);
        session_start();
    }

    public static function login(string $username, string $password): bool
    {
        $row = Repo::adminByUsername($username);
        // ユーザーが存在しなくてもハッシュ照合を1回行い、応答時間から存在を推測されにくくする
        $hash = $row['password_hash'] ?? password_hash('dummy-password', PASSWORD_DEFAULT);
        $ok = password_verify($password, $hash) && $row !== null;
        if ($ok) {
            self::start();
            session_regenerate_id(true);
            unset($_SESSION['csrf']);
            $_SESSION['admin_id'] = (int)$row['id'];
            $_SESSION['admin_name'] = (string)$row['username'];
        }
        return $ok;
    }

    public static function check(): bool
    {
        self::start();
        return !empty($_SESSION['admin_id']);
    }

    /** 未ログインならログイン画面へ。管理画面のすべてのページの先頭で呼ぶ。 */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            redirect('/admin/login.php');
        }
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'httponly' => true, 'samesite' => 'Lax']);
        }
        session_destroy();
    }
}
