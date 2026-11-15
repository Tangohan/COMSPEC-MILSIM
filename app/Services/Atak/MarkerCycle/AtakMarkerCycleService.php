<?php

declare(strict_types=1);

namespace App\Services\Atak\MarkerCycle;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Cycle de 24 h des marqueurs des postes web : calcule la fenêtre du cycle en cours (bascule à 12:00 chaque jour par
 * défaut, dans le fuseau de la communauté), son identifiant, le temps restant, et normalise les réglages.
 *
 * Déterministe : toutes les méthodes prennent l'instant en paramètre (secondes Unix) ; sans lui, l'horloge injectée
 * (ou time()). Aucun accès base hors le dépôt reçu.
 *
 * Réglages (par communauté) : enabled (actif par défaut), switch_time (« HH:MM », 12:00), duration_days (1 = 24 h, jusqu'à
 * 7), timezone (celui de la communauté, sinon Europe/Paris), retention_days (conservation des missions archivées,
 * 1095 jours par défaut, 0 = indéfinie). Plusieurs jours : les cycles s'enchaînent depuis un lundi fixe (06/01/2020).
 *
 * @phpstan-type Settings array{enabled: bool, switch_time: string, duration_days: int, timezone: string, retention_days: int}
 * @phpstan-type Window array{key: string, start: int, end: int, start_utc: string, end_utc: string, start_local: string, end_local: string, label: string, timezone: string}
 */
final class AtakMarkerCycleService
{
    public const DEFAULT_TIME = '12:00';
    public const DEFAULT_DAYS = 1;
    public const MAX_DAYS = 7;
    public const DEFAULT_RETENTION_DAYS = 1095;
    public const FALLBACK_TIMEZONE = 'Europe/Paris';

    private const ANCHOR = '2020-01-06';

    /** @var callable(): int */
    private $clock;

    /**
     * @param callable(): int|null $clock instant courant (tests : date fixe)
     */
    public function __construct(private MarkerCycleStore $store, ?callable $clock = null)
    {
        $this->clock = $clock ?? static fn (): int => time();
    }

    public function now(): int
    {
        return ($this->clock)();
    }

    public function store(): MarkerCycleStore
    {
        return $this->store;
    }

