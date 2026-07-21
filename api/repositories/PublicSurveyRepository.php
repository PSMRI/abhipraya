<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

final class PublicSurveyRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function findQrByToken(
        string $token
    ): ?array {
        $statement = $this->pdo->prepare(
            'SELECT
                q.qr_id,
                q.qr_token,
                q.facility_id,
                q.department_id,
                q.survey_id,
                q.survey_version_id,
                q.active_status,
                q.expires_at,
                f.fac_name AS facility_name,
                d.department_name,
                s.survey_name,
                s.status AS survey_status,
                v.definition_json
             FROM qr_master q
             INNER JOIN fac_master f
                ON f.id = q.facility_id
             INNER JOIN department_master d
                ON d.department_id = q.department_id
             INNER JOIN survey_master s
                ON s.survey_id = q.survey_id
             INNER JOIN survey_version v
                ON v.survey_version_id =
                   q.survey_version_id
             WHERE q.qr_token = :token
             LIMIT 1'
        );

        $statement->execute([
            'token' => $token,
        ]);

        $record = $statement->fetch();

        return is_array($record)
            ? $record
            : null;
    }

    public function isDuplicate(
        int $surveyId,
        int $facilityId,
        int $departmentId,
        string $deviceId
    ): bool {
        $statement = $this->pdo->prepare(
            'SELECT 1
             FROM survey_response
             WHERE survey_id = :survey_id
               AND facility_id = :facility_id
               AND department_id = :department_id
               AND device_id = :device_id
             LIMIT 1'
        );

        $statement->execute([
            'survey_id' => $surveyId,
            'facility_id' => $facilityId,
            'department_id' => $departmentId,
            'device_id' => $deviceId,
        ]);

        return (bool) $statement->fetchColumn();
    }

    public function createResponse(
        array $data
    ): int {
        $statement = $this->pdo->prepare(
            'INSERT INTO survey_response
                (
                    submission_id,
                    facility_id,
                    department_id,
                    survey_id,
                    survey_version_id,
                    qr_id,
                    device_id,
                    latitude,
                    longitude,
                    ip_address,
                    language_code,
                    answers_json,
                    submitted_at
                )
             VALUES
                (
                    :submission_id,
                    :facility_id,
                    :department_id,
                    :survey_id,
                    :survey_version_id,
                    :qr_id,
                    :device_id,
                    :latitude,
                    :longitude,
                    :ip_address,
                    :language_code,
                    :answers_json,
                    NOW()
                )'
        );

        $statement->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    public function recordScan(
        int $qrId,
        string $ipAddress,
        string $deviceId,
        bool $submitted
    ): void {
        $statement = $this->pdo->prepare(
            'INSERT INTO qr_scan_log
                (
                    qr_id,
                    ip_address,
                    device_id,
                    submitted,
                    scanned_at
                )
             VALUES
                (
                    :qr_id,
                    :ip_address,
                    :device_id,
                    :submitted,
                    NOW()
                )'
        );

        $statement->execute([
            'qr_id' => $qrId,
            'ip_address' => $ipAddress,
            'device_id' => $deviceId,
            'submitted' => $submitted ? 1 : 0,
        ]);
    }

    public function findResponseBySubmissionId(
        string $submissionId
    ): ?array {
        $statement = $this->pdo->prepare(
            'SELECT
                response_id,
                submission_id,
                facility_id,
                department_id,
                survey_id,
                submitted_at
             FROM survey_response
             WHERE submission_id = :submission_id
             LIMIT 1'
        );

        $statement->execute([
            'submission_id' => $submissionId,
        ]);

        $record = $statement->fetch();

        return is_array($record)
            ? $record
            : null;
    }
}
