<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Support\SteamWorkshop;

/**
 * Repère dans les mods remontés par les joueurs ceux qui ressemblent au pack COMSPEC
 * (nom / dossier) sans porter l’identifiant Workshop officiel, ou sans fiche Workshop du tout.
 *
 * Ce n’est pas une preuve de copie de code : c’est un signal d’enquête pour l’admin plateforme.
 */
final class LookalikeModProbe
{
    /** Motifs sur le dossier ou le nom affiché (insensible à la casse). */
    private const NAME_PATTERNS = [
        'comspec',
        'compsec', // faute / ancienne orthographe vue en atelier
        'atak.?native',
        'athena',
        'overwatch',
        'seek.?ii',
        '@sse',
        'sse.?comspec',
        'comspec.?sse',
        'comspec.?atak',
        'comspec.?overwatch',
    ];

    /**
     * Identifiants Workshop considérés comme officiels (packs COMSPEC + dépendances connues).
     *
     * @return list<string>
     */
    public static function officialWorkshopIds(): array
    {
        $ids = [];
        foreach (SteamWorkshop::items() as $item) {
            $id = trim((string) ($item['id'] ?? ''));
            if ($id !== '') {
                $ids[] = $id;
            }
        }
        $ids[] = SteamWorkshop::ATAK_NATIVE_ID;
        $ids[] = SteamWorkshop::CBA_ID;
        $ids[] = SteamWorkshop::ACE_ID;
        $ids[] = SteamWorkshop::ACRE_ID;

        return array_values(array_unique($ids));
    }

    /**
     * @return array{matched: bool, reasons: list<string>, severity: 'warn'|'bad'|'neut'}
     */
    public static function classify(string $modDir, string $name, string $workshopId): array
    {
        $modDir = trim($modDir);
        $name = trim($name);
        $workshopId = preg_replace('/\D/', '', $workshopId) ?? '';
        $hay = strtolower($modDir . ' ' . $name);
        $reasons = [];
        $matched = false;

        foreach (self::NAME_PATTERNS as $pat) {
            if (preg_match('/' . $pat . '/i', $hay) === 1) {
                $matched = true;
                $reasons[] = 'Nom ou dossier proche du pack COMSPEC';
                break;
            }
        }

        if (!$matched) {
            return ['matched' => false, 'reasons' => [], 'severity' => 'neut'];
        }

        $official = self::officialWorkshopIds();
        if ($workshopId === '') {
            $reasons[] = 'Aucune fiche Workshop associée (souvent non signé / copie locale)';

            return ['matched' => true, 'reasons' => array_values(array_unique($reasons)), 'severity' => 'bad'];
        }
        if (!in_array($workshopId, $official, true)) {
            $reasons[] = 'Fiche Workshop différente de celle du pack officiel';

            return ['matched' => true, 'reasons' => array_values(array_unique($reasons)), 'severity' => 'bad'];
        }

        // Même famille de noms + ID officiel : pas un signal (pack légitime).
        return ['matched' => false, 'reasons' => [], 'severity' => 'neut'];
    }
}
