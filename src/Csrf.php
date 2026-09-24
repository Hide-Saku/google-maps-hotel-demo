<?php
declare(strict_types=1);

/** CSRF 対策。POST は、セッションに入れたトークンと一致しなければ拒否する。 */
final class Csrf
{
    public static function token(): string
    {
        Auth::start();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="csrf" value="' . h(self::token()) . '">';
    }

    /** POST の先頭で呼ぶ。トークンが無い・違う場合は 403 で終了する。 */
    public static function requireValid(): void
    {
        Auth::start();
        $sent = is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '';
        $have = is_string($_SESSION['csrf'] ?? null) ? $_SESSION['csrf'] : '';
        if ($have === '' || !hash_equals($have, $sent)) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Forbidden (CSRF token mismatch)';
            exit;
        }
    }
}
