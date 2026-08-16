<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Référentiels métier des modules analytiques SSE.
 * Libellés destinés à l’UI — jamais les clés techniques seules.
 */
final class SseAnalyticalCatalog
{
    /** @var array<string,string> */
    public const SOURCE_ORIGINS = [
        'observation' => 'Observation directe',
        'compte_rendu' => 'Compte rendu terrain',
        'numerique' => 'Exploitation numérique',
        'partenaire' => 'Renseignement partenaire',
        'document' => 'Document',
        'photographie' => 'Photographie',
        'biometrie' => 'Biométrie',
        'humaine_fictive' => 'Source humaine (scénario)',
    ];

    /** Fiabilité de la source (lettre) — indépendante de la crédibilité. */
    /** @var array<string,string> */
    public const SOURCE_RELIABILITY = [
        'A' => 'A — Source totalement fiable',
        'B' => 'B — Source habituellement fiable',
        'C' => 'C — Source assez fiable',
        'D' => 'D — Source pas toujours fiable',
        'E' => 'E — Source non fiable',
        'F' => 'F — Fiabilité ne pouvant être jugée',
    ];

    /** Crédibilité de l’information (chiffre) — indépendante de la source. */
    /** @var array<int,string> */
    public const INFO_CREDIBILITY = [
        1 => '1 — Confirmée par d’autres sources',
        2 => '2 — Probablement vraie',
        3 => '3 — Possiblement vraie',
        4 => '4 — Douteuse',
        5 => '5 — Improbable',
        6 => '6 — Vérité ne pouvant être jugée',
    ];

    /** @var array<string,string> */
    public const CONFIDENCE = [
        'faible' => 'Faible',
        'modere' => 'Modéré',
        'eleve' => 'Élevé',
    ];

    /** @var array<string,string> */
    public const TEMPORALITY = [
        'valable_a_date' => 'Valable à la date du…',
        'ancien' => 'Renseignement ancien',
        'derniere_confirmation' => 'Dernière confirmation',
        'susceptible_evolution' => 'Situation susceptible d’avoir évolué',
    ];

    /** @var array<string,string> */
    public const URGENCY = [
        '' => '—',
        'exploitation_prioritaire' => 'Exploitation prioritaire',
        'attention_commandement' => 'Attention commandement',
        'validite_courte' => 'Information à durée de validité courte',
    ];

    /** @var array<string,string> */
    public const DIVERGENCES = [
        '' => '—',
        'identites_concurrentes' => 'Deux identités concurrentes',
        'infos_incompatibles' => 'Informations incompatibles',
        'chronologie_impossible' => 'Chronologie impossible',
        'photo_non_concordante' => 'Photographie non concordante',
    ];

    /** @var array<string,string> */
    public const HYPOTHESIS_CODES = [
        'H1' => 'H1 — Hypothèse privilégiée',
        'H2' => 'H2 — Hypothèse concurrente',
        'H3' => 'H3 — Hypothèse alternative',
    ];

    /** @var array<string,string> */
    public const GAP_KINDS = [
        'lacune' => 'Lacune identifiée',
        'besoin' => 'Besoin de renseignement',
        'critere' => 'Critère de confirmation',
    ];

    /** @var array<string,string> */
    public const GAP_PRIORITIES = [
        'basse' => 'Basse',
        'normale' => 'Normale',
        'prioritaire' => 'Prioritaire',
        'critique' => 'Critique',
    ];

    /** @var array<string,string> */
    public const GAP_STATUSES = [
        'ouvert' => 'Ouvert',
        'en_cours' => 'En cours',
        'satisfait' => 'Satisfait',
        'abandonne' => 'Abandonné',
    ];

    /** @var array<string,string> */
    public const DECISION_DOMAINS = [
        'rattachement' => 'Rattachement',
        'identite' => 'Identité',
        'confiance' => 'Niveau de confiance',
        'hypothese' => 'Hypothèse retenue',
        'qualification' => 'Qualification',
        'statut_dossier' => 'État du dossier',
        'fusion' => 'Fusion de dossiers',
        'dissociation' => 'Dissociation',
        'autre' => 'Autre décision',
    ];

    /** @var array<string,string> */
    public const CASE_RELATION_TYPES = [
        'parent' => 'Dossier parent',
        'derive' => 'Dossier dérivé',
        'connexe' => 'Dossier connexe',
        'source' => 'Dossier source',
        'doublon_potentiel' => 'Doublon potentiel',
        'fusionne_dans' => 'Fusionné dans',
        'dissocie_de' => 'Dissocié de',
    ];

    /** Doctrine rédactionnelle des mentions. */
    /** @var array<string,string> */
    public const DOCTRINES = [
        'neutre' => 'Neutre',
        'analytique' => 'Analytique',
        'synthese_commandement' => 'Synthèse commandement',
        'compte_rendu_terrain' => 'Compte rendu terrain',
    ];

    /** @var array<string,string> */
    public const FRAGMENT_KINDS = [
        'phrase' => 'Phrase / fragment',
        'bloc' => 'Bloc',
        'modele' => 'Modèle',
    ];

    public static function label(array $map, string|int $key, string $fallback = '—'): string
    {
        return $map[$key] ?? $fallback;
    }

    public static function ratingLabel(string $reliability, int $credibility): string
    {
        $rel = self::SOURCE_RELIABILITY[$reliability] ?? $reliability;
        $cred = self::INFO_CREDIBILITY[$credibility] ?? (string) $credibility;

        return $reliability . $credibility . ' — ' . $rel . ' / ' . $cred;
    }
}
