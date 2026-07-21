<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Authentication;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Validator;
use App\Repositories\AuthRepository;
use App\Repositories\SurveyRepository;
use PDO;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Survey business service.
 *
 * Responsibilities:
 * - Survey CRUD
 * - Survey version management
 * - JSON definition validation
 * - Publish/archive workflows
 * - Preview and questions
 * - Import/export
 * - Audit logging
 *
 * HTTP responses must remain inside endpoint files.
 */
final class SurveyService
{
    private SurveyRepository $surveyRepository;

    private AuthRepository $authRepository;

    public function __construct(
        ?SurveyRepository $surveyRepository = null,
        ?AuthRepository $authRepository = null
    ) {
        $this->surveyRepository = $surveyRepository
            ?? new SurveyRepository(Database::connection());

        $this->authRepository = $authRepository
            ?? new AuthRepository(Database::connection());
    }

    public function listSurveys(array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $limit = max(1, min(100, (int) ($query['limit'] ?? 20)));

        $filters = [
            'search' => trim((string) ($query['search'] ?? '')),
            'status' => $query['status'] ?? null,
            'survey_code' => $query['survey_code'] ?? null,
            'created_by' => $query['created_by'] ?? null,
        ];

        return $this->surveyRepository->paginate(
            $filters,
            $page,
            $limit
        );
    }

    public function getSurvey(int $surveyId): array
    {
        $this->assertPositiveId($surveyId, 'survey_id');

        $survey = $this->surveyRepository->findById($surveyId);

        if ($survey === null) {
            throw new SurveyNotFoundException();
        }

        return [
            'survey' => $survey,
            'versions' => $this->surveyRepository->getVersions($surveyId),
            'assignments' => $this->surveyRepository->getAssignments($surveyId),
        ];
    }

    public function createSurvey(
        array $payload,
        Request $request
    ): array {
        $this->validateCreatePayload($payload);

        $surveyCode = strtolower(
            trim((string) $payload['survey_code'])
        );

        if ($this->surveyRepository->codeExists($surveyCode)) {
            throw new SurveyValidationException(
                'Validation failed.',
                [
                    'survey_code' => [
                        'Survey code already exists.',
                    ],
                ]
            );
        }

        $actor = Authentication::requireUser();
        $actorUserId = (int) $actor['user_id'];

        $definition = $payload['definition'] ?? [
            'survey_code' => $surveyCode,
            'title' => (string) $payload['survey_name'],
            'version' => '1.0',
            'languages' => ['en'],
            'questions' => [],
        ];

        $validation = $this->validateDefinitionArray($definition);

        if (!$validation['valid']) {
            throw new SurveyValidationException(
                'Survey definition is invalid.',
                $validation['errors']
            );
        }

        $surveyId = Database::transaction(
            function (PDO $pdo) use (
                $payload,
                $surveyCode,
                $definition,
                $actorUserId,
                $request
            ): int {
                $surveyId = $this->surveyRepository->create([
                    'survey_code' => $surveyCode,
                    'survey_name' => trim((string) $payload['survey_name']),
                    'description' => $this->nullableString(
                        $payload['description'] ?? null
                    ),
                    'status' => 'DRAFT',
                    'created_by' => $actorUserId,
                ]);

                $versionId = $this->surveyRepository->createVersion([
                    'survey_id' => $surveyId,
                    'version_no' => (string) ($payload['version_no'] ?? '1.0'),
                    'status' => 'DRAFT',
                    'definition_json' => json_encode(
                        $definition,
                        JSON_UNESCAPED_UNICODE
                        | JSON_UNESCAPED_SLASHES
                        | JSON_THROW_ON_ERROR
                    ),
                    'created_by' => $actorUserId,
                ]);

                $this->authRepository->writeAudit(
                    $actorUserId,
                    'SURVEY_CREATED',
                    'SURVEY',
                    (string) $surveyId,
                    null,
                    [
                        'survey_code' => $surveyCode,
                        'survey_name' => $payload['survey_name'],
                        'survey_version_id' => $versionId,
                    ],
                    $request->ip(),
                    $request->requestId()
                );

                return $surveyId;
            }
        );

        return $this->getSurvey($surveyId);
    }

