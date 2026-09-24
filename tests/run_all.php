<?php
declare(strict_types=1);
// 実行: docker compose --profile test up -d --build web_test
//       docker compose --profile test exec -T web_test php tests/run_all.php
// テスト用DB（hotel_map_test）を作り直してから、単体テスト → HTTP テストを流す。デモのDBには触れない。
require __DIR__ . '/../src/bootstrap.php';

if (env('IS_TEST') !== '1') {
    fwrite(STDERR, "テストは web_test コンテナ（IS_TEST=1）で実行してください\n");
    exit(2);
}

$pass = 0;
$fail = 0;
function t(string $name, callable $fn): void
{
    global $pass, $fail;
    try {
        $fn();
        $pass++;
        echo "  ok   $name\n";
    } catch (Throwable $e) {
        $fail++;
        echo "  FAIL $name\n       " . $e->getMessage() . "\n";
    }
}
function ok(bool $cond, string $msg = 'assertion failed'): void
{
    if (!$cond) {
        throw new RuntimeException($msg);
    }
}
function eq(mixed $actual, mixed $expected, string $msg = ''): void
{
    if ($actual !== $expected) {
        throw new RuntimeException(($msg !== '' ? $msg . ': ' : '') . 'expected ' . var_export($expected, true) . ' got ' . var_export($actual, true));
    }
}
function sql_count(string $sql, array $params = []): int
{
    $st = Db::pdo()->prepare($sql);
    $st->execute($params);
    return (int)$st->fetchColumn();
}
function hotel_id(string $slug): int
{
    return (int)Repo::hotelBySlug($slug)['id'];
}

// ---------- HTTP ヘルパー ----------
function http(string $method, string $path, ?array $form = null, ?string $jar = null): array
{
    $ch = curl_init('http://127.0.0.1' . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 20, CURLOPT_CUSTOMREQUEST => $method]);
    if ($jar !== null) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $jar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $jar);
    }
    if ($form !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form));
    }
    $raw = (string)curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hs = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return ['code' => $code, 'headers' => substr($raw, 0, $hs), 'body' => substr($raw, $hs)];
}
function csrf_of(string $body): string
{
    return preg_match('/name="csrf" value="([0-9a-f]+)"/', $body, $m) ? $m[1] : '';
}
function new_jar(): string
{
    $j = tempnam(sys_get_temp_dir(), 'jar');
    return $j;
}
function session_cookie(string $jar): string
{
    foreach (file($jar) ?: [] as $line) {
        if (str_contains($line, "hotelmap")) {
            $parts = preg_split('/\s+/', trim($line));
            return (string)end($parts);
        }
    }
    return '';
}
function login(string $jar, string $user, string $pass): array
{
    $g = http('GET', '/admin/login.php', null, $jar);
    return http('POST', '/admin/login.php', ['csrf' => csrf_of($g['body']), 'username' => $user, 'password' => $pass], $jar);
}
/** ログイン済みのクッキー入れを作る */
function logged_in_jar(): string
{
    $jar = new_jar();
    $r = login($jar, env('ADMIN_USERNAME', 'admin'), env('ADMIN_PASSWORD'));
    ok($r['code'] === 302, 'ログインに失敗: HTTP ' . $r['code']);
    return $jar;
}
function page_csrf(string $jar, string $path): string
{
    $r = http('GET', $path, null, $jar);
    ok($r['code'] === 200, "GET $path が HTTP {$r['code']}");
    $c = csrf_of($r['body']);
    ok($c !== '', 'CSRF トークンがページに無い');
    return $c;
}

// ---------- 準備 ----------
echo "テスト用DBを初期化\n";
require __DIR__ . '/../scripts/reset_db.php';
$root = dirname(__DIR__);
$mock = $root . '/data/mock_facilities.json';
$kyoto = hotel_id('kyoto');

