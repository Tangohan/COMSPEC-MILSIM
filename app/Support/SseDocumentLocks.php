<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Texte verrouillé des documents SSE : blocs portant data-lock="1" (en-têtes et mentions obligatoires des modèles).
 *
 * Le client les rend non modifiables (contenteditable=false), mais seul le serveur fait foi : à chaque enregistrement, les blocs
 * verrouillés de la version précédente (ou du modèle choisi, pour un nouveau document) doivent se retrouver à l'identique, dans
 * le même ordre, dans le corps reçu. Sinon l'enregistrement est refusé. Seul un auteur habilité à déverrouiller peut les modifier.
 * La comparaison porte sur le HTML déjà assaini par SseRichText ; le texte des champs dynamiques (date, référence…) est ignoré,
 * car il est recalculé à l'affichage.
 */
final class SseDocumentLocks
{
    /**
     * Blocs verrouillés les plus extérieurs d'un HTML assaini, sous forme canonique.
     *
     * @return list<string>
     */
    public static function blocks(string $html): array
    {
        $html = SseRichText::sanitize($html);
        if ($html === '' || !str_contains($html, 'data-lock="1"') || !class_exists(\DOMDocument::class)) {
            return [];
        }
        $prev = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML('<?xml encoding="UTF-8"><html><body><div id="r">' . $html . '</div></body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $dom->getElementById('r');
        if (!$root instanceof \DOMElement) {
            return [];
        }
        $xp = new \DOMXPath($dom);
        // Valeurs des champs dynamiques : ignorées.
        foreach ($xp->query('.//span[contains(@class,"sse-field")]', $root) ?: [] as $f) {
            while ($f->firstChild) {
                $f->removeChild($f->firstChild);
            }
        }
        $out = [];
        $walk = static function (\DOMNode $n) use (&$walk, &$out, $dom): void {
            foreach (iterator_to_array($n->childNodes) as $c) {
                if (!$c instanceof \DOMElement) {
                    continue;
                }
                if ($c->getAttribute('data-lock') === '1') {
                    $out[] = trim((string) preg_replace('/\s+/', ' ', (string) $dom->saveHTML($c)));
                    continue;
                }
                $walk($c);
            }
        };
        $walk($root);

        return $out;
    }

    /**
     * Les blocs verrouillés de référence se retrouvent-ils, intacts et dans l'ordre, dans le nouveau corps ?
     *
     * @return list<int> positions (à partir de 1) des blocs de référence absents ou modifiés ; vide si tout est respecté
     */
    public static function violations(string $referenceHtml, string $newHtml): array
    {
        $ref = self::blocks($referenceHtml);
        if ($ref === []) {
            return [];
        }
        $new = self::blocks($newHtml);
        $bad = [];
        $cursor = 0;
        foreach ($ref as $i => $block) {
            $found = false;
            for ($j = $cursor, $n = count($new); $j < $n; $j++) {
                if ($new[$j] === $block) {
                    $cursor = $j + 1;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $bad[] = $i + 1;
            }
        }

        return $bad;
    }
}