    public function updateSurvey(
        int $surveyId,
        array $payload,
        Request $request
    ): array {
        $this->assertPositiveId($surveyId, 'survey_id');

        $existing = $this->surveyRepository->findById($surveyId);

        if ($existing === null) {
            throw new SurveyNotFoundException();
        }

        $this->validateUpdatePayload($payload);

        if (
            isset($payload['survey_code'])
            && strtolower(trim((string) $payload['survey_code']))
                !== (string) $existing['survey_code']
        ) {
            throw new SurveyValidationException(
                'Validation failed.',
                [
                    'survey_code' => [
                        'Survey code cannot be changed.',
                    ],
                ]
            );
        }

        if (
            strtoupper((string) $existing['status']) === 'ARCHIVED'
        ) {
            throw new SurveyValidationException(
                'Archived survey cannot be updated.'
            );
        }

        $newData = [
            'survey_name' => $payload['survey_name']
                ?? $existing['survey_name'],
            'description' => array_key_exists('description', $payload)
                ? $this->nullableString($payload['description'])
                : $existing['description'],
        ];

        $actor = Authentication::requireUser();
        $actorUserId = (int) $actor['user_id'];

        Database::transaction(
            function (PDO $pdo) use (
                $surveyId,
                $existing,
                $newData,
                $actorUserId,
                $request
            ): void {
                $this->surveyRepository->update(
                    $surveyId,
                    $newData
                );

                $this->authRepository->writeAudit(
                    $actorUserId,
                    'SURVEY_UPDATED',
                    'SURVEY',
                    (string) $surveyId,
                    $existing,
                    $newData,
                    $request->ip(),
                    $request->requestId()
                );
            }
        );

        return $this->getSurvey($surveyId);
    }

    public function deleteSurvey(
        int $surveyId,
        Request $request
    ): array {
        $this->assertPositiveId($surveyId, 'survey_id');

        $survey = $this->surveyRepository->findById($surveyId);

        if ($survey === null) {
            throw new SurveyNotFoundException();
        }

        if ($this->surveyRepository->countResponses($surveyId) > 0) {
            throw new SurveyValidationException(
                'Survey cannot be deleted.',
                [
                    'survey_id' => [
                        'Survey has responses. Archive it instead.',
                    ],
                ]
            );
        }

        if ($this->surveyRepository->countAssignments($surveyId) > 0) {
            throw new SurveyValidationException(
                'Survey cannot be deleted.',
                [
                    'survey_id' => [
                        'Remove department assignments first.',
                    ],
                ]
            );
        }

        $actor = Authentication::requireUser();
        $actorUserId = (int) $actor['user_id'];

        Database::transaction(
            function (PDO $pdo) use (
                $surveyId,
                $survey,
                $actorUserId,
                $request
            ): void {
                $this->surveyRepository->delete($surveyId);

                $this->authRepository->writeAudit(
                    $actorUserId,
                    'SURVEY_DELETED',
                    'SURVEY',
                    (string) $surveyId,
                    $survey,
                    null,
                    $request->ip(),
                    $request->requestId()
                );
            }
        );

        return [
            'survey_id' => $surveyId,
            'deleted' => true,
        ];
    }

    public function publishVersion(
        int $surveyVersionId,
        Request $request
    ): array {
        $this->assertPositiveId(
            $surveyVersionId,
            'survey_version_id'
        );

        $version = $this->surveyRepository
            ->findVersionById($surveyVersionId);

        if ($version === null) {
            throw new SurveyNotFoundException(
                'Survey version was not found.'
            );
        }

        if (strtoupper((string) $version['status']) === 'PUBLISHED') {
            return $version;
        }

        $definition = json_decode(
            (string) $version['definition_json'],
            true
        );

        if (!is_array($definition)) {
            throw new SurveyValidationException(
                'Survey definition JSON is invalid.'
            );
        }

        $validation = $this->validateDefinitionArray($definition);

        if (!$validation['valid']) {
            throw new SurveyValidationException(
                'Survey definition is invalid.',
                $validation['errors']
            );
        }

        $actor = Authentication::requireUser();
        $actorUserId = (int) $actor['user_id'];
        $surveyId = (int) $version['survey_id'];

        Database::transaction(
            function (PDO $pdo) use (
                $surveyVersionId,
                $surveyId,
                $actorUserId,
                $request
            ): void {
                $this->surveyRepository
                    ->unpublishOtherVersions(
                        $surveyId,
                        $surveyVersionId
                    );

                $this->surveyRepository
                    ->publishVersion($surveyVersionId);

                $this->surveyRepository
                    ->updateStatus($surveyId, 'PUBLISHED');

                $this->authRepository->writeAudit(
                    $actorUserId,
                    'SURVEY_VERSION_PUBLISHED',
                    'SURVEY_VERSION',
                    (string) $surveyVersionId,
                    null,
                    [
                        'survey_id' => $surveyId,
                        'status' => 'PUBLISHED',
                    ],
                    $request->ip(),
                    $request->requestId()
                );
            }
        );

        return $this->surveyRepository
            ->findVersionById($surveyVersionId)
            ?? [];
    }

