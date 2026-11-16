<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use App\Core\Database;
use App\Services\Organization\GradeLevel;
use App\Services\Personnel\JobRoleEnrichmentService;
use App\Support\JobRoleIconLibrary;
use App\Support\Migrations\SchemaInspector;
use App\Support\RichTextLite;
use App\Support\SqlText;
use InvalidArgumentException;
use PDO;

/**
 * Fiche descriptive d'un niveau d'accès (table roles, Effectifs > Accès) : abrégé, description détaillée, icône de la
 * bibliothèque des fonctions ou logo téléversé, étiquettes (catalogue partagé avec les fonctions), qualifications du
 * référentiel requises / recommandées / délivrées, emplois associés, plage de grades (niveaux OTAN de l'adéquation de
 * l'organisation), ordre d'affichage et archivage.
 *
 * Rien ici ne change les droits d'un niveau ni ses titulaires : la fiche décrit, elle n'attribue rien. Le nom, la description
 * courte et la couleur restent dans roles (RoleRepository). Sans l'étape 4870 (tables absentes), ready() est faux : rien n'est
 * lu ni écrit et la page garde ses champs d'origine.
 */
final class AccessRoleProfileService
{
    public const KIND_REQUIRED = 'required';
    public const KIND_RECOMMENDED = 'recommended';
    public const KIND_GRANTED = 'granted';

    /** Lien d'une qualification au niveau. */
    public const KINDS = [
        self::KIND_REQUIRED => 'Requise',
        self::KIND_RECOMMENDED => 'Recommandée',
        self::KIND_GRANTED => 'Délivrée par ce niveau',
    ];

    public const DETAILS_MAX = 4000;
    public const MAX_QUALIFICATIONS = 30;
    public const MAX_JOBS = 30;
    public const SORT_MIN = -999;
    public const SORT_MAX = 999;

    /** Champs comptés par l'indicateur de complétude. */
    public const COMPLETENESS_FIELDS = [
        'description' => 'Description courte',
        'details' => 'Description détaillée',
        'abbreviation' => 'Abrégé',
        'visual' => 'Icône ou logo',
        'tags' => 'Étiquettes',
        'qualifications' => 'Qualifications',
    ];

    public const NOT_READY = 'L’abrégé, la description détaillée, l’icône ou le logo, les étiquettes, les qualifications, les emplois, les grades, l’ordre et l’archivage demandent l’étape de base de données « Niveaux d’accès : fiche descriptive », encore à appliquer. Le nom, la description courte et la couleur restent modifiables.';

    private const TABLES = ['access_role_profiles', 'access_role_tag_links', 'access_role_qualifications', 'access_role_jobs'];

    /** @var array<string, bool> */
    private static array $schemaCache = [];

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    public static function resetCache(): void
    {
        self::$schemaCache = [];
    }

    private function tableReady(string $table): bool
    {
        $key = spl_object_id($this->pdo) . ':' . $table;
        if (!isset(self::$schemaCache[$key])) {
            try {
                self::$schemaCache[$key] = (new SchemaInspector($this->pdo))->tableExists($table);
            } catch (\Throwable) {
                self::$schemaCache[$key] = false;
            }
        }

        return self::$schemaCache[$key];
    }

    /** Étape 4870 passée : la fiche est lisible et enregistrable. */
    public function ready(): bool
    {
        foreach (self::TABLES as $table) {
            if (!$this->tableReady($table)) {
                return false;
            }
        }

        return true;
    }

    /** Catalogue des étiquettes des fonctions présent (étape 3360) : les étiquettes sont partagées avec lui. */
    public function tagsReady(): bool
    {
        return $this->ready() && $this->tableReady('personnel_job_role_tags');
    }

