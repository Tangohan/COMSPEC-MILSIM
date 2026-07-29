<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Core\Container;
use App\Repositories\MilitaryUnitRepository;

/**
 * Compatibilité API historique — délègue entièrement au référentiel BDD.
 * Aucune liste d'unités hardcodée.
 */
final class RealUnitAffiliationCatalog
{
    private static ?MilitaryReferentialService $service = null;

    private static function service(): MilitaryReferentialService
    {
        if (self::$service === null) {
            try {
                self::$service = Container::get(MilitaryReferentialService::class);
            } catch (\Throwable) {
                self::$service = new MilitaryReferentialService(new MilitaryUnitRepository());
            }
        }

        return self::$service;
    }

    /** @return array<string, string> code ISO => libellé pays */
    public static function countryLabels(): array
    {
        $svc = self::service();
        if ($svc->isReady()) {
            return $svc->countryLabels();
        }

        // Fallback minimal si migrations non encore exécutées
        return [
            'FR' => 'France',
            'US' => 'États-Unis',
            'DE' => 'Allemagne',
            'BE' => 'Belgique',
            'ES' => 'Espagne',
        ];
    }

    /** @return list<string> */
    public static function allowedCountryCodes(): array
    {
        return array_keys(self::countryLabels());
    }

    /**
     * @return list<array{id: string, name: string, tier: string, tier_order: int, indent: int}>
     */
    public static function unitsForCountry(string $countryCode): array
    {
        $svc = self::service();
        if (!$svc->isReady()) {
            return [];
        }

        return $svc->unitsForCountry($countryCode);
    }

    /** @return array{id: string, name: string, tier: string, tier_order: int, indent: int}|null */
    public static function findUnit(string $countryCode, string $unitId): ?array
    {
        $svc = self::service();
        if (!$svc->isReady()) {
            return null;
        }

        return $svc->findUnit($countryCode, $unitId);
    }

    /**
     * @param list<string> $unitIds
     * @return list<array{id: string, name: string}>
     */
    public static function resolveSelectedUnits(string $countryCode, array $unitIds): array
    {
        $svc = self::service();
        if (!$svc->isReady()) {
            return [];
        }

        return $svc->resolveSelectedUnits($countryCode, $unitIds);
    }

    /**
     * Payload JSON pour le front (assistant création).
     *
     * @return array{countries: array<string, string>, units: array<string, list<array<string, mixed>>>}
     */
    public static function frontendPayload(): array
    {
        $svc = self::service();
        if (!$svc->isReady()) {
            return [
                'countries' => self::countryLabels(),
                'units' => [],
            ];
        }

        return $svc->frontendPayload();
    }
}
