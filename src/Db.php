<?php
declare(strict_types=1);

final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                env('DB_HOST', 'db'), env('DB_PORT', '3306'), env('DB_NAME', 'hotel_map')
            );
            self::$pdo = new PDO($dsn, env('DB_USER', 'app'), env('DB_PASSWORD'), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }
        return self::$pdo;
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }

    /**
     * SQL ファイルを1文ずつ実行する。
     * 複数文をまとめて exec すると、2文目以降のエラーが例外にならないため、分けて実行する。
     */
    public static function execFile(string $path): void
    {
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException('SQL file not readable: ' . $path);
        }
        $lines = array_filter(explode("\n", str_replace("\r\n", "\n", $sql)), static fn($l) => !str_starts_with(ltrim($l), '--'));
        $statements = preg_split('/;\s*\n/', implode("\n", $lines)) ?: [];
        foreach ($statements as $st) {
            $st = trim($st);
            if ($st !== '') {
                self::pdo()->exec($st);
            }
        }
    }
}
