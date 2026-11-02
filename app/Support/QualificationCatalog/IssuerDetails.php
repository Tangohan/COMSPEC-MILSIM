<?php

declare(strict_types=1);

namespace App\Support\QualificationCatalog;

/**
 * Détails d’un émetteur écrits en lignes dans sa présentation (« Pays : … », « Localisation : … », « Site officiel :
 * … », « English: … ») avant l’étape 3680, qui leur a donné des colonnes (country_code, location, website_url,
 * description_en). split() sert à l’étape pour les extraire et, en lecture de repli, pour les lignes restantes.
 *
 * @deprecated Les détails s’enregistrent dans les colonnes de qualification_issuers ; compose() ne sert plus que si
 *             l’étape 3680 n’est pas appliquée, split() que pour relire d’anciennes présentations.
 */
final class IssuerDetails
{
    /**
     * Présentation enregistrée : texte français, puis une ligne par détail renseigné.
     *
     * @param array{description?: mixed, description_en?: mixed, country?: mixed, location?: mixed, website?: mixed} $in
     */
    public static function compose(array $in): string
    {
        $lines = [];
        $fr = trim((string) ($in['description'] ?? ''));
        $en = trim((string) ($in['description_en'] ?? ''));
        $country = strtoupper(trim((string) ($in['country'] ?? '')));
        $location = trim((string) ($in['location'] ?? ''));
        $website = trim((string) ($in['website'] ?? ''));
        $details = [];
        if (isset(QualificationCatalog::COUNTRIES[$country])) {
            $details[] = 'Pays : ' . QualificationCatalog::COUNTRIES[$country]['fr'];
        }
        if ($location !== '') {
            $details[] = 'Localisation : ' . mb_substr($location, 0, 200);
        }
        if ($website !== '' && self::validWebsite($website)) {
            $details[] = 'Site officiel : ' . $website;
        }
        if ($fr !== '') {
            $lines[] = $fr;
        }
        if ($details !== []) {
            if ($lines !== []) {
                $lines[] = '';
            }
            array_push($lines, ...$details);
        }
        if ($en !== '') {
            $lines[] = '';
            $lines[] = 'English: ' . $en;
        }

        return mb_substr(trim(implode("\n", $lines)), 0, 2000);
    }

    public static function validWebsite(string $url): bool
    {
        return preg_match('~^https?://[a-z0-9.-]+\.[a-z]{2,}(?::\d{2,5})?(/\S*)?$~i', trim($url)) === 1;
    }

    /** Pays relu dans une présentation (« Pays : France » ou « Country: France »), code du catalogue ou vide. */
    public static function country(string $description): string
    {
        if ($description === '' || preg_match('/^(?:Pays|Country)\s*:\s*(.+)$/miu', $description, $m) !== 1) {
            return '';
        }
        $label = mb_strtolower(trim($m[1]));
        foreach (QualificationCatalog::COUNTRIES as $code => $labels) {
            if ($label === mb_strtolower($labels['fr']) || $label === mb_strtolower($labels['en']) || $label === mb_strtolower($code)) {
                return $code;
            }
        }

        return '';
    }

    /**
     * Sépare une présentation en texte libre et détails reconnus. Seules les lignes structurées reconnues sont
     * retirées (pays connu, localisation, site valide, bloc « English: » jusqu’à la fin) ; le reste est conservé.
     *
     * @return array{description: string, country_code: string, location: string, website_url: string, description_en: string}
     */
    public static function split(string $description): array
    {
        $out = ['description' => '', 'country_code' => '', 'location' => '', 'website_url' => '', 'description_en' => ''];
        $keep = [];
        $lines = preg_split('/\R/u', $description) ?: [];
        foreach ($lines as $n => $line) {
            $t = trim($line);
            if (preg_match('/^English\s*:\s*(.*)$/u', $t, $m) === 1) {
                $out['description_en'] = trim($m[1] . "\n" . implode("\n", array_slice($lines, $n + 1)));
                break;
            }
            if ($out['country_code'] === '' && preg_match('/^(?:Pays|Country)\s*:\s*(.+)$/iu', $t) === 1 && ($c = self::country($t)) !== '') {
                $out['country_code'] = $c;
                continue;
            }
            if ($out['location'] === '' && preg_match('/^(?:Localisation|Location)\s*:\s*(.+)$/iu', $t, $m) === 1) {
                $out['location'] = mb_substr(trim($m[1]), 0, 200);
                continue;
            }
            if ($out['website_url'] === '' && preg_match('/^(?:Site officiel|Official website)\s*:\s*(\S+)$/iu', $t, $m) === 1 && self::validWebsite($m[1])) {
                $out['website_url'] = $m[1];
                continue;
            }
            $keep[] = rtrim($line);
        }
        $out['description'] = trim((string) preg_replace("/\n{3,}/", "\n\n", implode("\n", $keep)));

        return $out;
    }
}
