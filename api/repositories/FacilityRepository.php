<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Logger;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
use Throwable;

final class FacilityRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function paginate(array $filters = [], int $page = 1, int $limit = 20): array
    {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));
        $offset = ($page - 1) * $limit;

        $where = [];
        $params = [];

        $map = [
            'facility_type' => 'f.fac_type',
            'division_id' => 'f.fac_div_id',
            'district_id' => 'f.fac_dist_id',
            'block_id' => 'f.fac_block_id',
            'status' => 'f.active_status',
        ];

        if (!empty($filters['search'])) {
            $where[] = '(f.fac_name LIKE :search OR CAST(f.NIN_no AS CHAR) LIKE :search OR f.fac_address LIKE :search)';
            $params['search'] = '%' . trim((string) $filters['search']) . '%';
        }

        if (!empty($filters['nin'])) {
            $where[] = 'f.NIN_no = :nin';
            $params['nin'] = (string) $filters['nin'];
        }

        foreach ($map as $key => $column) {
            if (array_key_exists($key, $filters) && $filters[$key] !== '' && $filters[$key] !== null) {
                $where[] = "{$column} = :{$key}";
                $params[$key] = $key === 'status'
                    ? (int) $filters[$key]
                    : trim((string) $filters[$key]);
            }
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        try {
            $count = $this->pdo->prepare("SELECT COUNT(*) total FROM fac_master f {$whereSql}");
            $this->bind($count, $params);
            $count->execute();
            $total = (int) ($count->fetch()['total'] ?? 0);

            $sql = "SELECT
                        f.id, f.NIN_no, f.fac_name, f.fac_address, f.fac_type,
                        f.fac_dist_id, f.fac_block_id, f.fac_div_id,
                        f.fac_lat, f.fac_long, f.fac_geo_radius,
                        f.active_status, f.created_at, f.updated_at,
                        COUNT(DISTINCT fd.facility_department_id) department_count,
                        COUNT(DISTINCT ds.department_survey_id) active_survey_count,
                        COUNT(DISTINCT dq.qr_id) qr_count
                    FROM fac_master f
                    LEFT JOIN facility_department fd ON fd.facility_id = f.id
                    LEFT JOIN department_survey ds
                        ON ds.facility_department_id = fd.facility_department_id
                       AND ds.is_active = 1
                    LEFT JOIN department_qr dq
                        ON dq.facility_id = f.id
                       AND dq.active_status = 1
                    {$whereSql}
                    GROUP BY
                        f.id, f.NIN_no, f.fac_name, f.fac_address, f.fac_type,
                        f.fac_dist_id, f.fac_block_id, f.fac_div_id,
                        f.fac_lat, f.fac_long, f.fac_geo_radius,
                        f.active_status, f.created_at, f.updated_at
                    ORDER BY f.fac_name
                    LIMIT :limit OFFSET :offset";

            $stmt = $this->pdo->prepare($sql);
            $this->bind($stmt, $params);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return [
                'items' => array_map(fn(array $r) => $this->format($r), $stmt->fetchAll()),
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => $total ? (int) ceil($total / $limit) : 0,
                ],
            ];
        } catch (PDOException $e) {
            $this->fail('Unable to paginate facilities.', $e, ['filters' => $filters]);
        }
    }

    public function findById(int $facilityId): ?array
    {
        return $this->findOne('id = :value', $facilityId);
    }

    public function findByNin(string|int $nin): ?array
    {
        return $this->findOne('NIN_no = :value', (string) $nin);
    }

    private function findOne(string $condition, mixed $value): ?array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT id, NIN_no, fac_name, fac_address, fac_type,
                        fac_dist_id, fac_block_id, fac_div_id,
                        fac_lat, fac_long, fac_geo_radius,
                        active_status, created_at, updated_at
                 FROM fac_master
                 WHERE {$condition}
                 LIMIT 1"
            );
            $stmt->execute(['value' => $value]);
            $row = $stmt->fetch();

            return is_array($row) ? $this->format($row) : null;
        } catch (PDOException $e) {
            $this->fail('Unable to load facility.', $e);
        }
    }

    public function create(array $data): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO fac_master
                    (NIN_no, fac_name, fac_address, fac_type, fac_dist_id,
                     fac_block_id, fac_div_id, fac_lat, fac_long,
                     fac_geo_radius, active_status, created_at, updated_at)
                 VALUES
                    (:nin, :name, :address, :type, :district, :block,
                     :division, :lat, :lng, :radius, :status, NOW(), NOW())'
            );

            $this->bindFacilityData($stmt, $data);
            $stmt->execute();

            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            if ($this->duplicate($e)) {
                throw new RuntimeException('Facility NIN already exists.', 409);
            }
            $this->fail('Unable to create facility.', $e);
        }
    }

    public function update(int $facilityId, array $data): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE fac_master SET
                    fac_name = :name,
                    fac_address = :address,
                    fac_type = :type,
                    fac_dist_id = :district,
                    fac_block_id = :block,
                    fac_div_id = :division,
                    fac_lat = :lat,
                    fac_long = :lng,
                    fac_geo_radius = :radius,
                    active_status = :status,
                    updated_at = NOW()
                 WHERE id = :facility_id'
            );

            $this->bindFacilityData($stmt, $data, false);
            $stmt->bindValue(':facility_id', $facilityId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            $this->fail('Unable to update facility.', $e, ['facility_id' => $facilityId]);
        }
    }

    public function updateStatus(int $facilityId, int $status): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE fac_master
                 SET active_status = :status, updated_at = NOW()
                 WHERE id = :facility_id'
            );
            $stmt->execute(['status' => $status, 'facility_id' => $facilityId]);

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            $this->fail('Unable to update facility status.', $e);
        }
    }

    public function delete(int $facilityId): bool
    {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM fac_master WHERE id = :facility_id');
            $stmt->execute(['facility_id' => $facilityId]);

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            if ($this->foreignKey($e)) {
                throw new RuntimeException(
                    'Facility is in use and cannot be deleted. Deactivate it instead.',
                    409
                );
            }
            $this->fail('Unable to delete facility.', $e);
        }
    }

    public function ninExists(string|int $nin, ?int $excludeFacilityId = null): bool
    {
        $sql = 'SELECT 1 FROM fac_master WHERE NIN_no = :nin';
        $params = ['nin' => (string) $nin];

        if ($excludeFacilityId !== null) {
            $sql .= ' AND id <> :exclude_id';
            $params['exclude_id'] = $excludeFacilityId;
        }

        $sql .= ' LIMIT 1';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return (bool) $stmt->fetchColumn();
        } catch (PDOException $e) {
            $this->fail('Unable to verify facility NIN.', $e);
        }
    }

    public function getHierarchy(array $filters = []): array
    {
        $where = ['active_status = 1'];
        $params = [];

        foreach ([
            'division_id' => 'fac_div_id',
            'district_id' => 'fac_dist_id',
            'block_id' => 'fac_block_id',
        ] as $key => $column) {
            if (!empty($filters[$key])) {
                $where[] = "{$column} = :{$key}";
                $params[$key] = (string) $filters[$key];
            }
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        return [
            'divisions' => $this->distinct('fac_div_id', $whereSql, $params),
            'districts' => $this->distinct('fac_dist_id', $whereSql, $params),
            'blocks' => $this->distinct('fac_block_id', $whereSql, $params),
            'facility_types' => $this->distinct('fac_type', $whereSql, $params),
        ];
    }

    public function getDepartments(int $facilityId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT
                    fd.facility_department_id,
                    fd.facility_id,
                    fd.department_id,
                    fd.active_status,
                    fd.qr_enabled,
                    fd.created_at,
                    fd.updated_at,
                    d.department_code,
                    d.department_name,
                    d.description,
                    d.display_order
                 FROM facility_department fd
                 INNER JOIN department_master d
                    ON d.department_id = fd.department_id
                 WHERE fd.facility_id = :facility_id
                 ORDER BY d.display_order, d.department_name'
            );
            $stmt->execute(['facility_id' => $facilityId]);

            return array_map(
                static fn(array $r): array => [
                    'facility_department_id' => (int) $r['facility_department_id'],
                    'facility_id' => (int) $r['facility_id'],
                    'department_id' => (int) $r['department_id'],
                    'department_code' => (string) $r['department_code'],
                    'department_name' => (string) $r['department_name'],
                    'description' => $r['description'] ?? null,
                    'display_order' => (int) $r['display_order'],
                    'active' => (int) $r['active_status'] === 1,
                    'qr_enabled' => (int) $r['qr_enabled'] === 1,
                    'created_at' => $r['created_at'] ?? null,
                    'updated_at' => $r['updated_at'] ?? null,
                ],
                $stmt->fetchAll()
            );
        } catch (PDOException $e) {
            $this->fail('Unable to load facility departments.', $e);
        }
    }

    public function getAssignedSurveys(int $facilityId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT
                    ds.department_survey_id,
                    ds.facility_department_id,
                    ds.survey_id,
                    ds.survey_version_id,
                    ds.valid_from,
                    ds.valid_until,
                    ds.is_active,
                    ds.created_at,
                    d.department_id,
                    d.department_code,
                    d.department_name,
                    sm.survey_code,
                    sm.survey_name,
                    sm.status survey_status,
                    sv.version_no,
                    sv.status version_status,
                    sv.published_at
                 FROM department_survey ds
                 INNER JOIN facility_department fd
                    ON fd.facility_department_id = ds.facility_department_id
                 INNER JOIN department_master d
                    ON d.department_id = fd.department_id
                 INNER JOIN survey_master sm
                    ON sm.survey_id = ds.survey_id
                 INNER JOIN survey_version sv
                    ON sv.survey_version_id = ds.survey_version_id
                 WHERE fd.facility_id = :facility_id
                 ORDER BY d.department_name, ds.created_at DESC'
            );
            $stmt->execute(['facility_id' => $facilityId]);

            return array_map(
                static fn(array $r): array => [
                    'department_survey_id' => (int) $r['department_survey_id'],
                    'facility_department_id' => (int) $r['facility_department_id'],
                    'department_id' => (int) $r['department_id'],
                    'department_code' => (string) $r['department_code'],
                    'department_name' => (string) $r['department_name'],
                    'survey_id' => (int) $r['survey_id'],
                    'survey_code' => (string) $r['survey_code'],
                    'survey_name' => (string) $r['survey_name'],
                    'survey_status' => (string) $r['survey_status'],
                    'survey_version_id' => (int) $r['survey_version_id'],
                    'version_no' => (string) $r['version_no'],
                    'version_status' => (string) $r['version_status'],
                    'valid_from' => $r['valid_from'] ?? null,
                    'valid_until' => $r['valid_until'] ?? null,
                    'active' => (int) $r['is_active'] === 1,
                    'published_at' => $r['published_at'] ?? null,
                    'created_at' => $r['created_at'] ?? null,
                ],
                $stmt->fetchAll()
            );
        } catch (PDOException $e) {
            $this->fail('Unable to load facility surveys.', $e);
        }
    }

    public function getQrCodes(int $facilityId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT
                    q.qr_id, q.facility_id, q.department_id,
                    q.qr_url, q.qr_file_path, q.active_status,
                    q.generated_by, q.generated_at,
                    d.department_code, d.department_name
                 FROM department_qr q
                 INNER JOIN department_master d
                    ON d.department_id = q.department_id
                 WHERE q.facility_id = :facility_id
                 ORDER BY d.department_name'
            );
            $stmt->execute(['facility_id' => $facilityId]);

            return array_map(
                static fn(array $r): array => [
                    'qr_id' => (int) $r['qr_id'],
                    'facility_id' => (int) $r['facility_id'],
                    'department_id' => (int) $r['department_id'],
                    'department_code' => (string) $r['department_code'],
                    'department_name' => (string) $r['department_name'],
                    'qr_url' => (string) $r['qr_url'],
                    'qr_file_path' => $r['qr_file_path'] ?? null,
                    'active' => (int) $r['active_status'] === 1,
                    'generated_by' => (int) $r['generated_by'],
                    'generated_at' => $r['generated_at'] ?? null,
                ],
                $stmt->fetchAll()
            );
        } catch (PDOException $e) {
            $this->fail('Unable to load facility QR codes.', $e);
        }
    }

    public function countResponses(int $facilityId): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) total
                 FROM survey_response
                 WHERE facility_id = :facility_id'
            );
            $stmt->execute(['facility_id' => $facilityId]);

            return (int) ($stmt->fetch()['total'] ?? 0);
        } catch (PDOException $e) {
            $this->fail('Unable to count facility responses.', $e);
        }
    }

    public function lookup(?string $search = null, int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));
        $sql = 'SELECT id, NIN_no, fac_name, fac_type
                FROM fac_master
                WHERE active_status = 1';
        $params = [];

        if ($search !== null && trim($search) !== '') {
            $sql .= ' AND (fac_name LIKE :search OR CAST(NIN_no AS CHAR) LIKE :search)';
            $params['search'] = '%' . trim($search) . '%';
        }

        $sql .= ' ORDER BY fac_name LIMIT :limit';

        try {
            $stmt = $this->pdo->prepare($sql);
            $this->bind($stmt, $params);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return array_map(
                static fn(array $r): array => [
                    'id' => (int) $r['id'],
                    'nin' => (string) $r['NIN_no'],
                    'name' => (string) $r['fac_name'],
                    'type' => $r['fac_type'] ?? null,
                ],
                $stmt->fetchAll()
            );
        } catch (PDOException $e) {
            $this->fail('Unable to load facility lookup.', $e);
        }
    }

    private function bindFacilityData(PDOStatement $stmt, array $data, bool $includeNin = true): void
    {
        if ($includeNin) {
            $stmt->bindValue(':nin', (string) $data['nin_no'], PDO::PARAM_STR);
        }

        $stmt->bindValue(':name', trim((string) $data['facility_name']), PDO::PARAM_STR);
        $this->nullableString($stmt, ':address', $data['address'] ?? null);
        $this->nullableString($stmt, ':type', $data['facility_type'] ?? null);
        $this->nullableString($stmt, ':district', $data['district_id'] ?? null);
        $this->nullableString($stmt, ':block', $data['block_id'] ?? null);
        $this->nullableString($stmt, ':division', $data['division_id'] ?? null);
        $this->nullableString($stmt, ':lat', $data['latitude'] ?? null);
        $this->nullableString($stmt, ':lng', $data['longitude'] ?? null);

        $radius = ($data['geo_radius'] ?? '') === ''
            ? null
            : (int) $data['geo_radius'];

        $stmt->bindValue(
            ':radius',
            $radius,
            $radius === null ? PDO::PARAM_NULL : PDO::PARAM_INT
        );
        $stmt->bindValue(':status', (int) ($data['active_status'] ?? 1), PDO::PARAM_INT);
    }

    private function distinct(string $column, string $whereSql, array $params): array
    {
        $allowed = ['fac_div_id', 'fac_dist_id', 'fac_block_id', 'fac_type'];

        if (!in_array($column, $allowed, true)) {
            throw new RuntimeException('Unsupported hierarchy column.');
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT DISTINCT {$column} value
                 FROM fac_master
                 {$whereSql}
                   AND {$column} IS NOT NULL
                   AND {$column} <> ''
                 ORDER BY {$column}"
            );
            $this->bind($stmt, $params);
            $stmt->execute();

            return array_values(array_map(
                static fn(array $r): string => (string) $r['value'],
                $stmt->fetchAll()
            ));
        } catch (PDOException $e) {
            $this->fail('Unable to load hierarchy values.', $e);
        }
    }

    private function format(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'nin_no' => (string) $row['NIN_no'],
            'facility_name' => (string) $row['fac_name'],
            'address' => $row['fac_address'] ?? null,
            'facility_type' => $row['fac_type'] ?? null,
            'division_id' => $row['fac_div_id'] ?? null,
            'district_id' => $row['fac_dist_id'] ?? null,
            'block_id' => $row['fac_block_id'] ?? null,
            'latitude' => $row['fac_lat'] !== null ? (float) $row['fac_lat'] : null,
            'longitude' => $row['fac_long'] !== null ? (float) $row['fac_long'] : null,
            'geo_radius' => $row['fac_geo_radius'] !== null ? (int) $row['fac_geo_radius'] : null,
            'active' => (int) $row['active_status'] === 1,
            'department_count' => isset($row['department_count']) ? (int) $row['department_count'] : null,
            'active_survey_count' => isset($row['active_survey_count']) ? (int) $row['active_survey_count'] : null,
            'qr_count' => isset($row['qr_count']) ? (int) $row['qr_count'] : null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function bind(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $stmt->bindValue(
                ':' . $key,
                $value,
                is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
            );
        }
    }

    private function nullableString(PDOStatement $stmt, string $name, mixed $value): void
    {
        $value = $value === null ? null : trim((string) $value);
        $value = $value === '' ? null : $value;

        $stmt->bindValue(
            $name,
            $value,
            $value === null ? PDO::PARAM_NULL : PDO::PARAM_STR
        );
    }

    private function duplicate(PDOException $e): bool
    {
        return (string) $e->getCode() === '23000'
            && (!isset($e->errorInfo[1]) || (int) $e->errorInfo[1] === 1062);
    }

    private function foreignKey(PDOException $e): bool
    {
        return (string) $e->getCode() === '23000'
            && (!isset($e->errorInfo[1]) || in_array((int) $e->errorInfo[1], [1451, 1452], true));
    }

    private function fail(string $message, Throwable $e, array $context = []): never
    {
        Logger::error($message, array_merge($context, [
            'exception' => $e->getMessage(),
        ]));

        throw new RuntimeException($message);
    }
}