    public function archiveSurvey(
        int $surveyId,
        Request $request
    ): array {
        $this->assertPositiveId($surveyId, 'survey_id');

        $survey = $this->surveyRepository->findById($surveyId);

        if ($survey === null) {
            throw new SurveyNotFoundException();
        }

        $actor = Authentication::requireUser();
        $actorUserId = (int) $actor['user_id'];

        Database::transaction(
            function (PDO $pdo) use (
                $surveyId,
                $survey,
                $actorUserId,
                $request
            ): void {
                $this->surveyRepository
                    ->updateStatus($surveyId, 'ARCHIVED');

                $this->surveyRepository
                    ->deactivateAssignments($surveyId);

                $this->authRepository->writeAudit(
                    $actorUserId,
                    'SURVEY_ARCHIVED',
                    'SURVEY',
                    (string) $surveyId,
                    $survey,
                    ['status' => 'ARCHIVED'],
                    $request->ip(),
                    $request->requestId()
                );
            }
        );

        return $this->getSurvey($surveyId);
    }

    public function duplicateSurvey(
        int $sourceSurveyId,
        array $payload,
        Request $request
    ): array {
        $source = $this->surveyRepository
            ->findById($sourceSurveyId);

        if ($source === null) {
            throw new SurveyNotFoundException(
                'Source survey was not found.'
            );
        }

        $this->validateCreatePayload($payload);

        $newCode = strtolower(
            trim((string) $payload['survey_code'])
        );

        if ($this->surveyRepository->codeExists($newCode)) {
            throw new SurveyValidationException(
                'Validation failed.',
                [
                    'survey_code' => [
                        'Survey code already exists.',
                    ],
                ]
            );
        }

        $latestVersion = $this->surveyRepository
            ->getLatestVersion($sourceSurveyId);

        if ($latestVersion === null) {
            throw new SurveyValidationException(
                'Source survey has no version to duplicate.'
            );
        }

        $definition = json_decode(
            (string) $latestVersion['definition_json'],
            true
        );

        if (!is_array($definition)) {
            $definition = [];
        }

        $definition['survey_code'] = $newCode;
        $definition['title'] = (string) $payload['survey_name'];
        $definition['version'] = '1.0';

        return $this->createSurvey(
            [
                'survey_code' => $newCode,
                'survey_name' => $payload['survey_name'],
                'description' => $payload['description']
                    ?? $source['description']
                    ?? null,
                'version_no' => '1.0',
                'definition' => $definition,
            ],
            $request
        );
    }

    public function getVersions(int $surveyId): array
    {
        if ($this->surveyRepository->findById($surveyId) === null) {
            throw new SurveyNotFoundException();
        }

        return [
            'survey_id' => $surveyId,
            'items' => $this->surveyRepository->getVersions($surveyId),
        ];
    }

    public function validateSurveyDefinition(
        array $definition,
        Request $request
    ): array {
        return $this->validateDefinitionArray($definition);
    }

    public function previewVersion(
        int $surveyVersionId,
        string $language = 'en'
    ): array {
        $version = $this->surveyRepository
            ->findVersionById($surveyVersionId);

        if ($version === null) {
            throw new SurveyNotFoundException(
                'Survey version was not found.'
            );
        }

        $definition = json_decode(
            (string) $version['definition_json'],
            true
        );

        if (!is_array($definition)) {
            throw new SurveyValidationException(
                'Survey definition JSON is invalid.'
            );
        }

        return [
            'survey_version_id' => $surveyVersionId,
            'language' => $language,
            'definition' => $definition,
        ];
    }

    public function getQuestions(
        int $surveyVersionId,
        array $query = []
    ): array {
        $version = $this->surveyRepository
            ->findVersionById($surveyVersionId);

        if ($version === null) {
            throw new SurveyNotFoundException(
                'Survey version was not found.'
            );
        }

        $definition = json_decode(
            (string) $version['definition_json'],
            true
        );

        if (!is_array($definition)) {
            throw new SurveyValidationException(
                'Survey definition JSON is invalid.'
            );
        }

        return [
            'survey_version_id' => $surveyVersionId,
            'items' => array_values(
                $definition['questions'] ?? []
            ),
        ];
    }

