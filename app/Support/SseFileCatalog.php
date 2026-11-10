<?php

declare(strict_types=1);

namespace App\Support;

/**
 * « Passage aux fichiers » — valeurs fermées (types de requête, sources, mentions, verdicts SEEK II) et libellés.
 *
 * Les clés sont celles du contrat avec le jeu (GET /api/sse/identity/documents/lookup, kinds file_query et seek_record
 * de POST /api/sse/identity/checks) : ne pas les renommer sans changer le mod.
 */
final class SseFileCatalog
{
    /** Types de requête, façon terminal de consultation (code court, libellé). */
    public const QUERY_TYPES = [
        'QW' => 'Personne',
        'QV' => 'Véhicule',
        'QA' => 'Document',
        'QB' => 'Biométrie',
    ];

    public const SOURCES = [
        'web' => 'Site Athena',
        'phone' => 'Téléphone en jeu',
        'seek' => 'SEEK II',
        'idcheck' => 'Contrôle d’identité en jeu',
        'game' => 'Jeu (liaison directe)',
    ];

    /** Sources acceptées pour une consultation remontée plus tard (kind file_query). */
    public const DEFERRED_SOURCES = ['phone', 'seek', 'idcheck'];

    /** Mentions aux fichiers, de la plus grave à la moins grave. */
    public const MENTIONS = [
        'armed_dangerous' => 'Armé et dangereux',
        'warrant' => 'Mandat',
        'wanted' => 'Recherché',
        'missing' => 'Personne disparue',
        'flagged' => 'Signalé',
        'caution' => 'Prudence',
    ];

    /** Mentions qui font d'une réponse un « recherché » (sinon « signalé »). */
    public const MENTIONS_WANTED = ['armed_dangerous', 'warrant', 'wanted'];

    /** Verdicts d'une fiche SEEK II et verdict du contrôle N4 qui en découle. */
    public const SEEK_VERDICTS = [
        'concordant' => 'Concordant',
        'discordant' => 'Discordant',
        'inconnu' => 'Inconnu',
        'signale' => 'Signalé',
    ];

    public const SEEK_TO_CHECK_VERDICT = [
        'concordant' => 'ras',
        'discordant' => 'signale',
        'inconnu' => 'inconnu',
        'signale' => 'signale',
    ];

    /** État d'un papier dans une réponse (flag doc:PAYS|type|numéro|état). */
    public const DOC_STATES = [
        'ok' => 'En règle',
        'stolen' => 'Volé',
        'lost' => 'Perdu',
        'revoked' => 'Annulé',
        'suspended' => 'Suspendu',
        'expired' => 'Expiré',
    ];

    /** Codes de réponse ajoutés par le passage aux fichiers (en plus de SseIdentityCatalog::FLAGS). */
    public const FLAGS = [
        'no_vehicle_file' => 'Aucun fichier des véhicules dans ce registre',
    ];

    /** Ordre de gravité des codes simples (les codes à valeur doc:, seek:, dob:, checks:, lastcheck: viennent après). */
    public const GRAVITY = [
        'armed_dangerous', 'warrant', 'wanted', 'person_priority', 'missing', 'stolen', 'lost', 'revoked', 'suspended',
        'flagged', 'watchlist', 'caution', 'expired', 'holder_mismatch', 'dob_mismatch', 'unknown_document', 'no_vehicle_file',
    ];

    /** Préfixes des codes à valeur, dans l'ordre d'affichage. */
    public const VALUE_PREFIXES = ['doc', 'seek', 'dob', 'checks', 'lastcheck'];

    /** Codes lus par le mod (COMSPEC Link en lit 16 au plus). */
    public const MAX_FLAGS = 16;

    public const MAX_DOC_FLAGS = 5;

    public static function label(array $map, ?string $key, string $fallback = '—'): string
    {
        $key = (string) $key;

        return $key !== '' && isset($map[$key]) ? (string) $map[$key] : $fallback;
    }

    /** Numéro de requête lisible : FQ-AAAAMMJJ-000123. */
    public static function qrn(int $id, ?string $createdAt = null): string
    {
        $day = preg_match('/^(\d{4})-(\d{2})-(\d{2})/', (string) $createdAt, $m) === 1 ? $m[1] . $m[2] . $m[3] : gmdate('Ymd');

        return 'FQ-' . $day . '-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    /** Référence d'une fiche SEEK II sans numéro du jeu : REC-000012. */
    public static function seekRef(int $id): string
    {
        return 'REC-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Codes triés du plus grave au moins grave, sans doublon, 16 au plus ; le QRN est toujours gardé (en dernier).
     *
     * @param list<string> $flags
     * @return list<string>
     */
    public static function orderFlags(array $flags, ?string $qrn = null): array
    {
        $rank = static function (string $f): int {
            $simple = array_search($f, self::GRAVITY, true);
            if ($simple !== false) {
                return (int) $simple;
            }
            $prefix = strstr($f, ':', true);
            $value = $prefix !== false ? array_search($prefix, self::VALUE_PREFIXES, true) : false;

            return $value !== false ? 100 + (int) $value : 50;
        };
        $flags = array_values(array_unique(array_filter(
            array_map(static fn (mixed $f): string => trim((string) $f), $flags),
            static fn (string $f): bool => $f !== '' && !str_starts_with($f, 'qrn:')
        )));
        $indexed = [];
        foreach ($flags as $i => $f) {
            $indexed[] = [$rank($f), $i, $f];
        }
        usort($indexed, static fn (array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
        $out = array_map(static fn (array $r): string => $r[2], $indexed);
        if ($qrn !== null && $qrn !== '') {
            $out = array_slice($out, 0, self::MAX_FLAGS - 1);
            $out[] = 'qrn:' . $qrn;
        }

        return array_slice($out, 0, self::MAX_FLAGS);
    }

    /** Libellé d'un code de réponse (codes à valeur compris). */
    public static function flagLabel(string $flag): string
    {
        if (isset(self::MENTIONS[$flag])) {
            return self::MENTIONS[$flag];
        }
        if (isset(self::FLAGS[$flag])) {
            return self::FLAGS[$flag];
        }
        if (isset(SseIdentityCatalog::FLAGS[$flag])) {
            return SseIdentityCatalog::FLAGS[$flag];
        }
        $prefix = strstr($flag, ':', true);
        $value = $prefix !== false ? substr($flag, strlen($prefix) + 1) : '';

        return match ($prefix) {
            'qrn' => 'Numéro de requête ' . $value,
            'doc' => 'Papier ' . str_replace('|', ' · ', $value),
            'seek' => 'Fiche SEEK II ' . $value,
            'dob' => 'Né(e) le ' . $value,
            'checks' => 'Contrôles subis : ' . $value,
            'lastcheck' => 'Dernier contrôle ' . $value,
            default => $flag,
        };
    }
}
