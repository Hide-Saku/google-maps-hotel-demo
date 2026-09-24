<?php
declare(strict_types=1);
// 使い方: php scripts/import.php [ファイル]   （既定: data/mock_facilities.json）
require __DIR__ . '/../src/bootstrap.php';

$root = dirname(__DIR__);
$file = $argv[1] ?? ($root . '/data/mock_facilities.json');
try {
    $s = Importer::run($file, $root . '/logs/import_skipped.log');
} catch (Throwable $e) {
    fwrite(STDERR, "取り込みに失敗しました（変更はすべて取り消し）: " . $e->getMessage() . "\n");
    exit(1);
}
printf("取り込み結果: 追加 %d / 更新 %d / 変更なし %d / スキップ %d （run_id=%d）\n", $s['inserted'], $s['updated'], $s['unchanged'], $s['skipped'], $s['run_id']);
foreach ($s['skipped_rows'] as $r) {
    printf("  スキップ 行%d: %s\n", $r['row'], $r['reason']);
}
