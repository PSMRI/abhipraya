<?php
declare(strict_types=1);
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
    if (class_exists('chillerlan\\QRCode\\QRCode')) {
        $data = trim((string) ($_GET['data'] ?? ''));
        if ($data === '' || strlen($data) > 1000) { http_response_code(400); exit; }
        $options = new \chillerlan\QRCode\QROptions(['outputInterface' => \chillerlan\QRCode\Output\QRGdImagePNG::class, 'eccLevel' => 'M', 'scale' => 8]);
        header('Content-Type: image/png');
        header('Cache-Control: private, no-store');
        $png = (new \chillerlan\QRCode\QRCode($options))->render($data);
        if (is_string($png) && str_starts_with($png, 'data:image/png;base64,')) $png = base64_decode(substr($png, 22), true);
        if (!is_string($png) || $png === false) { http_response_code(500); exit; }
        echo $png;
        exit;
    }
}
$data = trim((string) ($_GET['data'] ?? ''));
if ($data === '' || strlen($data) > 1000) { http_response_code(400); exit; }
$urls = [
    'https://quickchart.io/qr?size=700&text=' . rawurlencode($data),
    'https://api.qrserver.com/v1/create-qr-code/?size=700x700&data=' . rawurlencode($data),
];
$context = stream_context_create(['http' => ['timeout' => 4], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
$image = false;
foreach ($urls as $url) {
    $image = false;
    if ($image !== false && $image !== '') break;
    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_TIMEOUT => 5, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_USERAGENT => 'Abhipraya QR service']);
        $candidate = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($status >= 200 && $status < 300 && is_string($candidate) && $candidate !== '') { $image = $candidate; break; }
    }
}
if ($image === false || $image === '') { http_response_code(502); exit; }
header('Content-Type: image/png'); header('Cache-Control: private, no-store'); echo $image;
