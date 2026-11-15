<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Cerbères : le nom de tout ce que le système fait TOUT SEUL dans le bureau SSE (rattacher un contrôle, classer une fiche,
 * corréler, détecter un avis de recherche, préparer une validation, créer un dossier du jour). Un traitement s'appelle
 * « Cerbère-Liaison », « Cerbère-Validation », « Cerbère-Corrélation », « Cerbère-Détection » ou « Cerbère-Création » ;
 * l'ensemble, « les Cerbères ».
 *
 * Le nom est un AFFICHAGE : aucun code stocké, colonne, route ni permission ne change (la colonne `rule_key` du moteur, le
 * libellé « Automatisme » gardé en base comme auteur des relations, `SSE_AUTO` du journal...). Cette classe tient la table de
 * correspondance entre ces codes stables et les libellés montrés (RULES, LEGACY_LABELS) ; les liens de provenance
 * (sse_entity_links.rule_code) portent le code de règle `cerbere.<traitement>.<règle>`.
 */
final class SseCerbere
{
    public const PREFIX = 'cerbere.';

    /** Acteur système d'un lien de provenance. */
    public const ACTOR_SYSTEM = 'system';

    public const ACTOR_USER = 'user';

    /** Règle d'un lien posé à la main (jamais un Cerbère). */
    public const RULE_MANUAL = 'manual.link';

    /**
     * Les traitements : clé stable, nom affiché, ce que chacun fait en une phrase.
     *
     * @var array<string, array{label: string, does: string}>
     */
    public const TREATMENTS = [
        'liaison' => [
            'label' => 'Cerbère-Liaison',
            'does' => 'Rattache tout seul un contrôle à la personne, à l’identité ou au papier qu’il désigne, et classe une fiche dans le dossier ouvert.',
        ],
        'validation' => [
            'label' => 'Cerbère-Validation',
            'does' => 'Prépare les propositions de rapprochement et de consolidation à valider ; rien n’est validé sans la décision d’une personne.',
        ],
        'correlation' => [
            'label' => 'Cerbère-Corrélation',
            'does' => 'Signale les doublons probables, les correspondances fortes avec une surveillance et les fiches saisies dans la foulée.',
        ],
        'detection' => [
            'label' => 'Cerbère-Détection',
            'does' => 'Prévient quand une identité sous avis de recherche actif réapparaît dans un contrôle, un relevé SEEK II ou une consultation.',
        ],
        'creation' => [
            'label' => 'Cerbère-Création',
            'does' => 'Ouvre le dossier du jour pour les fiches transmises sans code de dossier.',
        ],
    ];

