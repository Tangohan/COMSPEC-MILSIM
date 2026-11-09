<?php

declare(strict_types=1);

namespace App\Services\Atak;

use App\Services\Community\YearReviewAccess;
use App\Services\Community\YearReviewService;
use App\Support\YearReviewCalendar;
use App\Support\YearReviewStory;

/**
 * Rétrospective annuelle pour l'app « Bilan de l'année » du téléphone ATAK : mêmes écrans que la page du site
 * (/retrospective, YearReviewStory), mêmes droits (calendrier publié, bilan de l'unité réservé à la formule qui le
 * comprend), réduits à ce que le téléphone dessine. Texte en français comme sur le site (le site le traduit à l'affichage).
 *
 * Lecture seule, jamais d'écriture : « déjà vue » reste une action du site.
 */
final class AtakYearReviewService
{
    public const MAX_SLIDES = 24;
    public const MAX_STATS = 8;
    public const MAX_ROWS = 6;

    public function __construct(private ?YearReviewService $service = null)
    {
        $this->service ??= new YearReviewService();
    }

    /**
     * @return array{state: string, year: int, tenant: string, partial: bool, slides: list<array<string, mixed>>}
     *         state : OK | NOT_PUBLISHED (rien de publié pour l'instant)
     */
    public function forMember(int $tenantId, int $userId, string $tenantName = '', ?int $now = null, ?int $year = null): array
    {
        $now ??= time();
        $calendar = YearReviewAccess::calendar();
        $year ??= $calendar->currentYear($now);
        if ($year === null || $year < YearReviewCalendar::FIRST_YEAR || !$calendar->isPublished($year, $now)) {
            return ['state' => 'NOT_PUBLISHED', 'year' => 0, 'tenant' => $tenantName, 'partial' => false, 'slides' => []];
        }
        $review = $this->service->forTenant($tenantId, $year, $now);
        $player = $review['players']['u' . $userId] ?? null;
        if (is_array($player) && empty($player['active'])) {
            $player = null;
        }
        $slides = YearReviewStory::slides($year, $player, $review['community'] ?? null, [
            'tenant_name' => $tenantName,
            'partial' => (bool) ($review['partial'] ?? false),
            'until' => (string) ($review['until'] ?? ''),
            'collective' => YearReviewAccess::collectiveAllowed($tenantId),
            'upsell' => false,
            'community_only' => false,
        ]);

        return self::compact($year, $tenantName, (bool) ($review['partial'] ?? false), $slides);
    }

    /**
     * @param list<array<string, mixed>> $slides écrans de YearReviewStory
     * @return array{state: string, year: int, tenant: string, partial: bool, slides: list<array<string, mixed>>}
     */
    public static function compact(int $year, string $tenant, bool $partial, array $slides): array
    {
        $out = [];
        foreach ($slides as $s) {
            if (!is_array($s) || !empty($s['locked']) || count($out) >= self::MAX_SLIDES) {
                continue;
            }
            $stats = [];
            foreach (array_slice((array) ($s['stats'] ?? []), 0, self::MAX_STATS) as $row) {
                if (is_array($row) && count($row) >= 2) {
                    $stats[] = [self::text($row[0]), self::text($row[1])];
                }
            }
            $bars = [];
            foreach (array_slice((array) ($s['bars'] ?? []), 0, 12) as $b) {
                if (is_array($b)) {
                    $bars[] = [self::text($b['label'] ?? '', 2), max(0, min(100, (int) ($b['pct'] ?? 0)))];
                }
            }
            $boards = [];
            foreach (array_slice((array) ($s['boards'] ?? []), 0, 4) as $b) {
                if (!is_array($b)) {
                    continue;
                }
                $rows = [];
                foreach (array_slice((array) ($b['rows'] ?? []), 0, self::MAX_ROWS) as $r) {
                    if (is_array($r)) {
                        $rows[] = [(int) ($r['rank'] ?? 0), self::text($r['name'] ?? '', 40), self::text($r['value'] ?? '', 24)];
                    }
                }
                if ($rows !== []) {
                    $boards[] = [self::text($b['label'] ?? '', 60), $rows];
                }
            }
            $out[] = [
                'key' => self::text($s['key'] ?? '', 24),
                'tone' => self::text($s['tone'] ?? '', 12),
                'kicker' => self::text($s['kicker'] ?? ''),
                'name' => self::text($s['name'] ?? '', 60),
                'title' => self::text($s['title'] ?? ''),
                'lead' => self::text($s['lead'] ?? '', 300),
                'big' => self::text($s['big'] ?? '', 24),
                'big_label' => self::text($s['big_label'] ?? ''),
                'persona' => self::text($s['persona'] ?? '', 60),
                'stats' => $stats,
                'bars' => $bars,
                'boards' => $boards,
            ];
        }

        return ['state' => 'OK', 'year' => $year, 'tenant' => self::text($tenant, 60), 'partial' => $partial, 'slides' => $out];
    }

    private static function text(mixed $v, int $max = 120): string
    {
        $v = is_scalar($v) ? (string) $v : '';
        $v = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $v));

        return mb_substr($v, 0, $max);
    }
}
