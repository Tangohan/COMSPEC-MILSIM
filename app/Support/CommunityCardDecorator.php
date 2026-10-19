<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Habillage des cartes de l’annuaire public des communautés : bannière, couleur de marque, logo
 * et chiffres publics (membres, activité, unités ORBAT, opérations). Calcul pur : le contrôleur
 * fournit les lignes du registre, la marque (TenantBrandingRepository) et les indicateurs
 * (CommunityStatsService) ; chaque source est facultative et la carte reste lisible sans elle.
 *
 * Clés ajoutées : card_banner, card_accent, card_logo, card_members, card_activity, card_units,
 * card_ops, card_upcoming, card_rating, card_rating_count, card_age_days.
 */
final class CommunityCardDecorator
{
    /**
     * @param list<array<string, mixed>> $rows lignes du registre (TenantRepository::listForRegistry)
     * @param array<int, array<string, string>> $branding par tenant_id : banner_url, logo_url, primary_color, accent_color
     * @param array<string, array<string, mixed>> $stats par slug (CommunityStatsService::all)
     * @param callable(string): ?string|null $coverFile slug → URL de la couverture déposée, sinon null
     * @return list<array<string, mixed>>
     */
    public static function decorate(array $rows, array $branding, array $stats, ?callable $coverFile = null): array
    {
        foreach ($rows as $i => $row) {
            $id = (int) ($row['id'] ?? 0);
            $slug = trim((string) ($row['slug'] ?? ''));
            $brand = $branding[$id] ?? [];
            $s = $slug !== '' ? ($stats[$slug] ?? []) : [];

            $banner = self::banner((string) ($brand['banner_url'] ?? ''));
            if ($banner === '' && $coverFile !== null && $slug !== '') {
                $banner = (string) ($coverFile($slug) ?? '');
            }
            $logo = self::url((string) ($brand['logo_url'] ?? ''));
            if ($logo === '') {
                $logo = self::url((string) ($row['logo_url'] ?? ''));
            }

            $rows[$i]['card_banner'] = $banner;
            $rows[$i]['card_accent'] = self::color((string) ($brand['accent_color'] ?? '')) ?? self::color((string) ($brand['primary_color'] ?? ''));
            $rows[$i]['card_logo'] = $logo;
            $rows[$i]['card_members'] = max(0, (int) ($s['members'] ?? 0));
            $rows[$i]['card_activity'] = isset($s['activity']) && $s['activity'] !== null ? max(0, min(100, (int) round((float) $s['activity']))) : null;
            $rows[$i]['card_units'] = max(0, (int) ($s['units'] ?? 0));
            $rows[$i]['card_ops'] = max(0.0, round((float) ($s['ops_per_month'] ?? 0), 1));
            $rows[$i]['card_upcoming'] = max(0, (int) ($s['upcoming_ops'] ?? 0));
            $rows[$i]['card_rating'] = isset($s['rating']) && $s['rating'] !== null ? round((float) $s['rating'], 1) : null;
            $rows[$i]['card_rating_count'] = max(0, (int) ($s['rating_count'] ?? 0));
            $rows[$i]['card_age_days'] = isset($s['age_days']) && $s['age_days'] !== null ? (int) $s['age_days'] : null;
        }

        return $rows;
    }

    /** Bannière utilisable : URL absolue http(s) ou chemin du site, sinon chaîne vide. */
    public static function banner(string $url): string
    {
        return self::url($url);
    }

    /** Couleur de marque utilisable en CSS (#rgb ou #rrggbb), sinon null. */
    public static function color(string $color): ?string
    {
        $color = trim($color);

        return preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $color) === 1 ? strtolower($color) : null;
    }

    /**
     * Ancienneté lisible (« 3 mois », « 1 an »), ou chaîne vide si inconnue.
     */
    public static function age(?int $days): string
    {
        if ($days === null || $days < 0) {
            return '';
        }
        if ($days < 30) {
            return $days <= 1 ? '1 jour' : $days . ' jours';
        }
        if ($days < 365) {
            $m = intdiv($days, 30);

            return $m . ' mois';
        }
        $y = intdiv($days, 365);

        return $y === 1 ? '1 an' : $y . ' ans';
    }

    /** Nombre compact pour les chiffres de carte (1 234 → « 1,2 k »). */
    public static function compact(float|int $n): string
    {
        if ($n >= 1000) {
            return str_replace('.', ',', (string) round($n / 1000, 1)) . ' k';
        }
        if (is_float($n) && floor($n) !== $n) {
            return str_replace('.', ',', (string) round($n, 1));
        }

        return (string) (int) $n;
    }

    private static function url(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $url) === 1 || (str_starts_with($url, '/') && !str_starts_with($url, '//'))) {
            return $url;
        }

        return '';
    }
}
