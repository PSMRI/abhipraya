<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/public_api.php';
require_once dirname(__DIR__, 3) . '/helpers/SurveyConfig.php';

Security::requireMethod('POST');

try {
    $payload = Security::jsonInput();
    Security::requireFields($payload, ['ref', 'latitude', 'longitude']);

    $context = SurveyConfig::resolveReference((string) $payload['ref']);
    $latitude = filter_var($payload['latitude'], FILTER_VALIDATE_FLOAT);
    $longitude = filter_var($payload['longitude'], FILTER_VALIDATE_FLOAT);

    if ($latitude === false || $longitude === false
        || $latitude < -90 || $latitude > 90
        || $longitude < -180 || $longitude > 180) {
        Response::validation(['location' => 'Valid latitude and longitude are required.']);
    }

    $distance = SurveyConfig::distanceMeters(
        (float) $context['facility']['facilityLat'],
        (float) $context['facility']['facilityLong'],
        (float) $latitude,
        (float) $longitude
    );
    $radius = (int) $context['geo_radius_meters'];
    $withinRadius = $distance <= $radius;

    Response::success('Location checked', [
        'within_radius' => $withinRadius,
        'distance_meters' => (int) round($distance),
        'allowed_radius_meters' => $radius,
    ]);
} catch (InvalidArgumentException $exception) {
    Response::error($exception->getMessage(), null, 422);
} catch (Throwable $exception) {
    Response::serverError($exception->getMessage());
}
