<?php

declare(strict_types=1);

namespace App\Support\QualificationCatalog;

/**
 * Détails d’un émetteur créé par la communauté (assistant « Créer un émetteur ») : pays, localisation, site officiel
 * et présentation anglaise n’ont pas de colonne ; ils sont ajoutés à la présentation, sous la forme des émetteurs
 * réels importés (« Localisation : … », « Site officiel : … »), et le pays y est relu pour la liste.
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
}
