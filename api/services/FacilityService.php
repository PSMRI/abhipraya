<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Authentication;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Validator;
use App\Repositories\AuthRepository;
use App\Repositories\FacilityRepository;
use PDO;
use RuntimeException;
use Throwable;

final class FacilityService
{
    private readonly FacilityRepository $facilityRepository;
    private readonly AuthRepository $authRepository;

    public function __construct(
        ?FacilityRepository $facilityRepository = null,
        ?AuthRepository $authRepository = null
    ) {
        $this->facilityRepository = $facilityRepository ?? new FacilityRepository(Database::connection());
        $this->authRepository = $authRepository ?? new AuthRepository(Database::connection());
    }

    public function listFacilities(array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $limit = max(1, min(100, (int) ($query['limit'] ?? 20)));

        $filters = [
            'search' => trim((string) ($query['search'] ?? '')),
            'nin' => $query['nin'] ?? null,
            'facility_type' => $query['facility_type'] ?? null,
            'division_id' => $query['division_id'] ?? null,
            'district_id' => $query['district_id'] ?? null,
            'block_id' => $query['block_id'] ?? null,
            'status' => $query['status'] ?? null,
        ];

        return $this->facilityRepository->paginate(
            $filters,
            $page,
            $limit
        );
    }

    public function getFacility(int $facilityId): array
    {
        $this->assertPositiveId($facilityId);

        $facility = $this->facilityRepository->findById($facilityId);

        if ($facility === null) {
            throw new FacilityNotFoundException();
        }

        return [
            'facility' => $facility,
            'departments' => $this->facilityRepository->getDepartments($facilityId),
            'surveys' => $this->facilityRepository->getAssignedSurveys($facilityId),
            'qr_codes' => $this->facilityRepository->getQrCodes($facilityId),
            'response_count' => $this->facilityRepository->countResponses($facilityId),
        ];
    }

    public function createFacility(
        array $payload,
        Request $request
    ): array {
        $this->validateCreatePayload($payload);

        $nin = trim((string) $payload['nin_no']);

        if ($this->facilityRepository->ninExists($nin)) {
            throw new FacilityValidationException(
                'Validation failed.',
                [
                    'nin_no' => [
                        'Facility NIN already exists.',
                    ],
                ]
            );
        }

        $actor = Authentication::requireUser();
        $actorUserId = (int) $actor['user_id'];

        try {
            $facilityId = Database::transaction(
                function (PDO $pdo) use (
                    $payload,
                    $nin,
                    $actorUserId,
                    $request
                ): int {
                    $facilityId = $this->facilityRepository->create([
                        'nin_no' => $nin,
                        'facility_name' => trim((string) $payload['facility_name']),
                        'address' => $this->nullableString($payload['address'] ?? null),
                        'facility_type' => $this->nullableString($payload['facility_type'] ?? null),
                        'division_id' => $this->nullableString($payload['division_id'] ?? null),
                        'district_id' => $this->nullableString($payload['district_id'] ?? null),
                        'block_id' => $this->nullableString($payload['block_id'] ?? null),
                        'latitude' => $payload['latitude'] ?? null,
                        'longitude' => $payload['longitude'] ?? null,
                        'geo_radius' => $payload['geo_radius'] ?? null,
                        'active_status' => (int) ($payload['active_status'] ?? 1),
                    ]);

                    $this->authRepository->writeAudit(
                        $actorUserId,
                        'FACILITY_CREATED',
                        'FACILITY',
                        (string) $facilityId,
                        null,
                        [
                            'nin_no' => $nin,
                            'facility_name' => $payload['facility_name'],
                            'facility_type' => $payload['facility_type'] ?? null,
                        ],
                        $request->ip(),
                        $request->requestId()
                    );

                    return $facilityId;
                }
            );

            return $this->getFacility($facilityId);
        } catch (
            FacilityValidationException
            | RuntimeException $exception
        ) {
            throw $exception;
        } catch (Throwable $exception) {
            Logger::error(
                'Facility creation failed.',
                [
                    'nin_no' => $nin,
                    'exception' => $exception->getMessage(),
                ]
            );

            throw new RuntimeException(
                'Unable to create facility.',
                500
            );
        }
    }

