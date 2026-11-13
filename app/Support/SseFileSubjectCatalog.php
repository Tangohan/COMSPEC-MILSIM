<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Fichiers SSE — valeurs fermées de la fiche sujet : signalement, codes de prudence, liens, alias, motifs de
 * consultation, données à visibilité réglable (lore) et confiance des correspondances.
 *
 * Les codes de signalement suivent l'usage des fichiers de police (cheveux, yeux, corpulence) ; les libellés sont en
 * français, le pays du sujet et celui du terminal donnent l'affichage (SseRecordsCountryPacks).
 */
final class SseFileSubjectCatalog
{
    public const BUILDS = [
        'slim' => 'Mince',
        'medium' => 'Moyenne',
        'athletic' => 'Athlétique',
        'muscular' => 'Musclée',
        'heavy' => 'Forte',
    ];

    public const HAIR = [
        'black' => 'Noirs',
        'brown' => 'Bruns',
        'blond' => 'Blonds',
        'red' => 'Roux',
        'gray' => 'Gris',
        'white' => 'Blancs',
        'bald' => 'Chauve ou rasés',
    ];

    public const EYES = [
        'brown' => 'Marron',
        'black' => 'Noirs',
        'hazel' => 'Noisette',
        'green' => 'Verts',
        'blue' => 'Bleus',
        'gray' => 'Gris',
    ];

    /** Codes courts « façon fichier » (HAI/EYE). */
    public const HAIR_CODES = ['black' => 'BLK', 'brown' => 'BRO', 'blond' => 'BLN', 'red' => 'RED', 'gray' => 'GRY', 'white' => 'WHI', 'bald' => 'BAL'];

    public const EYE_CODES = ['brown' => 'BRO', 'black' => 'BLK', 'hazel' => 'HAZ', 'green' => 'GRN', 'blue' => 'BLU', 'gray' => 'GRY'];

    /** Codes de mise en garde (sécurité de l'opérateur), du plus grave au moins grave. */
    public const CAUTIONS = [
        'armed' => 'Armé',
        'violent' => 'Violent',
        'officer_safety' => 'Sécurité des intervenants',
        'explosives' => 'Explosifs',
        'gang' => 'Membre d’un groupe armé',
        'flight_risk' => 'Risque de fuite',
        'mental_health' => 'Fragilité psychologique',
        'medical' => 'Traitement médical',
    ];

    /** Liens d'une fiche. */
    public const LINK_KINDS = [
        'address' => 'Adresse',
        'vehicle' => 'Véhicule',
        'phone' => 'Téléphone',
        'email' => 'Courriel',
        'associate' => 'Personne liée',
    ];

    public const RELATIONS = [
        'family' => 'Famille',
        'spouse' => 'Conjoint',
        'associate' => 'Associé',
        'cell' => 'Même cellule',
        'employer' => 'Employeur',
        'contact' => 'Contact',
    ];

    /** Mentions portées par un véhicule (lien « vehicle »). */
    public const VEHICLE_FLAGS = [
        'stolen' => 'Volé',
        'flagged' => 'Signalé',
        'wanted' => 'Recherché',
    ];

    public const ALIAS_KINDS = [
        'aka' => 'Alias',
        'nickname' => 'Surnom',
        'spelling' => 'Variante d’orthographe',
        'legal' => 'Nom à l’état civil',
        'registry' => 'Alias du registre',
    ];

    /** Motif de la consultation, inscrit au journal (obligatoire au terminal). */
    public const PURPOSES = [
        'CS' => 'Contrôle sur la voie publique',
        'C' => 'Enquête',
        'F' => 'Arme à feu',
        'J' => 'Habilitation',
        'R' => 'Renseignement',
        'T' => 'Instruction et entraînement',
    ];

    /**
     * Données dont la visibilité se règle dans le lore (connu des fichiers ou caché MJ), avec une phase de révélation.
     * Les alias et les liens ont chacun leur propre réglage.
     */
    public const VIS_FIELDS = [
        'identity' => 'Nom, sexe et date de naissance',
        'birth' => 'Lieu de naissance et nationalité',
        'profession' => 'Profession',
        'physical' => 'Signalement (taille, poids, corpulence, cheveux, yeux)',
        'marks' => 'Signes particuliers',
        'photo' => 'Photo',
        'affiliation' => 'Camp et affiliation',
        'cover' => 'Couverture',
        'cautions' => 'Codes de prudence',
        'documents' => 'Papiers délivrés',
    ];

    public const VISIBILITY = [
        'known' => 'Connu des fichiers',
        'hidden' => 'Caché (MJ)',
    ];

    /** Confiance d'une correspondance, d'après son score. */
    public static function confidence(int $score): string
    {
        return match (true) {
            $score >= 90 => 'exact',
            $score >= 70 => 'probable',
            default => 'possible',
        };
    }

    public const CONFIDENCE_LABELS = [
        'exact' => 'Exact',
        'probable' => 'Probable',
        'possible' => 'Possible',
    ];

    /** @param array<string, string> $map */
    public static function pick(array $map, mixed $value): ?string
    {
        $v = is_scalar($value) ? strtolower(trim((string) $value)) : '';

        return $v !== '' && isset($map[$v]) ? $v : null;
    }

    /**
     * @param array<string, string> $map
     * @return list<string>
     */
    public static function pickMany(array $map, mixed $values): array
    {
        $list = is_array($values) ? $values : explode(',', is_scalar($values) ? (string) $values : '');
        $out = [];
        foreach ($list as $v) {
            $k = self::pick($map, $v);
            if ($k !== null && !in_array($k, $out, true)) {
                $out[] = $k;
            }
        }

        return $out;
    }

    /** @param array<string, string> $map */
    public static function label(array $map, ?string $key, string $fallback = '—'): string
    {
        return $key !== null && isset($map[$key]) ? $map[$key] : $fallback;
    }
}
