<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';

// GET /api/facilities.php?hotel=<slug>[&category[]=restaurant&category[]=sight]
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    json_out(405, ['error' => 'method_not_allowed']);
}
$slug = is_string($_GET['hotel'] ?? null) ? $_GET['hotel'] : '';
if (!preg_match('/^[a-z0-9-]{1,50}$/', $slug)) {
    json_out(400, ['error' => 'invalid_hotel']);
}
$hotel = Repo::hotelBySlug($slug);
if ($hotel === null) {
    json_out(404, ['error' => 'hotel_not_found']);
}
$enabled = Repo::enabledCategories((int)$hotel['id']);

$filter = null;
if (isset($_GET['category'])) {
    $req = is_array($_GET['category']) ? $_GET['category'] : [$_GET['category']];
    $filter = [];
    foreach ($req as $c) {
        if (is_string($c) && isset(Validator::CATEGORIES[$c]) && in_array($c, $enabled, true)) {
            $filter[] = $c;
        }
    }
    $filter = array_values(array_unique($filter));
}

$rows = Repo::visibleFacilities((int)$hotel['id'], $filter);
$facilities = array_map(static fn(array $r): array => [
    'id' => (int)$r['id'],
    'name' => $r['name'],
    'category' => $r['category'],
    'category_label' => Validator::CATEGORIES[$r['category']] ?? $r['category'],
    'lat' => (float)$r['lat'],
    'lng' => (float)$r['lng'],
    'address' => $r['address'],
    'description' => $r['description'],
    'updated_at' => $r['updated_at'],
], $rows);

json_out(200, [
    'hotel' => [
        'slug' => $hotel['slug'],
        'name' => $hotel['name'],
        'center' => ['lat' => (float)$hotel['center_lat'], 'lng' => (float)$hotel['center_lng']],
        'zoom' => (int)$hotel['zoom'],
        'categories' => array_map(static fn(string $c): array => ['key' => $c, 'label' => Validator::CATEGORIES[$c]], $enabled),
    ],
    'count' => count($facilities),
    'facilities' => $facilities,
]);
