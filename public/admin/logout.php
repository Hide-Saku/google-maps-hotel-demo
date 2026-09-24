<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}
Csrf::requireValid();
Auth::logout();
redirect('/admin/login.php');