    public function updateFacility(
        int $facilityId,
        array $payload,
        Request $request
    ): array {
        $this->assertPositiveId($facilityId);

        $existing = $this->facilityRepository->findById($facilityId);

        if ($existing === null) {
            throw new FacilityNotFoundException();
        }

        $this->validateUpdatePayload($payload);

        if (
            isset($payload['nin_no'])
            && (string) $payload['nin_no'] !== (string) $existing['nin_no']
        ) {
            throw new FacilityValidationException(
                'Validation failed.',
                [
                    'nin_no' => [
                        'Facility NIN cannot be changed.',
                    ],
                ]
            );
        }

        $newData = [
            'facility_name' => $payload['facility_name']
                ?? $existing['facility_name'],
            'address' => array_key_exists('address', $payload)
                ? $this->nullableString($payload['address'])
                : $existing['address'],
            'facility_type' => array_key_exists('facility_type', $payload)
                ? $this->nullableString($payload['facility_type'])
                : $existing['facility_type'],
            'division_id' => array_key_exists('division_id', $payload)
                ? $this->nullableString($payload['division_id'])
                : $existing['division_id'],
            'district_id' => array_key_exists('district_id', $payload)
                ? $this->nullableString($payload['district_id'])
                : $existing['district_id'],
            'block_id' => array_key_exists('block_id', $payload)
                ? $this->nullableString($payload['block_id'])
                : $existing['block_id'],
            'latitude' => $payload['latitude']
                ?? $existing['latitude'],
            'longitude' => $payload['longitude']
                ?? $existing['longitude'],
            'geo_radius' => $payload['geo_radius']
                ?? $existing['geo_radius'],
            'active_status' => array_key_exists('active_status', $payload)
                ? (int) $payload['active_status']
                : ($existing['active'] ? 1 : 0),
        ];

        $actor = Authentication::requireUser();
        $actorUserId = (int) $actor['user_id'];

        Database::transaction(
            function (PDO $pdo) use (
                $facilityId,
                $existing,
                $newData,
                $actorUserId,
                $request
            ): void {
                $this->facilityRepository->update(
                    $facilityId,
                    $newData
                );

                $this->authRepository->writeAudit(
                    $actorUserId,
                    'FACILITY_UPDATED',
                    'FACILITY',
                    (string) $facilityId,
                    $existing,
                    $newData,
                    $request->ip(),
                    $request->requestId()
                );
            }
        );

        return $this->getFacility($facilityId);
    }

    public function deleteFacility(
        int $facilityId,
        Request $request
    ): array {
        $this->assertPositiveId($facilityId);

        $facility = $this->facilityRepository->findById($facilityId);

        if ($facility === null) {
            throw new FacilityNotFoundException();
        }

        $responseCount = $this->facilityRepository->countResponses($facilityId);

        if ($responseCount > 0) {
            throw new FacilityValidationException(
                'Facility cannot be deleted.',
                [
                    'facility_id' => [
                        'Facility has survey responses. Deactivate it instead.',
                    ],
                ]
            );
        }

        $departments = $this->facilityRepository->getDepartments($facilityId);

        if ($departments !== []) {
            throw new FacilityValidationException(
                'Facility cannot be deleted.',
                [
                    'facility_id' => [
                        'Remove or deactivate department mappings first.',
                    ],
                ]
            );
        }

        $actor = Authentication::requireUser();
        $actorUserId = (int) $actor['user_id'];

        Database::transaction(
            function (PDO $pdo) use (
                $facilityId,
                $facility,
                $actorUserId,
                $request
            ): void {
                $this->facilityRepository->delete($facilityId);

                $this->authRepository->writeAudit(
                    $actorUserId,
                    'FACILITY_DELETED',
                    'FACILITY',
                    (string) $facilityId,
                    $facility,
                    null,
                    $request->ip(),
                    $request->requestId()
                );
            }
        );

        return [
            'facility_id' => $facilityId,
            'deleted' => true,
        ];
    }

    public function activateFacility(
        int $facilityId,
        Request $request
    ): array {
        return $this->changeStatus(
            $facilityId,
            1,
            $request
        );
    }

    public function deactivateFacility(
        int $facilityId,
        Request $request
    ): array {
        return $this->changeStatus(
            $facilityId,
            0,
            $request
        );
    }

    public function getHierarchy(array $query): array
    {
        return $this->facilityRepository->getHierarchy([
            'division_id' => $query['division_id'] ?? null,
            'district_id' => $query['district_id'] ?? null,
            'block_id' => $query['block_id'] ?? null,
        ]);
    }

    public function getDepartments(int $facilityId): array
    {
        $this->ensureFacilityExists($facilityId);

        return [
            'facility_id' => $facilityId,
            'items' => $this->facilityRepository->getDepartments($facilityId),
        ];
    }

    public function getSurveys(int $facilityId): array
    {
        $this->ensureFacilityExists($facilityId);

        return [
            'facility_id' => $facilityId,
            'items' => $this->facilityRepository->getAssignedSurveys($facilityId),
        ];
    }

    public function getQrCodes(int $facilityId): array
    {
        $this->ensureFacilityExists($facilityId);

        return [
            'facility_id' => $facilityId,
            'items' => $this->facilityRepository->getQrCodes($facilityId),
        ];
    }