echo "\n[単体] 入力の検証\n";
t('正しい施設は整形されて通る（緯度経度は小数6桁）', function () {
    [$c, $e] = Validator::facility(['name' => ' テスト ', 'category' => 'shop', 'lat' => '34.9865', 'lng' => 135.76, 'address' => '', 'description' => '']);
    ok($c !== null && $e === [], implode(',', $e));
    eq($c['name'], 'テスト');
    eq($c['lat'], '34.986500');
    eq($c['lng'], '135.760000');
});
t('不正な入力はすべて弾く（空の名前・範囲外・文字・未知のカテゴリ・長すぎる）', function () {
    $base = ['name' => 'x', 'category' => 'shop', 'lat' => '1', 'lng' => '1', 'address' => '', 'description' => ''];
    foreach ([['name' => ''], ['name' => str_repeat('あ', 101)], ['lat' => '91'], ['lat' => '-90.1'], ['lng' => '181'], ['lng' => 'abc'],
        ['lat' => '1e5'], ['lat' => ''], ['lat' => null], ['category' => 'spa'], ['category' => ''], ['address' => str_repeat('a', 201)],
        ['description' => str_repeat('a', 501)]] as $bad) {
        [$c] = Validator::facility(array_merge($base, $bad));
        ok($c === null, '通ってしまった: ' . json_encode($bad, JSON_UNESCAPED_UNICODE));
    }
});
t('ホテル設定の検証（ズーム・カテゴリ・中心）', function () {
    $ok = ['name' => 'H', 'center_lat' => '35', 'center_lng' => '135', 'zoom' => '14', 'categories' => ['shop']];
    [$c] = Validator::hotelSettings($ok);
    ok($c !== null);
    foreach ([['zoom' => '0'], ['zoom' => '21'], ['zoom' => 'x'], ['categories' => []], ['categories' => ['spa']], ['center_lat' => '100'], ['name' => '']] as $bad) {
        [$c] = Validator::hotelSettings(array_merge($ok, $bad));
        ok($c === null, '通ってしまった: ' . json_encode($bad, JSON_UNESCAPED_UNICODE));
    }
});

echo "\n[単体] DB（SQL の特殊文字・カテゴリ表示）\n";
t('SQL の特殊文字を含む名前が、そのまま保存・取得でき、テーブルは壊れない', function () use ($kyoto) {
    $evil = "Robert'); DROP TABLE facilities;-- \" OR 1=1 \\";
    [$c] = Validator::facility(['name' => $evil, 'category' => 'shop', 'lat' => '35', 'lng' => '135', 'address' => "a'b", 'description' => '']);
    $id = Repo::createFacility($kyoto, $c);
    eq(Repo::facility($id)['name'], $evil);
    eq(sql_count("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'facilities'"), 1);
    Repo::deleteFacility($id);
    eq(Repo::facility($id), null);
});
t('ホテルのスラッグに SQL を入れても、全件は返らない', function () {
    eq(Repo::hotelBySlug("kyoto' OR '1'='1"), null);
    eq(Repo::hotelBySlug('kyoto') !== null, true);
});
t('地図に出すのは、そのホテルで有効なカテゴリの施設だけ（札幌は「交通」が無効）', function () {
    $sap = hotel_id('sapporo');
    $before = count(Repo::visibleFacilities($sap));
    [$c] = Validator::facility(['name' => '札幌の交通施設', 'category' => 'transport', 'lat' => '43.06', 'lng' => '141.35', 'address' => '', 'description' => '']);
    $id = Repo::createFacility($sap, $c);
    eq(count(Repo::visibleFacilities($sap)), $before, '無効カテゴリの施設が表示対象に入った');
    eq(sql_count('SELECT COUNT(*) FROM facilities WHERE hotel_id = ?', [$sap]) - $before, 1);
    Repo::deleteFacility($id);
});
t('パスワードは平文でなくハッシュで保存され、照合できる', function () {
    $row = Repo::adminByUsername(env('ADMIN_USERNAME', 'admin'));
    ok($row !== null);
    ok($row['password_hash'] !== env('ADMIN_PASSWORD'), '平文で保存されている');
    ok(str_starts_with($row['password_hash'], '$2y$') || str_starts_with($row['password_hash'], '$argon2'), 'ハッシュの形式が違う');
    ok(password_verify(env('ADMIN_PASSWORD'), $row['password_hash']));
    ok(!password_verify('wrong', $row['password_hash']));
});

