<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/helpers/BoundaryCatalog.php';

Security::requireMethod('GET');
try {
    $boundary = BoundaryCatalog::find((string) ($_GET['code'] ?? ''));
    if ($boundary === null) {
        Response::notFound('Boundary record was not found.');
    }
    $children = BoundaryCatalog::search('', (string) $boundary['code']);
    Response::success('Boundary record loaded', [
        'boundary' => $boundary,
        'children' => $children,
    ]);
} catch (JsonException|RuntimeException $exception) {
    ErrorHandler::log($exception, ['endpoint' => 'boundaries.get']);
    Response::serverError();
}
