<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Fiches PDF d'une personne ou d'une identité fictive (jeu de rôle, aucune donnée réelle) : modèles, textes rédigeables de
 * chaque modèle (longueur bornée, trame par défaut), préfixe de référence, section de droits et durée de validité.
 *
 * Chaque libellé existe en français et en anglais.
 */
final class SseSheetCatalog
{
    /**
     * Modèles. `needs` : droits d'émettre (section, niveau), tous requis. « notice » garde les droits de l'avis de recherche
     * (SseWantedAccess) ; la vigilance, qui donne des ordres au terrain, demande aussi d'écrire dans « fichiers ».
     */
    public const MODELS = [
        'notice' => [
            'label' => ['Avis de recherche', 'Wanted notice'],
            'hint' => ['Notice de recherche d’une personne sous avis actif.', 'Notice for a person under an active wanted notice.'],
            'prefix' => 'AR', 'needs' => [],
        ],
        'identification' => [
            'label' => ['Fiche d’identification', 'Identification sheet'],
            'hint' => ['État civil connu, signalement, photo, alias, biométrie résumée, dernière observation.', 'Known civil status, description, photo, aliases, biometrics summary, last sighting.'],
            'prefix' => 'FI', 'needs' => [['fichiers', 'export']],
        ],
        'vigilance' => [
            'label' => ['Fiche de vigilance', 'Vigilance notice (BOLO)'],
            'hint' => ['Personne à surveiller : motif, comportement, véhicules, adresses, consignes, durée de validité.', 'Person to watch: reason, behaviour, vehicles, addresses, instructions, validity.'],
            'prefix' => 'FV', 'needs' => [['fichiers', 'write'], ['papiers', 'export']],
        ],
        'profile' => [
            'label' => ['Fiche de renseignement', 'Intelligence profile'],
            'hint' => ['Synthèse complète pour un briefing.', 'Full summary for a briefing.'],
            'prefix' => 'FR', 'needs' => [['fichiers', 'export']],
        ],
        'control' => [
            'label' => ['Fiche de contrôle terrain', 'Field check sheet'],
            'hint' => ['Résumé d’un ou plusieurs contrôles : date, lieu, contexte, qualité, vérifications.', 'Summary of one or more checks: date, place, context, quality, verifications.'],
            'prefix' => 'FC', 'needs' => [['papiers', 'export'], ['fichiers', 'read']],
        ],
    ];

    /**
     * Textes rédigeables : modèle => liste de [clé, longueur maximale, [libellé fr, en], [indication fr, en]].
     * La capacité de la mise en page est mesurée à l'aperçu (SseSheetPdf::overflow).
     */
    public const TEXTS = [
        'notice' => [
            ['reason', 800, ['Motifs et charges', 'Reasons and charges'], ['Faits reprochés ou raison de l’intérêt.', 'Alleged facts or reason for interest.']],
            ['instructions', 500, ['Mesures à prendre', 'Action to be taken'], ['Conduite à tenir en cas de rencontre.', 'What to do on encounter.']],
        ],
        'identification' => [
            ['description', 900, ['Signalement (description)', 'Description'], ['Description narrative de la personne.', 'Narrative description of the person.']],
            ['remarks', 600, ['Observations', 'Remarks'], ['Remarques utiles à l’identification.', 'Remarks useful for identification.']],
            ['mention', 300, ['Mentions', 'Notes'], ['Mention de diffusion ou précaution.', 'Distribution note or caution.']],
        ],
        'vigilance' => [
            ['reason', 700, ['Motif de la vigilance', 'Reason for vigilance'], ['Pourquoi cette personne est à surveiller.', 'Why this person is to be watched.']],
            ['behaviour', 700, ['Comportement observé', 'Observed behaviour'], ['Habitudes, déplacements, attitude.', 'Habits, movements, attitude.']],
            ['instructions', 900, ['Conduite à tenir', 'Instructions'], ['Consignes aux équipes : ne pas intervenir, signaler, etc.', 'Instructions to teams: do not engage, report, etc.']],
            ['mention', 300, ['Mentions', 'Notes'], ['Mention de diffusion ou précaution.', 'Distribution note or caution.']],
        ],
        'profile' => [
            ['summary', 1500, ['Synthèse', 'Summary'], ['Résumé de la situation de la personne.', 'Summary of the person’s situation.']],
            ['assessment', 900, ['Appréciation', 'Assessment'], ['Évaluation, fiabilité, hypothèses.', 'Assessment, reliability, hypotheses.']],
            ['recommendations', 600, ['Recommandations', 'Recommendations'], ['Suites proposées.', 'Proposed follow-up.']],
            ['mention', 300, ['Mentions', 'Notes'], ['Mention de diffusion ou précaution.', 'Distribution note or caution.']],
        ],
        'control' => [
            ['summary', 900, ['Synthèse du contrôle', 'Check summary'], ['Déroulé et résultat du ou des contrôles.', 'Course and outcome of the check(s).']],
            ['remarks', 700, ['Observations', 'Remarks'], ['Remarques de l’opérateur.', 'Operator remarks.']],
            ['mention', 300, ['Mentions', 'Notes'], ['Mention de diffusion ou précaution.', 'Distribution note or caution.']],
        ],
    ];

