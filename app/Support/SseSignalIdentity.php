<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Identité d'un signal du moteur analytique SSE : un seul signal ouvert par (dossier, type, règle, variante).
 *
 * La variante distingue ce qui l'est vraiment sous un même type : pour une lacune générée, la lacune elle-même (« Photographie
 * manquante ») ; pour un vieillissement, l'appréciation concernée ; sinon rien (le score d'une « Complétude faible (22/100) »
 * change chaque nuit, il ne fait pas l'identité). La clé se déduit des champs déjà stockés (type, règle, dossier, titre, détail) :
 * elle sert donc aussi aux lignes créées avant la colonne dedup_key, et au rattrapage des doublons.
 */
final class SseSignalIdentity
{
    public const GAP_PREFIX = 'Lacune / besoin proposé : ';

    /** Durée sans changement après laquelle un signal de ces types passe « en veille » (jours). */
    public const DORMANT_AFTER_DAYS = 30;

    /** @var list<string> types qui passent en veille quand rien n'a changé depuis DORMANT_AFTER_DAYS */
    public const EXPIRING_TYPES = ['completeness'];

    public const MAX_KEY = 190;

    public static function variant(string $type, string $title, ?string $detail): string
    {
        if ($type === 'gap_auto') {
            $gap = str_starts_with($title, self::GAP_PREFIX) ? substr($title, strlen(self::GAP_PREFIX)) : $title;

            return mb_strtolower(trim($gap));
        }
        if ($type === 'aging' && preg_match('/#(\d+)/', (string) $detail, $m) === 1) {
            return 'a' . $m[1];
        }

        return '';
    }

    public static function key(?int $caseId, string $type, string $rule, string $variant): string
    {
        $key = $type . '|' . $rule . '|' . (int) $caseId . '|' . $variant;

        return mb_strlen($key) <= self::MAX_KEY ? $key : mb_substr($key, 0, self::MAX_KEY - 9) . '#' . substr(sha1($key), 0, 8);
    }

    /**
     * Clé d'une ligne de sse_engine_signals.
     *
     * @param array<string, mixed> $row
     */
    public static function keyFor(array $row): string
    {
        $type = (string) ($row['signal_type'] ?? '');

        return self::key(
            ($row['case_id'] ?? null) !== null ? (int) $row['case_id'] : null,
            $type,
            (string) ($row['rule_key'] ?? $type),
            self::variant($type, (string) ($row['title'] ?? ''), isset($row['detail']) ? (string) $row['detail'] : null)
        );
    }

    /** Titre de la lacune ou du signal sans son préfixe ni son score : « Photographie manquante », « Complétude faible ». */
    public static function shortTitle(string $type, string $title): string
    {
        if ($type === 'gap_auto' && str_starts_with($title, self::GAP_PREFIX)) {
            return substr($title, strlen(self::GAP_PREFIX));
        }
        if ($type === 'completeness') {
            return trim((string) preg_replace('/\s*\(\d+\s*\/\s*100\)\s*$/u', '', $title));
        }

        return $title;
    }
}
