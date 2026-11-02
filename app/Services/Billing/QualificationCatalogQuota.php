<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Services\Platform\FeatureGateService;
use App\Support\QualificationCatalog\QualificationCatalog;
use App\Support\QualificationCatalog\QualificationIssuerCatalog;
use PDO;
use Throwable;

/**
 * Ce que la formule de la communauté permet d’ajouter au référentiel des qualifications : qualifications importées du
 * catalogue de référence, émetteurs réels importés, qualifications et brevets créés par la communauté, import d’un pays
 * entier en un clic. Les plafonds viennent de la formule (features_json, défauts dans SubscriptionPlanFeaturesCatalog :
 * c’est là qu’on les ajuste) ; 0 = illimité. Le catalogue reste toujours consultable en entier.
 *
 * Jamais de perte : on compte ce qui est dans le référentiel (fiches non archivées), seul l’ajout au-delà du plafond
 * est refusé ; ce qui existe déjà reste utilisable. Administrateurs de la plateforme : sans limite.
 */
final class QualificationCatalogQuota
{
    public const IMPORTS = 'max_catalog_qualification_imports';
    public const ISSUERS = 'max_real_issuer_imports';
    public const CUSTOM = 'max_custom_qualifications';
    public const BULK = 'qualification_catalog_bulk_import';

    /** Adresse de la page des formules, avec l’origine de la demande. */
    public const UPGRADE_URL = 'platform/upgrade?from=max_catalog_qualification_imports';

    /**
     * @param array<string, int> $limits plafond par clé (0 = illimité)
     * @param array<string, int> $used ce qui est déjà dans le référentiel, par clé
     */
    public function __construct(private array $limits, private array $used, private bool $bulk, private bool $unlimited = false)
    {
    }

    /** Sans limite (administrateur de la plateforme, ou lecture impossible : le contrôle ne bloque pas par erreur). */
    public static function unlimited(): self
    {
        return new self([], [], true, true);
    }

    public static function forTenant(int $tenantId, PDO $pdo, FeatureGateService $gate, bool $platformAdmin = false): self
    {
        if ($platformAdmin || $tenantId < 1) {
            return self::unlimited();
        }
        $limits = [];
        foreach ([self::IMPORTS, self::ISSUERS, self::CUSTOM] as $key) {
            $limits[$key] = $gate->intWithCatalogDefault($tenantId, $key);
        }

        return new self($limits, self::countUsed($tenantId, $pdo), $gate->allowsWithCatalogDefault($tenantId, self::BULK));
    }