    /** Trames par défaut, avec variables résolues selon les droits du rédacteur. [fr, en]. */
    public const DEFAULTS = [
        'identification' => [
            'description' => [
                '{nom_complet}. Date de naissance : {date_naissance}. Nationalité : {nationalite}. Signalement : {signalement}. Signes particuliers : {marques}.',
                '{nom_complet}. Date of birth: {date_naissance}. Nationality: {nationalite}. Description: {signalement}. Distinguishing marks: {marques}.',
            ],
            'remarks' => [
                'Dernière observation connue : {dernier_lieu} ({derniere_date}). Alias : {alias}.',
                'Last known sighting: {dernier_lieu} ({derniere_date}). Aliases: {alias}.',
            ],
            'mention' => ['', ''],
        ],
        'vigilance' => [
            'reason' => [
                'Personne à surveiller : {nom_complet} (alias {alias}). Niveau de menace : {menace}. Affiliation : {affiliation}.',
                'Person to watch: {nom_complet} (alias {alias}). Threat level: {menace}. Affiliation: {affiliation}.',
            ],
            'behaviour' => [
                'Dernier lieu connu : {dernier_lieu}. Comportement observé : à compléter.',
                'Last known location: {dernier_lieu}. Observed behaviour: to be completed.',
            ],
            'instructions' => [
                "- Ne pas intervenir seul : **observer et rendre compte**.\n- Relever l’heure, le lieu, le véhicule et les personnes accompagnantes.\n- Signaler toute observation au service {service}.",
                "- Do not engage alone: **observe and report**.\n- Note time, place, vehicle and accompanying persons.\n- Report every sighting to {service}.",
            ],
            'mention' => ['', ''],
        ],
        'profile' => [
            'summary' => [
                '{nom_complet} ({nationalite}) est rattaché(e) à : {affiliation}. Dernière observation : {dernier_lieu}. Niveau de menace : {menace}.',
                '{nom_complet} ({nationalite}) is linked to: {affiliation}. Last sighting: {dernier_lieu}. Threat level: {menace}.',
            ],
            'assessment' => ['', ''],
            'recommendations' => [
                "- Poursuivre la surveillance et croiser avec les contrôles récents.\n- Mettre à jour la fiche après chaque observation.",
                "- Continue surveillance and cross-check with recent checks.\n- Update the record after each sighting.",
            ],
            'mention' => ['', ''],
        ],
        'control' => [
            'summary' => [
                'Contrôle(s) de {nom_complet} : {nb_controles} contrôle(s) enregistré(s).',
                'Check(s) of {nom_complet}: {nb_controles} check(s) recorded.',
            ],
            'remarks' => ['', ''],
            'mention' => ['', ''],
        ],
    ];

    /** Durées de validité proposées (jours) ; la vigilance expire à la fin. */
    public const VALIDITY_DAYS = [7, 14, 30, 60, 90, 180];

    public const DEFAULT_VALIDITY = 30;

    public const FACES = ['auto', '1', '2'];

    /** Pages demandées : « auto », « 1 » ou « 2 ». */
    public static function faces(mixed $value): string
    {
        $v = is_scalar($value) ? (string) $value : '';

        return in_array($v, self::FACES, true) ? $v : 'auto';
    }

    public static function exists(string $model): bool
    {
        return isset(self::MODELS[$model]);
    }

    public static function label(string $model, string $lang): string
    {
        return self::MODELS[$model]['label'][$lang === 'en' ? 1 : 0] ?? $model;
    }

    public static function lang(mixed $value): string
    {
        return $value === 'en' ? 'en' : 'fr';
    }

    /** @return list<array{0: string, 1: int, 2: array{0: string, 1: string}, 3: array{0: string, 1: string}}> */
    public static function texts(string $model): array
    {
        return self::TEXTS[$model] ?? [];
    }

    public static function maxLength(string $model, string $key): int
    {
        foreach (self::texts($model) as $t) {
            if ($t[0] === $key) {
                return $t[1];
            }
        }

        return 0;
    }

    public static function default(string $model, string $key, string $lang): string
    {
        return self::DEFAULTS[$model][$key][$lang === 'en' ? 1 : 0] ?? '';
    }

    /** Durée de validité retenue (jours) : une des durées proposées, sinon 30. */
    public static function validity(mixed $days): int
    {
        $d = (int) $days;

        return in_array($d, self::VALIDITY_DAYS, true) ? $d : self::DEFAULT_VALIDITY;
    }
}