echo "\n[単体] 取り込み（冪等・不正な行のスキップ）\n";
$baseline = sql_count('SELECT COUNT(*) FROM facilities');
$logFile = sys_get_temp_dir() . '/import_skipped_test.log';
@unlink($logFile);
$run1 = null;
t('1回目: 有効な10件を追加し、不正な7件はスキップする', function () use ($mock, $logFile, $baseline, &$run1) {
    $run1 = Importer::run($mock, $logFile);
    eq($run1['inserted'], 10, 'inserted');
    eq($run1['skipped'], 7, 'skipped');
    eq($run1['updated'], 0, 'updated');
    eq(sql_count('SELECT COUNT(*) FROM facilities'), $baseline + 10);
});
t('スキップの理由が DB とログファイルに残る', function () use ($logFile, &$run1) {
    eq(sql_count('SELECT COUNT(*) FROM import_skipped WHERE run_id = ?', [$run1['run_id']]), 7);
    $log = (string)file_get_contents($logFile);
    ok(substr_count($log, 'skip:') === 7, 'ログの行数');
    ok(str_contains($log, '存在しないホテル') && str_contains($log, 'external_id') && str_contains($log, '重複'), '理由の文言がログに無い');
});
t('2回目（同じデータ）: 件数が増えない（冪等）', function () use ($mock, $baseline) {
    $before = sql_count('SELECT COUNT(*) FROM facilities');
    $r = Importer::run($mock, null);
    eq($r['inserted'], 0, 'inserted');
    eq($r['updated'], 0, 'updated');
    eq($r['unchanged'], 10, 'unchanged');
    eq(sql_count('SELECT COUNT(*) FROM facilities'), $before, '件数が変わった');
    eq($before, $baseline + 10);
});
t('内容が変わった行だけ更新される（追加はされない）', function () use ($mock) {
    $doc = json_decode((string)file_get_contents($mock), true);
    $doc['items'][0]['name'] = '朝焼けベーカリー（名前を変更・架空）';
    $tmp = sys_get_temp_dir() . '/mock_changed.json';
    file_put_contents($tmp, json_encode($doc, JSON_UNESCAPED_UNICODE));
    $before = sql_count('SELECT COUNT(*) FROM facilities');
    $r = Importer::run($tmp, null);
    eq([$r['inserted'], $r['updated'], $r['unchanged']], [0, 1, 9]);
    eq(sql_count('SELECT COUNT(*) FROM facilities'), $before);
    eq(sql_count("SELECT COUNT(*) FROM facilities WHERE external_id = 'MK-001' AND name LIKE '%名前を変更%'"), 1);
});
t('壊れたファイルは、何も変えずにエラーにする', function () {
    $before = [sql_count('SELECT COUNT(*) FROM facilities'), sql_count('SELECT COUNT(*) FROM import_runs')];
    $tmp = sys_get_temp_dir() . '/mock_broken.json';
    file_put_contents($tmp, '{"source": "x", "items": ');
    $threw = false;
    try {
        Importer::run($tmp, null);
    } catch (RuntimeException $e) {
        $threw = true;
    }
    ok($threw, '例外にならなかった');
    eq([sql_count('SELECT COUNT(*) FROM facilities'), sql_count('SELECT COUNT(*) FROM import_runs')], $before);
});

echo "\n[HTTP] 認証・CSRF・SQL・XSS（実際にリクエストを送る）\n";
$count = static fn(): int => sql_count('SELECT COUNT(*) FROM facilities');

