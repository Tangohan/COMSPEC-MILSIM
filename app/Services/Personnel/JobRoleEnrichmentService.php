<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Core\Database;
use App\Services\Rbac\MilitaryOperationalRoleCatalog;
use App\Support\JobRoleIconLibrary;
use App\Support\Migrations\SchemaInspector;
use InvalidArgumentException;
use PDO;

/**
 * Habillage des fonctions (emplois) d’une communauté : abrégé, icône, étiquettes, origine (site / communauté / ORBAT)
 * et fonctions du référentiel du site masquées par la communauté.
 *
 * Rien ici n’attribue, ne retire ni ne déplace un membre : masquer une fonction du site la retire seulement des choix
 * proposés ; les membres qui l’occupent la gardent et l’affichent normalement.
 */
final class JobRoleEnrichmentService
{
    public const ORIGIN_SITE = 'site';
    public const ORIGIN_COMMUNITY = 'community';
    public const ORIGIN_ORBAT = 'orbat';

    public const MAX_TAGS = 8;
    public const TAG_MAX_LENGTH = 40;

    /** Teintes proposées pour une étiquette (classe CSS job-tag--{clé}). */
    public const TAG_COLORS = [
        'slate' => 'Gris',
        'red' => 'Rouge',
        'amber' => 'Ambre',
        'green' => 'Vert',
        'teal' => 'Sarcelle',
        'blue' => 'Bleu',
        'violet' => 'Violet',
        'pink' => 'Framboise',
    ];

    /** Icône par défaut d’une fonction du site, d’après son domaine du référentiel. */
    private const CATALOG_CATEGORY_ICONS = [
        'Administrations et services' => 'clipboard',
        'Aérocombat' => 'helicopter',
        'Artillerie' => 'flame',
        'Combat blindé' => 'tank',
        'Enseignement, recherche et musique' => 'graduation',
        'Forces spéciales' => 'parachute',
        'Génie de combat, BTP et NRBC' => 'hammer',
        'Infanterie' => 'crosshair',
        'Cyber, informatique et télécoms' => 'radio',
        'Logistique et transports' => 'truck',
        'Maintenance' => 'wrench',
        'Renseignement' => 'binoculars',
        'Restauration' => 'utensils',
        'Santé' => 'medical',
        'Sécurité et prévention' => 'shield',
        'Sport' => 'dumbbell',
        'Statut' => 'user',
    ];

    /** @var array<string, array<int, array<string, mixed>>> */
    private static array $decorationCache = [];

    /** @var array<string, bool> */
    private static array $schemaCache = [];

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    public static function resetCache(): void
    {
        self::$decorationCache = [];
        self::$schemaCache = [];
    }

    private function cacheKey(string $suffix): string
    {
        return spl_object_id($this->pdo) . ':' . $suffix;
    }

    /** Colonnes abbreviation / icon présentes sur personnel_job_roles. */
    public function columnsReady(): bool
    {
        $key = $this->cacheKey('columns');
        if (!isset(self::$schemaCache[$key])) {
            try {
                $schema = new SchemaInspector($this->pdo);
                self::$schemaCache[$key] = $schema->columnExists('personnel_job_roles', 'abbreviation')
                    && $schema->columnExists('personnel_job_roles', 'icon');
            } catch (\Throwable) {
                self::$schemaCache[$key] = false;
            }
        }

        return self::$schemaCache[$key];
    }

    private function tableReady(string $table): bool
    {
        $key = $this->cacheKey('table:' . $table);
        if (!isset(self::$schemaCache[$key])) {
            try {
                self::$schemaCache[$key] = (new SchemaInspector($this->pdo))->tableExists($table);
            } catch (\Throwable) {
                self::$schemaCache[$key] = false;
            }
        }

        return self::$schemaCache[$key];
    }

    public function tagsReady(): bool
    {
        return $this->tableReady('personnel_job_role_tags') && $this->tableReady('personnel_job_role_tag_links');
    }

    public function hidingReady(): bool
    {
        return $this->tableReady('personnel_job_role_catalog_hidden');
    }