    /**
     * @param array<string, mixed>|null $row
     * @return array{enabled: bool, switch_time: string, duration_days: int, timezone: string, retention_days: int}
     */
    public static function normalizeSettings(?array $row, ?string $tenantTimezone = null): array
    {
        $row ??= [];
        $time = trim((string) ($row['switch_time'] ?? ''));
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) !== 1) {
            $time = self::DEFAULT_TIME;
        }
        $days = (int) ($row['duration_days'] ?? self::DEFAULT_DAYS);
        $days = $days < 1 ? self::DEFAULT_DAYS : min(self::MAX_DAYS, $days);
        $zones = DateTimeZone::listIdentifiers();
        $tz = trim((string) ($row['timezone'] ?? ''));
        if ($tz === '' || !in_array($tz, $zones, true)) {
            $tz = trim((string) $tenantTimezone);
            if ($tz === '' || !in_array($tz, $zones, true)) {
                $tz = self::FALLBACK_TIMEZONE;
            }
        }
        $retention = (int) ($row['retention_days'] ?? self::DEFAULT_RETENTION_DAYS);
        if ($retention !== 0 && ($retention < 30 || $retention > 3650)) {
            $retention = self::DEFAULT_RETENTION_DAYS;
        }
        $enabled = true;
        if (array_key_exists('enabled', $row)) {
            $enabled = $row['enabled'] === true || (int) $row['enabled'] === 1;
        }

        return [
            'enabled' => $enabled,
            'switch_time' => $time,
            'duration_days' => $days,
            'timezone' => $tz,
            'retention_days' => $retention,
        ];
    }

    /** @return array{enabled: bool, switch_time: string, duration_days: int, timezone: string, retention_days: int} */
    public function settings(int $tenantId): array
    {
        return self::normalizeSettings($this->store->settingsRow($tenantId), $this->store->tenantTimezone($tenantId));
    }

    /**
     * Enregistre les réglages (valeurs invalides ramenées aux valeurs par défaut).
     *
     * @param array<string, mixed> $input
     * @return array{enabled: bool, switch_time: string, duration_days: int, timezone: string, retention_days: int}
     */
    public function saveSettings(int $tenantId, array $input, ?int $userId): array
    {
        $clean = self::normalizeSettings($input, $this->store->tenantTimezone($tenantId));
        $this->store->saveSettings($tenantId, $clean, $userId);

        return $clean;
    }

    /**
     * Cycle qui contient l'instant $ts.
     *
     * @param array{enabled: bool, switch_time: string, duration_days: int, timezone: string, retention_days: int} $settings
     * @return array{key: string, start: int, end: int, start_utc: string, end_utc: string, start_local: string, end_local: string, label: string, timezone: string}
     */
    public static function windowAt(array $settings, int $ts): array
    {
        $tz = new DateTimeZone($settings['timezone']);
        [$h, $m] = array_map('intval', explode(':', $settings['switch_time']));
        $local = (new DateTimeImmutable('@' . $ts))->setTimezone($tz);
        $cand = $local->setTime($h, $m, 0);
        if ($cand->getTimestamp() > $ts) {
            $cand = $cand->modify('-1 day')->setTime($h, $m, 0);
        }
        $n = max(1, $settings['duration_days']);
        $start = $cand;
        if ($n > 1) {
            $days = intdiv(
                gmmktime(0, 0, 0, (int) $cand->format('n'), (int) $cand->format('j'), (int) $cand->format('Y'))
                - (int) strtotime(self::ANCHOR . ' 00:00:00 UTC'),
                86400
            );
            $k = (int) floor($days / $n) * $n;
            $start = (new DateTimeImmutable(self::ANCHOR . ' 00:00:00', $tz))->modify(($k >= 0 ? '+' : '') . $k . ' day')->setTime($h, $m, 0);
        }
        $end = $start->modify('+' . $n . ' day')->setTime($h, $m, 0);
        $s = $start->getTimestamp();
        $e = $end->getTimestamp();

        return [
            'key' => gmdate('Ymd\THis\Z', $s),
            'start' => $s,
            'end' => $e,
            'start_utc' => gmdate('Y-m-d H:i:s', $s),
            'end_utc' => gmdate('Y-m-d H:i:s', $e),
            'start_local' => $start->format('d/m H:i'),
            'end_local' => $end->format('d/m H:i'),
            'label' => 'Mission du ' . $start->format('d/m H:i') . ' → ' . $end->format('d/m H:i'),
            'timezone' => $settings['timezone'],
        ];
    }

    /**
     * Cycle en cours pour une communauté.
     *
     * @return array{key: string, start: int, end: int, start_utc: string, end_utc: string, start_local: string, end_local: string, label: string, timezone: string}
     */
    public function current(int $tenantId, ?int $now = null): array
    {
        return self::windowAt($this->settings($tenantId), $now ?? $this->now());
    }

    /**
     * Début du cycle en cours (« Y-m-d H:i:s » UTC) : tout élément plus ancien n'est plus affiché. Null si le cycle est
     * désactivé ou si les tables ne sont pas prêtes (comportement d'avant : tout est affiché).
     */
    public function readCutoff(int $tenantId, ?int $now = null): ?string
    {
        if (!$this->store->ready()) {
            return null;
        }
        $s = $this->settings($tenantId);
        if (!$s['enabled']) {
            return null;
        }

        return self::windowAt($s, $now ?? $this->now())['start_utc'];
    }

    /**
     * État affiché sur les postes web.
     *
     * @return array<string, mixed>
     */
    public function status(int $tenantId, ?int $now = null): array
    {
        $now ??= $this->now();
        $ready = $this->store->ready();
        $s = $this->settings($tenantId);
        $w = self::windowAt($s, $now);

        return [
            'ready' => $ready,
            'enabled' => $ready && $s['enabled'],
            'settings' => $s,
            'key' => $w['key'],
            'start' => $w['start'],
            'end' => $w['end'],
            'startLocal' => $w['start_local'],
            'endLocal' => $w['end_local'],
            'endTime' => substr($w['end_local'], 6),
            'label' => $w['label'],
            'timezone' => $w['timezone'],
            'remaining' => max(0, $w['end'] - $now),
            'now' => $now,
        ];
    }
}