t('未ログインで管理画面を開くと、ログイン画面へ飛ばされる', function () {
    foreach (['/admin/', '/admin/hotel.php?id=1', '/admin/facility.php?hotel_id=1'] as $p) {
        $r = http('GET', $p);
        eq($r['code'], 302, $p);
        ok(str_contains($r['headers'], '/admin/login.php'), "$p の Location");
    }
});
t('未ログインの POST（削除・追加）は、何も変えない', function () use ($count) {
    $before = $count();
    $fid = (int)Db::pdo()->query('SELECT MIN(id) FROM facilities')->fetchColumn();
    $r1 = http('POST', '/admin/hotel.php?id=1', ['action' => 'delete_facility', 'facility_id' => (string)$fid]);
    $r2 = http('POST', '/admin/facility.php?hotel_id=1', ['name' => '侵入', 'category' => 'shop', 'lat' => '35', 'lng' => '135']);
    ok(in_array($r1['code'], [302, 403], true), '削除: HTTP ' . $r1['code']);
    ok(in_array($r2['code'], [302, 403], true), '追加: HTTP ' . $r2['code']);
    eq($count(), $before, '件数が変わった');
    ok(Repo::facility($fid) !== null, '施設が消えた');
});
t('ログイン: CSRF なし・間違ったトークンは 403、間違ったパスワードは拒否', function () {
    $jar = new_jar();
    $g = http('GET', '/admin/login.php', null, $jar);
    eq(http('POST', '/admin/login.php', ['username' => 'admin', 'password' => env('ADMIN_PASSWORD')], $jar)['code'], 403, 'トークンなし');
    eq(http('POST', '/admin/login.php', ['csrf' => 'deadbeef', 'username' => 'admin', 'password' => env('ADMIN_PASSWORD')], $jar)['code'], 403, '違うトークン');
    $bad = http('POST', '/admin/login.php', ['csrf' => csrf_of($g['body']), 'username' => 'admin', 'password' => 'wrong-password'], $jar);
    eq($bad['code'], 200, '間違ったパスワード');
    ok(str_contains($bad['body'], '正しくありません'));
    eq(http('GET', '/admin/', null, $jar)['code'], 302, 'まだ未ログイン');
});
t('ログイン成功でセッションIDが変わる（固定化攻撃への対策）／クッキーは HttpOnly・SameSite', function () {
    $jar = new_jar();
    $g = http('GET', '/admin/login.php', null, $jar);
    $before = session_cookie($jar);
    ok($before !== '', '事前のセッションクッキーが無い');
    ok(str_contains($g['headers'], 'HttpOnly') && str_contains($g['headers'], 'SameSite=Lax'), 'Set-Cookie の属性: ' . $g['headers']);
    $r = http('POST', '/admin/login.php', ['csrf' => csrf_of($g['body']), 'username' => env('ADMIN_USERNAME', 'admin'), 'password' => env('ADMIN_PASSWORD')], $jar);
    eq($r['code'], 302);
    $after = session_cookie($jar);
    ok($after !== '' && $after !== $before, 'セッションIDが変わっていない');
    $adm = http('GET', '/admin/', null, $jar);
    eq($adm['code'], 200);
    ok(str_contains($adm['headers'], 'X-Frame-Options: DENY') && stripos($adm['headers'], 'no-store') !== false, 'セキュリティヘッダー');
});
t('施設の追加: CSRF なし・違うトークンは 403で件数不変、正しいトークンで追加される', function () use ($count, $kyoto) {
    $jar = logged_in_jar();
    $csrf = page_csrf($jar, "/admin/facility.php?hotel_id=$kyoto");
    $data = ['name' => 'CSRFテスト施設', 'category' => 'shop', 'lat' => '35.0', 'lng' => '135.0', 'address' => '', 'description' => ''];
    $before = $count();
    eq(http('POST', "/admin/facility.php?hotel_id=$kyoto", $data, $jar)['code'], 403, 'トークンなし');
    eq(http('POST', "/admin/facility.php?hotel_id=$kyoto", $data + ['csrf' => 'deadbeef'], $jar)['code'], 403, '違うトークン');
    eq($count(), $before, '拒否されたのに件数が変わった');
    eq(http('POST', "/admin/facility.php?hotel_id=$kyoto", $data + ['csrf' => $csrf], $jar)['code'], 302, '正しいトークン');
    eq($count(), $before + 1);
});
t('管理画面の入力に SQL の特殊文字を入れても壊れず、API がそのまま返す', function () use ($kyoto) {
    $jar = logged_in_jar();
    $csrf = page_csrf($jar, "/admin/facility.php?hotel_id=$kyoto");
    $evil = "x'); DROP TABLE facilities;-- ";
    $r = http('POST', "/admin/facility.php?hotel_id=$kyoto", ['csrf' => $csrf, 'name' => $evil, 'category' => 'shop', 'lat' => '35', 'lng' => '135', 'address' => "a' OR '1'='1", 'description' => ''], $jar);
    eq($r['code'], 302);
    eq(sql_count("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'facilities'"), 1, 'テーブルが消えた');
    $api = json_decode(http('GET', '/api/facilities.php?hotel=kyoto')['body'], true);
    $names = array_column($api['facilities'], 'name');
    ok(in_array(trim($evil), $names, true), 'API が名前をそのまま返していない（保存前に前後の空白だけが除かれる）');
});
t('XSS: 管理画面は施設名の HTML をエスケープして出す', function () use ($kyoto) {
    $jar = logged_in_jar();
    $csrf = page_csrf($jar, "/admin/facility.php?hotel_id=$kyoto");
    $xss = '<script>alert(1)</script><img src=x onerror=alert(2)>';
    eq(http('POST', "/admin/facility.php?hotel_id=$kyoto", ['csrf' => $csrf, 'name' => $xss, 'category' => 'shop', 'lat' => '35', 'lng' => '135', 'address' => '', 'description' => ''], $jar)['code'], 302);
    $page = http('GET', "/admin/hotel.php?id=$kyoto", null, $jar)['body'];
    ok(!str_contains($page, '<script>alert(1)'), 'スクリプトがそのまま出力された');
    ok(!str_contains($page, '<img src=x'), 'img タグがそのまま出力された');
    ok(str_contains($page, '&lt;script&gt;alert(1)'), 'エスケープされた文字列が見つからない');
});
t('不正な入力（緯度999）は 200 でエラーを表示し、DB は変わらない', function () use ($count, $kyoto) {
    $jar = logged_in_jar();
    $csrf = page_csrf($jar, "/admin/facility.php?hotel_id=$kyoto");
    $before = $count();
    $r = http('POST', "/admin/facility.php?hotel_id=$kyoto", ['csrf' => $csrf, 'name' => '緯度が不正', 'category' => 'shop', 'lat' => '999', 'lng' => '135'], $jar);
    eq($r['code'], 200);
    ok(str_contains($r['body'], '緯度は'), 'エラーメッセージが無い');
    eq($count(), $before);
});
t('施設の削除: POST＋CSRF のときだけ。GET では消えない', function () use ($count, $kyoto) {
    $jar = logged_in_jar();
    $fid = (int)Db::pdo()->query("SELECT MAX(id) FROM facilities WHERE hotel_id = $kyoto")->fetchColumn();
    $csrf = page_csrf($jar, "/admin/hotel.php?id=$kyoto");
    $before = $count();
    http('GET', "/admin/hotel.php?id=$kyoto&action=delete_facility&facility_id=$fid", null, $jar);
    eq($count(), $before, 'GET で消えた');
    eq(http('POST', "/admin/hotel.php?id=$kyoto", ['action' => 'delete_facility', 'facility_id' => (string)$fid], $jar)['code'], 403, 'トークンなし');
    eq($count(), $before, 'トークンなしで消えた');
    eq(http('POST', "/admin/hotel.php?id=$kyoto", ['csrf' => $csrf, 'action' => 'delete_facility', 'facility_id' => (string)$fid], $jar)['code'], 302);
    eq($count(), $before - 1);
});
t('ホテル設定: 正しい値は反映され、不正な値は拒否される（地図の中心・ズーム・カテゴリ）', function () {
    $sap = hotel_id('sapporo');
    $jar = logged_in_jar();
    $csrf = page_csrf($jar, "/admin/hotel.php?id=$sap");
    $good = ['csrf' => $csrf, 'action' => 'update_settings', 'name' => 'サンプルホテル札幌（架空）', 'center_lat' => '43.07', 'center_lng' => '141.35', 'zoom' => '13', 'categories' => ['restaurant', 'sight', 'transport']];
    eq(http('POST', "/admin/hotel.php?id=$sap", $good, $jar)['code'], 302);
    $api = json_decode(http('GET', '/api/facilities.php?hotel=sapporo')['body'], true);
    eq($api['hotel']['zoom'], 13);
    eq(array_column($api['hotel']['categories'], 'key'), ['restaurant', 'sight', 'transport']);
    $bad = http('POST', "/admin/hotel.php?id=$sap", array_merge($good, ['zoom' => '99', 'categories' => []]), $jar);
    eq($bad['code'], 200);
    $api2 = json_decode(http('GET', '/api/facilities.php?hotel=sapporo')['body'], true);
    eq($api2['hotel']['zoom'], 13, '不正な値で設定が変わった');
});
t('ログアウトは POST＋CSRF のときだけ。その後は管理画面に入れない', function () {
    $jar = logged_in_jar();
    eq(http('POST', '/admin/logout.php', [], $jar)['code'], 403);
    eq(http('GET', '/admin/', null, $jar)['code'], 200, 'CSRFなしのログアウトで抜けてしまった');
    $csrf = page_csrf($jar, '/admin/');
    eq(http('POST', '/admin/logout.php', ['csrf' => $csrf], $jar)['code'], 302);
    eq(http('GET', '/admin/', null, $jar)['code'], 302);
});