    /**
     * Les règles : code stable => traitement, libellé court, motif en clair (la phrase du lien).
     *
     * @var array<string, array{treatment: string, label: string, reason: string}>
     */
    public const RULES = [
        'cerbere.link.check_document' => ['treatment' => 'liaison', 'label' => 'papier présenté', 'reason' => 'Contrôle rattaché à l’identité du papier présenté.'],
        'cerbere.link.check_biokey' => ['treatment' => 'liaison', 'label' => 'clé biométrique', 'reason' => 'Contrôle rattaché par la clé biométrique déjà connue.'],
        'cerbere.link.check_netid' => ['treatment' => 'liaison', 'label' => 'identifiant de jeu', 'reason' => 'Contrôle rattaché à la personne par son identifiant de jeu.'],
        'cerbere.link.check_designated' => ['treatment' => 'liaison', 'label' => 'fiche désignée', 'reason' => 'Contrôle rattaché à la fiche désignée par le terminal.'],
        'cerbere.link.check_retro' => ['treatment' => 'liaison', 'label' => 'rattachement rétroactif', 'reason' => 'Contrôle précédent rattaché après coup : même clé biométrique qu’un contrôle qui a désigné la fiche.'],
        'cerbere.link.case_terminal' => ['treatment' => 'liaison', 'label' => 'dossier du terminal', 'reason' => 'Fiche classée au dossier dont le code a été saisi sur le terminal SEEK.'],
        'cerbere.create.case_from_code' => ['treatment' => 'creation', 'label' => 'dossier créé depuis un code', 'reason' => 'Dossier ouvert automatiquement : code inconnu saisi sur le terminal, première fiche transmise sous ce code.'],
        'cerbere.link.person_identity' => ['treatment' => 'liaison', 'label' => 'personne et identité', 'reason' => 'Identité du registre rattachée à la fiche personne.'],
        'cerbere.link.case_single_open' => ['treatment' => 'liaison', 'label' => 'dossier ouvert unique', 'reason' => 'Fiche classée au seul dossier ouvert.'],
        'cerbere.link.case_daily' => ['treatment' => 'liaison', 'label' => 'dossier du jour', 'reason' => 'Fiche classée au dossier du jour ouvert par le système.'],
        'cerbere.create.case_daily' => ['treatment' => 'creation', 'label' => 'dossier du jour', 'reason' => 'Dossier ouvert pour les fiches transmises sans code de dossier.'],
        'cerbere.correlate.duplicate_biometric' => ['treatment' => 'correlation', 'label' => 'doublon probable', 'reason' => 'Même référence de laboratoire : doublon probable (rien n’est fusionné).'],
        'cerbere.correlate.hard_match' => ['treatment' => 'correlation', 'label' => 'correspondance forte', 'reason' => 'Correspondance forte avec une liste de surveillance.'],
        'cerbere.correlate.co_presence' => ['treatment' => 'correlation', 'label' => 'co-présence', 'reason' => 'Fiches saisies coup sur coup dans le même dossier.'],
        'cerbere.correlate.engine_suggestion' => ['treatment' => 'correlation', 'label' => 'proposition du moteur', 'reason' => 'Rapprochement proposé par le moteur d’analyse, à valider.'],
        'cerbere.validate.engine_suggestion' => ['treatment' => 'validation', 'label' => 'proposition validée', 'reason' => 'Proposition du moteur validée par une personne.'],
        'cerbere.detect.wanted' => ['treatment' => 'detection', 'label' => 'avis de recherche', 'reason' => 'Identité sous avis de recherche actif détectée.'],
        'cerbere.backfill.column' => ['treatment' => 'liaison', 'label' => 'rétro-remplissage', 'reason' => 'Lien reconstruit depuis une colonne déjà enregistrée (rétro-remplissage).'],
    ];

    /**
     * Mots de l'ancien vocabulaire de l'interface pour ces traitements, avec le nom qui les remplace. Table de
     * correspondance d'AFFICHAGE : le test la compare aux vues pour trouver ce qui reste à renommer.
     *
     * @var array<string, string>
     */
    public const LEGACY_LABELS = [
        'Suggestions du moteur' => 'Propositions de Cerbère-Validation',
        'Automatisme' => 'Cerbère',
        'Classement automatique' => 'Cerbère-Liaison : classement',
        'Détection système' => 'Cerbère-Détection',
        'Détections système' => 'Cerbère-Détection',
        'Liens automatiques' => 'Liens Cerbère',
    ];

    public static function isCerbereRule(?string $code): bool
    {
        return $code !== null && str_starts_with($code, self::PREFIX);
    }

    /** Traitement d'un code de règle, ou null (règle manuelle ou inconnue). */
    public static function treatmentOf(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        return self::RULES[$code]['treatment'] ?? (self::isCerbereRule($code) ? (explode('.', $code)[1] ?? null) : null);
    }

    /** « Cerbère-Liaison » pour une règle de ce traitement, « Cerbère » si la règle est inconnue. */
    public static function treatmentLabel(?string $code): string
    {
        $t = self::treatmentOf($code);

        return $t !== null && isset(self::TREATMENTS[$t]) ? self::TREATMENTS[$t]['label'] : 'Cerbère';
    }

    /** « Cerbère-Liaison (règle cerbere.link.check_biokey) » : l'acteur lisible d'un lien posé par un traitement. */
    public static function actorLabel(?string $code): string
    {
        return self::treatmentLabel($code) . ' (règle ' . (string) $code . ')';
    }

    /** « Posé automatiquement par Cerbère-Liaison (règle …) » : la phrase d'un lien automatique. */
    public static function sentence(?string $code, string $verb = 'Posé'): string
    {
        return $verb . ' automatiquement par ' . self::actorLabel($code);
    }

    /** Motif en clair d'une règle connue, sinon vide. */
    public static function reasonOf(?string $code): string
    {
        return self::RULES[(string) $code]['reason'] ?? '';
    }

    /**
     * Règles d'un traitement.
     *
     * @return list<string>
     */
    public static function rulesOf(string $treatment): array
    {
        return array_keys(array_filter(self::RULES, static fn (array $r): bool => $r['treatment'] === $treatment));
    }
}
