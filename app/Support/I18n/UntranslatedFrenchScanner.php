<?php

declare(strict_types=1);

namespace App\Support\I18n;

use App\Support\HtmlTranslator;

/**
 * Contrôle des oublis de traduction anglaise : relève dans une vue (views/) ou un script (public/assets/js) les textes
 * français écrits en dur qui n'ont pas d'entrée dans lang/en/site_auto.php, donc restent en français pour un lecteur
 * anglais (HtmlTranslator à l'affichage, i18n_dom.js dans le navigateur).
 *
 * Heuristique, alignée sur tools/i18n/site_i18n.py : textes entre balises, attributs traduits (title, placeholder,
 * aria-label, alt, value, data-confirm, data-label), littéraux PHP et JS qui ont l'air français (accent ou mot outil
 * français, ou mot d'interface courant). Les clés passées à __() / t() et les commentaires sont ignorés ; chaque
 * <?= ... ?> au milieu d'un texte devient %1, %2... comme dans le dictionnaire.
 *
 * Utilisé par tools/i18n/untranslated.php (rapport) et par le test de non-régression des vues traitées.
 */
final class UntranslatedFrenchScanner
{
    public const FRENCH_HINT = '/[àâäçéèêëîïôöùûüÿœÀÂÇÉÈÊÎÔÙÛŒ]|\b(le|la|les|des|du|un|une|et|ou|pour|avec|sans|dans|sur|par|vos|votre|nos|notre|est|sont|pas|ne|aucun|aucune|au|aux|ce|cette|ces|son|sa|ses|leur|qui|que|quand|de|en|mon|ma|mes|nous|vous|il|ils|elle|elles|se|chaque|tout|toute|tous|toutes|jours?|ans|mois|semaines?|heures?|libres?|tr[eè]s|ici|oui|mot|objet|bienvenue|merci|nouveau|nouvelle|bonjour|partiels?|interdite?s?)\b|\b(?:[cdjlmnst]|qu)[\'’]\p{L}/iu';

    /** Mots d'interface français sans accent ni mot outil (« Annuler », « Fermer »). */
    private const UI_WORDS = '/^(Annuler|Fermer|Enregistrer|Valider|Supprimer|Modifier|Ajouter|Rechercher|Filtrer|Suivant|Retour|Envoyer|Ouvrir|Voir|Afficher|Masquer|Choisir|Confirmer|Continuer|Signer|Membres?|Nom|Grade|Statut|Oui|Non|Tous|Toutes|Aucun|Chargement|Erreur|Effectifs|Emplois?|Fonctions?|Postes?|Profil|Compte|Copier|Imprimer|Exporter|Importer|Trier|Rafraichir|Actualiser|Dupliquer|Archiver|Restaurer|Activer|Desactiver|Publier|Brouillon|Lecture|Ecriture|Heure|Jour|Semaine|Mois|Raison|Motif|Commentaire|Titre|Libelle|Recherche|Filtres?|Fiche|Dossier|Groupe|Unite)$/u';

    private const ATTR = '/\b(?:title|placeholder|aria-label|alt|value|data-confirm|data-label)\s*=\s*(["\'])(.*?)\1/is';

    /** @var array<string, string>|null */
    private static ?array $dict = null;

    /**
     * Textes volontairement laissés tels quels (identiques en anglais, noms propres, bruit d'extraction), avec leur
     * raison : tools/i18n/untranslated_keep.tsv (« texte<TAB>raison »).
     *
     * @var array<string, true>|null
     */
    private static ?array $keep = null;

    /** @param array<string, string>|null $dict null : lang/en/site_auto.php */
    public static function useDictionary(?array $dict): void
    {
        self::$dict = $dict;
        HtmlTranslator::reset();
        if ($dict !== null) {
            HtmlTranslator::useDictionary($dict);
        }
    }

    /**
     * Textes français sans traduction d'un fichier (vue .php ou script .js).
     *
     * @return list<string>
     */
    public static function missingInFile(string $path): array
    {
        $src = (string) file_get_contents($path);
        $segments = str_ends_with($path, '.js') ? self::jsSegments($src) : self::viewSegments($src);
        $out = [];
        foreach ($segments as $s) {
            if (!self::isTranslated($s)) {
                $out[] = $s;
            }
        }
        sort($out);

        return array_values(array_unique($out));
    }

    public static function isTranslated(string $s): bool
    {
        $d = self::dict();
        $keep = self::keep();
        foreach ([$s, str_replace("'", '’', $s), str_replace('’', "'", $s)] as $v) {
            if (isset($d[$v]) || isset($keep[$v])) {
                return true;
            }
        }
        // Repli du traducteur (« Libellé : valeur », morceaux « · », modèle %1).
        return HtmlTranslator::translateText($s) !== $s;
    }

