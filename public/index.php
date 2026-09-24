<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
send_security_headers();
header('Content-Type: text/html; charset=utf-8');

$hotels = Repo::hotels();
$slug = is_string($_GET['hotel'] ?? null) ? $_GET['hotel'] : '';
$current = null;
foreach ($hotels as $h) {
    if ($h['slug'] === $slug) {
        $current = $h;
    }
}
$current ??= $hotels[0] ?? null;
$key = env('GOOGLE_MAPS_API_KEY');
$map = ($_GET['map'] ?? '') === 'google' && $key !== '' ? 'google' : 'leaflet';
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>施設マップ（デモ）</title>
<link rel="stylesheet" href="/assets/vendor/leaflet/leaflet.css">
<link rel="stylesheet" href="/assets/css/app.css">
<?php if ($key !== ''): ?><meta name="gmaps-key" content="<?= h($key) ?>"><?php endif; ?>
</head>
<body>
<header class="bar"><strong>施設マップ（デモ）</strong><span class="grow"></span><a href="/admin/">管理画面</a></header>
<?php if ($current === null): ?>
<main><p>ホテルが登録されていません。</p></main>
<?php else: ?>
<main id="app" data-hotel="<?= h($current['slug']) ?>" data-map="<?= h($map) ?>" data-google="<?= $key !== '' ? '1' : '0' ?>">
  <p class="note">自主検証のデモです。ホテル・施設はすべて架空のデータです。</p>
  <div class="controls">
    <label>ホテル
      <select id="hotel-select">
        <?php foreach ($hotels as $h): ?>
          <option value="<?= h($h['slug']) ?>"<?= $h['slug'] === $current['slug'] ? ' selected' : '' ?>><?= h($h['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>地図
      <select id="map-select">
        <option value="leaflet"<?= $map === 'leaflet' ? ' selected' : '' ?>>Leaflet + OpenStreetMap</option>
        <option value="google"<?= $map === 'google' ? ' selected' : '' ?><?= $key === '' ? ' disabled' : '' ?>>Google Maps<?= $key === '' ? '（APIキー未設定・未検証）' : '' ?></option>
      </select>
    </label>
  </div>
  <fieldset id="categories"><legend>カテゴリ</legend><span id="category-boxes"></span></fieldset>
  <p id="counts" class="counts" aria-live="polite">読み込み中…</p>
  <p id="map-error" class="errors" role="alert" hidden></p>
  <div id="map" role="region" aria-label="施設の地図"></div>
</main>
<script src="/assets/vendor/leaflet/leaflet.js"></script>
<script src="/assets/js/adapters/leaflet.js"></script>
<script src="/assets/js/adapters/google.js"></script>
<script src="/assets/js/map.js"></script>
<?php endif; ?>
</body>
</html>
