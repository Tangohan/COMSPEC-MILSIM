<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Libellés et filtres de la page d’administration « Retours sur l’interface ».
 */
final class UxFeedbackAdminPresentation
{
    public const TYPE_ALL = '';
    public const TYPE_RATINGS = 'avis';
    public const TYPE_SURVEYS = 'questionnaires';

    public const SAT_ALL = '';
    public const SAT_WEAK = 'a-ameliorer';
    public const SAT_OK = 'correct';
    public const SAT_GOOD = 'satisfaisant';

    /** @return array<string, string> */
    public static function issueLabels(): array
    {
        return [
            'navigation' => 'Navigation confuse',
            'labels' => 'Libellés peu clairs',
            'performance' => 'Lenteur / chargement',
            'mobile' => 'Affichage mobile',
            'accessibility' => 'Accessibilité',
            'missing_info' => 'Information manquante',
            'workflow' => 'Parcours trop long',
            'visual_noise' => 'Interface chargée',
        ];
    }

    public static function issueLabel(string $slug): string
    {
        $labels = self::issueLabels();

        return $labels[$slug] ?? $slug;
    }

    /**
     * @return list<string>
     */
    public static function decodeIssues(mixed $raw): array
    {
        $decoded = [];
        if (is_string($raw) && $raw !== '') {
            $parsed = json_decode($raw, true);
            if (is_array($parsed)) {
                $decoded = $parsed;
            }
        } elseif (is_array($raw)) {
            $decoded = $raw;
        }

        $out = [];
        foreach ($decoded as $slug) {
            $slug = trim((string) $slug);
            if ($slug === '') {
                continue;
            }
            $out[] = self::issueLabel($slug);
        }

        return $out;
    }

    public static function normalizeType(string $raw): string
    {
        $raw = trim($raw);

        return in_array($raw, [self::TYPE_RATINGS, self::TYPE_SURVEYS], true) ? $raw : self::TYPE_ALL;
    }

    public static function normalizeSatisfaction(string $raw): string
    {
        $raw = trim($raw);

        return in_array($raw, [self::SAT_WEAK, self::SAT_OK, self::SAT_GOOD], true) ? $raw : self::SAT_ALL;
    }

    public static function normalizeScreen(string $raw): string
    {
        $raw = trim($raw);

        return mb_substr($raw, 0, 255);
    }

    /**
     * @return array{key: string, label: string, pill: string}
     */
    public static function satisfactionFromScore(float $score): array
    {
        if ($score <= 2.49) {
            return ['key' => self::SAT_WEAK, 'label' => 'À améliorer', 'pill' => 'rose'];
        }
        if ($score < 4.0) {
            return ['key' => self::SAT_OK, 'label' => 'Correct', 'pill' => 'amber'];
        }

        return ['key' => self::SAT_GOOD, 'label' => 'Satisfaisant', 'pill' => 'mint'];
    }

    public static function matchesSatisfaction(float $score, string $filter): bool
    {
        $filter = self::normalizeSatisfaction($filter);
        if ($filter === self::SAT_ALL) {
            return true;
        }

        return self::satisfactionFromScore($score)['key'] === $filter;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function surveyScore(array $row): float
    {
        $sum = (int) ($row['ease_rating'] ?? 0)
            + (int) ($row['clarity_rating'] ?? 0)
            + (int) ($row['design_rating'] ?? 0)
            + (int) ($row['usefulness_rating'] ?? 0);

        return round($sum / 4, 2);
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function rowMatchesScreen(array $row, string $screen): bool
    {
        $screen = self::normalizeScreen($screen);
        if ($screen === '') {
            return true;
        }

        return (string) ($row['page_key'] ?? '') === $screen;
    }

    public static function formatDateTime(?string $sql): string
    {
        if ($sql === null || trim($sql) === '') {
            return '—';
        }
        return ParisDateTime::format($sql, 'd/m/Y H:i');
    }

    public static function scoreLabel(float $score, int $decimals = 1): string
    {
        if ($decimals === 0) {
            return (string) (int) round($score) . ' / 5';
        }

        return number_format($score, $decimals, ',', ' ') . ' / 5';
    }

    public static function screenLocation(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        if (str_contains($path, '://')) {
            $parsed = parse_url($path, PHP_URL_PATH);
            if (is_string($parsed) && $parsed !== '') {
                $path = $parsed;
            }
        } else {
            $cut = strpos($path, '?');
            if ($cut !== false) {
                $path = substr($path, 0, $cut);
            }
        }
        $path = trim(str_replace('\\', '/', $path), '/');
        $bits = [];
        foreach (explode('/', $path) as $bit) {
            $bit = trim($bit);
            if ($bit === '' || in_array(strtolower($bit), ['public', 'index.php'], true)) {
                continue;
            }
            $bits[] = str_replace(['-', '_'], ' ', $bit);
        }

        return implode(' · ', $bits);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array{key: string, title: string}>
     */
    public static function screenOptions(array $rows): array
    {
        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            $key = trim((string) ($row['page_key'] ?? ''));
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $title = trim((string) ($row['page_title'] ?? ''));
            $out[] = [
                'key' => $key,
                'title' => $title !== '' ? $title : $key,
            ];
        }

        usort($out, static fn (array $a, array $b): int => strcasecmp($a['title'], $b['title']));

        return $out;
    }
}
