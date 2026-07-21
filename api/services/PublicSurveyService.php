<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Request;
use App\Core\Validator;
use App\Repositories\PublicSurveyRepository;
use RuntimeException;

final class PublicSurveyService
{
    public function __construct(
        private readonly PublicSurveyRepository $repository =
            new PublicSurveyRepository(Database::connection())
    ) {
    }

    public function resolveToken(
        string $token,
        Request $request
    ): array {
        $record = $this->repository->findQrByToken($token);

        if ($record === null) {
            throw new PublicSurveyNotFoundException(
                'Survey link was not found.'
            );
        }

        $this->assertQrUsable($record);

        return [
            'token' => $token,
            'facility_id' => (int) $record['facility_id'],
            'facility_name' => (string) $record['facility_name'],
            'department_id' => (int) $record['department_id'],
            'department_name' => (string) $record['department_name'],
            'survey_id' => (int) $record['survey_id'],
            'survey_version_id' =>
                (int) $record['survey_version_id'],
            'languages' => $this->extractLanguages(
                (string) $record['definition_json']
            ),
        ];
    }

    public function validateToken(
        string $token,
        Request $request
    ): array {
        $record = $this->repository->findQrByToken($token);

        if ($record === null) {
            return [
                'valid' => false,
                'reason' => 'not_found',
            ];
        }

        try {
            $this->assertQrUsable($record);

            return [
                'valid' => true,
                'active' => true,
                'expires_at' => $record['expires_at'] ?? null,
            ];
        } catch (RuntimeException $exception) {
            return [
                'valid' => false,
                'reason' => $exception->getMessage(),
            ];
        }
    }

    public function getSurvey(
        string $token,
        string $language,
        Request $request
    ): array {
        $resolved = $this->resolveToken($token, $request);
        $questions = $this->getQuestions(
            $token,
            $language,
            $request
        );

        return [
            'context' => $resolved,
            'configuration' =>
                $this->getConfiguration($token, $language),
            'questions' => $questions['items'],
        ];
    }

    public function getQuestions(
        string $token,
        string $language,
        Request $request
    ): array {
        $record = $this->repository->findQrByToken($token);

        if ($record === null) {
            throw new PublicSurveyNotFoundException();
        }

        $this->assertQrUsable($record);

        $definition = json_decode(
            (string) $record['definition_json'],
            true
        );

        if (!is_array($definition)) {
            throw new RuntimeException(
                'Survey definition is invalid.'
            );
        }

        return [
            'survey_version_id' =>
                (int) $record['survey_version_id'],
            'language' => $language,
            'items' => array_values(
                $definition['questions'] ?? []
            ),
        ];
    }

    public function submitResponse(
        array $payload,
        Request $request
    ): array {
        $validator = new Validator();

        $validator
            ->required($payload, 'token')
            ->string($payload, 'token', 10, 255)
            ->required($payload, 'device_id')
            ->string($payload, 'device_id', 3, 250)
            ->required($payload, 'answers');

        if (!isset($payload['answers']) || !is_array($payload['answers'])) {
            $validator->add(
                'answers',
                'answers must be an object or array.'
            );
        }

        if ($validator->fails()) {
            throw new PublicSurveyValidationException(
                'Validation failed.',
                $validator->errors()
            );
        }

        $token = trim((string) $payload['token']);
        $record = $this->repository->findQrByToken($token);

        if ($record === null) {
            throw new PublicSurveyNotFoundException();
        }

        $this->assertQrUsable($record);

        $deviceId = trim((string) $payload['device_id']);

        if (
            $this->repository->isDuplicate(
                (int) $record['survey_id'],
                (int) $record['facility_id'],
                (int) $record['department_id'],
                $deviceId
            )
        ) {
            throw new RuntimeException(
                'You have already submitted feedback for this survey.',
                409
            );
        }

        $submissionId = bin2hex(random_bytes(32));

        $responseId = $this->repository->createResponse([
            'submission_id' => $submissionId,
            'facility_id' => (int) $record['facility_id'],
            'department_id' => (int) $record['department_id'],
            'survey_id' => (int) $record['survey_id'],
            'survey_version_id' =>
                (int) $record['survey_version_id'],
            'qr_id' => (int) $record['qr_id'],
            'device_id' => $deviceId,
            'latitude' => $payload['latitude'] ?? null,
            'longitude' => $payload['longitude'] ?? null,
            'ip_address' => $request->ip(),
            'language' => $payload['language'] ?? 'en',
            'answers_json' => json_encode(
                $payload['answers'],
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            ),
        ]);

        $this->repository->recordScan(
            (int) $record['qr_id'],
            $request->ip(),
            $deviceId,
            true
        );

        return [
            'response_id' => $responseId,
            'submission_id' => $submissionId,
            'submitted' => true,
        ];
    }

