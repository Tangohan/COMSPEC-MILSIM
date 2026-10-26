<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\AtakDistanceRepository;
use App\Support\PilotSyllabusCatalog;
use App\Support\QualificationAdminStatus;

/**
 * Évalue les règles du cursus pilote sur les heures de vol relevées par le mod (atak_pilot_time) : pour chaque membre
 * et chaque palier, détenu, en attente de validation, bloqué (échec ou retrait : on ne repropose pas tout seul),
 * éligible (heures et palier précédent atteints) ou en progression. Pur : ni base, ni session, testable seul.
 */
final class PilotQualificationEngine
{
    public const HELD = 'held';
    public const PENDING = 'pending';
    public const BLOCKED = 'blocked';
    public const ELIGIBLE = 'eligible';
    public const PROGRESS = 'progress';

    /**
     * Heures d'un membre par périmètre : « kind:heli », « kind:plane » et « family:<clé> », avec le dernier vol.
     *
     * @param list<array<string, mixed>> $rows lignes atak_pilot_time d'un membre (vehicle_class, vehicle_label, vehicle_kind, seconds, last_flown_at)
     * @return array<string, array{seconds: int, last_flown_at: string}>
     */
    public static function scopeTotals(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $seconds = max(0, (int) ($row['seconds'] ?? 0));
            if ($seconds < 1) {
                continue;
            }
            $kind = ($row['vehicle_kind'] ?? '') === 'plane' ? 'plane' : 'heli';
            $family = AtakDistanceRepository::aircraftFamily((string) ($row['vehicle_class'] ?? ''), (string) ($row['vehicle_label'] ?? ''));
            $last = (string) ($row['last_flown_at'] ?? '');
            foreach (['kind:' . $kind, 'family:' . $family['key']] as $scope) {
                $prev = $out[$scope] ?? ['seconds' => 0, 'last_flown_at' => ''];
                $out[$scope] = [
                    'seconds' => $prev['seconds'] + $seconds,
                    'last_flown_at' => strcmp($last, $prev['last_flown_at']) > 0 ? $last : $prev['last_flown_at'],
                ];
            }
        }

