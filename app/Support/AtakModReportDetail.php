<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Rend lisible le « Détail technique » d'un rapport du mod (/admin/atak-mod-reports).
 *
 * Le mod envoie souvent un tableau SQF passé plusieurs fois dans str : les guillemets sont doublés à chaque
 * passage (""""ok""""), la réponse JSON d'Athena est collée telle quelle et les accents arrivent parfois
 * cassés (octets Windows-1252 ou caractère de remplacement « � »). On en tire :
 *   - facts : lignes « libellé → valeur » (niveau, code HTTP, message du serveur, adresse, identifiant de requête…) ;
 *   - json  : le JSON trouvé, indenté ;
 *   - text  : le reste en clair, guillemets dédoublés ;
 *   - raw   : le texte reçu, encodage réparé, pour la vue « brut ».
 */
final class AtakModReportDetail
{
    /** Codes HTTP fréquents → explication courte. */
    private const HTTP_MEANING = [
        400 => 'requête refusée par Athena (données invalides)',
        401 => 'connexion refusée (jeton absent ou périmé)',
        403 => 'accès interdit pour ce compte',
        404 => 'adresse ou élément introuvable',
        408 => 'délai dépassé',
        409 => 'conflit avec une donnée existante',
        413 => 'envoi trop volumineux',
        422 => 'données refusées',
        429 => 'trop de requêtes, envoi freiné',
        500 => 'erreur interne d’Athena',
        502 => 'Athena injoignable derrière le proxy',
        503 => 'Athena momentanément indisponible',
        504 => 'Athena n’a pas répondu à temps',
    ];

    /** Clés JSON connues → libellé. Les autres sont affichées sous leur nom. */
    private const JSON_LABELS = [
        'message' => 'Message du serveur',
        'error' => 'Erreur',
        'code' => 'Code',
        'status' => 'Statut',
        'request_id' => 'Identifiant de requête',
        'reason' => 'Raison',
        'detail' => 'Détail',
    ];

    /** Ordre d'affichage des faits ; les autres suivent dans l'ordre d'arrivée. */
    private const FACT_ORDER = [
        'Niveau', 'Code HTTP', 'Message du serveur', 'Erreur', 'Raison', 'Adresse appelée', 'Il y a',
        'Diagnostic Athena', 'Identifiant de requête',
    ];

    /** Mots français dont l'accent est arrivé en « � » : reconstruits tels quels. */
    private const ACCENT_WORDS = [
        'momentan�ment' => 'momentanément', 'R�essayez' => 'Réessayez', 'r�essayez' => 'réessayez',
        'acc�s' => 'accès', 'Acc�s' => 'Accès', 'refus�' => 'refusé', 'refus�e' => 'refusée',
        'd�lai' => 'délai', 'D�lai' => 'Délai', 'd�pass�' => 'dépassé', 'p�rim�' => 'périmé',
        'connect�' => 'connecté', 'd�connect�' => 'déconnecté', 'trouv�' => 'trouvé', 'trouv�e' => 'trouvée',
        'cl�' => 'clé', 'Cl�' => 'Clé', 'cr��' => 'créé', 'g�n�r�' => 'généré', 'requ�te' => 'requête',
        'Requ�te' => 'Requête', 'donn�es' => 'données', 'Donn�es' => 'Données', 'r�seau' => 'réseau',
        'R�seau' => 'Réseau', 'communaut�' => 'communauté', 'activ�' => 'activé', 'activ�e' => 'activée',
        'd�sactiv�' => 'désactivé', 'expir�' => 'expiré', 'expir�e' => 'expirée', '�chec' => 'échec',
        '�CHEC' => 'ÉCHEC', '�chou�' => 'échoué', 'op�rateur' => 'opérateur',
        'pr�sentation' => 'présentation', 'Pr�sentation' => 'Présentation', 'r�ponse' => 'réponse',
        'R�ponse' => 'Réponse', 'm�me' => 'même', 'li�' => 'lié', 'li�e' => 'liée', 'v�rifiez' => 'vérifiez',
        'V�rifiez' => 'Vérifiez', 'pr�t' => 'prêt', 'enregistr�' => 'enregistré', 'envoy�' => 'envoyé',
    ];

    /**
     * @return array{
     *   facts: list<array{label:string, value:string, note?:string}>,
     *   json: ?string,
     *   text: string,
     *   raw: string,
     *   readable: bool,
     *   repaired: bool
     * }
     */
    public static function parse(string $raw): array
    {
        [$fixed, $repaired] = self::repairEncoding(trim($raw));
        $facts = [];
        $jsonParts = [];
        $textLines = [];

        foreach (preg_split('/\R/u', $fixed) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            // Lignes ajoutées par Athena à la réception (diagnostic connexion, formule…).
            if (preg_match('/^Diagnostic Athena\s*:\s*(.+)$/u', $line, $m) === 1) {
                $facts[] = ['label' => 'Diagnostic Athena', 'value' => trim($m[1])];
                continue;
            }
            [$lineFacts, $lineJson, $lineText] = self::parseLine($line);
            array_push($facts, ...$lineFacts);
            array_push($jsonParts, ...$lineJson);
            if ($lineText !== '') {
                $textLines[] = $lineText;
            }
        }

        $text = implode("\n", $textLines);

        return [
            'facts' => self::uniqueFacts($facts),
            'json' => $jsonParts !== [] ? implode("\n\n", $jsonParts) : null,
            'text' => $text,
            'raw' => $fixed,
            // Vrai quand la mise en forme apporte quelque chose par rapport au brut.
            'readable' => $facts !== [] || $jsonParts !== [] || $text !== $fixed,
            'repaired' => $repaired,
        ];
    }

    /** Une phrase courte pour Discord ou un aperçu : faits principaux, sinon texte nettoyé. */
    public static function summary(string $raw): string
    {
        $p = self::parse($raw);
        $bits = [];
        foreach ($p['facts'] as $f) {
            $bits[] = $f['label'] . ' : ' . $f['value'] . (isset($f['note']) ? ' (' . $f['note'] . ')' : '');
        }
        if ($p['text'] !== '') {
            $bits[] = $p['text'];
        }

        return $bits !== [] ? implode(' · ', $bits) : $p['raw'];
    }

    /**
     * @return array{0: list<array{label:string, value:string, note?:string}>, 1: list<string>, 2: string}
     */
    private static function parseLine(string $line): array
    {
        $s = self::unquote($line);
        $facts = [];
        $json = [];

        // Objets JSON collés (réponse HTTP d'Athena) : décodés puis retirés du texte.
        $offset = 0;
        while (($start = strpos($s, '{', $offset)) !== false) {
            $end = self::matchingBrace($s, $start);
            if ($end === null) {
                break;
            }
            $chunk = substr($s, $start, $end - $start + 1);
            $decoded = json_decode($chunk, true);
            if (!is_array($decoded)) {
                $offset = $start + 1;
                continue;
            }
            foreach ($decoded as $key => $value) {
                if (is_scalar($value) && !is_bool($value) && trim((string) $value) !== '') {
                    $label = self::JSON_LABELS[(string) $key] ?? (string) $key;
                    $facts[] = ['label' => $label, 'value' => trim((string) $value)];
                }
            }
            $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (is_string($pretty)) {
                $json[] = $pretty;
            }
            $s = substr($s, 0, $start) . ' ' . substr($s, $end + 1);
            $offset = $start;
        }

        // Reste : morceaux du tableau SQF (["ERROR","HTTP 503: …",0,0]).
        $tokens = preg_split('/\s*(?:[\[\]"]|,(?=\s*["\[\]\d-]|\s*$))\s*/u', $s) ?: [];
        $rest = [];
        foreach ($tokens as $tok) {
            $tok = trim($tok, " \t,:");
            if ($tok === '' || preg_match('/^-?0(?:\.0+)?$/', $tok) === 1) {
                continue; // 0 de remplissage des tableaux SQF : sans intérêt.
            }
            if (preg_match('/^(ERROR|ERREUR|WARN|WARNING|INFO|DEBUG|FAIL|FAILED|OK)$/i', $tok) === 1) {
                $facts[] = ['label' => 'Niveau', 'value' => strtoupper($tok)];
                continue;
            }
            // « 400|/public/api/atak/telemetry/batch|0 » : code HTTP | adresse | il y a (s).
            if (preg_match('#^(\d{3})\|(/[^|\s]*)(?:\|(\d+))?$#', $tok, $m) === 1) {
                $facts[] = self::httpFact((int) $m[1]);
                $facts[] = ['label' => 'Adresse appelée', 'value' => $m[2]];
                if (isset($m[3]) && $m[3] !== '') {
                    $facts[] = ['label' => 'Il y a', 'value' => $m[3] . ' s'];
                }
                continue;
            }
            if (preg_match('/^HTTP\s+(\d{3})\b\s*(.*)$/iu', $tok, $m) === 1) {
                $facts[] = self::httpFact((int) $m[1]);
                $tok = trim($m[2], " \t,:");
                if ($tok === '') {
                    continue;
                }
            }
            $rest[] = $tok;
        }

        return [$facts, $json, implode(' · ', $rest)];
    }

    /** @return array{label:string, value:string, note?:string} */
    private static function httpFact(int $code): array
    {
        $meaning = self::HTTP_MEANING[$code] ?? null;

        return ['label' => 'Code HTTP', 'value' => (string) $code] + ($meaning !== null ? ['note' => $meaning] : []);
    }

    /**
     * Défait l'échappement SQF répété : toute suite de guillemets devient un seul guillemet,
     * puis les guillemets qui enveloppent toute la ligne sont retirés.
     */
    private static function unquote(string $s): string
    {
        $s = (string) preg_replace('/"{2,}/', '"', $s);
        while (strlen($s) >= 2 && $s[0] === '"' && substr($s, -1) === '"') {
            $s = trim(substr($s, 1, -1));
        }

        return $s;
    }

    private static function matchingBrace(string $s, int $start): ?int
    {
        $depth = 0;
        $inString = false;
        $len = strlen($s);
        for ($i = $start; $i < $len; $i++) {
            $c = $s[$i];
            if ($inString) {
                if ($c === '\\') {
                    $i++;
                } elseif ($c === '"') {
                    $inString = false;
                }
                continue;
            }
            if ($c === '"') {
                $inString = true;
            } elseif ($c === '{') {
                $depth++;
            } elseif ($c === '}' && --$depth === 0) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Octets non UTF-8 → lus comme Windows-1252 ; « Ã© » (UTF-8 relu en Latin-1) → « é » ;
     * « � » dans les mots courants → accent reconstruit.
     *
     * @return array{0:string, 1:bool}
     */
    private static function repairEncoding(string $s): array
    {
        $orig = $s;
        if (!mb_check_encoding($s, 'UTF-8')) {
            $s = (string) mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
        }
        if (preg_match('/Ã[\x{0080}-\x{00BF}]/u', $s) === 1) {
            $back = mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
            if (is_string($back) && mb_check_encoding($back, 'UTF-8')) {
                $s = $back;
            }
        }
        if (str_contains($s, "\u{FFFD}")) {
            $s = strtr($s, self::ACCENT_WORDS);
        }

        return [$s, $s !== $orig];
    }

    /**
     * @param list<array{label:string, value:string, note?:string}> $facts
     * @return list<array{label:string, value:string, note?:string}>
     */
    private static function uniqueFacts(array $facts): array
    {
        $seen = [];
        $out = [];
        foreach ($facts as $f) {
            $k = $f['label'] . "\0" . $f['value'];
            if (!isset($seen[$k])) {
                $seen[$k] = true;
                $out[] = $f;
            }
        }
        $rank = static function (array $f): int {
            $i = array_search($f['label'], self::FACT_ORDER, true);

            return $i === false ? count(self::FACT_ORDER) : $i;
        };
        // Tri stable (PHP 8) : à rang égal, l'ordre d'arrivée est gardé.
        usort($out, static fn (array $a, array $b): int => $rank($a) <=> $rank($b));

        return $out;
    }
}