    /** @return array<string, true> */
    private static function keep(): array
    {
        if (self::$keep === null) {
            self::$keep = [];
            $file = dirname(__DIR__, 3) . '/tools/i18n/untranslated_keep.tsv';
            foreach (is_file($file) ? (file($file, FILE_IGNORE_NEW_LINES) ?: []) : [] as $line) {
                $text = explode(chr(9), $line, 2)[0];
                if ($text !== '' && !str_starts_with($text, '#')) {
                    self::$keep[$text] = true;
                }
            }
        }

        return self::$keep;
    }

    /** @return array<string, string> */
    private static function dict(): array
    {
        if (self::$dict === null) {
            /** @var mixed $data */
            $data = require dirname(__DIR__, 3) . '/lang/en/site_auto.php';
            self::useDictionary(is_array($data) ? $data : []);
        }

        return self::$dict ?? [];
    }

    /** @return list<string> */
    public static function viewSegments(string $src): array
    {
        $out = [];
        // Littéraux PHP (libellés de tableaux, ternaires, messages) : seulement s'ils ont l'air français.
        $tokens = @token_get_all($src);
        $prev = [];
        foreach ($tokens as $tok) {
            if (!is_array($tok)) {
                $prev[] = $tok;
                $prev = array_slice($prev, -2);
                continue;
            }
            if (in_array($tok[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            if ($tok[0] === T_CONSTANT_ENCAPSED_STRING) {
                $isKey = $prev === [[T_STRING, '__'], '('] || $prev === [[T_STRING, 't'], '('] || $prev === [[T_STRING, 'trans'], '('];
                if (!$isKey) {
                    $lit = self::phpLiteral($tok[1]);
                    if (preg_match(self::FRENCH_HINT, $lit)) {
                        foreach (preg_split('/<[^<>]*>/', $lit) ?: [] as $chunk) {
                            $n = 0;
                            $chunk = (string) preg_replace_callback('/%(\d+\$)?[sd]/', static function () use (&$n): string {
                                return '%' . (++$n);
                            }, $chunk);
                            array_push($out, ...self::segments($chunk));
                        }
                    }
                }
            }
            $prev[] = [$tok[0], $tok[0] === T_STRING ? $tok[1] : ''];
            $prev = array_slice($prev, -2);
        }

        // Gabarit HTML : PHP non affiché retiré, chaque écho PHP devient %N dans son texte.
        $tpl = (string) preg_replace('/<\?php.*?(?:\?>|\z)/s', "\x00", $src);
        $tpl = (string) preg_replace_callback('~<(script|style|textarea|pre|code)\b[^>]*>.*?</\1\s*>~is', static function (array $m): string {
            return strtolower($m[1]) === 'textarea' ? substr($m[0], 0, (int) strpos($m[0], '>') + 1) . "\x00" : "\x00";
        }, $tpl);
        // Élément marqué translate="no" sur une seule ligne : contenu saisi, jamais traduit.
        $tpl = (string) preg_replace('~<([a-z][a-z0-9]*)\b[^>]*\btranslate="no"[^>]*>[^<]*</\1>~i', "\x00", $tpl);
        if (preg_match_all(self::ATTR, $tpl, $mm)) {
            foreach ($mm[2] as $v) {
                array_push($out, ...self::segments(self::templ($v)));
            }
        }
        $pieces = preg_split('/(<(?!\?=)[^<>]*(?:<\?=.*?\?>[^<>]*)*>)/s', $tpl, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        foreach ($pieces as $p) {
            if (str_starts_with($p, '<') && !str_starts_with($p, '<?=')) {
                continue;
            }
            foreach (explode("\x00", $p) as $chunk) {
                array_push($out, ...self::segments(self::templ($chunk)));
            }
        }

        return array_values(array_unique($out));
    }

    /** @return list<string> */
    public static function jsSegments(string $code): array
    {
        $code = self::stripJsComments($code);
        $raw = [];
        $str = '\'(?:[^\'\\\\\n]|\\\\.)*\'|"(?:[^"\\\\\n]|\\\\.)*"';
        // « 'Texte ' + x + ' fin' » -> « Texte %1 fin ».
        if (preg_match_all('/(?:(?:' . $str . ')\s*\+\s*[^;\'"\n+]{1,60}?\s*\+\s*)+(?:' . $str . ')/', $code, $mm)) {
            foreach ($mm[0] as $expr) {
                $n = 0;
                $buf = '';
                foreach (preg_split('/\s*\+\s*/', $expr) ?: [] as $part) {
                    $part = trim($part);
                    if (strlen($part) >= 2 && ($part[0] === "'" || $part[0] === '"') && substr($part, -1) === $part[0]) {
                        $buf .= self::jsUnescape(substr($part, 1, -1));
                    } else {
                        $buf .= '%' . (++$n);
                    }
                }
                $raw[] = $buf;
            }
        }
        if (preg_match_all('/\'((?:[^\'\\\\\n]|\\\\.)*)\'|"((?:[^"\\\\\n]|\\\\.)*)"|`((?:[^`\\\\]|\\\\.)*)`/', $code, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $m) {
                if (isset($m[3]) && $m[3] !== '') {
                    $n = 0;
                    $raw[] = (string) preg_replace_callback('/\$\{[^}]*\}/', static function () use (&$n): string {
                        return '%' . (++$n);
                    }, $m[3]);
                } else {
                    $raw[] = self::jsUnescape(($m[1] ?? '') !== '' ? $m[1] : ($m[2] ?? ''));
                }
            }
        }
        $out = [];
        foreach ($raw as $s) {
            foreach (preg_split('/<[^<>]*>/', $s) ?: [] as $chunk) {
                foreach (self::segments($chunk) as $seg) {
                    if (mb_strlen($seg) >= 4 && !preg_match('/[{};]|=>|\bfunction\b|\$\(|document\.|querySelector|\w%\d|%\d\w/', $seg)) {
                        $out[] = $seg;
                    }
                }
            }
        }

        return array_values(array_unique($out));
    }

    /** Retire les commentaires JS (hors chaînes) : leurs apostrophes passaient pour des chaînes. */
    public static function stripJsComments(string $code): string
    {
        $out = '';
        $len = strlen($code);
        $i = 0;
        while ($i < $len) {
            $c = $code[$i];
            if ($c === "'" || $c === '"' || $c === '`') {
                $j = $i + 1;
                while ($j < $len && $code[$j] !== $c && ($c === '`' || $code[$j] !== "\n")) {
                    $j += $code[$j] === '\\' ? 2 : 1;
                }
                $out .= substr($code, $i, $j - $i + 1);
                $i = $j + 1;
                continue;
            }
            if ($c === '/' && $i + 1 < $len && $code[$i + 1] === '/') {
                $nl = strpos($code, "\n", $i);
                $i = $nl === false ? $len : $nl;
                continue;
            }
            if ($c === '/' && $i + 1 < $len && $code[$i + 1] === '*') {
                $end = strpos($code, '*/', $i + 2);
                $i = $end === false ? $len : $end + 2;
                continue;
            }
            // Expression régulière littérale après un opérateur : recopiée telle quelle (une apostrophe dedans
            // ouvrirait une fausse chaîne).
            if ($c === '/' && preg_match('~[=(,:!&|?;\[]\s*$~', substr($out, -20))) {
                if (preg_match('~\G/(?:[^/\\\\\n\[]|\\\\.|\[(?:[^\]\\\\\n]|\\\\.)*\])+/[a-z]*~', $code, $m, 0, $i)) {
                    $out .= $m[0];
                    $i += strlen($m[0]);
                    continue;
                }
            }
            $out .= $c;
            $i++;
        }

        return $out;
    }

    /** @return list<string> */
    private static function segments(string $text): array
    {
        $out = [];
        // « \x00 » : bloc PHP retiré au milieu d'un attribut, jamais un texte d'un seul tenant.
        foreach (preg_split('/\s+·\s+|\x00/u', $text) ?: [] as $part) {
            $p = self::norm($part);
            if (!self::isText($p) || preg_match('/^(%\d\s*)+$/', $p)) {
                continue;
            }
            if (!preg_match(self::FRENCH_HINT, $p) && !preg_match(self::UI_WORDS, $p)) {
                // Sans indice de français : déjà anglais, identifiant ou nom propre (bruit écarté).
                continue;
            }
            $out[] = $p;
        }

        return $out;
    }

    private static function isText(string $s): bool
    {
        if ($s === '' || !preg_match('/[A-Za-zÀ-ÿ]/u', $s)) {
            return false;
        }
        if (preg_match('~^[\w\-./:#%]+$~u', $s) && !preg_match('/[À-ÿ]/u', $s) && !str_contains($s, ' ')) {
            return (bool) preg_match('/^[A-ZÀ-Ý][a-zà-ÿ]+$/u', $s);
        }
        if (preg_match('/[{}$]|(?<! );|=>|->|::|\bfunction\b|https?:\/\//', $s)) {
            return false;
        }

        return true;
    }

    private static function norm(string $s): string
    {
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $s));
    }

    private static function templ(string $chunk): string
    {
        $n = 0;

        return (string) preg_replace_callback('/<\?=.*?\?>/s', static function () use (&$n): string {
            return '%' . (++$n);
        }, $chunk);
    }

    private static function phpLiteral(string $tok): string
    {
        $q = $tok[0];
        $body = substr($tok, 1, -1);

        return $q === "'" ? str_replace(["\\'", '\\\\'], ["'", '\\'], $body) : stripcslashes($body);
    }

    private static function jsUnescape(string $s): string
    {
        $s = (string) preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', static fn (array $m): string => mb_chr((int) hexdec($m[1]), 'UTF-8'), $s);

        return (string) preg_replace_callback('/\\\\(.)/s', static fn (array $m): string => ['n' => "\n", 't' => ' '][$m[1]] ?? $m[1], $s);
    }
}
