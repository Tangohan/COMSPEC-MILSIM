<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Signalement physique d'un avis de recherche (jeu de rôle) : instantané daté, propre à l'avis. La fiche de l'identité garde sa
 * propre copie (profil SSE) : modifier l'un ne modifie jamais l'autre. Listes standardisées (clés stables, libellés français et
 * anglais) et saisie libre pour les détails. Aucune donnée d'état civil : ni nom, ni date de naissance, ni adresse.
 *
 * Forme gardée (colonne physical_json de sse_wanted_notice_extras) : {@see self::normalize()}. Forme courte exposée au PDF et au
 * jeu : {@see self::export()} et {@see self::shortLine()}.
 */
final class SseWantedPhysical
{
    public const SEX = ['male' => ['Homme', 'Male'], 'female' => ['Femme', 'Female'], 'unknown' => ['Inconnu', 'Unknown']];

    public const AGE = [
        'u18' => ['Moins de 18 ans', 'Under 18'], '18_25' => ['18 à 25 ans', '18 to 25'], '26_35' => ['26 à 35 ans', '26 to 35'],
        '36_45' => ['36 à 45 ans', '36 to 45'], '46_55' => ['46 à 55 ans', '46 to 55'], '56_65' => ['56 à 65 ans', '56 to 65'], 'o65' => ['Plus de 65 ans', 'Over 65'],
    ];

    /** Mêmes clés que le profil SSE de la fiche (SseFileSubjectCatalog), pour le préremplissage. */
    public const BUILD = [
        'slim' => ['Mince', 'Slim'], 'medium' => ['Moyenne', 'Medium'], 'athletic' => ['Athlétique', 'Athletic'],
        'muscular' => ['Musclée', 'Muscular'], 'heavy' => ['Forte', 'Heavy'],
    ];

    public const HAIR = [
        'black' => ['Noirs', 'Black'], 'brown' => ['Bruns', 'Brown'], 'blond' => ['Blonds', 'Blond'], 'red' => ['Roux', 'Red'],
        'gray' => ['Gris', 'Gray'], 'white' => ['Blancs', 'White'], 'bald' => ['Chauve ou rasés', 'Bald or shaved'],
    ];

    public const EYES = [
        'brown' => ['Marron', 'Brown'], 'black' => ['Noirs', 'Black'], 'hazel' => ['Noisette', 'Hazel'],
        'green' => ['Verts', 'Green'], 'blue' => ['Bleus', 'Blue'], 'gray' => ['Gris', 'Gray'],
    ];

    public const COMPLEXION = [
        'fair' => ['Claire', 'Fair'], 'medium' => ['Mate', 'Medium'], 'olive' => ['Olivâtre', 'Olive'],
        'tan' => ['Hâlée', 'Tan'], 'dark' => ['Foncée', 'Dark'],
    ];

    public const FACIAL_HAIR = [
        'none' => ['Rasé de près', 'Clean-shaven'], 'stubble' => ['Barbe de quelques jours', 'Stubble'], 'beard' => ['Barbe', 'Beard'],
        'mustache' => ['Moustache', 'Mustache'], 'goatee' => ['Bouc', 'Goatee'],
    ];

    public const GAIT = [
        'normal' => ['Normale', 'Normal'], 'limp' => ['Boite', 'Limp'], 'stooped' => ['Voûté', 'Stooped'],
        'fast' => ['Rapide et saccadée', 'Quick and jerky'], 'slow' => ['Lente', 'Slow'],
    ];

    /** Équipements habituels (plusieurs choix). */
    public const EQUIPMENT = [
        'rifle' => ['Arme d’épaule', 'Rifle'], 'pistol' => ['Arme de poing', 'Pistol'], 'rpg' => ['Lance-roquettes', 'Rocket launcher'],
        'vest' => ['Gilet porte-plaques', 'Plate carrier'], 'radio' => ['Radio portative', 'Handheld radio'], 'satphone' => ['Téléphone satellite', 'Satellite phone'],
        'nvg' => ['Vision nocturne', 'Night vision'], 'drone' => ['Drone', 'Drone'], 'explosives' => ['Explosifs artisanaux', 'Improvised explosives'],
        'vehicle' => ['Véhicule armé', 'Armed vehicle'], 'bodyguard' => ['Escorte armée', 'Armed escort'],
    ];

