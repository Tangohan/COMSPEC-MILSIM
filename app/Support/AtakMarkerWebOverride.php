<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Personnalisation poste des marqueurs théâtre (nom, icône, description, permanence).
 * Préservée quand le jeu renvoie le même marqueur.
 */
final class AtakMarkerWebOverride
{
    /** @var list<string> */
    public const LOCKED_FIELDS = [
        'text',
        'label',
        'description',
        'type',
        'color',
        'pngUrl',
        'icon',
        'web_locked',
        'web_permanent',
        'web_edited_at',
        'web_edited_by',
    ];

    /**
     * @param array<string, mixed> $incoming
     * @param array<string, mixed> $previous
     * @return array<string, mixed>
     */
    public static function preserveOnUpsert(array $incoming, array $previous): array
    {
        if (empty($previous['web_locked']) && empty($previous['web_permanent'])) {
            return $incoming;
        }

        foreach (self::LOCKED_FIELDS as $key) {
            if (!array_key_exists($key, $previous)) {
                continue;
            }
            $prev = $previous[$key];
            if ($prev === null || $prev === '') {
                continue;
            }
            $incoming[$key] = $prev;
        }

        // Permanence / verrouillage toujours repris depuis le poste.
        if (!empty($previous['web_locked'])) {
            $incoming['web_locked'] = true;
        }
        if (!empty($previous['web_permanent'])) {
            $incoming['web_permanent'] = true;
        }

        return $incoming;
    }

    /**
     * @param array<string, mixed> $decoded
     */
    public static function isPermanent(array $decoded): bool
    {
        return !empty($decoded['web_permanent']);
    }

    /**
     * @param array<string, mixed> $decoded
     */
    public static function isLocked(array $decoded): bool
    {
        return !empty($decoded['web_locked']) || self::isPermanent($decoded);
    }
}
