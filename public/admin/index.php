<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';
Auth::requireLogin();

$hotels = Repo::hotelsWithCounts();
$runs = Db::pdo()->query('SELECT id, started_at, source, file, inserted, updated, unchanged, skipped, status FROM import_runs ORDER BY id DESC LIMIT 5')->fetchAll();
View::header('ホテル一覧');
?>
<table>
  <thead><tr><th>ホテル</th><th>スラッグ</th><th>施設数</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($hotels as $h): ?>
    <tr>
      <td><?= h($h['name']) ?></td>
      <td><?= h($h['slug']) ?></td>
      <td><?= (int)$h['facility_count'] ?></td>
      <td><a href="/admin/hotel.php?id=<?= (int)$h['id'] ?>">設定・施設を編集</a> ／ <a href="/?hotel=<?= h($h['slug']) ?>">地図で見る</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<h2>取り込みの履歴（直近5件）</h2>
<?php if (!$runs): ?><p class="muted">まだありません。</p><?php else: ?>
<table>
  <thead><tr><th>#</th><th>日時（日本時間）</th><th>取得元</th><th>ファイル</th><th>追加</th><th>更新</th><th>変更なし</th><th>スキップ</th><th>状態</th></tr></thead>
  <tbody>
  <?php foreach ($runs as $r): ?>
    <tr><td><?= (int)$r['id'] ?></td><td><?= h(jst((string)$r['started_at'])) ?></td><td><?= h($r['source']) ?></td><td><?= h($r['file']) ?></td>
        <td><?= (int)$r['inserted'] ?></td><td><?= (int)$r['updated'] ?></td><td><?= (int)$r['unchanged'] ?></td><td><?= (int)$r['skipped'] ?></td><td><?= h($r['status']) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php View::footer();
