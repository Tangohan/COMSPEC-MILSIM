<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\Migrations\SchemaInspector;
use App\Support\QualificationCatalog\IssuerDetails;
use App\Support\QualificationCatalog\QualificationCatalog;
use PDO;
use Throwable;

/**
 * Émetteurs des qualifications (école, centre de formation, unité, instructeur, organisme externe) :
 * fiche enrichie, qualifications rattachées, habilités et délivrances. Toutes les lectures tolèrent un schéma
 * antérieur à l’étape 3470 (colonnes et table absentes) ; rien n’est jamais supprimé côté délivrances.
 */
final class QualificationIssuerRepository
{
    public const KINDS = ['school', 'training_center', 'unit', 'instructor', 'external'];
    public const ROLES = ['instructor', 'signatory'];

    private ?PDO $pdo;

    /** @var array<string, bool> */
    private array $columnCache = [];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    public function hasColumn(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (!isset($this->columnCache[$key])) {
            try {
                $this->columnCache[$key] = (new SchemaInspector($this->pdo()))->columnExists($table, $column);
            } catch (Throwable) {
                $this->columnCache[$key] = false;
            }
        }

        return $this->columnCache[$key];
    }

    public function hasTable(string $table): bool
    {
        $key = $table . '.*';
        if (!isset($this->columnCache[$key])) {
            try {
                $this->columnCache[$key] = (new SchemaInspector($this->pdo()))->tableExists($table);
            } catch (Throwable) {
                $this->columnCache[$key] = false;
            }
        }

        return $this->columnCache[$key];
    }

    /** Étape 3470 appliquée : fiche enrichie et archivage disponibles. */
    public function enrichedReady(): bool
    {
        return $this->hasColumn('qualification_issuers', 'archived_at');
    }

    /** Étape 3680 appliquée : pays, localisation, site, présentation anglaise et clé du catalogue en colonnes. */
    public function fieldsReady(): bool
    {
        return $this->hasColumn('qualification_issuers', 'country_code');
    }

    /** Code pays du catalogue (US, FR, NATO…) ou null. */
    public static function normalizeCountry(mixed $code): ?string
    {
        $code = strtoupper(trim((string) ($code ?? '')));

        return isset(QualificationCatalog::COUNTRIES[$code]) ? $code : null;
    }

