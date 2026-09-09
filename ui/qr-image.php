<?php
declare(strict_types=1);

$data = trim((string) ($_GET['data'] ?? ''));
if ($data === '' || strlen($data) > 1000) { http_response_code(400); exit; }

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
    if (class_exists('chillerlan\\QRCode\\QRCode')) {
        try {
            /* SVG is generated entirely in PHP. Unlike QRGdImagePNG it does
             * not require the optional GD extension, which is unavailable on
             * some production IIS/PHP installations. */
            $options = new \chillerlan\QRCode\QROptions([
                'outputInterface' => \chillerlan\QRCode\Output\QRMarkupSVG::class,
                'outputBase64' => false,
                'eccLevel' => 'M',
            ]);
            $svg = (new \chillerlan\QRCode\QRCode($options))->render($data);
            $svgStart = ltrim((string) $svg);
            if (
                is_string($svg)
                && (str_starts_with($svgStart, '<svg') || str_starts_with($svgStart, '<?xml'))
            ) {
                header('Content-Type: image/svg+xml; charset=utf-8');
                header('Cache-Control: private, no-store');
                echo $svg;
                exit;
            }
        } catch (Throwable) {
            // Continue to the legacy fallback when Composer is incomplete.
        }
    }
}
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
