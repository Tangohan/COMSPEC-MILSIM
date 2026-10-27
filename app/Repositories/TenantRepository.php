<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Services\Community\TenantArchiveState;
use App\Support\LazyDatabaseConnection;
use App\Support\SqlText;

use PDO;

class TenantRepository
{
    use LazyDatabaseConnection;


    /** @var bool|null cache information_schema pour tenants.tenant_type */
    private ?bool $hasTenantTypeColumn = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    private function hasTenantTypeColumn(): bool
    {
        if ($this->hasTenantTypeColumn !== null) {
            return $this->hasTenantTypeColumn;
        }
        try {
            $st = $this->pdo()->query(
                "SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenants' AND COLUMN_NAME = 'tenant_type' LIMIT 1"
            );
            $this->hasTenantTypeColumn = $st !== false && (bool) $st->fetchColumn();
        } catch (\Throwable) {
            $this->hasTenantTypeColumn = false;
        }

        return $this->hasTenantTypeColumn;
    }

    /** @var array<string, bool> */
    private array $tableReady = [];

    private function scalarIfTable(string $table, string $sql, string $fallback): string
    {
        if (!isset($this->tableReady[$table])) {
            try {
                $st = $this->pdo()->prepare(
                    'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
                );
                $st->execute([$table]);
                $this->tableReady[$table] = (bool) $st->fetchColumn();
            } catch (\Throwable) {
                $this->tableReady[$table] = false;
            }
        }

        return $this->tableReady[$table] ? '(' . $sql . ')' : $fallback;
    }

    private static function nullableDate(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        return (string) $raw;
    }

    private static function latestDate(mixed $left, mixed $right): ?string
    {
        $a = self::nullableDate($left);
        $b = self::nullableDate($right);
        if ($a === null) {
            return $b;
        }
        if ($b === null) {
            return $a;
        }

        return strtotime($a) >= strtotime($b) ? $a : $b;
    }

    /**
     * Cache de requête de findById() (singleton du Container : la même ligne est relue par les middlewares,
     * l’en-tête, la navigation, les bandeaux…). Vidé par chaque écriture de ce dépôt ; désactivé en CLI
     * (crons et workers longs).
     *
     * @var array<int, array<string, mixed>|null>
     */
    private array $findByIdCache = [];