    public function lookupFacilities(array $query): array
    {
        return [
            'items' => $this->facilityRepository->lookup(
                isset($query['search'])
                    ? trim((string) $query['search'])
                    : null,
                isset($query['limit'])
                    ? (int) $query['limit']
                    : 50
            ),
        ];
    }

    private function changeStatus(
        int $facilityId,
        int $status,
        Request $request
    ): array {
        $this->assertPositiveId($facilityId);

        $facility = $this->facilityRepository->findById($facilityId);

        if ($facility === null) {
            throw new FacilityNotFoundException();
        }

        $currentStatus = $facility['active'] ? 1 : 0;

        if ($currentStatus === $status) {
            return $this->getFacility($facilityId);
        }

        $actor = Authentication::requireUser();
        $actorUserId = (int) $actor['user_id'];

        Database::transaction(
            function (PDO $pdo) use (
                $facilityId,
                $status,
                $currentStatus,
                $actorUserId,
                $request
            ): void {
                $this->facilityRepository->updateStatus(
                    $facilityId,
                    $status
                );

                $this->authRepository->writeAudit(
                    $actorUserId,
                    $status === 1
                        ? 'FACILITY_ACTIVATED'
                        : 'FACILITY_DEACTIVATED',
                    'FACILITY',
                    (string) $facilityId,
                    [
                        'active_status' => $currentStatus,
                    ],
                    [
                        'active_status' => $status,
                    ],
                    $request->ip(),
                    $request->requestId()
                );
            }
        );

        return $this->getFacility($facilityId);
    }

    private function validateCreatePayload(array $payload): void
    {
        $validator = new Validator();

        $validator
            ->required($payload, 'nin_no')
            ->string($payload, 'nin_no', 5, 20)
            ->required($payload, 'facility_name')
            ->string($payload, 'facility_name', 2, 150)
            ->string($payload, 'address', 0, 255)
            ->string($payload, 'facility_type', 0, 100);

        if (isset($payload['active_status'])) {
            $validator->in(
                $payload,
                'active_status',
                [0, 1, '0', '1']
            );
        }

        $this->validateCoordinates(
            $payload,
            $validator
        );

        if ($validator->fails()) {
            throw new FacilityValidationException(
                'Validation failed.',
                $validator->errors()
            );
        }
    }

    private function validateUpdatePayload(array $payload): void
    {
        $validator = new Validator();

        $validator
            ->string($payload, 'facility_name', 2, 150)
            ->string($payload, 'address', 0, 255)
            ->string($payload, 'facility_type', 0, 100);

        if (isset($payload['active_status'])) {
            $validator->in(
                $payload,
                'active_status',
                [0, 1, '0', '1']
            );
        }

        $this->validateCoordinates(
            $payload,
            $validator
        );

        if ($validator->fails()) {
            throw new FacilityValidationException(
                'Validation failed.',
                $validator->errors()
            );
        }
    }

    private function validateCoordinates(
        array $payload,
        Validator $validator
    ): void {
        if (
            isset($payload['latitude'])
            && $payload['latitude'] !== ''
        ) {
            if (
                !is_numeric($payload['latitude'])
                || (float) $payload['latitude'] < -90
                || (float) $payload['latitude'] > 90
            ) {
                $validator->add(
                    'latitude',
                    'Latitude must be between -90 and 90.'
                );
            }
        }

        if (
            isset($payload['longitude'])
            && $payload['longitude'] !== ''
        ) {
            if (
                !is_numeric($payload['longitude'])
                || (float) $payload['longitude'] < -180
                || (float) $payload['longitude'] > 180
            ) {
                $validator->add(
                    'longitude',
                    'Longitude must be between -180 and 180.'
                );
            }
        }

        if (
            isset($payload['geo_radius'])
            && $payload['geo_radius'] !== ''
        ) {
            if (
                filter_var(
                    $payload['geo_radius'],
                    FILTER_VALIDATE_INT
                ) === false
                || (int) $payload['geo_radius'] < 0
            ) {
                $validator->add(
                    'geo_radius',
                    'Geo radius must be a non-negative integer.'
                );
            }
        }
    }

    private function ensureFacilityExists(int $facilityId): void
    {
        $this->assertPositiveId($facilityId);

        if ($this->facilityRepository->findById($facilityId) === null) {
            throw new FacilityNotFoundException();
        }
    }

    private function assertPositiveId(int $facilityId): void
    {
        if ($facilityId <= 0) {
            throw new FacilityValidationException(
                'Validation failed.',
                [
                    'facility_id' => [
                        'A positive facility ID is required.',
                    ],
                ]
            );
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === ''
            ? null
            : $value;
    }
}