    /** Zones de texte libre : clé => longueur maximale. */
    public const FREE = ['marks' => 240, 'tattoos' => 240, 'scars' => 240, 'clothing' => 240, 'language' => 100, 'disabilities' => 160, 'notes' => 240];

    /** Listes à choix unique : clé du champ => constante. */
    private const LISTS = ['sex' => 'SEX', 'age' => 'AGE', 'build' => 'BUILD', 'hair' => 'HAIR', 'eyes' => 'EYES', 'complexion' => 'COMPLEXION', 'facial_hair' => 'FACIAL_HAIR', 'gait' => 'GAIT'];

    /** @return array<string, array<string, array{0: string, 1: string}>> */
    public static function lists(): array
    {
        $out = [];
        foreach (self::LISTS as $field => $const) {
            $out[$field] = constant(self::class . '::' . $const);
        }

        return $out;
    }

    /**
     * Valeurs saisies vers la forme gardée, bornée ; null si rien n'est renseigné.
     *
     * @param array<string, mixed> $in
     * @return array<string, mixed>|null
     */
    public static function normalize(array $in, ?string $snapshotAt = null): ?array
    {
        $out = [];
        $h = $in['height_cm'] ?? null;
        if (is_numeric($h) && (int) $h >= 100 && (int) $h <= 250) {
            $out['height_cm'] = (int) $h;
        }
        $w = $in['weight_kg'] ?? null;
        if (is_numeric($w) && (int) $w >= 30 && (int) $w <= 250) {
            $out['weight_kg'] = (int) $w;
        }
        foreach (self::lists() as $field => $map) {
            $v = $in[$field] ?? null;
            if (is_string($v) && $v !== '' && isset($map[$v])) {
                $out[$field] = $v;
            }
        }
        foreach (self::FREE as $field => $max) {
            $v = SseWantedCatalog::line($in[$field] ?? '', $max);
            if ($v !== '') {
                $out[$field] = $v;
            }
        }
        $eq = [];
        foreach (is_array($in['equipment'] ?? null) ? $in['equipment'] : [] as $k) {
            if (is_string($k) && isset(self::EQUIPMENT[$k]) && !in_array($k, $eq, true)) {
                $eq[] = $k;
            }
        }
        if ($eq !== []) {
            $out['equipment'] = $eq;
        }
        if ($out === []) {
            return null;
        }
        $out['v'] = 1;
        $out['snapshot_at'] = $snapshotAt ?? gmdate('Y-m-d H:i:s');

        return $out;
    }

    /**
     * Valeurs de départ tirées du profil de la fiche (taille, poids, corpulence, cheveux, yeux, teint, signes). Rien pour une
     * identité de membre : le signalement d'un membre n'est jamais copié.
     *
     * @param array<string, mixed>|null $profile profil SSE hydraté
     * @return array<string, mixed>
     */
    public static function fromProfile(?array $profile, bool $isMember): array
    {
        if ($profile === null || $isMember) {
            return [];
        }
        $out = [];
        foreach (['height_cm', 'weight_kg'] as $k) {
            if (($profile[$k] ?? null) !== null) {
                $out[$k] = (int) $profile[$k];
            }
        }
        foreach (['build' => self::BUILD, 'hair' => self::HAIR, 'eyes' => self::EYES] as $k => $map) {
            if (is_string($profile[$k] ?? null) && isset($map[$profile[$k]])) {
                $out[$k] = $profile[$k];
            }
        }
        $c = strtolower((string) ($profile['complexion'] ?? ''));
        if (isset(self::COMPLEXION[$c])) {
            $out['complexion'] = $c;
        }
        $marks = SseWantedCatalog::line($profile['marks'] ?? '', self::FREE['marks']);
        if ($marks !== '') {
            $out['marks'] = $marks;
        }

        return $out;
    }

    /** Relit une forme gardée (JSON, absente ou abîmée : tableau vide). @return array<string, mixed> */
    public static function read(mixed $stored): array
    {
        if (is_string($stored)) {
            $stored = json_decode($stored, true);
        }
        if (!is_array($stored)) {
            return [];
        }

        return self::normalize($stored, is_string($stored['snapshot_at'] ?? null) ? $stored['snapshot_at'] : null) ?? [];
    }

    /** Libellé d'une valeur de liste (la clé elle-même si elle est inconnue). */
    public static function label(string $field, string $key, string $lang): string
    {
        $map = self::lists()[$field] ?? ($field === 'equipment' ? self::EQUIPMENT : []);

        return isset($map[$key]) ? $map[$key][$lang === 'en' ? 1 : 0] : $key;
    }