echo "\n[HTTP] 地図の API と画面\n";
t('API の件数 = 画面に出す施設の数（DB で数えた表示対象の件数）。3ホテルすべて', function () {
    foreach (['kyoto', 'sapporo', 'naha'] as $slug) {
        $r = http('GET', '/api/facilities.php?hotel=' . $slug);
        eq($r['code'], 200);
        $d = json_decode($r['body'], true);
        eq($d['count'], count($d['facilities']), "$slug: count と配列の長さ");
        $sql = sql_count('SELECT COUNT(*) FROM facilities f JOIN hotel_categories hc ON hc.hotel_id = f.hotel_id AND hc.category = f.category JOIN hotels h ON h.id = f.hotel_id WHERE h.slug = ?', [$slug]);
        eq($d['count'], $sql, "$slug: DB の件数");
        ok($d['count'] > 0);
    }
});
t('カテゴリで絞り込める。無効なカテゴリ・空の選択は0件', function () {
    $all = json_decode(http('GET', '/api/facilities.php?hotel=kyoto')['body'], true);
    $shop = json_decode(http('GET', '/api/facilities.php?hotel=kyoto&category[]=shop')['body'], true);
    ok($shop['count'] > 0 && $shop['count'] < $all['count']);
    foreach ($shop['facilities'] as $f) {
        eq($f['category'], 'shop');
    }
    eq(json_decode(http('GET', '/api/facilities.php?hotel=kyoto&category[]=')['body'], true)['count'], 0, '空の選択');
    eq(json_decode(http('GET', '/api/facilities.php?hotel=kyoto&category[]=spa')['body'], true)['count'], 0, '未知のカテゴリ');
});
t('API の異常系: 存在しないホテルは404、不正なスラッグは400（SQL 文字を含む）、POST は405', function () {
    eq(http('GET', '/api/facilities.php?hotel=nowhere')['code'], 404);
    eq(http('GET', '/api/facilities.php?hotel=' . rawurlencode("kyoto' OR '1'='1"))['code'], 400);
    eq(http('GET', '/api/facilities.php')['code'], 400);
    eq(http('POST', '/api/facilities.php?hotel=kyoto', [])['code'], 405);
});
t('地図ページ: ホテル名と Leaflet を含み、キーが未設定なら Google のキーを出さない', function () {
    $r = http('GET', '/');
    eq($r['code'], 200);
    ok(str_contains($r['body'], 'サンプルホテル京都') && str_contains($r['body'], 'leaflet.js'));
    ok(!str_contains($r['body'], 'gmaps-key'), 'キー未設定なのに meta が出ている');
    ok(!str_contains($r['body'], 'password'), '管理者情報が含まれている');
});