    public function findById(int $id): ?array
    {
        $useCache = PHP_SAPI !== 'cli';
        if ($useCache && array_key_exists($id, $this->findByIdCache)) {
            return $this->findByIdCache[$id];
        }
        $stmt = $this->pdo()->prepare('SELECT * FROM tenants WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $row = $row ?: null;
        if ($useCache) {
            $this->findByIdCache[$id] = $row;
        }

        return $row;
    }

    /** Vide le cache de findById() (à appeler après une écriture directe sur tenants hors de ce dépôt). */
    public function forgetCache(): void
    {
        $this->findByIdCache = [];
    }

    /**
     * Communautés portant exactement ce nom (le jeu ne connaît que le nom affiché de la communauté liée).
     *
     * @return list<int>
     */
    public function findIdsByName(string $name, int $limit = 2): array
    {
        $stmt = $this->pdo()->prepare('SELECT id FROM tenants WHERE name = ? ORDER BY id LIMIT ' . max(1, min(10, $limit)));
        $stmt->execute([$name]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Une communauté archivée par l’administration du site n’est plus trouvée par son adresse
     * (page publique, recrutement…). L’adresse reste réservée (slugExists).
     */
    public function findBySlug(string $slug): ?array
    {
        $slugEq = SqlText::equals($this->pdo(), 'slug');
        $stmt = $this->pdo()->prepare('SELECT * FROM tenants WHERE ' . $slugEq . ' LIMIT 1');
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || TenantArchiveState::isArchived($row)) {
            return null;
        }

        return $row;
    }

    /**
     * Registre des unités / communautés (hors tenant système `default`).
     * Exclut les communautés avec `settings.community.registry_listed === false`.
     * Le tenant id = 1 n’est plus exclu : en production ce n’est pas forcément le placeholder.
     *
     * @return list<array<string, mixed>>
     */
    public function listForRegistry(): array
    {
        $stmt = $this->pdo()->query(
            'SELECT * FROM tenants ORDER BY name ASC'
        );
        if ($stmt === false) {
            return [];
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            if (strtolower(trim((string) ($row['slug'] ?? ''))) === 'default' || TenantArchiveState::isArchived($row)) {
                continue;
            }
            $community = [];
            if (!empty($row['settings']) && is_string($row['settings'])) {
                $decoded = json_decode($row['settings'], true);
                if (is_array($decoded) && isset($decoded['community']) && is_array($decoded['community'])) {
                    $community = $decoded['community'];
                }
            }
            if (array_key_exists('registry_listed', $community) && $community['registry_listed'] === false) {
                continue;
            }
            $meta = \App\Services\Community\TenantCommunityProfileService::registryCardMeta($community);
            $row['registry_tagline'] = $meta['tagline'];
            $row['registry_style_badge_labels'] = $meta['style_badge_labels'];
            $row['registry_style_badge_slugs'] = $meta['style_badge_slugs'] ?? [];
            $row['registry_tag_labels'] = $meta['registry_tag_labels'];
            $row['registry_tag_slugs'] = $meta['registry_tag_slugs'] ?? [];
            $row['registry_unit_affiliation_label'] = $meta['unit_affiliation_label'] ?? '';
            $row['game_label'] = trim((string) ($community['game_label'] ?? ''));
            $row['registry_locale'] = (string) ($meta['locale'] ?? '');
            $row['registry_locale_label'] = (string) ($meta['locale_label'] ?? '');
            $row['registry_locked'] = !empty($community['community_locked']);
            $row['registry_simple_reg'] = ($community['registration_mode'] ?? 'milsim') === 'simple';
            // Mise en avant : seulement si la formule de la communauté l’inclut.
            $row['registry_featured'] = !empty($meta['featured']) && \App\Support\RegistryFeatured::planAllows($row);
            $row['registry_recruitment_open'] = !empty($meta['recruitment_open']);
            // Pays de l'unité réelle rattachée (filtre « Pays » de l'annuaire).
            $affiliation = is_array($community['unit_affiliation'] ?? null) ? $community['unit_affiliation'] : [];
            $row['registry_country'] = !empty($affiliation['is_real']) ? strtoupper(trim((string) ($affiliation['country'] ?? ''))) : '';
            $row['registry_country_label'] = $row['registry_country'] !== '' ? trim((string) ($affiliation['country_label'] ?? $row['registry_country'])) : '';
            $welcome = trim((string) ($community['welcome_text'] ?? ''));
            $excerpt = $meta['tagline'];
            if ($excerpt === '' && $welcome !== '') {
                $excerpt = $welcome;
            }
            if ($excerpt !== '' && function_exists('mb_strlen') && mb_strlen($excerpt) > 220) {
                $excerpt = mb_substr($excerpt, 0, 217) . '…';
            } elseif ($excerpt !== '' && strlen($excerpt) > 220) {
                $excerpt = substr($excerpt, 0, 217) . '…';
            }
            $row['registry_excerpt'] = $excerpt;
            unset($row['settings']);
            $out[] = $row;
        }

        // Mise en avant d’abord, puis ordre alphabétique.
        usort($out, static function (array $a, array $b): int {
            $fa = !empty($a['registry_featured']) ? 0 : 1;
            $fb = !empty($b['registry_featured']) ? 0 : 1;
            if ($fa !== $fb) {
                return $fa <=> $fb;
            }

            return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        return $out;
    }

    public static function normalizeCommunityCode(string $raw): string
    {
        $s = strtoupper(trim($raw));
        $s = preg_replace('/\s+/', '-', $s);
        $s = preg_replace('/[^A-Z0-9\-]/', '', $s);

        return $s;
    }

    public function findByCommunityCode(string $code): ?array
    {
        $norm = self::normalizeCommunityCode($code);
        if ($norm === '' || strlen($norm) < 3) {
            return null;
        }
        $codeEq = SqlText::equals($this->pdo(), 'community_code');
        $stmt = $this->pdo()->prepare('SELECT * FROM tenants WHERE ' . $codeEq . ' LIMIT 1');
        $stmt->execute([$norm]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || TenantArchiveState::isArchived($row)) {
            return null;
        }

        return $row;
    }

    public function isCommunityCodeTaken(string $normalizedCode, ?int $exceptTenantId = null): bool
    {
        if ($normalizedCode === '') {
            return false;
        }
        if ($exceptTenantId !== null) {
            $codeEq = SqlText::equals($this->pdo(), 'community_code');
            $stmt = $this->pdo()->prepare('SELECT 1 FROM tenants WHERE ' . $codeEq . ' AND id != ? LIMIT 1');
            $stmt->execute([$normalizedCode, $exceptTenantId]);
        } else {
            $codeEq = SqlText::equals($this->pdo(), 'community_code');
            $stmt = $this->pdo()->prepare('SELECT 1 FROM tenants WHERE ' . $codeEq . ' LIMIT 1');
            $stmt->execute([$normalizedCode]);
        }

        return (bool) $stmt->fetchColumn();
    }

    /** @param string|null $normalized null pour retirer le code */
    public function updateCommunityCode(int $tenantId, ?string $normalized): void
    {
        $stmt = $this->pdo()->prepare('UPDATE tenants SET community_code = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$normalized === '' ? null : $normalized, $tenantId]);
        $this->forgetCache();
    }

    public function getDefaultTenant(): ?array
    {
        $stmt = $this->pdo()->query('SELECT * FROM tenants ORDER BY id ASC LIMIT 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function slugExists(string $slug): bool
    {
        $slugEq = SqlText::equals($this->pdo(), 'slug');
        $stmt = $this->pdo()->prepare('SELECT 1 FROM tenants WHERE ' . $slugEq . ' LIMIT 1');
        $stmt->execute([$slug]);
        return (bool) $stmt->fetchColumn();
    }

    public function isSlugTakenByOther(int $tenantId, string $slug): bool
    {
        $slugEq = SqlText::equals($this->pdo(), 'slug');
        $stmt = $this->pdo()->prepare('SELECT 1 FROM tenants WHERE ' . $slugEq . ' AND id != ? LIMIT 1');
        $stmt->execute([$slug, $tenantId]);

        return (bool) $stmt->fetchColumn();
    }

    public function updateSlug(int $tenantId, string $slug): void
    {
        $stmt = $this->pdo()->prepare('UPDATE tenants SET slug = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$slug, $tenantId]);
        $this->forgetCache();
    }

    public function updateName(int $tenantId, string $name): void
    {
        $stmt = $this->pdo()->prepare('UPDATE tenants SET name = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$name, $tenantId]);
        $this->forgetCache();
        \App\Services\Moderation\NameModerationService::afterSave('tenant', $tenantId, $tenantId, $name);
    }

    public function getTenantType(int $tenantId): string
    {
        $tenant = $this->findById($tenantId);
        return \App\Services\Community\TenantTypeConfig::normalizeType(
            $tenant ? (string) ($tenant['tenant_type'] ?? 'full') : 'full'
        );
    }

    public function updateTenantType(int $tenantId, string $tenantType): void
    {
        if (!$this->hasTenantTypeColumn()) {
            throw new \RuntimeException(
                'La colonne du profil de communauté est absente. Lancez les migrations puis réessayez.'
            );
        }
        $tenantType = \App\Services\Community\TenantTypeConfig::normalizeType($tenantType);
        $stmt = $this->pdo()->prepare('UPDATE tenants SET tenant_type = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$tenantType, $tenantId]);
        $this->forgetCache();
    }

    public function updateLogoUrl(int $tenantId, ?string $url): void
    {
        $url = $url !== null ? trim($url) : '';
        if ($url === '') {
            return;
        }
        if (strlen($url) > 500) {
            $url = substr($url, 0, 500);
        }
        $stmt = $this->pdo()->prepare('UPDATE tenants SET logo_url = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$url, $tenantId]);
        $this->forgetCache();
    }

    /** Retire explicitement le logo (contrairement à updateLogoUrl(), qui ignore les valeurs vides). */
    public function clearLogoUrl(int $tenantId): void
    {
        $stmt = $this->pdo()->prepare('UPDATE tenants SET logo_url = NULL, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$tenantId]);
        $this->forgetCache();
    }

    /** @return int id du tenant créé */
    public function create(string $name, string $slug, string $planSlug = 'free', string $tenantType = 'full'): int
    {
        $tenantType = \App\Services\Community\TenantTypeConfig::normalizeType($tenantType);
        if ($this->hasTenantTypeColumn()) {
            $stmt = $this->pdo()->prepare(
                'INSERT INTO tenants (name, slug, tenant_type, plan_slug, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())'
            );
            $stmt->execute([$name, $slug, $tenantType, $planSlug]);
        } else {
            // Sans la colonne, tout est traité comme « Complet » : refuser un profil restreint
            // plutôt que de créer une communauté ATAK/Effectifs qui verrait tous les modules.
            if ($tenantType !== \App\Services\Community\TenantTypeConfig::TYPE_FULL) {
                throw new \RuntimeException(
                    'Le profil choisi ne peut pas être enregistré : lancez les migrations (colonne du profil de communauté), puis recréez la communauté.'
                );
            }
            $stmt = $this->pdo()->prepare(
                'INSERT INTO tenants (name, slug, plan_slug, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())'
            );
            $stmt->execute([$name, $slug, $planSlug]);
        }

        $tenantId = (int) $this->pdo()->lastInsertId();
        \App\Services\Moderation\NameModerationService::afterSave('tenant', $tenantId, $tenantId, $name, true);

        return $tenantId;
    }

    public function setOwner(int $tenantId, int $ownerUserId): void
    {
        $stmt = $this->pdo()->prepare('UPDATE tenants SET owner_user_id = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$ownerUserId, $tenantId]);
        $this->forgetCache();
    }

    /** @return array<string,mixed> */
    public function getSettings(int $tenantId): array
    {
        $tenant = $this->findById($tenantId);
        if (!$tenant) {
            return [];
        }
        $raw = $tenant['settings'] ?? null;
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string,mixed> $settings */
    public function updateSettings(int $tenantId, array $settings): void
    {
        // Lecture fraîche avant fusion (pas de cache de requête pour une lecture-écriture).
        $this->forgetCache();
        $current = $this->getSettings($tenantId);
        $merged = array_replace_recursive($current, $settings);
        $stmt = $this->pdo()->prepare('UPDATE tenants SET settings = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $tenantId]);
        $this->forgetCache();
    }

    /**
     * Écrit le JSON de réglages tel quel (listes remplacées, pas fusionnées par index).
     *
     * @param array<string, mixed> $settings
     */
    public function replaceSettings(int $tenantId, array $settings): void
    {
        $stmt = $this->pdo()->prepare('UPDATE tenants SET settings = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $tenantId]);
        $this->forgetCache();
    }

    public function updateSubscriptionFromStripe(
        int $tenantId,
        ?string $stripeCustomerId,
        ?string $stripeSubscriptionId,
        string $status,
        ?string $planSlug,
        ?string $periodEndIso
    ): void {
        $periodEnd = null;
        if ($periodEndIso !== null && $periodEndIso !== '') {
            $periodEnd = date('Y-m-d H:i:s', (int) strtotime($periodEndIso));
        }
        $row = $this->findById($tenantId);
        if (!$row) {
            return;
        }
        $stmt = $this->pdo()->prepare(
            'UPDATE tenants SET stripe_customer_id = ?, stripe_subscription_id = ?, subscription_status = ?, plan_slug = ?, subscription_current_period_end = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([
            $stripeCustomerId ?? $row['stripe_customer_id'] ?? null,
            $stripeSubscriptionId ?? $row['stripe_subscription_id'] ?? null,
            $status,
            $planSlug ?? ($row['plan_slug'] ?? 'free'),
            $periodEnd ?? $row['subscription_current_period_end'] ?? null,
            $tenantId,
        ]);
        $this->forgetCache();
    }

    public function updateSubscriptionFromPayPal(
        int $tenantId,
        ?string $paypalPayerId,
        ?string $paypalSubscriptionId,
        string $status,
        ?string $planSlug,
        ?string $periodEndIso
    ): void {
        $periodEnd = null;
        if ($periodEndIso !== null && $periodEndIso !== '') {
            $periodEnd = date('Y-m-d H:i:s', (int) strtotime($periodEndIso));
        }
        $row = $this->findById($tenantId);
        if (!$row) {
            return;
        }
        if (!$this->hasPayPalTenantColumns()) {
            // Repli : stocke le statut / plan sans colonnes PayPal dédiées
            $stmt = $this->pdo()->prepare(
                'UPDATE tenants SET subscription_status = ?, plan_slug = ?, subscription_current_period_end = ?, updated_at = NOW() WHERE id = ?'
            );
            $stmt->execute([
                $status,
                $planSlug ?? ($row['plan_slug'] ?? 'free'),
                $periodEnd ?? $row['subscription_current_period_end'] ?? null,
                $tenantId,
            ]);
            $this->forgetCache();

            return;
        }
        $stmt = $this->pdo()->prepare(
            'UPDATE tenants SET paypal_payer_id = ?, paypal_subscription_id = ?, subscription_status = ?, plan_slug = ?, subscription_current_period_end = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([
            $paypalPayerId ?? $row['paypal_payer_id'] ?? null,
            $paypalSubscriptionId ?? $row['paypal_subscription_id'] ?? null,
            $status,
            $planSlug ?? ($row['plan_slug'] ?? 'free'),
            $periodEnd ?? $row['subscription_current_period_end'] ?? null,
            $tenantId,
        ]);
        $this->forgetCache();
    }

    /** @return array<string, mixed>|null */
    public function findByPayPalSubscriptionId(string $subscriptionId): ?array
    {
        $subscriptionId = trim($subscriptionId);
        if ($subscriptionId === '' || !$this->hasPayPalTenantColumns()) {
            return null;
        }
        $stmt = $this->pdo()->prepare('SELECT * FROM tenants WHERE paypal_subscription_id = ? LIMIT 1');
        $stmt->execute([$subscriptionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function hasPayPalTenantColumns(): bool
    {
        try {
            $st = $this->pdo()->query(
                "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenants' AND COLUMN_NAME = 'paypal_subscription_id' LIMIT 1"
            );

            return $st && (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /** Fusionne un objet JSON dans tenants.settings. */
    public function mergeSettings(int $tenantId, array $patch): void
    {
        // Lecture fraîche avant fusion (pas de cache de requête pour une lecture-écriture).
        $this->forgetCache();
        $row = $this->findById($tenantId);
        if (!$row) {
            return;
        }
        $current = [];
        if (!empty($row['settings'])) {
            $decoded = json_decode((string) $row['settings'], true);
            if (is_array($decoded)) {
                $current = $decoded;
            }
        }
        $merged = array_merge($current, $patch);
        $stmt = $this->pdo()->prepare('UPDATE tenants SET settings = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([json_encode($merged, JSON_THROW_ON_ERROR), $tenantId]);
        $this->forgetCache();
    }

    /**
     * Liste minimale des autres communautés (missions inter-équipes, sélecteurs admin).
     *
     * @return list<array{id: int, name: string, slug: string}>
     */
    /**
     * Recherche de communautés par nom (sélecteurs de coopération) : champs publics uniquement.
     *
     * @return list<array{id: int, name: string, slug: string, tenant_type: ?string, logo_url: ?string}>
     */
    public function searchBasicExcluding(string $query, int $excludeTenantId, int $limit = 15): array
    {
        $q = trim($query);
        if (mb_strlen($q) < 2) {
            return [];
        }
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
        $limit = max(1, min(30, $limit));
        try {
            $stmt = $this->pdo()->prepare(
                "SELECT id, name, slug, tenant_type, logo_url FROM tenants
                 WHERE id != ? AND id > 1 AND (name LIKE ? OR slug LIKE ?)
                 ORDER BY (name LIKE ?) DESC, name ASC LIMIT {$limit}"
            );
            $stmt->execute([$excludeTenantId, $like, $like, $q . '%']);
        } catch (\Throwable) {
            $stmt = $this->pdo()->prepare(
                "SELECT id, name, slug, NULL AS tenant_type, NULL AS logo_url FROM tenants
                 WHERE id != ? AND id > 1 AND (name LIKE ? OR slug LIKE ?) ORDER BY name ASC LIMIT {$limit}"
            );
            $stmt->execute([$excludeTenantId, $like, $like]);
        }
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $out[] = [
                'id' => (int) $r['id'],
                'name' => (string) $r['name'],
                'slug' => (string) $r['slug'],
                'tenant_type' => $r['tenant_type'] !== null ? (string) $r['tenant_type'] : null,
                'logo_url' => $r['logo_url'] !== null && trim((string) $r['logo_url']) !== '' ? (string) $r['logo_url'] : null,
            ];
        }

        return $out;
    }

    public function listBasicExcluding(int $excludeTenantId): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT id, name, slug FROM tenants WHERE id != ? AND id > 1 ORDER BY name ASC'
        );
        $stmt->execute([$excludeTenantId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Liste des communautés (hors tenant technique id = 1) pour sélecteurs opérateur.
     *
     * @return list<array{id: int, name: string, slug: string}>
     */
    public function listBasicAll(): array
    {
        $stmt = $this->pdo()->query('SELECT id, name, slug FROM tenants WHERE id > 1 ORDER BY name ASC');
        if ($stmt === false) {
            return [];
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Annuaire opérateur : toutes les communautés « réelles » (hors tenant technique id = 1),
     * avec effectif des comptes rattachés et formule d’accès.
     *
     * @return list<array{
     *   id: int,
     *   name: string,
     *   slug: string,
     *   created_at: string|null,
     *   user_count: int,
     *   plan_slug: string,
     *   subscription_status: string,
     *   subscription_current_period_end: string|null,
     *   stripe_subscription_id: string|null,
     *   effective_plan_slug: string,
     *   plan_gift: array{gift_id: int, plan_slug: string, ends_at: ?string}|null
     * }>
     */
    public function listOverviewForPlatform(): array
    {
        $humanUsers = UserRepository::sqlLiteralExcludeTechnicalInternalEmails('u');
        $humanLogin = UserRepository::sqlLiteralExcludeTechnicalInternalEmails('u2');
        $typeSelect = $this->hasTenantTypeColumn() ? 't.tenant_type,' : "'full' AS tenant_type,";
        $lastLogin = $this->scalarIfTable('users', "SELECT MAX(u2.last_login_at) FROM users u2 WHERE u2.tenant_id = t.id AND {$humanLogin}", 'NULL');
        $lastIngest = $this->scalarIfTable('atak_ingest_traffic', 'SELECT MAX(x.bucket_ts) FROM atak_ingest_traffic x WHERE x.tenant_id = t.id', 'NULL');
        $lastProfile = $this->scalarIfTable('operator_game_profiles', 'SELECT MAX(o.last_seen_at) FROM operator_game_profiles o WHERE o.tenant_id = t.id', 'NULL');
        $terminals = $this->scalarIfTable('atak_terminals', 'SELECT COUNT(*) FROM atak_terminals a WHERE a.tenant_id = t.id', '0');
        $units = $this->scalarIfTable('atak_units', 'SELECT COUNT(*) FROM atak_units a WHERE a.tenant_id = t.id', '0');
        $profiles = $this->scalarIfTable('operator_game_profiles', 'SELECT COUNT(*) FROM operator_game_profiles o WHERE o.tenant_id = t.id', '0');
        $sessions = $this->scalarIfTable('game_sessions', 'SELECT COUNT(*) FROM game_sessions g WHERE g.tenant_id = t.id', '0');
        $sql = <<<SQL
SELECT t.id,
       t.name,
       t.slug,
       {$typeSelect}
       t.created_at AS created_at,
       t.plan_slug,
       t.subscription_status,
       t.subscription_current_period_end,
       t.stripe_subscription_id,
       t.settings,
       (SELECT COUNT(*) FROM users u WHERE u.tenant_id = t.id AND {$humanUsers}) AS user_count,
       {$lastLogin} AS last_login_at,
       {$lastIngest} AS last_ingest_at,
       {$lastProfile} AS last_profile_at,
       {$terminals} AS terminal_count,
       {$units} AS unit_count,
       {$profiles} AS profile_count,
       {$sessions} AS session_count
FROM tenants t
WHERE t.id > 1
ORDER BY t.name ASC
SQL;
        $stmt = $this->pdo()->query($sql);
        if ($stmt === false) {
            return [];
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $stripeSub = isset($row['stripe_subscription_id']) ? trim((string) $row['stripe_subscription_id']) : '';
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
                'tenant_type' => (string) ($row['tenant_type'] ?? 'full'),
                'created_at' => isset($row['created_at']) && $row['created_at'] !== null && $row['created_at'] !== ''
                    ? (string) $row['created_at']
                    : null,
                'user_count' => (int) ($row['user_count'] ?? 0),
                'plan_slug' => (string) ($row['plan_slug'] ?? 'free'),
                'subscription_status' => (string) ($row['subscription_status'] ?? 'none'),
                'subscription_current_period_end' => isset($row['subscription_current_period_end']) && $row['subscription_current_period_end'] !== null && $row['subscription_current_period_end'] !== ''
                    ? (string) $row['subscription_current_period_end']
                    : null,
                'stripe_subscription_id' => $stripeSub !== '' ? $stripeSub : null,
                // Formule réellement appliquée (formule offerte, essai fondateur) : c'est elle que l'annuaire affiche.
                'effective_plan_slug' => \App\Support\PlanGift::effectivePlanSlug($row),
                'plan_gift' => \App\Support\PlanGift::activeFromSettings($row['settings'] ?? null),
                // Seules les clés utiles aux fins de formule (essai fondateur, formule offerte même terminée).
                'plan_window' => self::planWindowSettings($row['settings'] ?? null),
                'archive' => TenantArchiveState::infoFromSettings($row['settings'] ?? null),
                'last_login_at' => self::nullableDate($row['last_login_at'] ?? null),
                'last_game_at' => self::latestDate($row['last_ingest_at'] ?? null, $row['last_profile_at'] ?? null),
                'terminal_count' => (int) ($row['terminal_count'] ?? 0),
                'unit_count' => (int) ($row['unit_count'] ?? 0),
                'profile_count' => (int) ($row['profile_count'] ?? 0),
                'session_count' => (int) ($row['session_count'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return array{founder_trial_ends_at: ?string, plan_gift: ?array<string, mixed>, plan_end_warning_sent: ?string, manual_billing: ?array<string, mixed>}
     */
    private static function planWindowSettings(mixed $raw): array
    {
        $s = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : (is_array($raw) ? $raw : []);
        if (!is_array($s)) {
            $s = [];
        }
        $trial = $s['founder_trial_ends_at'] ?? null;
        $gift = $s[\App\Support\PlanGift::SETTINGS_KEY] ?? null;
        $warned = $s[\App\Support\PlanAccessWindow::WARNED_KEY] ?? null;
        $manual = $s[\App\Support\ManualBilling::SETTINGS_KEY] ?? null;

        return [
            'founder_trial_ends_at' => is_string($trial) && $trial !== '' ? $trial : null,
            'plan_gift' => is_array($gift) ? $gift : null,
            'plan_end_warning_sent' => is_string($warned) ? $warned : null,
            'manual_billing' => is_array($manual) ? $manual : null,
        ];
    }

    /**
     * Affectation manuelle d’une formule (opérateur plateforme).
     * Ne touche pas aux identifiants de paiement : un webhook peut toujours écraser le statut.
     */
    public function updatePlanAssignment(int $tenantId, string $planSlug, string $subscriptionStatus): bool
    {
        if ($tenantId < 2) {
            return false;
        }
        $stmt = $this->pdo()->prepare(
            'UPDATE tenants SET plan_slug = ?, subscription_status = ?, updated_at = NOW() WHERE id = ?'
        );

        $ok = $stmt->execute([$planSlug, $subscriptionStatus, $tenantId]);
        $this->forgetCache();

        return $ok;
    }
}
