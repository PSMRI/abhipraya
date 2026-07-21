<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/public_api.php';
Security::requireMethod('GET');

try {
    $left = random_int(2, 9);
    $right = random_int(1, 9);
    $_SESSION['abhipraya_login_captcha'] = (string) ($left + $right);
    $_SESSION['abhipraya_login_captcha_expires'] = time() + 300;
    Response::success('Captcha generated', [
        'question' => $left . ' + ' . $right . ' = ?',
        'expires_in' => 300,
    ]);
} catch (Throwable $exception) {
    Response::serverError($exception->getMessage());
}