    /** @return array<string, mixed> fiche vide (niveau sans fiche ou étape absente) */
    public static function emptyProfile(): array
    {
        return [
            'abbreviation' => null, 'details' => '', 'icon' => null, 'logo' => null, 'logo_version' => 0,
            'grade_min' => null, 'grade_max' => null, 'sort_order' => 0, 'archived' => false, 'archived_at' => null,
            'tags' => [], 'qualifications' => [self::KIND_REQUIRED => [], self::KIND_RECOMMENDED => [], self::KIND_GRANTED => []],
            'jobs' => [],
        ];
    }

    /**
     * Fiches des niveaux de la communauté, par identifiant de niveau (seuls les niveaux qui en ont une).
     *
     * @return array<int, array<string, mixed>>
     */
    public function profiles(int $tenantId): array
    {
        if ($tenantId < 1 || !$this->ready()) {
            return [];
        }
        $out = [];
        $st = $this->pdo->prepare('SELECT * FROM access_role_profiles WHERE tenant_id = ?');
        $st->execute([$tenantId]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rid = (int) $row['role_id'];
            $icon = trim((string) ($row['icon'] ?? ''));
            $abbr = trim((string) ($row['abbreviation'] ?? ''));
            $min = $row['grade_min_level'] !== null ? (int) $row['grade_min_level'] : null;
            $max = $row['grade_max_level'] !== null ? (int) $row['grade_max_level'] : null;
            $out[$rid] = [
                'abbreviation' => $abbr !== '' ? $abbr : null,
                'details' => (string) ($row['details'] ?? ''),
                'icon' => JobRoleIconLibrary::has($icon) ? $icon : null,
                'logo' => trim((string) ($row['logo_path'] ?? '')) !== '' ? (string) $row['logo_path'] : null,
                'logo_version' => (int) ($row['logo_version'] ?? 0),
                'grade_min' => $min !== null && $min >= GradeLevel::MIN && $min <= GradeLevel::MAX ? $min : null,
                'grade_max' => $max !== null && $max >= GradeLevel::MIN && $max <= GradeLevel::MAX ? $max : null,
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'archived' => trim((string) ($row['archived_at'] ?? '')) !== '',
                'archived_at' => $row['archived_at'] ?? null,
            ] + self::emptyProfile();
        }
        $ensure = static function (int $rid) use (&$out): void {
            $out[$rid] ??= self::emptyProfile();
        };
        if ($this->tableReady('personnel_job_role_tags')) {
            $tq = $this->pdo->prepare(
                'SELECT l.role_id, t.id, t.name, t.color FROM access_role_tag_links l
                 INNER JOIN personnel_job_role_tags t ON t.id = l.tag_id AND t.tenant_id = l.tenant_id
                 WHERE l.tenant_id = ? ORDER BY t.name'
            );
            $tq->execute([$tenantId]);
            foreach ($tq->fetchAll(PDO::FETCH_ASSOC) as $t) {
                $ensure((int) $t['role_id']);
                $out[(int) $t['role_id']]['tags'][] = ['id' => (int) $t['id'], 'name' => (string) $t['name'], 'color' => JobRoleEnrichmentService::normalizeTagColor($t['color'] ?? null)];
            }
        }
        $qq = $this->pdo->prepare('SELECT role_id, qualification_id, kind FROM access_role_qualifications WHERE tenant_id = ? ORDER BY qualification_id');
        $qq->execute([$tenantId]);
        foreach ($qq->fetchAll(PDO::FETCH_ASSOC) as $q) {
            $kind = (string) $q['kind'];
            if (!isset(self::KINDS[$kind])) {
                continue;
            }
            $ensure((int) $q['role_id']);
            $out[(int) $q['role_id']]['qualifications'][$kind][] = (int) $q['qualification_id'];
        }
        $jq = $this->pdo->prepare('SELECT role_id, job_role_id FROM access_role_jobs WHERE tenant_id = ? ORDER BY job_role_id');
        $jq->execute([$tenantId]);
        foreach ($jq->fetchAll(PDO::FETCH_ASSOC) as $j) {
            $ensure((int) $j['role_id']);
            $out[(int) $j['role_id']]['jobs'][] = (int) $j['job_role_id'];
        }

        return $out;
    }

