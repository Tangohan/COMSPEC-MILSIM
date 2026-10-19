<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Repositories\PlatformSettingsRepository;
use App\Repositories\RegistryTrendingRepository;

/**
 * Annuaire public des communautés (/communities) : épinglage par l'administration du site et unités en tendance.
 *
 * - Épingles : réglage plateforme « registry.pins » (JSON), géré dans /admin/system/annuaire, jamais côté communauté.
 *   Une épingle n'a d'effet que sur une communauté déjà listée (registry_listed) : elle ne rend rien visible.
 * - Tendance : fenêtre des 30 derniers jours, score = visiteurs + 6 × recrues + membres connectés
 *   + hausse des visiteurs par rapport aux 30 jours précédents. Mis en cache 15 minutes.
 */
final class RegistryDirectory
{
    public const PINS_KEY = 'registry.pins';

    public const TREND_DAYS = 30;

    public const TREND_LIMIT = 4;

    /** Score minimal pour apparaître en tendance (évite d'afficher une communauté sans activité réelle). */
    public const TREND_MIN_SCORE = 8;

    public const RECRUIT_WEIGHT = 6;

    public const MAX_POSITION = 20;

    private const CACHE_TTL = 900;

    public function __construct(
        private ?PlatformSettingsRepository $settings = null,
        private ?RegistryTrendingRepository $trending = null,
        private ?string $cacheFile = null,
    ) {}

    private function settings(): PlatformSettingsRepository
    {
        return $this->settings ??= new PlatformSettingsRepository();
    }

    // ---------------------------------------------------------------- Épingles

    /**
     * Épingle propre ou null si l'entrée est invalide.
     *
     * @param array<string, mixed> $raw
     * @return array{tenant_id: int, position: int, until: ?string, note: string, by: int, at: string}|null
     */
    public static function normalizePin(array $raw): ?array
    {
        $tid = (int) ($raw['tenant_id'] ?? 0);
        if ($tid < 2) {
            return null;
        }
        $until = trim((string) ($raw['until'] ?? ''));
        if ($until !== '') {
            $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $until);
            $until = $d !== false && $d->format('Y-m-d') === $until ? $until : '';
        }
        $note = trim(preg_replace('/\s+/u', ' ', (string) ($raw['note'] ?? '')) ?? '');

