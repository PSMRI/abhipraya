<?php
declare(strict_types=1);
$data = trim((string) ($_GET['data'] ?? ''));
if ($data === '' || strlen($data) > 1000) { http_response_code(400); exit; }
$url = 'https://api.qrserver.com/v1/create-qr-code/?size=700x700&data=' . rawurlencode($data);
$context = stream_context_create(['http' => ['timeout' => 8], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
$image = @file_get_contents($url, false, $context);
if ($image === false || $image === '') { http_response_code(502); exit; }
header('Content-Type: image/png'); header('Cache-Control: private, no-store'); echo $image;