    /**
     * Signalement sous forme de lignes (étiquette, valeur), pour la fiche et le PDF.
     *
     * @param array<string, mixed> $physical
     * @return list<array{field: string, label: string, value: string}>
     */
    public static function rows(array $physical, string $lang): array
    {
        $en = $lang === 'en';
        $names = [
            'sex' => ['Sexe', 'Sex'], 'age' => ['Âge estimé', 'Estimated age'], 'height_cm' => ['Taille', 'Height'], 'weight_kg' => ['Poids', 'Weight'],
            'build' => ['Corpulence', 'Build'], 'hair' => ['Cheveux', 'Hair'], 'facial_hair' => ['Pilosité faciale', 'Facial hair'], 'eyes' => ['Yeux', 'Eyes'],
            'complexion' => ['Teint', 'Complexion'], 'marks' => ['Signes particuliers', 'Distinguishing marks'], 'tattoos' => ['Tatouages', 'Tattoos'],
            'scars' => ['Cicatrices', 'Scars'], 'clothing' => ['Vêtements portés', 'Clothing worn'], 'gait' => ['Démarche', 'Gait'],
            'language' => ['Langue et accent', 'Language and accent'], 'disabilities' => ['Handicaps', 'Disabilities'], 'equipment' => ['Équipements habituels', 'Usual equipment'],
            'notes' => ['Autres précisions', 'Other details'],
        ];
        $lists = self::lists();
        $out = [];
        foreach ($names as $field => $label) {
            if (!isset($physical[$field])) {
                continue;
            }
            $v = $physical[$field];
            $value = match (true) {
                $field === 'height_cm' => $v . ' cm',
                $field === 'weight_kg' => $v . ' kg',
                $field === 'equipment' => implode(', ', array_map(static fn (string $k): string => self::label('equipment', $k, $lang), (array) $v)),
                isset($lists[$field]) => self::label($field, (string) $v, $lang),
                default => (string) $v,
            };
            if ($value !== '') {
                $out[] = ['field' => $field, 'label' => $label[$en ? 1 : 0], 'value' => $value];
            }
        }

        return $out;
    }

    /** Signalement en une ligne (sexe, âge, taille, poids, corpulence, cheveux, yeux). @param array<string, mixed> $physical */
    public static function shortLine(array $physical, string $lang): string
    {
        $en = $lang === 'en';
        $parts = [];
        foreach (['sex', 'age'] as $k) {
            if (isset($physical[$k])) {
                $parts[] = self::label($k, (string) $physical[$k], $lang);
            }
        }
        if (isset($physical['height_cm'])) {
            $parts[] = $physical['height_cm'] . ' cm';
        }
        if (isset($physical['weight_kg'])) {
            $parts[] = $physical['weight_kg'] . ' kg';
        }
        if (isset($physical['build'])) {
            $parts[] = self::label('build', (string) $physical['build'], $lang);
        }
        if (isset($physical['hair'])) {
            $parts[] = ($en ? 'hair ' : 'cheveux ') . mb_strtolower(self::label('hair', (string) $physical['hair'], $lang));
        }
        if (isset($physical['eyes'])) {
            $parts[] = ($en ? 'eyes ' : 'yeux ') . mb_strtolower(self::label('eyes', (string) $physical['eyes'], $lang));
        }

        return implode(' · ', $parts);
    }

    /**
     * Forme exposée au jeu : champs courts (listes en clés, textes tronqués à 80 caractères) et une ligne de résumé par langue.
     *
     * @param array<string, mixed> $physical
     * @return array<string, mixed>
     */
    public static function export(array $physical): array
    {
        if ($physical === []) {
            return [];
        }
        $out = ['summary_fr' => self::shortLine($physical, 'fr'), 'summary_en' => self::shortLine($physical, 'en')];
        foreach (['height_cm', 'weight_kg'] as $k) {
            if (isset($physical[$k])) {
                $out[$k] = (int) $physical[$k];
            }
        }
        foreach (array_keys(self::lists()) as $k) {
            if (isset($physical[$k])) {
                $out[$k] = (string) $physical[$k];
            }
        }
        foreach (['marks', 'tattoos', 'scars', 'clothing'] as $k) {
            if (isset($physical[$k])) {
                $out[$k] = mb_substr((string) $physical[$k], 0, 80);
            }
        }

        return $out;
    }
}
