<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\TenantRepository;
use App\Support\MemberName;

/**
 * Réglage d’affichage des noms d’une communauté (Organisation › Noms et trigrammes), rangé dans les réglages
 * de la communauté sous la clé « member_names ».
 *
 * Par défaut : « Prénom N. » dans les listes, « Prénom NOM » sur les fiches, grade non ajouté, trigrammes actifs.
 */
final class MemberNameSettings
{
    public const KEY = 'member_names';

    /** @var array{list_format: string, card_format: string, show_grade: bool, trigrams_enabled: bool} */
    public const DEFAULTS = [
        'list_format' => MemberName::SHORT,
        'card_format' => MemberName::FULL,
        'show_grade' => false,
        'trigrams_enabled' => true,
    ];

    /** @var array<int, array{list_format: string, card_format: string, show_grade: bool, trigrams_enabled: bool}> */
    private static array $cache = [];

    /**
     * @return array{list_format: string, card_format: string, show_grade: bool, trigrams_enabled: bool}
     */
    public static function forTenant(int $tenantId, ?TenantRepository $tenants = null): array
    {
        if ($tenantId < 1) {
            return self::DEFAULTS;
        }
        if (isset(self::$cache[$tenantId])) {
            return self::$cache[$tenantId];
        }
        $raw = [];
        try {
            $settings = ($tenants ?? new TenantRepository())->getSettings($tenantId);
            $raw = is_array($settings[self::KEY] ?? null) ? $settings[self::KEY] : [];
        } catch (\Throwable) {
            // Réglage illisible : les valeurs par défaut s’appliquent, la page s’affiche.
        }

        return self::$cache[$tenantId] = self::normalize($raw);
    }

    /**
     * @param array<string, mixed> $raw
     * @return array{list_format: string, card_format: string, show_grade: bool, trigrams_enabled: bool}
     */
    public static function normalize(array $raw): array
    {
        $out = [
            'list_format' => MemberName::normalizeFormat((string) ($raw['list_format'] ?? ''), self::DEFAULTS['list_format']),
            'card_format' => MemberName::normalizeFormat((string) ($raw['card_format'] ?? ''), self::DEFAULTS['card_format']),
            'show_grade' => array_key_exists('show_grade', $raw) ? (bool) $raw['show_grade'] : self::DEFAULTS['show_grade'],
            'trigrams_enabled' => array_key_exists('trigrams_enabled', $raw) ? (bool) $raw['trigrams_enabled'] : self::DEFAULTS['trigrams_enabled'],
        ];
        // Trigrammes coupés : un format « trigramme » retombe sur le nom abrégé.
        foreach (['list_format', 'card_format'] as $k) {
            if (!$out['trigrams_enabled'] && $out[$k] === MemberName::TRIGRAM) {
                $out[$k] = MemberName::SHORT;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{list_format: string, card_format: string, show_grade: bool, trigrams_enabled: bool}
     */
    public static function save(int $tenantId, array $input, ?TenantRepository $tenants = null): array
    {
        $clean = self::normalize($input);
        ($tenants ?? new TenantRepository())->updateSettings($tenantId, [self::KEY => $clean]);
        self::$cache[$tenantId] = $clean;

        return $clean;
    }

    /**
     * Format d’un contexte : « list » (listes, espaces serrés), « card » (fiche), ou un format explicite.
     *
     * @param array{list_format: string, card_format: string, show_grade: bool, trigrams_enabled: bool} $settings
     */
    public static function formatFor(array $settings, string $context): string
    {
        return match ($context) {
            'card' => $settings['card_format'],
            'list' => $settings['list_format'],
            default => MemberName::normalizeFormat($context, $settings['list_format']),
        };
    }

    /** Oublie le réglage en mémoire (tests, enregistrement). */
    public static function forget(?int $tenantId = null): void
    {
        if ($tenantId === null) {
            self::$cache = [];

            return;
        }
        unset(self::$cache[$tenantId]);
    }
}