    public function uploadJson(
        string $temporaryPath,
        string $originalName,
        Request $request
    ): array {
        $this->assertReadableFile($temporaryPath);

        $raw = file_get_contents($temporaryPath);

        if ($raw === false) {
            throw new RuntimeException(
                'Unable to read uploaded JSON file.'
            );
        }

        $definition = json_decode($raw, true);

        if (!is_array($definition)) {
            throw new SurveyValidationException(
                'Uploaded file does not contain valid JSON.'
            );
        }

        $validation = $this->validateDefinitionArray($definition);

        if (!$validation['valid']) {
            throw new SurveyValidationException(
                'Survey definition is invalid.',
                $validation['errors']
            );
        }

        return $this->createSurvey(
            [
                'survey_code' => $definition['survey_code'],
                'survey_name' => $definition['title']
                    ?? $definition['survey_name']
                    ?? $definition['survey_code'],
                'description' => $definition['description'] ?? null,
                'version_no' => $definition['version'] ?? '1.0',
                'definition' => $definition,
            ],
            $request
        );
    }

    public function downloadJson(int $surveyVersionId): array
    {
        $version = $this->surveyRepository
            ->findVersionById($surveyVersionId);

        if ($version === null) {
            throw new SurveyNotFoundException(
                'Survey version was not found.'
            );
        }

        $directory = $this->ensureExportDirectory();
        $filename = sprintf(
            'survey-%d-version-%s.json',
            (int) $version['survey_id'],
            preg_replace(
                '/[^A-Za-z0-9._-]/',
                '-',
                (string) $version['version_no']
            )
        );

        $path = $directory . DIRECTORY_SEPARATOR . $filename;

        file_put_contents(
            $path,
            json_encode(
                json_decode(
                    (string) $version['definition_json'],
                    true
                ),
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            )
        );

        return [
            'path' => $path,
            'filename' => $filename,
        ];
    }

    public function exportSurvey(
        int $surveyId,
        string $format = 'zip'
    ): array {
        $survey = $this->surveyRepository->findById($surveyId);

        if ($survey === null) {
            throw new SurveyNotFoundException();
        }

        $directory = $this->ensureExportDirectory();

        if ($format === 'json') {
            $latestVersion = $this->surveyRepository
                ->getLatestVersion($surveyId);

            if ($latestVersion === null) {
                throw new SurveyValidationException(
                    'Survey has no version to export.'
                );
            }

            $download = $this->downloadJson(
                (int) $latestVersion['survey_version_id']
            );

            return [
                'path' => $download['path'],
                'filename' => $download['filename'],
                'content_type' => 'application/json',
            ];
        }

        $zipPath = $directory
            . DIRECTORY_SEPARATOR
            . 'survey-'
            . $surveyId
            . '.zip';

        $zip = new ZipArchive();

        if (
            $zip->open(
                $zipPath,
                ZipArchive::CREATE | ZipArchive::OVERWRITE
            ) !== true
        ) {
            throw new RuntimeException(
                'Unable to create survey export package.'
            );
        }

        $zip->addFromString(
            'manifest.json',
            json_encode(
                [
                    'survey' => $survey,
                    'exported_at' => date(DATE_ATOM),
                ],
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            )
        );

        foreach (
            $this->surveyRepository->getVersions($surveyId)
            as $version
        ) {
            $zip->addFromString(
                'versions/'
                . (string) $version['version_no']
                . '/survey.json',
                (string) $version['definition_json']
            );
        }

        $zip->close();

        return [
            'path' => $zipPath,
            'filename' => basename($zipPath),
            'content_type' => 'application/zip',
        ];
    }

    public function importSurveyPackage(
        string $temporaryPath,
        string $originalName,
        Request $request
    ): array {
        $this->assertReadableFile($temporaryPath);

        $extension = strtolower(
            pathinfo($originalName, PATHINFO_EXTENSION)
        );

        if ($extension === 'json') {
            return $this->uploadJson(
                $temporaryPath,
                $originalName,
                $request
            );
        }

        if ($extension !== 'zip') {
            throw new SurveyValidationException(
                'Only JSON and ZIP survey packages are supported.'
            );
        }

        $zip = new ZipArchive();

        if ($zip->open($temporaryPath) !== true) {
            throw new SurveyValidationException(
                'Unable to open survey package.'
            );
        }

        $manifestRaw = $zip->getFromName('manifest.json');

        if ($manifestRaw === false) {
            $zip->close();

            throw new SurveyValidationException(
                'Survey package manifest is missing.'
            );
        }

        $manifest = json_decode($manifestRaw, true);

        if (!is_array($manifest)) {
            $zip->close();

            throw new SurveyValidationException(
                'Survey package manifest is invalid.'
            );
        }

        $definitionRaw = null;

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entry = $zip->getNameIndex($index);

            if (
                is_string($entry)
                && str_ends_with($entry, '/survey.json')
            ) {
                $definitionRaw = $zip->getFromIndex($index);
                break;
            }
        }

