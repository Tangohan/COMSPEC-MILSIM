<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\SseFictionMentions;

/**
 * Texte d'un PDF produit par TCPDF, pour les tests : propriétés du fichier (titre, sujet, mots-clés) et texte de chaque
 * page (flux décompressés, chaînes UTF-16BE des polices Unicode). Sert à vérifier qu'un document ne porte PAS une mention :
 * une recherche dans les octets bruts ne suffit pas, le contenu des pages étant compressé.
 *
 * Aucune dépendance aux fins de ligne : le PDF est lu comme une suite d'octets.
 */
final class PdfText
{
    /** Tout le texte lisible du PDF, chaînes séparées par un espace. */
    public static function of(string $pdf): string
    {
        $out = [];
        // Propriétés et objets hors flux.
        $outside = (string) preg_replace('/stream\r?\n.*?endstream/s', ' ', $pdf);
        foreach (self::literals($outside) as $raw) {
            $out[] = self::decode($raw);
        }
        // Contenu des pages (flux compressés ou non).
        if (preg_match_all('/stream\n(.*?)\nendstream/s', $pdf, $m) > 0) {
            foreach ($m[1] as $stream) {
                $plain = @gzuncompress($stream);
                // Un flux dont le dernier octet compressé est un retour chariot le perd dans l'expression ci-dessus : on le rend.
                $plain = $plain !== false ? $plain : @gzuncompress($stream . chr(13));
                $content = $plain !== false ? $plain : $stream;
                // Seuls les flux de dessin (opérateurs de texte) sont lus : ni polices, ni images.
                if (!str_contains($content, 'BT') || preg_match('/\b(?:Tj|TJ)\b/', $content) !== 1) {
                    continue;
                }
                foreach (self::literals($content) as $raw) {
                    $out[] = self::decode($raw);
                }
            }
        }

        return implode(' ', array_filter($out, static fn (string $s): bool => $s !== ''));
    }

    /**
     * Première mention de jeu trouvée dans le PDF (SseFictionMentions::FORBIDDEN), ou null. Le texte est aussi comparé sans
     * aucun espace, pour qu'une mention découpée mot à mot par la mise en page soit vue.
     */
    public static function fictionMention(string $pdf): ?string
    {
        $text = self::of($pdf);
        $hit = SseFictionMentions::find($text);
        if ($hit !== null) {
            return $hit;
        }
        $dense = (string) preg_replace('/\s+/u', '', $text);
        foreach (['documentdefiction', 'fictionaldocument', 'jeuderôle', 'jeuderole', 'role-play', 'roleplay', 'usagerp', 'rpuniquement', 'preuvejudiciaire', 'fiction-rp'] as $needle) {
            if (mb_stripos($dense, $needle) !== false) {
                return $needle;
            }
        }

        return null;
    }

    /**
     * Chaînes littérales « (…) » d'un morceau de PDF, parenthèses imbriquées et échappements résolus.
     *
     * @return list<string>
     */
    private static function literals(string $data): array
    {
        $out = [];
        $n = strlen($data);
        $i = 0;
        while ($i < $n) {
            if ($data[$i] !== '(') {
                ++$i;

                continue;
            }
            $depth = 1;
            $buf = '';
            ++$i;
            while ($i < $n && $depth > 0) {
                $c = $data[$i];
                if ($c === '\\' && $i + 1 < $n) {
                    $next = $data[$i + 1];
                    $map = ['n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\x0c", '(' => '(', ')' => ')', '\\' => '\\'];
                    if (isset($map[$next])) {
                        $buf .= $map[$next];
                        $i += 2;
                    } elseif (preg_match('/\G[0-7]{1,3}/', $data, $oct, 0, $i + 1) === 1) {
                        $buf .= chr((int) octdec($oct[0]) & 0xFF);
                        $i += 1 + strlen($oct[0]);
                    } else {
                        $i += 2;
                    }

                    continue;
                }
                if ($c === '(') {
                    ++$depth;
                } elseif ($c === ')') {
                    --$depth;
                    if ($depth === 0) {
                        ++$i;

                        break;
                    }
                }
                $buf .= $c;
                ++$i;
            }
            if ($buf !== '') {
                $out[] = $buf;
            }
        }

        return $out;
    }

    /** Chaîne PDF en UTF-8 : UTF-16BE (avec ou sans marque d'ordre) pour les polices Unicode, sinon Latin-1. */
    private static function decode(string $raw): string
    {
        if (str_starts_with($raw, "\xFE\xFF")) {
            return (string) mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16BE');
        }
        if (strlen($raw) % 2 === 0 && str_contains($raw, "\x00")) {
            return (string) mb_convert_encoding($raw, 'UTF-8', 'UTF-16BE');
        }

        return (string) mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1');
    }
}
