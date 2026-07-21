<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/public_api.php';
require_once dirname(__DIR__, 3) . '/helpers/SurveyConfig.php';
Security::requireMethod('GET');
try {
    $context = SurveyConfig::resolveReference((string) ($_GET['ref'] ?? ''));
    $language = (int) ($_GET['lang'] ?? 1);
    if (!in_array($language, [1, 2], true)) {
        Response::validation(['lang' => 'Choose a supported language.']);
    }
    Response::success('Survey questions loaded', [
        'reference' => $context['reference'],
        'language' => $language,
        'buttons' => SurveyConfig::buttonLabels($language),
        'questions' => SurveyConfig::questions($context, $language),
    ]);
} catch (InvalidArgumentException $exception) {
    Response::error($exception->getMessage(), null, 422);
} catch (Throwable $exception) {
    Response::serverError($exception->getMessage());
}