    /** Étape de migration passée : tous les champs sont enregistrables. */
    public function schemaReady(): bool
    {
        return $this->columnsReady() && $this->tagsReady() && $this->hidingReady();
    }

    /**
     * Abrégé saisi : espaces retirés, majuscules, lettres et chiffres seulement, 2 à 8 caractères. Vide = aucun.
     */
    public static function normalizeAbbreviation(mixed $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        if (!is_scalar($raw)) {
            throw new InvalidArgumentException('L’abrégé est invalide.');
        }
        $value = (string) preg_replace('/[\s.\-_]+/u', '', (string) $raw);
        if ($value === '') {
            return null;
        }
        if (class_exists(\Normalizer::class)) {
            $value = (string) preg_replace('/\p{Mn}+/u', '', (string) \Normalizer::normalize($value, \Normalizer::FORM_D));
        } elseif (function_exists('iconv')) {
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            $value = is_string($ascii) ? str_replace(["'", '`', '^', '~', '"'], '', $ascii) : $value;
        }
        $value = strtoupper($value);
        if (preg_match('/^[A-Z0-9]{2,8}$/', $value) !== 1) {
            throw new InvalidArgumentException('L’abrégé doit compter 2 à 8 lettres ou chiffres (ex. CDG, MED, JTAC).');
        }

        return $value;
    }

    /**
     * Étiquettes saisies (« Médical, Pilote » ou liste) : espaces nettoyés, doublons retirés sans tenir compte de la casse.
     *
     * @return list<string>
     */
    public static function normalizeTagNames(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        $parts = is_array($raw) ? $raw : explode(',', is_scalar($raw) ? (string) $raw : '');
        $out = [];
        $seen = [];
        foreach ($parts as $part) {
            if (!is_scalar($part)) {
                continue;
            }
            $name = trim((string) preg_replace('/\s+/u', ' ', (string) $part));
            $name = trim($name, " \t#");
            if ($name === '') {
                continue;
            }
            if (mb_strlen($name, 'UTF-8') > self::TAG_MAX_LENGTH) {
                throw new InvalidArgumentException('Une étiquette est limitée à 40 caractères.');
            }
            $key = mb_strtolower($name, 'UTF-8');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $name;
        }
        if (count($out) > self::MAX_TAGS) {
            throw new InvalidArgumentException('Une fonction peut porter au plus 8 étiquettes.');
        }

        return $out;
    }

    public static function normalizeTagColor(mixed $raw): ?string
    {
        $value = is_scalar($raw) ? trim((string) $raw) : '';

        return isset(self::TAG_COLORS[$value]) ? $value : null;
    }

    /** @return array<string, array<string, mixed>> fonctions du référentiel du site, par identifiant */
    public static function catalogBySlug(): array
    {
        static $bySlug = null;
        if ($bySlug === null) {
            $bySlug = [];
            foreach (MilitaryOperationalRoleCatalog::entries() as $entry) {
                $slug = trim((string) ($entry['slug'] ?? ''));
                if ($slug !== '') {
                    $bySlug[$slug] = $entry;
                }
            }
        }

        return $bySlug;
    }

    public static function defaultIconForCatalogSlug(string $slug): ?string
    {
        $entry = self::catalogBySlug()[$slug] ?? null;
        if ($entry === null) {
            return null;
        }
        $icon = self::CATALOG_CATEGORY_ICONS[trim((string) ($entry['category'] ?? ''))] ?? null;

        return JobRoleIconLibrary::has($icon) ? $icon : null;
    }

    /**
     * Origine d’une fonction de la communauté : copiée du référentiel du site, née d’une unité de l’ORBAT, ou créée
     * par la communauté.
     *
     * @param array<string, mixed> $role
     */
    public static function origin(array $role): string
    {
        $slug = trim((string) ($role['slug'] ?? ''));
        if (!empty($role['is_system']) || ($slug !== '' && isset(self::catalogBySlug()[$slug]))) {
            return self::ORIGIN_SITE;
        }
        if (str_starts_with($slug, 'unit-') || (int) ($role['source_unit_id'] ?? 0) > 0) {
            return self::ORIGIN_ORBAT;
        }

        return self::ORIGIN_COMMUNITY;
    }

