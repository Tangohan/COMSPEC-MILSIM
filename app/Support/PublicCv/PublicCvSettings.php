<?php

declare(strict_types=1);

namespace App\Support\PublicCv;

/**
 * Réglages de la page publique du joueur : sections affichables, adresse courte, présentation.
 * Aucune dépendance base : tout est testable seul.
 */
final class PublicCvSettings
{
    public const SLUG_MIN = 3;
    public const SLUG_MAX = 32;
    public const BIO_MAX = 300;

    /** Clé interne => [libellé, aide] affichés dans le réglage du compte. */
    public const SECTIONS = [
        'unit' => ['Grade, unité et communauté', 'Sous votre indicatif, en haut de la page, avec la bannière et le lien de votre communauté.'],
        'roles' => ['Fonction et poste', 'Votre fonction principale, vos autres fonctions et le poste que vous tenez dans votre unité.'],
        'counters' => ['Compteurs', 'Opérations jouées, heures de vol, qualifications et formations.'],
        'activity' => ['Activité en jeu', 'Kilomètres parcourus (à pied, en véhicule, en vol, en mer, en chute libre), heures de jeu et rôles tenus en mission.'],
        'since' => ['Membre depuis', 'Le mois où vous avez rejoint la communauté.'],
        'career' => ['Parcours des grades', 'Vos promotions et leurs dates. Les motifs et commentaires du commandement n’y figurent jamais.'],
        'evaluations' => ['Évaluations du commandement', 'Les notes (sur 5) et les commentaires des bilans du commandement dans cette communauté. Les bilans RH et de recrutement n’y figurent jamais.'],
        'mutations' => ['Mutations', 'Vos changements d’unité, de poste et d’affectation dans cette communauté.'],
        'discord' => ['Lien Discord', 'Un lien vers votre compte Discord, seulement si vous l’avez lié à Athena.'],
        'bio' => ['Présentation', 'Le court texte que vous écrivez ci-dessous.'],
        'decorations' => ['Décorations', 'Les distinctions qui vous ont été remises.'],
        'qualifications' => ['Qualifications', 'Vos qualifications en cours de validité.'],
        'trainings' => ['Formations validées', 'Les formations et cursus Academy terminés.'],
        'operations' => ['Opérations marquantes', 'Les dernières opérations auxquelles vous avez participé.'],
        'photos' => ['Photos', 'Les photos que vous avez publiées sur la page de la communauté.'],
    ];

    /**
     * Sections ajoutées après coup qui restent masquées tant que le joueur ne les coche pas (y compris sur les pages
     * déjà ouvertes). Les autres sont visibles par défaut.
     */
    public const OPT_IN = ['since', 'career', 'evaluations', 'mutations', 'discord'];

    /** Adresses réservées : pages du site ou mots trompeurs. */
    private const RESERVED = [
        'admin', 'administrateur', 'moderateur', 'staff', 'support', 'athena', 'comspec', 'api', 'login',
        'connexion', 'compte', 'account', 'nouveau', 'new', 'edit', 'modifier', 'me', 'moi', 'null', 'undefined',
    ];

    /**
     * Normalise une saisie libre en adresse courte (minuscules, chiffres, tirets).
     */
    public static function normalizeSlug(string $raw): string
    {
        $s = trim($raw);
        if ($s === '') {
            return '';
        }
        if (function_exists('iconv')) {
            $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if (is_string($t) && $t !== '') {
                $s = $t;
            }
        }
        $s = strtolower($s);
        $s = (string) preg_replace('/[^a-z0-9]+/', '-', $s);
        $s = trim((string) preg_replace('/-{2,}/', '-', $s), '-');

        return substr($s, 0, self::SLUG_MAX);
    }

    /** Message d’erreur lisible, ou null si l’adresse est acceptable. */
    public static function slugError(string $slug): ?string
    {
        if ($slug === '') {
            return 'Choisissez l’adresse de votre page.';
        }
        if (strlen($slug) < self::SLUG_MIN) {
            return 'L’adresse doit contenir au moins ' . self::SLUG_MIN . ' caractères.';
        }
        if (preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $slug) !== 1) {
            return 'Utilisez seulement des lettres, des chiffres et des tirets.';
        }
        if (in_array($slug, self::RESERVED, true)) {
            return 'Cette adresse est réservée par le site. Choisissez-en une autre.';
        }

        return null;
    }

    /** Valeur de l’adresse acceptée par la page publique (même règle qu’à l’enregistrement). */
    public static function isValidSlug(string $slug): bool
    {
        return self::slugError($slug) === null;
    }

    public static function normalizeBio(string $raw): string
    {
        $s = trim(str_replace(["\r\n", "\r"], "\n", strip_tags($raw)));
        $s = (string) preg_replace("/\n{3,}/", "\n\n", $s);

        return mb_substr($s, 0, self::BIO_MAX);
    }

    /**
     * Sections par défaut : visibles sauf OPT_IN (la page elle-même reste désactivée tant que le joueur ne l’a pas ouverte).
     *
     * @return array<string, bool>
     */
    public static function defaultSections(): array
    {
        $out = array_fill_keys(array_keys(self::SECTIONS), true);
        foreach (self::OPT_IN as $key) {
            $out[$key] = false;
        }

        return $out;
    }

    /**
     * Lit les sections enregistrées ; une clé inconnue est ignorée, une clé absente reprend sa valeur par défaut.
     *
     * @return array<string, bool>
     */
    public static function decodeSections(?string $json): array
    {
        $out = self::defaultSections();
        if ($json === null || trim($json) === '') {
            return $out;
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return $out;
        }
        foreach ($out as $key => $_) {
            if (array_key_exists($key, $data)) {
                $out[$key] = (bool) $data[$key];
            }
        }

        return $out;
    }

    /**
     * Sections cochées dans le formulaire (liste de clés) => carte complète.
     *
     * @param mixed $checked
     * @return array<string, bool>
     */
    public static function sectionsFromForm(mixed $checked): array
    {
        $keys = is_array($checked) ? array_map('strval', $checked) : [];
        $out = [];
        foreach (array_keys(self::SECTIONS) as $key) {
            $out[$key] = in_array($key, $keys, true);
        }

        return $out;
    }

    /** @param array<string, bool> $sections */
    public static function encodeSections(array $sections): string
    {
        $out = [];
        foreach (array_keys(self::SECTIONS) as $key) {
            $out[$key] = !empty($sections[$key]) ? 1 : 0;
        }

        return (string) json_encode($out);
    }
}
