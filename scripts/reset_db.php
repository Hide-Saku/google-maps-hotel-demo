<?php
declare(strict_types=1);
// テスト用: DB を作り直す（スキーマ → 初期データ → 管理者）。取り込みは行わない。
// 安全のため、IS_TEST=1（web_test コンテナ）でしか実行できない。
require __DIR__ . '/../src/bootstrap.php';

if (env('IS_TEST') !== '1' || env('DB_NAME') !== 'hotel_map_test') {
    fwrite(STDERR, "reset_db.php は、テスト用DB（hotel_map_test・IS_TEST=1）でしか実行できません\n");
    exit(2);
}
$root = dirname(__DIR__);
Db::execFile($root . '/db/schema.sql');
Db::execFile($root . '/db/seed.sql');
Repo::createAdmin(env('ADMIN_USERNAME', 'admin'), password_hash(env('ADMIN_PASSWORD'), PASSWORD_DEFAULT));
echo "テスト用DBを初期化しました\n";