        $zip->close();

        if (!is_string($definitionRaw)) {
            throw new SurveyValidationException(
                'Survey definition is missing from package.'
            );
        }

        $definition = json_decode($definitionRaw, true);

        if (!is_array($definition)) {
            throw new SurveyValidationException(
                'Survey definition in package is invalid.'
            );
        }

        return $this->createSurvey(
            [
                'survey_code' => $definition['survey_code'],
                'survey_name' => $definition['title']
                    ?? $definition['survey_code'],
                'description' => $definition['description'] ?? null,
                'version_no' => $definition['version'] ?? '1.0',
                'definition' => $definition,
            ],
            $request
        );
    }

    private function validateCreatePayload(array $payload): void
    {
        $validator = new Validator();

        $validator
            ->required($payload, 'survey_code')
            ->string($payload, 'survey_code', 2, 100)
            ->required($payload, 'survey_name')
            ->string($payload, 'survey_name', 2, 150)
            ->string($payload, 'description', 0, 500);

        if (isset($payload['survey_code'])) {
            $validator->regex(
                $payload,
                'survey_code',
                '/^[a-zA-Z][a-zA-Z0-9_-]*$/',
                'Survey code may contain letters, numbers, underscores and hyphens only.'
            );
        }

        if ($validator->fails()) {
            throw new SurveyValidationException(
                'Validation failed.',
                $validator->errors()
            );
        }
    }

    private function validateUpdatePayload(array $payload): void
    {
        $validator = new Validator();

        $validator
            ->string($payload, 'survey_name', 2, 150)
            ->string($payload, 'description', 0, 500);

        if ($validator->fails()) {
            throw new SurveyValidationException(
                'Validation failed.',
                $validator->errors()
            );
        }
    }

    private function validateDefinitionArray(
        array $definition
    ): array {
        $errors = [];

        foreach (
            ['survey_code', 'version', 'questions']
            as $required
        ) {
            if (
                !array_key_exists($required, $definition)
                || $definition[$required] === ''
                || $definition[$required] === null
            ) {
                $errors[$required][] =
                    "{$required} is required.";
            }
        }

        if (
            isset($definition['questions'])
            && !is_array($definition['questions'])
        ) {
            $errors['questions'][] =
                'questions must be an array.';
        }

        $questionIds = [];

        foreach (
            is_array($definition['questions'] ?? null)
                ? $definition['questions']
                : []
            as $index => $question
        ) {
            if (!is_array($question)) {
                $errors["questions.{$index}"][] =
                    'Each question must be an object.';
                continue;
            }

            $questionId = trim(
                (string) ($question['id'] ?? '')
            );

            if ($questionId === '') {
                $errors["questions.{$index}.id"][] =
                    'Question ID is required.';
            } elseif (isset($questionIds[$questionId])) {
                $errors["questions.{$index}.id"][] =
                    'Question ID must be unique.';
            } else {
                $questionIds[$questionId] = true;
            }

            if (
                trim((string) ($question['type'] ?? '')) === ''
            ) {
                $errors["questions.{$index}.type"][] =
                    'Question type is required.';
            }

            if (
                !isset($question['text'])
                && !isset($question['translations'])
            ) {
                $errors["questions.{$index}.text"][] =
                    'Question text or translations are required.';
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'question_count' =>
                count($definition['questions'] ?? []),
        ];
    }

    private function assertPositiveId(
        int $id,
        string $field
    ): void {
        if ($id <= 0) {
            throw new SurveyValidationException(
                'Validation failed.',
                [
                    $field => [
                        'A positive integer is required.',
                    ],
                ]
            );
        }
    }

    private function assertReadableFile(
        string $path
    ): void {
        if (
            !is_file($path)
            || !is_readable($path)
        ) {
            throw new SurveyValidationException(
                'Uploaded file is not readable.'
            );
        }
    }

    private function ensureExportDirectory(): string
    {
        $directory = dirname(__DIR__, 2)
            . '/storage/exports/surveys';

        if (
            !is_dir($directory)
            && !mkdir($directory, 0775, true)
            && !is_dir($directory)
        ) {
            throw new RuntimeException(
                'Unable to create export directory.'
            );
        }

        return $directory;
    }

    private function nullableString(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === ''
            ? null
            : $value;
    }
}