t('管理画面: 取り込み履歴の日時は日本時間で表示（DB は UTC）', function () {
    eq(jst('2026-01-01 00:00:00'), '2026-01-01 09:00:00');
    eq(jst('2026-12-31 15:30:00'), '2027-01-01 00:30:00');
    Db::pdo()->exec("INSERT INTO import_runs (started_at, source, file, inserted, updated, unchanged, skipped, status) VALUES ('2031-05-06 01:02:03', 'tz-test', 'x.json', 0, 0, 0, 0, 'ok')");
    $body = http('GET', '/admin/', null, logged_in_jar())['body'];
    ok(str_contains($body, '日時（日本時間）'), '見出しに「日本時間」が無い');
    ok(str_contains($body, '2031-05-06 10:02:03'), 'UTC の 01:02:03 が日本時間 10:02:03 で出ていない');
    ok(!str_contains($body, '2031-05-06 01:02:03'), 'UTC のまま出ている');
});
t('管理画面: 「地図に表示」の列は「表示／非表示」の文言（札幌は交通が非表示）', function () {
    Db::pdo()->prepare("DELETE FROM hotel_categories WHERE hotel_id = ? AND category = 'transport'")->execute([hotel_id('sapporo')]);
    $body = http('GET', '/admin/hotel.php?id=' . hotel_id('sapporo'), null, logged_in_jar())['body'];
    ok(str_contains($body, '非表示（このホテルでは表示しないカテゴリ）'), '非表示の文言が無い');
    ok(str_contains($body, '<td>表示</td>'), '表示の文言が無い');
    ok(!str_contains($body, '○'), '空の丸（○）が残っている');
});

echo "\n結果: $pass 件合格 / $fail 件失敗\n";
exit($fail === 0 ? 0 : 1);