    /**
     * Valide l’abrégé, l’icône et les étiquettes saisis pour une fonction (roleId = 0 à la création).
     *
     * @param array<string, mixed> $input
     * @return array{abbreviation: ?string, icon: ?string, tags: list<string>}
     */
    public function validate(int $tenantId, int $roleId, array $input): array
    {
        $abbreviation = self::normalizeAbbreviation($input['abbreviation'] ?? null);
        $iconRaw = $input['icon'] ?? '';
        $icon = is_scalar($iconRaw) ? trim((string) $iconRaw) : '';
        if ($icon !== '' && !JobRoleIconLibrary::has($icon)) {
            throw new InvalidArgumentException('Choisissez une icône de la bibliothèque.');
        }
        $tags = self::normalizeTagNames($input['tags'] ?? null);
        if ($abbreviation !== null && $this->columnsReady()) {
            $st = $this->pdo->prepare('SELECT id FROM personnel_job_roles WHERE tenant_id = ? AND id <> ? AND ' . \App\Support\SqlText::equals($this->pdo, 'abbreviation') . ' LIMIT 1');
            $st->execute([$tenantId, $roleId, $abbreviation]);
            if ($st->fetchColumn() !== false) {
                throw new InvalidArgumentException('L’abrégé « ' . $abbreviation . ' » est déjà utilisé par une autre fonction de votre communauté.');
            }
        }

        return ['abbreviation' => $abbreviation, 'icon' => $icon !== '' ? $icon : null, 'tags' => $tags];
    }

    /**
     * @param array{abbreviation: ?string, icon: ?string, tags: list<string>} $data
     */
    public function save(int $tenantId, int $roleId, array $data): void
    {
        if ($tenantId < 1 || $roleId < 1) {
            return;
        }
        if ($this->columnsReady()) {
            try {
                $this->pdo->prepare('UPDATE personnel_job_roles SET abbreviation = ?, icon = ? WHERE id = ? AND tenant_id = ?')
                    ->execute([$data['abbreviation'], $data['icon'], $roleId, $tenantId]);
            } catch (\PDOException $e) {
                if ((string) $e->getCode() === '23000') {
                    throw new InvalidArgumentException('Cet abrégé est déjà utilisé par une autre fonction de votre communauté.');
                }
                throw $e;
            }
        }
        if ($this->tagsReady()) {
            $this->replaceRoleTags($tenantId, $roleId, $data['tags']);
        }
        self::$decorationCache = [];
    }

    /** @param list<string> $names */
    private function replaceRoleTags(int $tenantId, int $roleId, array $names): void
    {
        $existing = [];
        foreach ($this->tags($tenantId) as $tag) {
            $existing[mb_strtolower($tag['name'], 'UTF-8')] = $tag['id'];
        }
        $ids = [];
        foreach ($names as $name) {
            $key = mb_strtolower($name, 'UTF-8');
            if (!isset($existing[$key])) {
                $this->pdo->prepare('INSERT INTO personnel_job_role_tags (tenant_id, name) VALUES (?, ?)')->execute([$tenantId, $name]);
                $existing[$key] = (int) $this->pdo->lastInsertId();
            }
            $ids[$existing[$key]] = true;
        }
        $this->pdo->prepare('DELETE FROM personnel_job_role_tag_links WHERE tenant_id = ? AND job_role_id = ?')->execute([$tenantId, $roleId]);
        $insert = $this->pdo->prepare('INSERT INTO personnel_job_role_tag_links (tenant_id, job_role_id, tag_id) VALUES (?, ?, ?)');
        foreach (array_keys($ids) as $tagId) {
            $insert->execute([$tenantId, $roleId, $tagId]);
        }
    }

    /**
     * Étiquettes de la communauté (suggestions de saisie, filtres, réglages).
     *
     * @return list<array{id: int, name: string, color: ?string, usage: int}>
     */
    public function tags(int $tenantId): array
    {
        if ($tenantId < 1 || !$this->tagsReady()) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT t.id, t.name, t.color, COUNT(l.job_role_id) AS usage_count
             FROM personnel_job_role_tags t
             LEFT JOIN personnel_job_role_tag_links l ON l.tag_id = t.id AND l.tenant_id = t.tenant_id
             WHERE t.tenant_id = ? GROUP BY t.id, t.name, t.color ORDER BY t.name'
        );
        $st->execute([$tenantId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'color' => self::normalizeTagColor($row['color'] ?? null),
                'usage' => (int) $row['usage_count'],
            ];
        }