    /**
     * Lecture de repli : détails encore écrits en lignes dans la présentation (avant l’étape 3680, ou ligne restante)
     * reportés dans les champs vides ; la présentation lue n’en garde que le texte libre.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function withFields(array $row): array
    {
        $split = IssuerDetails::split((string) ($row['description'] ?? ''));
        foreach (['country_code', 'location', 'website_url', 'description_en'] as $f) {
            if (trim((string) ($row[$f] ?? '')) === '') {
                $row[$f] = $split[$f];
            }
        }
        $row['description'] = $split['description'];
        $row['issuer_key'] = (string) ($row['issuer_key'] ?? '');
        $row['issuer_type'] = (string) ($row['issuer_type'] ?? '');

        return $row;
    }

    public function linkReady(): bool
    {
        return $this->hasColumn('personnel_qualification_definitions', 'default_issuer_id');
    }

    public function authorizationsReady(): bool
    {
        return $this->hasTable('qualification_issuer_authorizations');
    }

    public static function normalizeKind(string $kind): string
    {
        return in_array($kind, self::KINDS, true) ? $kind : 'unit';
    }

    /**
     * @param array{country?: string, kind?: string} $filters pays (code du catalogue) et type d’émetteur
     * @return list<array<string, mixed>>
     */
    public function listForTenant(int $tenantId, array $filters = []): array
    {
        if (!$this->hasTable('qualification_issuers')) {
            return [];
        }
        $unitJoin = $this->hasColumn('qualification_issuers', 'unit_id') && $this->hasTable('units');
        $sql = 'SELECT qi.*, p.name AS parent_name' . ($unitJoin ? ', un.name AS unit_name' : '') . '
                FROM qualification_issuers qi
                LEFT JOIN qualification_issuers p ON p.id = qi.parent_issuer_id AND p.tenant_id = qi.tenant_id'
            . ($unitJoin ? ' LEFT JOIN units un ON un.id = qi.unit_id AND un.tenant_id = qi.tenant_id' : '')
            . ' WHERE qi.tenant_id = ? ORDER BY qi.name ASC';
        try {
            $st = $this->pdo()->prepare($sql);
            $st->execute([$tenantId]);
            $rows = array_map([self::class, 'withFields'], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
        } catch (Throwable) {
            return [];
        }
        $country = self::normalizeCountry($filters['country'] ?? null);
        $kind = trim((string) ($filters['kind'] ?? ''));
        $kind = in_array($kind, self::KINDS, true) ? $kind : '';
        if ($country === null && $kind === '') {
            return $rows;
        }

        return array_values(array_filter($rows, static fn (array $r): bool => ($country === null || $r['country_code'] === $country)
            && ($kind === '' || (string) $r['issuer_kind'] === $kind)));
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $id): ?array
    {
        foreach ($this->listForTenant($tenantId) as $row) {
            if ((int) $row['id'] === $id) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data name, short_name, issuer_kind, parent_issuer_id, description, unit_id,
     *                                   signatory_name, signatory_title, country_code, location, website_url,
     *                                   description_en, issuer_key, issuer_type
     */
    public function create(int $tenantId, array $data, ?int $actorId = null): int
    {
        $cols = ['tenant_id', 'name', 'short_name', 'issuer_kind', 'parent_issuer_id'];
        $vals = [
            $tenantId,
            trim((string) ($data['name'] ?? '')),
            self::nullableText($data['short_name'] ?? null, 80),
            self::normalizeKind((string) ($data['issuer_kind'] ?? 'unit')),
            self::nullableId($data['parent_issuer_id'] ?? null),
        ];
        if ($this->enrichedReady()) {
            foreach ($this->detailValues($data) as $c => $v) {
                $cols[] = $c;
                $vals[] = $v;
            }
            $cols[] = 'created_by';
            $vals[] = $actorId;
        }
        if ($this->fieldsReady()) {
            $cols[] = 'issuer_key';
            $vals[] = self::nullableText($data['issuer_key'] ?? null, 80);
            $cols[] = 'issuer_type';
            $vals[] = self::nullableText($data['issuer_type'] ?? null, 30);
        }
        $st = $this->pdo()->prepare(
            'INSERT INTO qualification_issuers (' . implode(', ', $cols) . ') VALUES ('
            . implode(', ', array_fill(0, count($cols), '?')) . ')'
        );
        $st->execute($vals);

        return (int) $this->pdo()->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $tenantId, int $id, array $data): bool
    {
        $parent = self::nullableId($data['parent_issuer_id'] ?? null);
        if ($parent === $id) {
            $parent = null;
        }
        $set = ['name = ?', 'short_name = ?', 'issuer_kind = ?', 'parent_issuer_id = ?'];
        $vals = [
            trim((string) ($data['name'] ?? '')),
            self::nullableText($data['short_name'] ?? null, 80),
            self::normalizeKind((string) ($data['issuer_kind'] ?? 'unit')),
            $parent,
        ];
        if ($this->enrichedReady()) {
            foreach ($this->detailValues($data) as $c => $v) {
                $set[] = $c . ' = ?';
                $vals[] = $v;
            }
        }
        $vals[] = $tenantId;
        $vals[] = $id;
        $st = $this->pdo()->prepare('UPDATE qualification_issuers SET ' . implode(', ', $set) . ' WHERE tenant_id = ? AND id = ?');

        return $st->execute($vals);
    }

    public function setArchived(int $tenantId, int $id, bool $archived): bool
    {
        if (!$this->enrichedReady()) {
            return false;
        }
        $st = $this->pdo()->prepare(
            'UPDATE qualification_issuers SET archived_at = ' . ($archived ? 'NOW()' : 'NULL') . ' WHERE tenant_id = ? AND id = ?'
        );

        return $st->execute([$tenantId, $id]);
    }

    public function setLogo(int $tenantId, int $id, string $relativePath): bool
    {
        if (!$this->enrichedReady()) {
            return false;
        }
        $st = $this->pdo()->prepare('UPDATE qualification_issuers SET logo_media_path = ? WHERE tenant_id = ? AND id = ?');

        return $st->execute([$relativePath, $tenantId, $id]);
    }

    /**
     * Chiffres par émetteur : délivrances (toutes, 90 derniers jours), qualifications différentes délivrées,
     * dernière délivrance.
     *
     * @return array<int, array{total: int, recent: int, definitions: int, last_at: string}>
     */
    public function awardStats(int $tenantId): array
    {
        if (!$this->hasColumn('personnel_qualifications', 'issuer_id')) {
            return [];
        }
        try {
            $st = $this->pdo()->prepare(
                'SELECT pq.issuer_id,
                        COUNT(*) AS total,
                        SUM(CASE WHEN COALESCE(pq.obtained_at, pq.created_at) >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN 1 ELSE 0 END) AS recent,
                        COUNT(DISTINCT pq.definition_id) AS definitions,
                        MAX(COALESCE(pq.obtained_at, pq.created_at)) AS last_at
                 FROM personnel_qualifications pq
                 JOIN qualification_issuers qi ON qi.id = pq.issuer_id AND qi.tenant_id = ?
                 GROUP BY pq.issuer_id'
            );
            $st->execute([$tenantId]);
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $out[(int) $r['issuer_id']] = [
                    'total' => (int) $r['total'],
                    'recent' => (int) $r['recent'],
                    'definitions' => (int) $r['definitions'],
                    'last_at' => (string) ($r['last_at'] ?? ''),
                ];
            }

            return $out;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Qualifications liées à chaque émetteur : rattachées (émetteur habituel) ou déjà délivrées en son nom.
     *
     * @return array<int, array<int, array{id: int, name: string, code: string, attached: bool, delivered: int, archived: bool}>>
     */
    public function definitionsByIssuer(int $tenantId): array
    {
        $out = [];
        try {
            if ($this->linkReady()) {
                $st = $this->pdo()->prepare(
                    'SELECT id, name, code, default_issuer_id, archived_at FROM personnel_qualification_definitions
                     WHERE tenant_id = ? AND default_issuer_id IS NOT NULL ORDER BY name ASC'
                );
                $st->execute([$tenantId]);
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                    $out[(int) $r['default_issuer_id']][(int) $r['id']] = [
                        'id' => (int) $r['id'],
                        'name' => (string) $r['name'],
                        'code' => (string) ($r['code'] ?? ''),
                        'attached' => true,
                        'delivered' => 0,
                        'archived' => !empty($r['archived_at']),
                    ];
                }
            }
            if ($this->hasColumn('personnel_qualifications', 'issuer_id')) {
                $st = $this->pdo()->prepare(
                    'SELECT pq.issuer_id, d.id, d.name, d.code, d.archived_at, COUNT(*) AS n
                     FROM personnel_qualifications pq
                     JOIN qualification_issuers qi ON qi.id = pq.issuer_id AND qi.tenant_id = ?
                     JOIN personnel_qualification_definitions d ON d.id = pq.definition_id
                     GROUP BY pq.issuer_id, d.id, d.name, d.code, d.archived_at
                     ORDER BY d.name ASC'
                );
                $st->execute([$tenantId]);
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                    $iid = (int) $r['issuer_id'];
                    $did = (int) $r['id'];
                    if (isset($out[$iid][$did])) {
                        $out[$iid][$did]['delivered'] = (int) $r['n'];
                        continue;
                    }
                    $out[$iid][$did] = [
                        'id' => $did,
                        'name' => (string) $r['name'],
                        'code' => (string) ($r['code'] ?? ''),
                        'attached' => false,
                        'delivered' => (int) $r['n'],
                        'archived' => !empty($r['archived_at']),
                    ];
                }
            }
        } catch (Throwable) {
        }

        return $out;
    }

    /**
     * Rattache exactement ces qualifications à l’émetteur (émetteur habituel) ; celles qu’on décoche reviennent
     * sans émetteur habituel. Les délivrances passées ne changent pas.
     *
     * @param list<int> $definitionIds
     */
    public function syncDefinitions(int $tenantId, int $issuerId, array $definitionIds): void
    {
        if (!$this->linkReady()) {
            return;
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $definitionIds), static fn (int $i): bool => $i > 0)));
        $clear = 'UPDATE personnel_qualification_definitions SET default_issuer_id = NULL WHERE tenant_id = ? AND default_issuer_id = ?';
        $params = [$tenantId, $issuerId];
        if ($ids !== []) {
            $clear .= ' AND id NOT IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            $params = array_merge($params, $ids);
        }
        $this->pdo()->prepare($clear)->execute($params);
        if ($ids !== []) {
            $this->pdo()->prepare(
                'UPDATE personnel_qualification_definitions SET default_issuer_id = ?
                 WHERE tenant_id = ? AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'
            )->execute(array_merge([$issuerId, $tenantId], $ids));
        }
    }

    /** Émetteur habituel d’une seule qualification (null : aucun). Les délivrances passées ne changent pas. */
    public function setDefaultIssuer(int $tenantId, int $definitionId, ?int $issuerId): void
    {
        if (!$this->linkReady() || $definitionId < 1) {
            return;
        }
        $this->pdo()->prepare('UPDATE personnel_qualification_definitions SET default_issuer_id = ? WHERE tenant_id = ? AND id = ?')
            ->execute([$issuerId !== null && $issuerId > 0 ? $issuerId : null, $tenantId, $definitionId]);
    }

    /** Émetteur habituel d’une qualification, s’il est actif. */
    public function defaultIssuerFor(int $tenantId, int $definitionId): ?int
    {
        if (!$this->linkReady()) {
            return null;
        }
        try {
            $st = $this->pdo()->prepare(
                'SELECT qi.id FROM personnel_qualification_definitions d
                 JOIN qualification_issuers qi ON qi.id = d.default_issuer_id AND qi.tenant_id = d.tenant_id
                 WHERE d.tenant_id = ? AND d.id = ?' . ($this->enrichedReady() ? ' AND qi.archived_at IS NULL' : '') . ' LIMIT 1'
            );
            $st->execute([$tenantId, $definitionId]);
            $id = (int) $st->fetchColumn();

            return $id > 0 ? $id : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Dernières délivrances au nom de l’émetteur.
     *
     * @return list<array<string, mixed>>
     */
    public function recentAwards(int $tenantId, int $issuerId, int $limit = 12): array
    {
        if (!$this->hasColumn('personnel_qualifications', 'issuer_id')) {
            return [];
        }
        try {
            $st = $this->pdo()->prepare(
                'SELECT pq.id, pq.user_id, pq.definition_id, pq.obtained_at, pq.created_at, pq.certificate_number,
                        pq.certificate_document_path, COALESCE(pq.admin_status, pq.status) AS status_code,
                        d.name AS definition_name, u.display_name, u.callsign
                 FROM personnel_qualifications pq
                 LEFT JOIN personnel_qualification_definitions d ON d.id = pq.definition_id
                 JOIN qualification_issuers qi ON qi.id = pq.issuer_id AND qi.tenant_id = ?
                 LEFT JOIN users u ON u.id = pq.user_id
                 WHERE pq.issuer_id = ?
                 ORDER BY COALESCE(pq.obtained_at, pq.created_at) DESC, pq.id DESC
                 LIMIT ' . max(1, min(50, $limit))
            );
            $st->execute([$tenantId, $issuerId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Habilités, par émetteur.
     *
     * @return array<int, list<array<string, mixed>>>
     */
    public function authorizationsByIssuer(int $tenantId): array
    {
        if (!$this->authorizationsReady()) {
            return [];
        }
        $jobJoin = $this->hasTable('personnel_job_roles');
        try {
            $st = $this->pdo()->prepare(
                'SELECT a.*, u.display_name, u.callsign' . ($jobJoin ? ', jr.name AS job_role_name' : ', NULL AS job_role_name') . '
                 FROM qualification_issuer_authorizations a
                 LEFT JOIN users u ON u.id = a.user_id'
                . ($jobJoin ? ' LEFT JOIN personnel_job_roles jr ON jr.id = a.job_role_id' : '') . '
                 WHERE a.tenant_id = ?
                 ORDER BY a.role DESC, a.id ASC'
            );
            $st->execute([$tenantId]);
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $out[(int) $r['issuer_id']][] = $r;
            }

            return $out;
        } catch (Throwable) {
            return [];
        }
    }

    /** Ajoute une habilitation (membre ou fonction) ; ne crée pas de doublon. */
    public function addAuthorization(int $tenantId, int $issuerId, ?int $userId, ?int $jobRoleId, string $role, ?int $actorId): bool
    {
        if (!$this->authorizationsReady() || (($userId ?? 0) <= 0 && ($jobRoleId ?? 0) <= 0)) {
            return false;
        }
        $role = in_array($role, self::ROLES, true) ? $role : 'instructor';
        $userId = ($userId ?? 0) > 0 ? $userId : null;
        $jobRoleId = $userId === null && ($jobRoleId ?? 0) > 0 ? $jobRoleId : null;
        $st = $this->pdo()->prepare(
            'SELECT id FROM qualification_issuer_authorizations
             WHERE tenant_id = ? AND issuer_id = ? AND role = ?
               AND ' . ($userId !== null ? 'user_id = ?' : 'user_id IS NULL AND job_role_id = ?') . ' LIMIT 1'
        );
        $st->execute([$tenantId, $issuerId, $role, $userId ?? $jobRoleId]);
        if ($st->fetchColumn()) {
            return false;
        }
        $this->pdo()->prepare(
            'INSERT INTO qualification_issuer_authorizations (tenant_id, issuer_id, user_id, job_role_id, role, created_by)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$tenantId, $issuerId, $userId, $jobRoleId, $role, $actorId]);

        return true;
    }

    public function removeAuthorization(int $tenantId, int $issuerId, int $authorizationId): bool
    {
        if (!$this->authorizationsReady()) {
            return false;
        }
        $st = $this->pdo()->prepare('DELETE FROM qualification_issuer_authorizations WHERE tenant_id = ? AND issuer_id = ? AND id = ?');
        $st->execute([$tenantId, $issuerId, $authorizationId]);

        return $st->rowCount() > 0;
    }

    /** @return list<array{id: int, name: string}> */
    public function unitOptions(int $tenantId): array
    {
        if (!$this->hasTable('units')) {
            return [];
        }
        try {
            $st = $this->pdo()->prepare('SELECT id, name FROM units WHERE tenant_id = ? ORDER BY name ASC');
            $st->execute([$tenantId]);

            return array_map(
                static fn (array $r): array => ['id' => (int) $r['id'], 'name' => (string) $r['name']],
                $st->fetchAll(PDO::FETCH_ASSOC) ?: []
            );
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function detailValues(array $data): array
    {
        $out = [
            'description' => self::nullableText($data['description'] ?? null, 2000),
            'unit_id' => self::nullableId($data['unit_id'] ?? null),
            'signatory_name' => self::nullableText($data['signatory_name'] ?? null, 150),
            'signatory_title' => self::nullableText($data['signatory_title'] ?? null, 150),
        ];
        if ($this->fieldsReady()) {
            $website = trim((string) ($data['website_url'] ?? ''));
            $out += [
                'country_code' => self::normalizeCountry($data['country_code'] ?? null),
                'location' => self::nullableText($data['location'] ?? null, 200),
                'website_url' => $website !== '' && IssuerDetails::validWebsite($website) ? mb_substr($website, 0, 300) : null,
                'description_en' => self::nullableText($data['description_en'] ?? null, 2000),
            ];
        } else {
            // Schéma antérieur à l’étape 3680 : détails écrits en lignes dans la présentation (repli déprécié).
            $out['description'] = self::nullableText(IssuerDetails::compose([
                'description' => $data['description'] ?? '',
                'description_en' => $data['description_en'] ?? '',
                'country' => $data['country_code'] ?? '',
                'location' => $data['location'] ?? '',
                'website' => $data['website_url'] ?? '',
            ]), 2000);
        }

        return $out;
    }

    private static function nullableText(mixed $v, int $max): ?string
    {
        $v = trim((string) ($v ?? ''));

        return $v === '' ? null : mb_substr($v, 0, $max, 'UTF-8');
    }

    private static function nullableId(mixed $v): ?int
    {
        $v = (int) ($v ?? 0);

        return $v > 0 ? $v : null;
    }
}