    /**
     * Qualifications du référentiel de la communauté (identifiant => nom), archivées comprises pour garder un lien existant lisible.
     *
     * @return array<int, array{name: string, archived: bool}>
     */
    public function qualificationCatalog(int $tenantId): array
    {
        if ($tenantId < 1 || !$this->tableReady('personnel_qualification_definitions')) {
            return [];
        }
        $rows = [];
        try {
            $st = $this->pdo->prepare('SELECT id, name, archived_at, is_active FROM personnel_qualification_definitions WHERE tenant_id = ? ORDER BY name');
            $st->execute([$tenantId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            $st = $this->pdo->prepare('SELECT id, name FROM personnel_qualification_definitions WHERE tenant_id = ? ORDER BY name');
            $st->execute([$tenantId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        }
        $out = [];
        foreach ($rows as $r) {
            $name = trim((string) ($r['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $archived = trim((string) ($r['archived_at'] ?? '')) !== '' || (array_key_exists('is_active', $r) && $r['is_active'] !== null && (int) $r['is_active'] === 0);
            $out[(int) $r['id']] = ['name' => $name, 'archived' => $archived];
        }

        return $out;
    }

    /** @return array<int, string> emplois de la communauté (identifiant => nom) */
    public function jobCatalog(int $tenantId): array
    {
        if ($tenantId < 1 || !$this->tableReady('personnel_job_roles')) {
            return [];
        }
        $st = $this->pdo->prepare('SELECT id, name FROM personnel_job_roles WHERE tenant_id = ? ORDER BY name');
        $st->execute([$tenantId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $name = trim((string) ($r['name'] ?? ''));
            if ($name !== '') {
                $out[(int) $r['id']] = $name;
            }
        }

        return $out;
    }

    /**
     * Étiquettes du catalogue partagé (suggestions), avec leur emploi par les niveaux.
     *
     * @return list<array{id: int, name: string, color: ?string, usage: int, roles: int}>
     */
    public function tagCatalog(int $tenantId): array
    {
        if (!$this->tagsReady()) {
            return [];
        }
        $byRole = [];
        $st = $this->pdo->prepare('SELECT tag_id, COUNT(role_id) FROM access_role_tag_links WHERE tenant_id = ? GROUP BY tag_id');
        $st->execute([$tenantId]);
        foreach ($st->fetchAll(PDO::FETCH_NUM) as [$tagId, $n]) {
            $byRole[(int) $tagId] = (int) $n;
        }
        $out = [];
        foreach ((new JobRoleEnrichmentService($this->pdo))->tags($tenantId) as $tag) {
            $out[] = $tag + ['roles' => $byRole[$tag['id']] ?? 0];
        }

        return $out;
    }

    /**
     * Valide la fiche saisie (roleId = 0 à la création). Les identifiants de qualification et d'emploi doivent appartenir à la
     * communauté ; une valeur inconnue est refusée, jamais enregistrée.
     *
     * @param array<string, mixed> $input
     * @return array{abbreviation: ?string, details: string, icon: ?string, tags: list<string>, qualifications: array<int, string>,
     *   jobs: list<int>, grade_min: ?int, grade_max: ?int, sort_order: int}
     */
    public function validate(int $tenantId, int $roleId, array $input): array
    {
        $abbreviation = JobRoleEnrichmentService::normalizeAbbreviation($input['abbreviation'] ?? null);
        $details = self::scalar($input['details'] ?? '');
        $details = trim(str_replace(["\r\n", "\r"], "\n", $details));
        if (mb_strlen($details, 'UTF-8') > self::DETAILS_MAX) {
            throw new InvalidArgumentException('La description détaillée est limitée à ' . self::DETAILS_MAX . ' caractères.');
        }
        $icon = trim(self::scalar($input['icon'] ?? ''));
        if ($icon !== '' && !JobRoleIconLibrary::has($icon)) {
            throw new InvalidArgumentException('Choisissez une icône de la bibliothèque.');
        }
        $tags = JobRoleEnrichmentService::normalizeTagNames($input['tags'] ?? null);

        $qualifications = [];
        $rawQuals = is_array($input['qualifications'] ?? null) ? $input['qualifications'] : [];
        if ($rawQuals !== []) {
            $catalog = $this->qualificationCatalog($tenantId);
            foreach ($rawQuals as $qid => $kind) {
                $kind = self::scalar($kind);
                if ($kind === '' || $kind === 'none') {
                    continue;
                }
                if (!isset(self::KINDS[$kind])) {
                    throw new InvalidArgumentException('Lien de qualification inconnu : choisissez Requise, Recommandée ou Délivrée.');
                }
                if (!isset($catalog[(int) $qid])) {
                    throw new InvalidArgumentException('Une qualification choisie n’existe pas dans le référentiel de votre communauté.');
                }
                $qualifications[(int) $qid] = $kind;
            }
        }
        if (count($qualifications) > self::MAX_QUALIFICATIONS) {
            throw new InvalidArgumentException('Un niveau peut être lié à ' . self::MAX_QUALIFICATIONS . ' qualifications au plus.');
        }

        $jobs = [];
        $rawJobs = is_array($input['jobs'] ?? null) ? $input['jobs'] : [];
        if ($rawJobs !== []) {
            $catalog = $this->jobCatalog($tenantId);
            foreach ($rawJobs as $jid) {
                $jid = (int) self::scalar($jid);
                if ($jid < 1) {
                    continue;
                }
                if (!isset($catalog[$jid])) {
                    throw new InvalidArgumentException('Un emploi choisi n’existe pas dans votre communauté.');
                }
                $jobs[$jid] = true;
            }
        }
        if (count($jobs) > self::MAX_JOBS) {
            throw new InvalidArgumentException('Un niveau peut être associé à ' . self::MAX_JOBS . ' emplois au plus.');
        }

        $min = self::level($input['grade_min'] ?? null);
        $max = self::level($input['grade_max'] ?? null);
        if ($min !== null && $max !== null && $min > $max) {
            throw new InvalidArgumentException('Le grade minimum doit être inférieur ou égal au grade maximum.');
        }
        $sortRaw = trim(self::scalar($input['sort_order'] ?? ''));
        if ($sortRaw !== '' && preg_match('/^-?\d{1,4}$/', $sortRaw) !== 1) {
            throw new InvalidArgumentException('L’ordre d’affichage est un nombre entier (ex. 10, 20, 30).');
        }
        $sort = max(self::SORT_MIN, min(self::SORT_MAX, (int) $sortRaw));

        if ($abbreviation !== null && $this->ready()) {
            $st = $this->pdo->prepare('SELECT role_id FROM access_role_profiles WHERE tenant_id = ? AND role_id <> ? AND ' . SqlText::equals($this->pdo, 'abbreviation') . ' LIMIT 1');
            $st->execute([$tenantId, $roleId, $abbreviation]);
            if ($st->fetchColumn() !== false) {
                throw new InvalidArgumentException('L’abrégé « ' . $abbreviation . ' » est déjà utilisé par un autre niveau de votre communauté.');
            }
        }

        return [
            'abbreviation' => $abbreviation, 'details' => $details, 'icon' => $icon !== '' ? $icon : null, 'tags' => $tags,
            'qualifications' => $qualifications, 'jobs' => array_keys($jobs), 'grade_min' => $min, 'grade_max' => $max, 'sort_order' => $sort,
        ];
    }

    /**
     * Enregistre la fiche d'un niveau DE LA COMMUNAUTÉ (rien n'est écrit pour un niveau d'une autre communauté).
     *
     * @param array{abbreviation: ?string, details: string, icon: ?string, tags: list<string>, qualifications: array<int, string>,
     *   jobs: list<int>, grade_min: ?int, grade_max: ?int, sort_order: int} $data
     */
    public function save(int $tenantId, int $roleId, array $data, int $userId): bool
    {
        if (!$this->ready() || !$this->roleInTenant($tenantId, $roleId)) {
            return false;
        }
        $now = date('Y-m-d H:i:s');
        $this->pdo->beginTransaction();
        try {
            $this->ensureRow($tenantId, $roleId);
            $this->pdo->prepare(
                'UPDATE access_role_profiles SET abbreviation = ?, details = ?, icon = ?, grade_min_level = ?, grade_max_level = ?, sort_order = ?,
                 updated_at = ?, updated_by = ? WHERE role_id = ? AND tenant_id = ?'
            )->execute([
                $data['abbreviation'], $data['details'] !== '' ? $data['details'] : null, $data['icon'], $data['grade_min'], $data['grade_max'],
                $data['sort_order'], $now, $userId > 0 ? $userId : null, $roleId, $tenantId,
            ]);
            if ($this->tagsReady()) {
                $this->replaceTags($tenantId, $roleId, $data['tags']);
            }
            $this->pdo->prepare('DELETE FROM access_role_qualifications WHERE tenant_id = ? AND role_id = ?')->execute([$tenantId, $roleId]);
            $iq = $this->pdo->prepare('INSERT INTO access_role_qualifications (tenant_id, role_id, qualification_id, kind) VALUES (?, ?, ?, ?)');
            foreach ($data['qualifications'] as $qid => $kind) {
                $iq->execute([$tenantId, $roleId, (int) $qid, $kind]);
            }
            $this->pdo->prepare('DELETE FROM access_role_jobs WHERE tenant_id = ? AND role_id = ?')->execute([$tenantId, $roleId]);
            $ij = $this->pdo->prepare('INSERT INTO access_role_jobs (tenant_id, role_id, job_role_id) VALUES (?, ?, ?)');
            foreach ($data['jobs'] as $jid) {
                $ij->execute([$tenantId, $roleId, (int) $jid]);
            }
            $this->pdo->commit();
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            if ((string) $e->getCode() === '23000') {
                throw new InvalidArgumentException('Cet abrégé est déjà utilisé par un autre niveau de votre communauté.');
            }
            throw $e;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return true;
    }

    /** Archive (masque sans supprimer) ou désarchive un niveau de la communauté. */
    public function setArchived(int $tenantId, int $roleId, bool $archived, int $userId): bool
    {
        if (!$this->ready() || !$this->roleInTenant($tenantId, $roleId)) {
            return false;
        }
        $this->ensureRow($tenantId, $roleId);
        $st = $this->pdo->prepare('UPDATE access_role_profiles SET archived_at = ?, archived_by = ?, updated_at = ?, updated_by = ? WHERE role_id = ? AND tenant_id = ?');
        $now = date('Y-m-d H:i:s');
        $st->execute([$archived ? $now : null, $archived ? ($userId > 0 ? $userId : null) : null, $now, $userId > 0 ? $userId : null, $roleId, $tenantId]);

        return true;
    }

    /** Recopie la fiche d'un niveau sur un autre (duplication) : ni l'abrégé (unique) ni le logo (fichier propre) ni l'archivage. */
    public function copy(int $tenantId, int $fromRoleId, int $toRoleId, int $userId): bool
    {
        if (!$this->ready() || $fromRoleId === $toRoleId) {
            return false;
        }
        $src = $this->profiles($tenantId)[$fromRoleId] ?? null;
        if ($src === null) {
            return false;
        }
        $quals = [];
        foreach ($src['qualifications'] as $kind => $ids) {
            foreach ($ids as $qid) {
                $quals[(int) $qid] = (string) $kind;
            }
        }

        return $this->save($tenantId, $toRoleId, [
            'abbreviation' => null, 'details' => (string) $src['details'], 'icon' => $src['icon'], 'tags' => array_column($src['tags'], 'name'),
            'qualifications' => $quals, 'jobs' => $src['jobs'], 'grade_min' => $src['grade_min'], 'grade_max' => $src['grade_max'], 'sort_order' => (int) $src['sort_order'],
        ], $userId);
    }

    /**
     * Pose le fichier de logo (nom rendu par AccessRoleLogoStore) ; renvoie l'ancien nom à effacer.
     *
     * @return array{ok: bool, previous: ?string, version: int}
     */
    public function setLogo(int $tenantId, int $roleId, ?string $file, ?string $mime, int $userId): array
    {
        if (!$this->ready() || !$this->roleInTenant($tenantId, $roleId)) {
            return ['ok' => false, 'previous' => null, 'version' => 0];
        }
        $this->ensureRow($tenantId, $roleId);
        $st = $this->pdo->prepare('SELECT logo_path, logo_version FROM access_role_profiles WHERE role_id = ? AND tenant_id = ?');
        $st->execute([$roleId, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $previous = trim((string) ($row['logo_path'] ?? ''));
        $version = (int) ($row['logo_version'] ?? 0) + 1;
        $this->pdo->prepare('UPDATE access_role_profiles SET logo_path = ?, logo_mime = ?, logo_version = ?, updated_at = ?, updated_by = ? WHERE role_id = ? AND tenant_id = ?')
            ->execute([$file, $file !== null ? $mime : null, $version, date('Y-m-d H:i:s'), $userId > 0 ? $userId : null, $roleId, $tenantId]);

        return ['ok' => true, 'previous' => $previous !== '' ? $previous : null, 'version' => $version];
    }

    /** @return array{file: string, mime: string, version: int}|null logo enregistré d'un niveau de la communauté */
    public function logoOf(int $tenantId, int $roleId): ?array
    {
        if ($tenantId < 1 || $roleId < 1 || !$this->ready()) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT logo_path, logo_mime, logo_version FROM access_role_profiles WHERE role_id = ? AND tenant_id = ?');
        $st->execute([$roleId, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || trim((string) ($row['logo_path'] ?? '')) === '') {
            return null;
        }

        return ['file' => (string) $row['logo_path'], 'mime' => (string) ($row['logo_mime'] ?? ''), 'version' => (int) ($row['logo_version'] ?? 0)];
    }

    /** Niveau supprimé : sa fiche part avec lui. Renvoie le fichier de logo à effacer. */
    public function forget(int $tenantId, int $roleId): ?string
    {
        if (!$this->ready()) {
            return null;
        }
        $logo = $this->logoOf($tenantId, $roleId);
        foreach (self::TABLES as $table) {
            $this->pdo->prepare('DELETE FROM ' . $table . ' WHERE tenant_id = ? AND role_id = ?')->execute([$tenantId, $roleId]);
        }

        return $logo['file'] ?? null;
    }

    /**
     * Complétude d'une fiche : champs renseignés sur les six suivis (COMPLETENESS_FIELDS).
     *
     * @param array<string, mixed> $role ligne de roles
     * @param array<string, mixed> $profile fiche (profiles() ou emptyProfile())
     * @return array{done: int, total: int, missing: list<string>}
     */
    public static function completeness(array $role, array $profile): array
    {
        $quals = 0;
        foreach ((array) ($profile['qualifications'] ?? []) as $ids) {
            $quals += count((array) $ids);
        }
        $filled = [
            'description' => trim((string) ($role['description'] ?? '')) !== '',
            'details' => trim((string) ($profile['details'] ?? '')) !== '',
            'abbreviation' => trim((string) ($profile['abbreviation'] ?? '')) !== '',
            'visual' => !empty($profile['icon']) || !empty($profile['logo']),
            'tags' => ((array) ($profile['tags'] ?? [])) !== [],
            'qualifications' => $quals > 0,
        ];
        $missing = [];
        foreach ($filled as $key => $ok) {
            if (!$ok) {
                $missing[] = self::COMPLETENESS_FIELDS[$key];
            }
        }

        return ['done' => count(array_filter($filled)), 'total' => count($filled), 'missing' => $missing];
    }

    /** Description détaillée rendue (texte riche à liste blanche, rien du HTML saisi n'est gardé). */
    public static function detailsHtml(string $details): string
    {
        return trim($details) === '' ? '' : RichTextLite::render($details);
    }

    /** Libellé d'une plage de grades d'après les grades de la communauté (même rendu que l'adéquation de l'organisation). */
    public static function gradeRangeLabel(?int $min, ?int $max, array $labelsByLevel = []): string
    {
        if ($min === null && $max === null) {
            return '';
        }
        if ($min !== null && $max !== null) {
            return GradeLevel::rangeLabel($min, $max, $labelsByLevel);
        }
        if ($min !== null) {
            return 'À partir de ' . GradeLevel::rangeLabel($min, $min, $labelsByLevel);
        }

        return 'Jusqu’à ' . GradeLevel::rangeLabel((int) $max, (int) $max, $labelsByLevel);
    }

    private function roleInTenant(int $tenantId, int $roleId): bool
    {
        if ($tenantId < 1 || $roleId < 1) {
            return false;
        }
        $st = $this->pdo->prepare('SELECT 1 FROM roles WHERE id = ? AND tenant_id = ?');
        $st->execute([$roleId, $tenantId]);

        return $st->fetchColumn() !== false;
    }

    private function ensureRow(int $tenantId, int $roleId): void
    {
        $st = $this->pdo->prepare('SELECT 1 FROM access_role_profiles WHERE role_id = ?');
        $st->execute([$roleId]);
        if ($st->fetchColumn() === false) {
            $this->pdo->prepare('INSERT INTO access_role_profiles (role_id, tenant_id, logo_version, sort_order) VALUES (?, ?, 0, 0)')->execute([$roleId, $tenantId]);
        }
    }

    /** @param list<string> $names */
    private function replaceTags(int $tenantId, int $roleId, array $names): void
    {
        $existing = [];
        $st = $this->pdo->prepare('SELECT id, name FROM personnel_job_role_tags WHERE tenant_id = ?');
        $st->execute([$tenantId]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $t) {
            $existing[JobRoleEnrichmentService::tagKey((string) $t['name'])] = (int) $t['id'];
        }
        $ids = [];
        foreach ($names as $name) {
            $key = JobRoleEnrichmentService::tagKey($name);
            if (!isset($existing[$key])) {
                $this->pdo->prepare('INSERT INTO personnel_job_role_tags (tenant_id, name) VALUES (?, ?)')->execute([$tenantId, $name]);
                $existing[$key] = (int) $this->pdo->lastInsertId();
            }
            $ids[$existing[$key]] = true;
        }
        $this->pdo->prepare('DELETE FROM access_role_tag_links WHERE tenant_id = ? AND role_id = ?')->execute([$tenantId, $roleId]);
        $insert = $this->pdo->prepare('INSERT INTO access_role_tag_links (tenant_id, role_id, tag_id) VALUES (?, ?, ?)');
        foreach (array_keys($ids) as $tagId) {
            $insert->execute([$tenantId, $roleId, $tagId]);
        }
    }

    private static function scalar(mixed $v): string
    {
        return is_scalar($v) ? (string) $v : '';
    }

    private static function level(mixed $raw): ?int
    {
        $v = trim(self::scalar($raw));
        if ($v === '' || $v === '0') {
            return null;
        }
        if (preg_match('/^\d{1,2}$/', $v) !== 1 || (int) $v < GradeLevel::MIN || (int) $v > GradeLevel::MAX) {
            throw new InvalidArgumentException('Grade inconnu : choisissez un grade de la liste.');
        }

        return (int) $v;
    }
}
