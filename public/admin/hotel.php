<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';
Auth::requireLogin();

$id = (int)($_GET['id'] ?? 0);
$hotel = Repo::hotelById($id);
if ($hotel === null) {
    http_response_code(404);
    View::header('見つかりません');
    echo '<p>ホテルが見つかりません。</p><p><a href="/admin/">一覧へ</a></p>';
    View::footer();
    exit;
}

$errors = [];
$form = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::requireValid();
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    if ($action === 'update_settings') {
        [$clean, $errors] = Validator::hotelSettings($_POST);
        if ($clean !== null) {
            Repo::updateHotel($id, $clean);
            View::flash('ホテルの設定を保存しました');
            redirect('/admin/hotel.php?id=' . $id);
        }
        $form = $_POST;
    } elseif ($action === 'delete_facility') {
        $fid = (int)($_POST['facility_id'] ?? 0);
        $f = Repo::facility($fid);
        if ($f !== null && (int)$f['hotel_id'] === $id) {
            Repo::deleteFacility($fid);
            View::flash('施設を削除しました');
        }
        redirect('/admin/hotel.php?id=' . $id);
    }
}

$enabled = $form !== null ? (is_array($form['categories'] ?? null) ? $form['categories'] : []) : Repo::enabledCategories($id);
$val = static fn(string $k, string $default): string => $form !== null ? (is_string($form[$k] ?? null) ? $form[$k] : '') : $default;
$facilities = Repo::allFacilities($id);
View::header('ホテルの設定・施設: ' . $hotel['name']);
View::errors($errors);
?>
<form method="post" class="card">
  <?= Csrf::field() ?>
  <input type="hidden" name="action" value="update_settings">
  <label>ホテル名<input name="name" maxlength="100" required value="<?= h($val('name', $hotel['name'])) ?>"></label>
  <div class="row">
    <label>地図の中心（緯度）<input name="center_lat" required value="<?= h($val('center_lat', (string)$hotel['center_lat'])) ?>"></label>
    <label>地図の中心（経度）<input name="center_lng" required value="<?= h($val('center_lng', (string)$hotel['center_lng'])) ?>"></label>
    <label>ズーム（1〜20）<input name="zoom" inputmode="numeric" required value="<?= h($val('zoom', (string)$hotel['zoom'])) ?>"></label>
  </div>
  <fieldset><legend>地図に表示するカテゴリ</legend>
    <?php foreach (Validator::CATEGORIES as $key => $label): ?>
      <label class="check"><input type="checkbox" name="categories[]" value="<?= h($key) ?>"<?= in_array($key, $enabled, true) ? ' checked' : '' ?>> <?= h($label) ?></label>
    <?php endforeach; ?>
  </fieldset>
  <button type="submit" class="btn">設定を保存</button>
</form>

<h2>施設（<?= count($facilities) ?>件）</h2>
<p><a class="btn small" href="/admin/facility.php?hotel_id=<?= $id ?>">施設を追加</a></p>
<table>
  <thead><tr><th>名前</th><th>カテゴリ</th><th>緯度・経度</th><th>取得元</th><th>地図に表示</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($facilities as $f): ?>
    <tr>
      <td><?= h($f['name']) ?></td>
      <td><?= h(Validator::CATEGORIES[$f['category']] ?? $f['category']) ?></td>
      <td><?= h((string)$f['lat']) ?>, <?= h((string)$f['lng']) ?></td>
      <td><?= h($f['source']) ?><?= $f['external_id'] !== null ? ' / ' . h($f['external_id']) : '' ?></td>
      <td><?= (int)$f['visible'] === 1 ? '○' : '×（このホテルでは非表示のカテゴリ）' ?></td>
      <td>
        <a href="/admin/facility.php?hotel_id=<?= $id ?>&amp;id=<?= (int)$f['id'] ?>">編集</a>
        <form method="post" class="inline" onsubmit="return confirm('この施設を削除しますか？');">
          <?= Csrf::field() ?>
          <input type="hidden" name="action" value="delete_facility">
          <input type="hidden" name="facility_id" value="<?= (int)$f['id'] ?>">
          <button type="submit" class="link danger">削除</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php View::footer();
