<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';
Auth::requireLogin();

$hotelId = (int)($_GET['hotel_id'] ?? 0);
$hotel = Repo::hotelById($hotelId);
$fid = (int)($_GET['id'] ?? 0);
$facility = $fid > 0 ? Repo::facility($fid) : null;
if ($hotel === null || ($fid > 0 && ($facility === null || (int)$facility['hotel_id'] !== $hotelId))) {
    http_response_code(404);
    View::header('見つかりません');
    echo '<p>ホテルまたは施設が見つかりません。</p><p><a href="/admin/">一覧へ</a></p>';
    View::footer();
    exit;
}

$errors = [];
$form = $facility ?? ['name' => '', 'category' => 'restaurant', 'lat' => (string)$hotel['center_lat'], 'lng' => (string)$hotel['center_lng'], 'address' => '', 'description' => ''];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::requireValid();
    [$clean, $errors] = Validator::facility($_POST);
    if ($clean !== null) {
        if ($facility === null) {
            Repo::createFacility($hotelId, $clean);
            View::flash('施設を追加しました');
        } else {
            Repo::updateFacility($fid, $clean);
            View::flash('施設を更新しました');
        }
        redirect('/admin/hotel.php?id=' . $hotelId);
    }
    foreach (['name', 'category', 'lat', 'lng', 'address', 'description'] as $k) {
        $form[$k] = is_string($_POST[$k] ?? null) ? $_POST[$k] : '';
    }
}
View::header(($facility === null ? '施設を追加' : '施設を編集') . ': ' . $hotel['name']);
View::errors($errors);
?>
<form method="post" class="card">
  <?= Csrf::field() ?>
  <label>施設名<input name="name" maxlength="100" required value="<?= h((string)$form['name']) ?>"></label>
  <label>カテゴリ
    <select name="category">
      <?php foreach (Validator::CATEGORIES as $key => $label): ?>
        <option value="<?= h($key) ?>"<?= $form['category'] === $key ? ' selected' : '' ?>><?= h($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <div class="row">
    <label>緯度<input name="lat" required value="<?= h((string)$form['lat']) ?>"></label>
    <label>経度<input name="lng" required value="<?= h((string)$form['lng']) ?>"></label>
  </div>
  <label>住所<input name="address" maxlength="200" value="<?= h((string)$form['address']) ?>"></label>
  <label>説明<textarea name="description" maxlength="500" rows="3"><?= h((string)$form['description']) ?></textarea></label>
  <button type="submit" class="btn">保存</button>
  <a href="/admin/hotel.php?id=<?= $hotelId ?>">キャンセル</a>
</form>
<?php View::footer();