        return [
            'tenant_id' => $tid,
            'position' => max(1, min(self::MAX_POSITION, (int) ($raw['position'] ?? 1))),
            'until' => $until !== '' ? $until : null,
            'note' => mb_substr($note, 0, 140),
            'by' => max(0, (int) ($raw['by'] ?? 0)),
            'at' => (string) ($raw['at'] ?? ''),
        ];
    }

    /** Épingle encore valable à cette date (la date de fin est incluse). */
    public static function isPinActive(array $pin, ?\DateTimeImmutable $now = null): bool
    {
        $until = $pin['until'] ?? null;
        if ($until === null || $until === '') {
            return true;
        }
        $today = ($now ?? new \DateTimeImmutable('now'))->format('Y-m-d');

        return strcmp((string) $until, $today) >= 0;
    }

    /** @return list<array{tenant_id: int, position: int, until: ?string, note: string, by: int, at: string}> */
    public static function decodePins(string $json): array
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return [];
        }
        $out = [];
        foreach ($data as $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $pin = self::normalizePin($raw);
            if ($pin !== null) {
                $out[$pin['tenant_id']] = $pin;
            }
        }
        $out = array_values($out);
        usort($out, static fn (array $a, array $b): int => [$a['position'], $a['tenant_id']] <=> [$b['position'], $b['tenant_id']]);

        return $out;
    }

    /** @return list<array{tenant_id: int, position: int, until: ?string, note: string, by: int, at: string}> */
    public function pins(): array
    {
        return self::decodePins($this->settings()->get(self::PINS_KEY, '[]'));
    }

    /** @return array<int, array{tenant_id: int, position: int, until: ?string, note: string, by: int, at: string}> */
    public function activePinsByTenant(?\DateTimeImmutable $now = null): array
    {
        $out = [];
        foreach ($this->pins() as $pin) {
            if (self::isPinActive($pin, $now)) {
                $out[$pin['tenant_id']] = $pin;
            }
        }

        return $out;
    }

    /** Ajoute ou met à jour l'épingle d'une communauté. */
    public function pin(int $tenantId, int $position, ?string $until, string $note, int $actorId): bool
    {
        $pin = self::normalizePin([
            'tenant_id' => $tenantId,
            'position' => $position,
            'until' => $until,
            'note' => $note,
            'by' => $actorId,
            'at' => (new \DateTimeImmutable('now'))->format('Y-m-d H:i'),
        ]);
        if ($pin === null) {
            return false;
        }
        $pins = array_values(array_filter($this->pins(), static fn (array $p): bool => $p['tenant_id'] !== $tenantId));
        $pins[] = $pin;
        $this->savePins($pins);

        return true;
    }

    public function unpin(int $tenantId): bool
    {
        $pins = $this->pins();
        $kept = array_values(array_filter($pins, static fn (array $p): bool => $p['tenant_id'] !== $tenantId));
        if (count($kept) === count($pins)) {
            return false;
        }
        $this->savePins($kept);

        return true;
    }

    /** @param list<array<string, mixed>> $pins */
    private function savePins(array $pins): void
    {
        $json = json_encode(self::decodePins((string) json_encode($pins)), JSON_UNESCAPED_UNICODE);
        $this->settings()->setMany([self::PINS_KEY => $json !== false ? $json : '[]']);
    }

    // ---------------------------------------------------------------- Tendance

    /** @param array{visitors?: int, visitors_prev?: int, recruits?: int, active?: int} $s */
    public static function trendScore(array $s): int
    {
        $visitors = max(0, (int) ($s['visitors'] ?? 0));
        $prev = max(0, (int) ($s['visitors_prev'] ?? 0));

        return $visitors
            + self::RECRUIT_WEIGHT * max(0, (int) ($s['recruits'] ?? 0))
            + max(0, (int) ($s['active'] ?? 0))
            + max(0, $visitors - $prev);
    }

    /**
     * Classement des communautés en tendance (au plus TREND_LIMIT, score >= TREND_MIN_SCORE).
     *
     * @param array<int, array{visitors: int, visitors_prev: int, recruits: int, active: int}> $signals
     * @return array<int, int> tenant_id => rang (1 = première)
     */
    public static function rankTrending(array $signals, int $limit = self::TREND_LIMIT, int $minScore = self::TREND_MIN_SCORE): array
    {
        $scored = [];
        foreach ($signals as $tid => $s) {
            $score = self::trendScore($s);
            if ($score >= $minScore) {
                $scored[] = [(int) $tid, $score, (int) ($s['recruits'] ?? 0)];
            }
        }
        usort($scored, static fn (array $a, array $b): int => [$b[1], $b[2], $a[0]] <=> [$a[1], $a[2], $b[0]]);
        $out = [];
        foreach (array_slice($scored, 0, max(0, $limit)) as $i => $row) {
            $out[$row[0]] = $i + 1;
        }

        return $out;
    }

    /**
     * @param list<int> $tenantIds
     * @return array<int, array{visitors: int, visitors_prev: int, recruits: int, active: int}>
     */
    public function signals(array $tenantIds): array
    {
        sort($tenantIds);
        $key = md5(implode(',', $tenantIds));
        $file = $this->cacheFile ?? base_path('storage/cache/registry_trending.json');
        if (is_file($file) && filemtime($file) > time() - self::CACHE_TTL) {
            $cached = json_decode((string) @file_get_contents($file), true);
            if (is_array($cached) && ($cached['key'] ?? '') === $key && is_array($cached['signals'] ?? null)) {
                $out = [];
                foreach ($cached['signals'] as $tid => $s) {
                    $out[(int) $tid] = array_map('intval', (array) $s) + ['visitors' => 0, 'visitors_prev' => 0, 'recruits' => 0, 'active' => 0];
                }

                return $out;
            }
        }
        try {
            $signals = ($this->trending ??= new RegistryTrendingRepository())->signals($tenantIds, self::TREND_DAYS);
        } catch (\Throwable) {
            return [];
        }
        $dir = dirname($file);
        if (is_dir($dir) && is_writable($dir)) {
            @file_put_contents($file, (string) json_encode(['key' => $key, 'signals' => $signals]), LOCK_EX);
        }

        return $signals;
    }

    // ---------------------------------------------------------------- Annuaire

    /**
     * Ajoute épingle, tendance et pays aux cartes de l'annuaire, puis les trie :
     * épinglées (ordre choisi), puis mises en avant, puis ordre alphabétique.
     * Ne travaille que sur les lignes reçues (déjà filtrées par registry_listed) : rien n'est ajouté.
     *
     * @param list<array<string, mixed>> $rows
     * @param array<int, array<string, mixed>> $pins
     * @param array<int, array{visitors: int, visitors_prev: int, recruits: int, active: int}> $signals
     * @return list<array<string, mixed>>
     */
    public static function decorate(array $rows, array $pins, array $signals): array
    {
        $ranks = self::rankTrending($signals);
        foreach ($rows as &$row) {
            $tid = (int) ($row['id'] ?? 0);
            $pin = $pins[$tid] ?? null;
            $row['registry_pinned'] = $pin !== null;
            $row['registry_pin_position'] = $pin !== null ? (int) $pin['position'] : 0;
            $s = $signals[$tid] ?? ['visitors' => 0, 'visitors_prev' => 0, 'recruits' => 0, 'active' => 0];
            $row['registry_trend'] = $s + ['score' => self::trendScore($s)];
            $row['registry_trending_rank'] = $ranks[$tid] ?? 0;
        }
        unset($row);

        usort($rows, static function (array $a, array $b): int {
            $pa = !empty($a['registry_pinned']) ? 0 : 1;
            $pb = !empty($b['registry_pinned']) ? 0 : 1;
            if ($pa !== $pb) {
                return $pa <=> $pb;
            }
            if ($pa === 0 && $a['registry_pin_position'] !== $b['registry_pin_position']) {
                return $a['registry_pin_position'] <=> $b['registry_pin_position'];
            }
            $fa = !empty($a['registry_featured']) ? 0 : 1;
            $fb = !empty($b['registry_featured']) ? 0 : 1;
            if ($fa !== $fb) {
                return $fa <=> $fb;
            }

            return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        return $rows;
    }

    /**
     * Cartes de l'annuaire prêtes à afficher (épingles actives + tendance).
     *
     * @param list<array<string, mixed>> $rows lignes de TenantRepository::listForRegistry()
     * @return list<array<string, mixed>>
     */
    public function forRegistry(array $rows): array
    {
        $ids = array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $rows);

        return self::decorate($rows, $this->activePinsByTenant(), $this->signals($ids));
    }
}