    public function checkDuplicate(
        array $payload,
        Request $request
    ): array {
        $token = trim((string) ($payload['token'] ?? ''));
        $deviceId = trim(
            (string) ($payload['device_id'] ?? '')
        );

        if ($token === '' || $deviceId === '') {
            throw new PublicSurveyValidationException(
                'Validation failed.',
                [
                    'token' => ['QR token is required.'],
                    'device_id' => ['Device ID is required.'],
                ]
            );
        }

        $record = $this->repository->findQrByToken($token);

        if ($record === null) {
            throw new PublicSurveyNotFoundException();
        }

        return [
            'duplicate' => $this->repository->isDuplicate(
                (int) $record['survey_id'],
                (int) $record['facility_id'],
                (int) $record['department_id'],
                $deviceId
            ),
        ];
    }

    public function getLanguages(string $token): array
    {
        $record = $this->repository->findQrByToken($token);

        if ($record === null) {
            throw new PublicSurveyNotFoundException();
        }

        return [
            'items' => $this->extractLanguages(
                (string) $record['definition_json']
            ),
        ];
    }

    public function getConfiguration(
        string $token,
        string $language
    ): array {
        $record = $this->repository->findQrByToken($token);

        if ($record === null) {
            throw new PublicSurveyNotFoundException();
        }

        $definition = json_decode(
            (string) $record['definition_json'],
            true
        );

        return [
            'language' => $language,
            'title' => $definition['title']
                ?? $record['survey_name'],
            'description' =>
                $definition['description'] ?? null,
            'theme' => $definition['theme'] ?? [],
            'show_progress' =>
                (bool) ($definition['show_progress'] ?? true),
            'allow_back' =>
                (bool) ($definition['allow_back'] ?? true),
            'voice_enabled' =>
                (bool) ($definition['voice_enabled'] ?? false),
        ];
    }

    public function getAudio(
        string $token,
        string $questionId,
        string $language
    ): array {
        $record = $this->repository->findQrByToken($token);

        if ($record === null) {
            throw new PublicSurveyNotFoundException();
        }

        $definition = json_decode(
            (string) $record['definition_json'],
            true
        );

        foreach ($definition['questions'] ?? [] as $question) {
            if ((string) ($question['id'] ?? '') !== $questionId) {
                continue;
            }

            $path = $question['audio'][$language] ?? null;

            if (!is_string($path) || $path === '') {
                throw new PublicSurveyNotFoundException(
                    'Question audio was not found.'
                );
            }

            return [
                'path' => $path,
                'content_type' => 'audio/mpeg',
            ];
        }

        throw new PublicSurveyNotFoundException(
            'Question was not found.'
        );
    }

    public function getThankYou(
        string $submissionId,
        string $language
    ): array {
        $response = $this->repository
            ->findResponseBySubmissionId($submissionId);

        if ($response === null) {
            throw new PublicSurveyNotFoundException(
                'Submission was not found.'
            );
        }

        return [
            'submission_id' => $submissionId,
            'language' => $language,
            'message' => $language === 'hi'
                ? 'आपकी प्रतिक्रिया सफलतापूर्वक दर्ज की गई है। धन्यवाद।'
                : 'Your feedback has been submitted successfully. Thank you.',
        ];
    }

    private function assertQrUsable(array $record): void
    {
        if ((int) $record['active_status'] !== 1) {
            throw new RuntimeException(
                'This survey link is inactive.',
                410
            );
        }

        if (
            !empty($record['expires_at'])
            && strtotime((string) $record['expires_at']) < time()
        ) {
            throw new RuntimeException(
                'This survey link has expired.',
                410
            );
        }

        if (
            strtoupper((string) $record['survey_status'])
            !== 'PUBLISHED'
        ) {
            throw new RuntimeException(
                'Survey is not currently published.',
                410
            );
        }
    }

    private function extractLanguages(
        string $definitionJson
    ): array {
        $definition = json_decode(
            $definitionJson,
            true
        );

        if (!is_array($definition)) {
            return ['en'];
        }

        return array_values(
            $definition['languages'] ?? ['en']
        );
    }
}