        return $out;
    }

    public function updateTagColor(int $tenantId, int $tagId, ?string $color): bool
    {
        if (!$this->tagsReady()) {
            return false;
        }
        $st = $this->pdo->prepare('UPDATE personnel_job_role_tags SET color = ? WHERE id = ? AND tenant_id = ?');
        $st->execute([self::normalizeTagColor($color), $tagId, $tenantId]);
        self::$decorationCache = [];

        return $st->rowCount() > 0;
    }

    /** Supprime l’étiquette et la retire de toutes les fonctions (les fonctions elles-mêmes restent). */
    public function deleteTag(int $tenantId, int $tagId): bool
    {
        if (!$this->tagsReady()) {
            return false;
        }
        $this->pdo->prepare('DELETE FROM personnel_job_role_tag_links WHERE tenant_id = ? AND tag_id = ?')->execute([$tenantId, $tagId]);
        $st = $this->pdo->prepare('DELETE FROM personnel_job_role_tags WHERE id = ? AND tenant_id = ?');
        $st->execute([$tagId, $tenantId]);
        self::$decorationCache = [];

        return $st->rowCount() > 0;
    }

    /** @return array<string, true> identifiants des fonctions du site masquées par la communauté */
    public function hiddenCatalogSlugs(int $tenantId): array
    {
        if ($tenantId < 1 || !$this->hidingReady()) {
            return [];
        }
        $st = $this->pdo->prepare('SELECT catalog_slug FROM personnel_job_role_catalog_hidden WHERE tenant_id = ?');
        $st->execute([$tenantId]);

        return array_fill_keys(array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN)), true);
    }

    /**
     * Masque ou réaffiche des fonctions du site pour la communauté. Seuls les identifiants du référentiel du site
     * (ou d’une fonction système de la communauté) sont acceptés ; aucune attribution n’est touchée.
     *
     * @param list<mixed> $slugs
     * @return int nombre de fonctions dont l’état a changé
     */
    public function setCatalogHidden(int $tenantId, array $slugs, bool $hidden, int $userId): int
    {
        if ($tenantId < 1 || !$this->hidingReady()) {
            return 0;
        }
        $allowed = self::catalogBySlug();
        $systemSlugs = [];
        try {
            $st = $this->pdo->prepare('SELECT slug FROM personnel_job_roles WHERE tenant_id = ? AND is_system = 1');
            $st->execute([$tenantId]);
            $systemSlugs = array_fill_keys(array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN)), true);
        } catch (\Throwable) {
        }
        $current = $this->hiddenCatalogSlugs($tenantId);
        $changed = 0;
        foreach (array_unique(array_map(static fn (mixed $s): string => is_scalar($s) ? trim((string) $s) : '', $slugs)) as $slug) {
            if ($slug === '' || (!isset($allowed[$slug]) && !isset($systemSlugs[$slug]))) {
                continue;
            }
            if ($hidden && !isset($current[$slug])) {
                $this->pdo->prepare('INSERT INTO personnel_job_role_catalog_hidden (tenant_id, catalog_slug, hidden_by, hidden_at) VALUES (?, ?, ?, ?)')
                    ->execute([$tenantId, $slug, $userId > 0 ? $userId : null, date('Y-m-d H:i:s')]);
                $changed++;
            } elseif (!$hidden && isset($current[$slug])) {
                $this->pdo->prepare('DELETE FROM personnel_job_role_catalog_hidden WHERE tenant_id = ? AND ' . \App\Support\SqlText::equals($this->pdo, 'catalog_slug'))
                    ->execute([$tenantId, $slug]);
                $changed++;
            }
        }
        self::$decorationCache = [];

        return $changed;
    }

    /**
     * Habillage de chaque fonction de la communauté, par identifiant.
     *
     * @return array<int, array{slug: string, abbreviation: ?string, icon: ?string, icon_is_default: bool, description: string,
     *   tags: list<array{id: int, name: string, color: ?string}>, origin: string, hidden: bool}>
     */
    public function decorations(int $tenantId): array
    {
        if ($tenantId < 1) {
            return [];
        }
        $key = $this->cacheKey('t' . $tenantId);
        if (isset(self::$decorationCache[$key])) {
            return self::$decorationCache[$key];
        }
        $out = [];
        try {
            $st = $this->pdo->prepare('SELECT * FROM personnel_job_roles WHERE tenant_id = ?');
            $st->execute([$tenantId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return self::$decorationCache[$key] = [];
        }
        $hidden = $this->hiddenCatalogSlugs($tenantId);
        $tagsByRole = [];
        if ($this->tagsReady()) {
            $tq = $this->pdo->prepare(
                'SELECT l.job_role_id, t.id, t.name, t.color FROM personnel_job_role_tag_links l
                 INNER JOIN personnel_job_role_tags t ON t.id = l.tag_id AND t.tenant_id = l.tenant_id
                 WHERE l.tenant_id = ? ORDER BY t.name'
            );
            $tq->execute([$tenantId]);
            foreach ($tq->fetchAll(PDO::FETCH_ASSOC) as $t) {
                $tagsByRole[(int) $t['job_role_id']][] = ['id' => (int) $t['id'], 'name' => (string) $t['name'], 'color' => self::normalizeTagColor($t['color'] ?? null)];
            }
        }
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $slug = trim((string) ($row['slug'] ?? ''));
            $origin = self::origin($row);
            $icon = trim((string) ($row['icon'] ?? ''));
            $icon = JobRoleIconLibrary::has($icon) ? $icon : null;
            $default = $icon === null && $origin === self::ORIGIN_SITE ? self::defaultIconForCatalogSlug($slug) : null;
            $abbr = trim((string) ($row['abbreviation'] ?? ''));
            $out[$id] = [
                'slug' => $slug,
                'abbreviation' => $abbr !== '' ? $abbr : null,
                'icon' => $icon ?? $default,
                'icon_is_default' => $icon === null && $default !== null,
                'description' => trim((string) ($row['description'] ?? '')),
                'tags' => $tagsByRole[$id] ?? [],
                'origin' => $origin,
                'hidden' => $origin === self::ORIGIN_SITE && isset($hidden[$slug]),
            ];
        }

        return self::$decorationCache[$key] = $out;
    }

    /** @return list<int> fonctions portant l’étiquette */
    public function roleIdsWithTag(int $tenantId, int $tagId): array
    {
        if ($tenantId < 1 || $tagId < 1 || !$this->tagsReady()) {
            return [];
        }
        $st = $this->pdo->prepare('SELECT job_role_id FROM personnel_job_role_tag_links WHERE tenant_id = ? AND tag_id = ?');
        $st->execute([$tenantId, $tagId]);

        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return array<int, true> membres qui occupent au moins une fonction portant l’étiquette */
    public function userIdsWithTag(int $tenantId, int $tagId): array
    {
        $roleIds = $this->roleIdsWithTag($tenantId, $tagId);
        if ($roleIds === []) {
            return [];
        }
        try {
            $ph = implode(',', array_fill(0, count($roleIds), '?'));
            $st = $this->pdo->prepare('SELECT DISTINCT user_id FROM personnel_profile_job_roles WHERE tenant_id = ? AND personnel_job_role_id IN (' . $ph . ')');
            $st->execute([$tenantId, ...$roleIds]);

            return array_fill_keys(array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)), true);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Nombre de membres qui occupent chaque fonction de la communauté (pour l’avertissement « masquée »).
     *
     * @return array<int, int>
     */
    public function holderCounts(int $tenantId): array
    {
        try {
            $st = $this->pdo->prepare('SELECT personnel_job_role_id, COUNT(DISTINCT user_id) FROM personnel_profile_job_roles WHERE tenant_id = ? GROUP BY personnel_job_role_id');
            $st->execute([$tenantId]);

            return array_map('intval', $st->fetchAll(PDO::FETCH_KEY_PAIR));
        } catch (\Throwable) {
            return [];
        }
    }
}
