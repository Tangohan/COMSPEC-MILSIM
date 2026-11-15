<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Mentions de jeu autrefois portées par les documents SSE (« Document de fiction », « usage RP », « (fictif) »…) : plus
 * aucune ne figure sur un document, un PDF, ni leur aperçu.
 *
 * Cette classe sert à deux choses :
 * - à la LECTURE, retirer d'un texte enregistré avant ce changement la mention EXACTE livrée par défaut (nom d'un service
 *   du catalogue suivi de « (fictif) », autorité par défaut suivie de « — RP », ancien pied de page livré), sans jamais
 *   toucher un texte saisi par une communauté ;
 * - aux TESTS et à l'outil de rattrapage (tools/sse-strip-fiction-mentions.php), donner la même règle.
 *
 * Les garde-fous restent : aucun nom d'organisation réelle (SseWantedRealNames), bandeaux de classification, accès aux
 * images des membres (SseMemberImageGate), marquage des papiers des pays réels stylisés (SseIdMarking).
 */
final class SseFictionMentions
{
    /** Anciennes autorités émettrices par défaut de la notice => valeur actuelle. */
    public const LEGACY_AUTHORITIES = [
        'Agence interservices du renseignement — RP' => 'Agence interservices du renseignement',
        'Interservice Intelligence Agency — RP' => 'Interservice Intelligence Agency',
    ];

    /** Anciennes descriptions des modèles de présentation livrés => valeur actuelle (texte d'administration). */
    public const LEGACY_PREFAB_DESCRIPTIONS = [
        'Présentation actuelle du dossier : bandeau rouge, feuille crème, mention RP.' => 'Présentation actuelle du dossier : bandeau rouge, feuille crème.',
        'Notes de travail froissées, ton informel pour le RP.' => 'Notes de travail froissées, ton informel.',
    ];

    /**
     * Mentions qu'aucun document ne doit plus porter, en français et en anglais (expressions régulières, insensibles à la
     * casse). « simulation » seul n'y figure pas : le mot reste légitime hors document (mode d'essai des outils, etc.).
     */
    public const FORBIDDEN = [
        '/document de fiction/iu',
        '/fictional document/iu',
        '/jeu de r[oô]le/iu',
        '/role[- ]?play/iu',
        '/usage RP/iu',
        '/RP uniquement/iu',
        '/\bRP (?:use|only)\b/iu',
        '/\bfor RP\b/iu',
        '/FICTION - RP/iu',
        '/preuve judiciaire/iu',
        '/judicial evidence/iu',
        '/\(ficti(?:f|ve|fs|ves|onal)\)/iu',
        '/sp[ée]cimen/iu',
        '/factice/iu',
        '/document de jeu/iu',
        '/game document/iu',
        '/sans valeur/iu',
    ];

    /** @var array<string, true>|null noms livrés par le catalogue des services (fr, en, autorités) */
    private static ?array $catalogueNames = null;

    /** Première mention interdite trouvée dans un texte, ou null. */
    public static function find(string $text): ?string
    {
        foreach (self::FORBIDDEN as $pattern) {
            if (preg_match($pattern, $text, $m) === 1) {
                return $m[0];
            }
        }

        return null;
    }

    /**
     * Nom d'autorité ou de service sans l'ancienne mention livrée. Seules deux formes exactes sont reconnues : une ancienne
     * autorité par défaut, ou un nom du catalogue livré suivi de « (fictif) », « (fictive) » ou « (fictional) ». Tout autre
     * texte (donc tout nom saisi par une communauté) est rendu tel quel.
     */
    public static function cleanName(string $name): string
    {
        $trimmed = trim($name);
        if (isset(self::LEGACY_AUTHORITIES[$trimmed])) {
            return self::LEGACY_AUTHORITIES[$trimmed];
        }
        if (preg_match('/^(.+) \((?:fictifs?|fictives?|fictional)\)$/u', $trimmed, $m) === 1 && isset(self::catalogueNames()[$m[1]])) {
            return $m[1];
        }

        return $name;
    }

    /** Comme cleanName(), pour une valeur absente (null rendu tel quel). */
    public static function cleanNullable(?string $name): ?string
    {
        return $name === null ? null : self::cleanName($name);
    }

    /**
     * Instantané d'un service (enregistré avec un avis) sans l'ancienne mention sur ses noms.
     *
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>
     */
    public static function cleanServiceSnapshot(array $snapshot): array
    {
        foreach (['fr', 'en', 'auth_fr', 'auth_en', 'name', 'authority'] as $key) {
            if (isset($snapshot[$key]) && is_string($snapshot[$key])) {
                $snapshot[$key] = self::cleanName($snapshot[$key]);
            }
        }

        return $snapshot;
    }

    /** @return array<string, true> */
    private static function catalogueNames(): array
    {
        if (self::$catalogueNames !== null) {
            return self::$catalogueNames;
        }
        $names = [];
        $raw = is_file($file = dirname(__DIR__, 2) . '/config/sse_wanted_services.php') ? require $file : [];
        foreach ((array) ($raw['services'] ?? []) as $row) {
            foreach (['fr', 'en', 'auth_fr', 'auth_en'] as $key) {
                if (is_array($row) && isset($row[$key]) && is_string($row[$key]) && $row[$key] !== '') {
                    $names[$row[$key]] = true;
                }
            }
        }

        return self::$catalogueNames = $names;
    }
}
