<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/helpers/BoundaryCatalog.php';

Security::requireMethod('GET');
try {
    $level = trim((string) ($_GET['level'] ?? ''));
    if ($level !== '' && !in_array($level, ['state', 'district', 'block', 'facility'], true)) {
        Response::validation(['level' => 'Choose state, district, block, or facility.']);
    }
    $limit = max(1, min(500, (int) ($_GET['limit'] ?? 100)));
    $items = BoundaryCatalog::search($level, (string) ($_GET['parent'] ?? ''), (string) ($_GET['search'] ?? ''));
    Response::success('Boundary records loaded', [
        'boundaries' => array_slice($items, 0, $limit),
        'total' => count($items),
        'limit' => $limit,
    ]);
} catch (JsonException|RuntimeException $exception) {
    ErrorHandler::log($exception, ['endpoint' => 'boundaries.list']);
    Response::serverError();
}
