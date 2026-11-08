<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Export CSV sans piège pour les tableurs : une cellule qui commence par = + - @ (ou une tabulation, un retour
 * chariot) serait lue comme une formule à l’ouverture. On la précède d’une apostrophe, le texte reste lisible.
 */
final class CsvSafe
{
    public static function cell(string|int|float|null $value): string
    {
        $text = (string) ($value ?? '');
        if ($text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $text;
        }

        return $text;
    }

    /**
     * @param list<string|int|float|null> $cells
     * @return list<string>
     */
    public static function row(array $cells): array
    {
        return array_map([self::class, 'cell'], array_values($cells));
    }

    /**
     * Fichier CSV complet (séparateur « ; », BOM UTF-8 pour Excel), toutes cellules neutralisées.
     *
     * @param list<string> $header
     * @param iterable<list<string|int|float|null>> $rows
     */
    public static function build(array $header, iterable $rows): string
    {
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, self::row($header), ';', '"', '\\');
        foreach ($rows as $row) {
            fputcsv($fh, self::row($row), ';', '"', '\\');
        }
        rewind($fh);
        $csv = "\xEF\xBB\xBF" . (stream_get_contents($fh) ?: '');
        fclose($fh);

        return $csv;
    }
}
