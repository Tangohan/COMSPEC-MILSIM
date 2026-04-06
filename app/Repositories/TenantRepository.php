<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class TenantRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tenants WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tenants WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Registre des unités / communautés (hors tenant placeholder id = 1).
     * Exclut les communautés avec `settings.community.registry_listed === false`.
     *
     * @return list<array<string, mixed>>
     */
    public function listForRegistry(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, name, slug, community_code, logo_url, settings FROM tenants WHERE id != 1 ORDER BY name ASC'
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
            $row['registry_tag_labels'] = $meta['registry_tag_labels'];
            $row['game_label'] = trim((string) ($community['game_label'] ?? ''));
            $row['registry_locked'] = !empty($community['community_locked']);
            $row['registry_simple_reg'] = ($community['registration_mode'] ?? 'milsim') === 'simple';
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
        $stmt = $this->pdo->prepare('SELECT * FROM tenants WHERE community_code = ? LIMIT 1');
        $stmt->execute([$norm]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function isCommunityCodeTaken(string $normalizedCode, ?int $exceptTenantId = null): bool
    {
        if ($normalizedCode === '') {
            return false;
        }
        if ($exceptTenantId !== null) {
            $stmt = $this->pdo->prepare('SELECT 1 FROM tenants WHERE community_code = ? AND id != ? LIMIT 1');
            $stmt->execute([$normalizedCode, $exceptTenantId]);
        } else {
            $stmt = $this->pdo->prepare('SELECT 1 FROM tenants WHERE community_code = ? LIMIT 1');
            $stmt->execute([$normalizedCode]);
        }

        return (bool) $stmt->fetchColumn();
    }

    /** @param string|null $normalized null pour retirer le code */
    public function updateCommunityCode(int $tenantId, ?string $normalized): void
    {
        $stmt = $this->pdo->prepare('UPDATE tenants SET community_code = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$normalized === '' ? null : $normalized, $tenantId]);
    }

    public function getDefaultTenant(): ?array
    {
        $stmt = $this->pdo->query('SELECT * FROM tenants ORDER BY id ASC LIMIT 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function slugExists(string $slug): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM tenants WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        return (bool) $stmt->fetchColumn();
    }

    public function isSlugTakenByOther(int $tenantId, string $slug): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM tenants WHERE slug = ? AND id != ? LIMIT 1');
        $stmt->execute([$slug, $tenantId]);

        return (bool) $stmt->fetchColumn();
    }

    public function updateSlug(int $tenantId, string $slug): void
    {
        $stmt = $this->pdo->prepare('UPDATE tenants SET slug = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$slug, $tenantId]);
    }

    public function updateName(int $tenantId, string $name): void
    {
        $stmt = $this->pdo->prepare('UPDATE tenants SET name = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$name, $tenantId]);
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
        $stmt = $this->pdo->prepare('UPDATE tenants SET logo_url = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$url, $tenantId]);
    }

    /** @return int id du tenant créé */
    public function create(string $name, string $slug, string $planSlug = 'free'): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO tenants (name, slug, plan_slug, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())');
        $stmt->execute([$name, $slug, $planSlug]);
        return (int) $this->pdo->lastInsertId();
    }

    public function setOwner(int $tenantId, int $ownerUserId): void
    {
        $stmt = $this->pdo->prepare('UPDATE tenants SET owner_user_id = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$ownerUserId, $tenantId]);
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
        $current = $this->getSettings($tenantId);
        $merged = array_replace_recursive($current, $settings);
        $stmt = $this->pdo->prepare('UPDATE tenants SET settings = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $tenantId]);
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
        $stmt = $this->pdo->prepare(
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
    }

    /** Fusionne un objet JSON dans tenants.settings. */
    public function mergeSettings(int $tenantId, array $patch): void
    {
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
        $stmt = $this->pdo->prepare('UPDATE tenants SET settings = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([json_encode($merged, JSON_THROW_ON_ERROR), $tenantId]);
    }

    /**
     * Liste minimale des autres communautés (missions inter-équipes, sélecteurs admin).
     *
     * @return list<array{id: int, name: string, slug: string}>
     */
    public function listBasicExcluding(int $excludeTenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, slug FROM tenants WHERE id != ? AND id > 1 ORDER BY name ASC'
        );
        $stmt->execute([$excludeTenantId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }
}