    /**
     * Ce que la communauté a déjà : qualifications du catalogue (code du catalogue), créations (autres codes, hors
     * ACAD- des cours intégrés), émetteurs qui correspondent à un émetteur réel. Fiches archivées non comptées.
     *
     * @return array<string, int>
     */
    public static function countUsed(int $tenantId, PDO $pdo): array
    {
        $out = [self::IMPORTS => 0, self::ISSUERS => 0, self::CUSTOM => 0];
        $catalogCodes = [];
        foreach (QualificationCatalog::rawEntries() as $entry) {
            $catalogCodes[strtoupper((string) ($entry['code'] ?? ''))] = true;
        }
        try {
            $st = $pdo->prepare('SELECT code FROM personnel_qualification_definitions WHERE tenant_id = ? AND archived_at IS NULL');
            $st->execute([$tenantId]);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) ?: [] as $code) {
                $code = strtoupper(trim((string) $code));
                if (isset($catalogCodes[$code])) {
                    $out[self::IMPORTS]++;
                } elseif (!str_starts_with($code, 'ACAD-')) {
                    $out[self::CUSTOM]++;
                }
            }
        } catch (Throwable) {
        }
        try {
            $st = $pdo->prepare('SELECT id, name, short_name FROM qualification_issuers WHERE tenant_id = ? AND archived_at IS NULL');
            $st->execute([$tenantId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            try {
                $st = $pdo->prepare('SELECT id, name, short_name FROM qualification_issuers WHERE tenant_id = ?');
                $st->execute([$tenantId]);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable) {
                $rows = [];
            }
        }
        $matched = [];
        foreach (QualificationIssuerCatalog::all() as $issuer) {
            $id = QualificationIssuerCatalog::matchTenant($issuer, $rows);
            if ($id > 0) {
                $matched[$id] = true;
            }
        }
        $out[self::ISSUERS] = count($matched);

        return $out;
    }

    public function isUnlimited(string $key): bool
    {
        return $this->unlimited || (int) ($this->limits[$key] ?? 0) <= 0;
    }

    public function limit(string $key): int
    {
        return $this->isUnlimited($key) ? 0 : (int) $this->limits[$key];
    }

    public function used(string $key): int
    {
        return (int) ($this->used[$key] ?? 0);
    }

    /** Places restantes, null = illimité. */
    public function remaining(string $key): ?int
    {
        return $this->isUnlimited($key) ? null : max(0, $this->limit($key) - $this->used($key));
    }

    public function canAdd(string $key, int $count = 1): bool
    {
        $left = $this->remaining($key);

        return $left === null || $left >= $count;
    }

    /** « Tout importer » d’un pays en un clic. */
    public function bulk(): bool
    {
        return $this->unlimited || $this->bulk;
    }

    /** Le plafond vient d’être utilisé (après un ajout réussi). */
    public function consume(string $key, int $count = 1): void
    {
        $this->used[$key] = $this->used($key) + max(0, $count);
    }

    /**
     * @return array{used: int, limit: int, remaining: int|null, unlimited: bool, can_add: bool, offer: array{slug: string, name: string, value: int}|null, upgrade_url: string}
     */
    public function status(string $key): array
    {
        return ['used' => $this->used($key), 'limit' => $this->limit($key), 'remaining' => $this->remaining($key), 'unlimited' => $this->isUnlimited($key), 'can_add' => $this->canAdd($key),
            'offer' => $this->isUnlimited($key) ? null : self::upgradeOffer($key, $this->limit($key)), 'upgrade_url' => self::upgradeUrl()];
    }

    public static function upgradeUrl(): string
    {
        return function_exists('url') ? url(self::UPGRADE_URL) : '/' . self::UPGRADE_URL;
    }

    /**
     * Première formule payante qui donne plus (plafond plus haut ou illimité, value 0) ; pour BULK, la première qui
     * l’inclut (value 1). Nom de la formule lu dans les formules du site, sinon nom par défaut.
     *
     * @return array{slug: string, name: string, value: int}|null
     */
    public static function upgradeOffer(string $key, int $currentLimit = 0): ?array
    {
        foreach (['standard', 'pro', 'pro_plus'] as $slug) {
            $value = self::planValue($slug, $key);
            $better = $key === self::BULK ? !empty($value) : ($value !== null && ((int) $value === 0 || (int) $value > $currentLimit));
            if ($better) {
                return ['slug' => $slug, 'name' => self::planName($slug), 'value' => (int) $value];
            }
        }

        return null;
    }

    /** Valeur réelle de la formule du site (réglée dans l'administration), sinon le défaut du catalogue. */
    private static function planValue(string $slug, string $key): bool|int|null
    {
        try {
            $row = \App\Core\Container::get(\App\Repositories\SubscriptionPlanRepository::class)->findBySlug($slug);
            if (is_array($row)) {
                return SubscriptionPlanFeaturesCatalog::planValues($row)['values'][$key] ?? null;
            }
        } catch (Throwable) {
        }

        return SubscriptionPlanFeaturesCatalog::defaultsForSlug($slug)[$key] ?? null;
    }

    public static function planName(string $slug): string
    {
        try {
            $name = trim((string) (\App\Core\Container::get(\App\Repositories\SubscriptionPlanRepository::class)->findBySlug($slug)['name'] ?? ''));
            if ($name !== '') {
                return $name;
            }
        } catch (Throwable) {
        }

        return ['standard' => 'Standard', 'pro' => 'Pro', 'pro_plus' => 'Pro+'][$slug] ?? $slug;
    }
}
