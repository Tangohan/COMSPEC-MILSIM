<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Texte libre des fiches PDF (jeu de rôle) : un mini-balisage sûr, jamais de HTML.
 *
 *  - gras : **texte** ; italique : *texte* ; liste : une ligne qui commence par « - » ;
 *  - tout `<` et `>` est retiré à l'enregistrement, les caractères de contrôle aussi, la longueur est bornée par champ ;
 *  - le PDF reçoit du HTML fabriqué ici à partir du texte échappé (jamais le texte saisi tel quel).
 *
 * Variables insérables ({nom}, {alias}, {menace}…) : résolues par la fabrique de la fiche selon l'habilitation du rédacteur ;
 * une variable de donnée masquée donne la barre de caviardage, jamais la valeur.
 */
final class SseSheetText
{
    /** Variables (clé => [français, anglais, catégorie de caviardage ou null]). */
    public const VARIABLES = [
        'nom' => ['Nom de famille', 'Family name', 'identite'],
        'prenom' => ['Prénom', 'First name', 'identite'],
        'nom_complet' => ['Nom complet', 'Full name', 'identite'],
        'alias' => ['Alias', 'Aliases', 'identite'],
        'date_naissance' => ['Date de naissance', 'Date of birth', 'identite'],
        'nationalite' => ['Nationalité', 'Nationality', 'identite'],
        'affiliation' => ['Affiliation', 'Affiliation', null],
        'signalement' => ['Signalement (taille, yeux, cheveux)', 'Description (height, eyes, hair)', null],
        'marques' => ['Signes particuliers', 'Distinguishing marks', null],
        'menace' => ['Niveau de menace', 'Threat level', null],
        'dernier_lieu' => ['Dernier lieu connu', 'Last known location', 'lieu'],
        'derniere_date' => ['Date de dernière observation', 'Last seen date', 'horodatage'],
        'nb_controles' => ['Nombre de contrôles', 'Number of checks', null],
        'ref' => ['Référence du document', 'Document reference', null],
        'service' => ['Service émetteur', 'Issuing service', null],
        'date' => ['Date d’émission', 'Issue date', null],
    ];

    /** Retire le HTML, les caractères de contrôle et borne la longueur. Garde le mini-balisage. */
    public static function clean(mixed $raw, int $max): string
    {
        $s = is_scalar($raw) ? (string) $raw : '';
        $s = str_replace(["\r\n", "\r"], "\n", $s);
        $s = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s);
        $s = strip_tags($s);
        $s = str_replace(['<', '>'], '', $s);
        $s = (string) preg_replace("/[ \t]+\n/", "\n", $s);
        $s = (string) preg_replace("/\n{3,}/", "\n\n", $s);
        $s = trim($s);

        return mb_substr($s, 0, max(0, $max), 'UTF-8');
    }

    /** Texte sans balisage : sert à mesurer la longueur imprimée et au texte brut. */
    public static function plain(string $clean): string
    {
        $s = (string) preg_replace('/^[ \t]*-[ \t]+/m', '• ', $clean);
        $s = (string) preg_replace('/\*\*(.+?)\*\*/su', '$1', $s);

        return (string) preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/su', '$1', $s);
    }

    /** HTML sûr du texte nettoyé (échappé d'abord, puis balises fabriquées ici). */
    public static function toHtml(string $clean): string
    {
        $out = '';
        foreach (explode("\n", $clean) as $line) {
            $isItem = preg_match('/^\s*-\s+(.*)$/u', $line, $m) === 1;
            $html = static function (string $t): string {
                $e = htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
                $e = (string) preg_replace('/\*\*(.+?)\*\*/su', '<b>$1</b>', $e);

                return (string) preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/su', '<i>$1</i>', $e);
            };
            // Liste : puces écrites en texte (les listes HTML de TCPDF ajoutent de grandes marges).
            $out .= $isItem ? '&bull;&nbsp;' . $html((string) $m[1]) . '<br>' : (trim($line) === '' ? '<br>' : $html($line) . '<br>');
        }

        return (string) preg_replace('/(<br>)+$/', '', $out);
    }

    /**
     * Remplace les {variables} connues. Une variable absente de $values reste en clair (faute de frappe visible).
     *
     * @param array<string, string> $values
     * @return array{text: string, used: list<string>} texte et variables effectivement remplacées
     */
    public static function resolve(string $text, array $values): array
    {
        $used = [];
        $out = (string) preg_replace_callback('/\{([a-z_]{2,20})\}/', static function (array $m) use ($values, &$used): string {
            if (!array_key_exists($m[1], $values)) {
                return $m[0];
            }
            $used[$m[1]] = true;

            return $values[$m[1]];
        }, $text);

        return ['text' => $out, 'used' => array_keys($used)];
    }

    /** Catégories de caviardage touchées par ces variables (identité, lieu…). @param list<string> $vars @return list<string> */
    public static function categories(array $vars): array
    {
        $cats = [];
        foreach ($vars as $v) {
            $c = self::VARIABLES[$v][2] ?? null;
            if ($c !== null) {
                $cats[$c] = true;
            }
        }

        return array_keys($cats);
    }

    /** Nombre de caractères imprimés (balisage exclu). */
    public static function length(string $clean): int
    {
        return mb_strlen(self::plain($clean), 'UTF-8');
    }
}