        return $out;
    }

    /**
     * Ajoute le carnet de vol aux heures relevées : un vol saisi par le pilote compte une fois contresigné (les vols
     * du mod sont déjà dans atak_pilot_time) ; les heures de nuit, aux instruments et en patrouille notées sur un vol
     * comptent une fois contresignées, sous « cond:night:heli », « cond:ifr:plane »…
     *
     * @param array<string, array{seconds: int, last_flown_at: string}> $totals
     * @param list<array<string, mixed>> $entries lignes pilot_logbook_entries d'un membre
     * @return array<string, array{seconds: int, last_flown_at: string}>
     */
    public static function mergeLogbook(array $totals, array $entries): array
    {
        $add = static function (array &$totals, string $scope, int $seconds, string $at): void {
            if ($seconds < 1) {
                return;
            }
            $prev = $totals[$scope] ?? ['seconds' => 0, 'last_flown_at' => ''];
            $totals[$scope] = [
                'seconds' => $prev['seconds'] + $seconds,
                'last_flown_at' => strcmp($at, $prev['last_flown_at']) > 0 ? $at : $prev['last_flown_at'],
            ];
        };
        foreach ($entries as $e) {
            if (($e['status'] ?? '') !== 'countersigned') {
                continue;
            }
            $kind = ($e['vehicle_kind'] ?? '') === 'plane' ? 'plane' : 'heli';
            $at = (string) ($e['flown_on'] ?? '');
            $at = $at !== '' && strlen($at) === 10 ? $at . ' 00:00:00' : $at;
            $seconds = max(0, (int) ($e['seconds'] ?? 0));
            if (($e['source'] ?? '') === 'pilot') {
                $family = AtakDistanceRepository::aircraftFamily((string) ($e['vehicle_class'] ?? ''), (string) ($e['vehicle_label'] ?? ''));
                $add($totals, 'kind:' . $kind, $seconds, $at);
                $add($totals, 'family:' . $family['key'], $seconds, $at);
            }
            foreach (['night' => 'night_seconds', 'ifr' => 'ifr_seconds', 'formation' => 'formation_seconds'] as $cond => $col) {
                $add($totals, 'cond:' . $cond . ':' . $kind, min($seconds, max(0, (int) ($e[$col] ?? 0))), $at);
            }
        }

        return $totals;
    }

    /**
     * État d'un palier pour un membre.
     *
     * @param array<string, mixed> $rule règle (definition_id, scope, track, min_scope_hours, min_track_hours, prereq_definition_id)
     * @param array<string, array{seconds: int, last_flown_at: string}> $totals heures du membre (scopeTotals)
     * @param array<int, array<string, mixed>> $awards attributions du membre indexées par qualification (admin_status, id…)
     * @return array{state: string, scope_hours: float, track_hours: float, target_scope: float, target_track: float, cond: ?string, cond_hours: float, target_cond: float, pct: int, prereq_ok: bool, hours_ok: bool, award_id: int, last_flown_at: string}
     */
    public static function evaluate(array $rule, array $totals, array $awards): array
    {
        $scope = (string) ($rule['scope'] ?? '');
        $track = (string) ($rule['track'] ?? 'heli');
        $scopeHours = round(($totals[$scope]['seconds'] ?? 0) / 3600, 2);
        $trackHours = round(($totals['kind:' . $track]['seconds'] ?? 0) / 3600, 2);
        $targetScope = max(0.0, (float) ($rule['min_scope_hours'] ?? 0));
        $targetTrack = max(0.0, (float) ($rule['min_track_hours'] ?? 0));
        $cond = PilotSyllabusCatalog::tier((string) ($rule['tier'] ?? ''))['cond'] ?? null;
        $targetCond = $cond !== null ? max(0.0, (float) ($rule['min_cond_hours'] ?? 0)) : 0.0;
        $condHours = $cond !== null ? round(($totals['cond:' . $cond . ':' . $track]['seconds'] ?? 0) / 3600, 2) : 0.0;
        $hoursOk = $scopeHours >= $targetScope && $trackHours >= $targetTrack && $condHours >= $targetCond;

        $prereqId = (int) ($rule['prereq_definition_id'] ?? 0);
        $prereqOk = $prereqId < 1 || self::statusOf($awards[$prereqId] ?? null) === QualificationAdminStatus::OBTAINED;

        $parts = [];
        if ($targetScope > 0) {
            $parts[] = min(1.0, $scopeHours / $targetScope);
        }
        if ($targetTrack > 0) {
            $parts[] = min(1.0, $trackHours / $targetTrack);
        }
        if ($targetCond > 0) {
            $parts[] = min(1.0, $condHours / $targetCond);
        }
        $pct = $parts === [] ? 100 : (int) floor(min($parts) * 100);

        $award = $awards[(int) ($rule['definition_id'] ?? 0)] ?? null;
        $status = self::statusOf($award);
        $state = match (true) {
            $status === QualificationAdminStatus::OBTAINED => self::HELD,
            in_array($status, [QualificationAdminStatus::CANDIDATE, QualificationAdminStatus::IN_TRAINING, QualificationAdminStatus::IN_EVALUATION], true) => self::PENDING,
            in_array($status, [QualificationAdminStatus::FAILED, QualificationAdminStatus::SUSPENDED, QualificationAdminStatus::REVOKED], true) => self::BLOCKED,
            $hoursOk && $prereqOk => self::ELIGIBLE,
            default => self::PROGRESS,
        };

        return [
            'state' => $state,
            'scope_hours' => $scopeHours,
            'track_hours' => $trackHours,
            'target_scope' => $targetScope,
            'target_track' => $targetTrack,
            'cond' => $cond,
            'cond_hours' => $condHours,
            'target_cond' => $targetCond,
            'pct' => $pct,
            'prereq_ok' => $prereqOk,
            'hours_ok' => $hoursOk,
            'award_id' => is_array($award) ? (int) ($award['id'] ?? 0) : 0,
            'last_flown_at' => (string) ($totals[$scope]['last_flown_at'] ?? ''),
        ];
    }

    /**
     * Attributions d'un membre réduites à une par qualification : celle qui compte le plus (obtenue, puis en attente,
     * puis la plus récente).
     *
     * @param list<array<string, mixed>> $rows lignes personnel_qualifications d'un membre
     * @return array<int, array<string, mixed>>
     */
    public static function awardsByDefinition(array $rows): array
    {
        $rank = static fn (?array $a): int => match (self::statusOf($a)) {
            QualificationAdminStatus::OBTAINED => 3,
            QualificationAdminStatus::CANDIDATE, QualificationAdminStatus::IN_TRAINING, QualificationAdminStatus::IN_EVALUATION => 2,
            '' => 0,
            default => 1,
        };
        $out = [];
        foreach ($rows as $row) {
            $def = (int) ($row['definition_id'] ?? 0);
            if ($def < 1) {
                continue;
            }
            $prev = $out[$def] ?? null;
            if ($prev === null || $rank($row) > $rank($prev) || ($rank($row) === $rank($prev) && (int) ($row['id'] ?? 0) > (int) ($prev['id'] ?? 0))) {
                $out[$def] = $row;
            }
        }

        return $out;
    }

    /** Statut administratif d'une attribution ; les anciennes lignes n'ont que status (valid, expired, in_progress). */
    public static function statusOf(?array $award): string
    {
        if ($award === null) {
            return '';
        }
        $raw = trim((string) ($award['admin_status'] ?? ''));
        if ($raw === '') {
            $legacy = (string) ($award['status'] ?? '');
            $raw = match ($legacy) {
                'valid', 'expiring' => QualificationAdminStatus::OBTAINED,
                'expired' => QualificationAdminStatus::REVOKED,
                default => QualificationAdminStatus::IN_TRAINING,
            };
        }

        return QualificationAdminStatus::normalize($raw);
    }

    /** Heures lisibles : « 2 h 05 », « 45 min ». */
    public static function formatHours(float $hours): string
    {
        $minutes = (int) round($hours * 60);
        if ($minutes < 60) {
            return $minutes . ' min';
        }

        return intdiv($minutes, 60) . ' h ' . str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT);
    }
}
