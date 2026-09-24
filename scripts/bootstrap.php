<?php
declare(strict_types=1);
// コンテナ起動時に実行: DB を待つ → テーブルが無ければ作る → 初期データ → 管理者 → モックの取り込み（初回のみ）
require __DIR__ . '/../src/bootstrap.php';

$root = dirname(__DIR__);
$tries = 0;
while (true) {
    try {
        Db::pdo();
        break;
    } catch (Throwable $e) {
        if (++$tries > 60) {
            fwrite(STDERR, "DB に接続できません: " . $e->getMessage() . "\n");
            exit(1);
        }
        Db::reset();
        sleep(1);
    }
}

$pdo = Db::pdo();
if (!$pdo->query("SHOW TABLES LIKE 'hotels'")->fetch()) {
    Db::execFile($root . '/db/schema.sql');
    fwrite(STDOUT, "[bootstrap] テーブルを作成しました\n");
}
if (Repo::count('hotels') === 0) {
    Db::execFile($root . '/db/seed.sql');
    fwrite(STDOUT, "[bootstrap] 初期データ（架空のホテル3件・施設）を入れました\n");
}
if (Repo::count('admin_users') === 0) {
    $u = env('ADMIN_USERNAME', 'admin');
    $p = env('ADMIN_PASSWORD');
    if ($p === '') {
        $p = bin2hex(random_bytes(8));
        fwrite(STDOUT, "[bootstrap] ADMIN_PASSWORD が未設定のため、ランダムなパスワードを作りました（一度だけ表示）: $p\n");
    }
    Repo::createAdmin($u, password_hash($p, PASSWORD_DEFAULT));
    fwrite(STDOUT, "[bootstrap] 管理者 '$u' を作成しました（パスワードはハッシュで保存）\n");
}
if (env('IS_TEST') === '' && Repo::count('import_runs') === 0) {
    $s = Importer::run($root . '/data/mock_facilities.json', $root . '/logs/import_skipped.log');
    fwrite(STDOUT, sprintf("[bootstrap] モックを取り込みました: 追加%d 更新%d 変更なし%d スキップ%d\n", $s['inserted'], $s['updated'], $s['unchanged'], $s['skipped']));
}
