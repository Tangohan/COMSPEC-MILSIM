<?php

declare(strict_types=1);

namespace App\Services\DataQuality;

use DateTimeImmutable;

/**
 * Calcul pur (sans base ni session) : taux de remplissage de chaque fiche et vérifications d'anomalies, à partir des
 * lignes brutes de DataQualitySource::memberRows. Les valeurs d'état civil (prénom, nom, date de naissance, téléphone)
 * ne servent qu'à ces calculs : elles ne ressortent jamais dans les résultats, seulement des constats.
 */
final class DataQualityAnalyzer
{
    /** Champ => clés de la ligne brute qui le renseignent (le champ est ignoré si aucune de ces clés n'existe). */
    private const FIELD_SOURCES = [
        'display_name' => ['display_name'],
        'callsign' => ['callsign'],
        'first_name' => ['first_name'],
        'last_name' => ['last_name'],
        'portrait' => ['character_portrait_path', 'avatar_url'],
        'email' => ['email'],
        'phone' => ['phone'],
        'athena_identifier' => ['athena_identifier'],
        'tenant_member_number' => ['tenant_member_number'],
        'service_number' => ['service_number'],
        'matricule_internal' => ['matricule_internal'],
        'steam_id' => ['steam_id'],
        'grade' => ['grade_id'],
        'unit' => ['primary_unit_id', 'main_unit_id', 'unit_count'],
        'enlistment_date' => ['enlistment_date'],
    ];

    /**
     * @param list<array<string, mixed>> $rows lignes de DataQualitySource::memberRows
     * @param array<int, array{bad_range: int, future: int}> $qualificationIssues
     * @return array{members: list<array<string, mixed>>, findings: list<array<string, mixed>>}
     */
    public static function analyze(array $rows, int $tenantId, array $qualificationIssues, DateTimeImmutable $now): array
    {
        $members = [];
        $findings = [];
        foreach ($rows as $row) {
            $member = self::member($row, $tenantId);
            if ($member['id'] < 1) {
                continue;
            }
            $members[$member['id']] = $member;
            foreach (self::rowChecks($row, $member, $tenantId, $qualificationIssues[$member['id']] ?? null, $now) as $code => $_) {
                $findings[] = self::finding($code, $member, []);
            }
        }
        foreach (self::duplicateChecks($rows, $members) as $f) {
            $findings[] = $f;
        }
        usort($findings, static function (array $a, array $b): int {
            return [DataQualityCatalog::SEVERITY_RANK[$a['severity']] ?? 1, $a['code'], $a['user_id']]
                <=> [DataQualityCatalog::SEVERITY_RANK[$b['severity']] ?? 1, $b['code'], $b['user_id']];
        });

        return ['members' => array_values($members), 'findings' => $findings];
    }

    /** Une valeur est renseignée si elle n'est ni vide, ni « 0 », ni une date nulle. */
    public static function isFilled(mixed $value): bool
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return false;
        }
        $s = trim((string) $value);

        return $s !== '' && $s !== '0' && !str_starts_with($s, '0000-00-00');
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id: int, name: string, unit_id: int, has_profile: bool, filled: array<string, bool>, filled_count: int, total: int, percent: int, block_percent: array<string, int>, missing: list<string>, updated_at: string}
     */
    public static function member(array $row, int $tenantId): array
    {
        $filled = [];
        foreach (self::FIELD_SOURCES as $field => $keys) {
            $applicable = false;
            $ok = false;
            foreach ($keys as $key) {
                if (array_key_exists($key, $row)) {
                    $applicable = true;
                    $ok = $ok || self::isFilled($row[$key]);
                }
            }
            if ($applicable) {
                $filled[$field] = $ok;
            }
        }
        $count = count(array_filter($filled));
        $total = count($filled);
        $blocks = [];
        foreach (array_keys(DataQualityCatalog::BLOCKS) as $block) {
            $inBlock = array_filter($filled, static fn (string $f): bool => (DataQualityCatalog::FIELDS[$f]['block'] ?? '') === $block, ARRAY_FILTER_USE_KEY);
            $blocks[$block] = $inBlock === [] ? 100 : (int) round(count(array_filter($inBlock)) * 100 / count($inBlock));
        }
        $id = (int) ($row['id'] ?? 0);
        $name = trim((string) ($row['display_name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($row['callsign'] ?? ''));
        }
        $primary = (int) ($row['primary_unit_id'] ?? 0);
        $unitId = $primary > 0 && (int) ($row['primary_unit_tenant'] ?? $tenantId) === $tenantId ? $primary : (int) ($row['main_unit_id'] ?? 0);
        $updated = '';
        foreach (['updated_at', 'pp_updated', 'uli_updated'] as $key) {
            $v = (string) ($row[$key] ?? '');
            if (self::isFilled($v) && $v > $updated) {
                $updated = $v;
            }
        }

        return [
            'id' => $id,
            'name' => $name,
            'unit_id' => $unitId,
            'has_profile' => (int) ($row['pp_id'] ?? 0) > 0,
            'filled' => $filled,
            'filled_count' => $count,
            'total' => $total,
            'percent' => $total > 0 ? (int) round($count * 100 / $total) : 100,
            'block_percent' => $blocks,
            'missing' => array_keys(array_filter($filled, static fn (bool $ok): bool => !$ok)),
            'updated_at' => $updated,
        ];
    }

    /**
     * Vérifications qui ne regardent qu'une ligne : codes déclenchés.
     *
     * @param array<string, mixed> $row
     * @param array<string, mixed> $member
     * @param array{bad_range: int, future: int}|null $qual
     * @return array<string, true>
     */
    private static function rowChecks(array $row, array $member, int $tenantId, ?array $qual, DateTimeImmutable $now): array
    {
        $hit = [];
        $callsign = self::norm($row['callsign'] ?? '');
        $first = self::norm($row['first_name'] ?? '');
        $last = self::norm($row['last_name'] ?? '');
        if (mb_strlen($callsign) >= 2 && ($callsign === $first || $callsign === $last || ($first !== '' && $last !== '' && ($callsign === $first . ' ' . $last || $callsign === $last . ' ' . $first)))) {
            $hit['callsign_is_real_name'] = true;
        }
        if (str_contains((string) ($row['display_name'] ?? ''), '@')) {
            $hit['display_name_is_email'] = true;
        }
        $email = trim((string) ($row['email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $hit['email_invalid'] = true;
        }
        $gradeId = (int) ($row['grade_id'] ?? 0);
        $gradeKnown = $gradeId > 0 && array_key_exists('grade_tenant', $row) && $row['grade_tenant'] !== null && (int) $row['grade_tenant'] === $tenantId;
        if ($gradeId > 0 && array_key_exists('grade_tenant', $row) && !$gradeKnown) {
            $hit['grade_unknown'] = true;
        }
        $display = self::norm($row['rank_display'] ?? '');
        if ($gradeKnown && $display !== '') {
            $gname = self::norm($row['grade_name'] ?? '');
            $gshort = self::norm($row['grade_short'] ?? '');
            $same = ($gname !== '' && str_contains($display, $gname)) || ($gshort !== '' && str_contains($display, $gshort)) || ($gname !== '' && str_contains($gname, $display));
            if (!$same) {
                $hit['grade_display_mismatch'] = true;
            }
        }
        $primary = (int) ($row['primary_unit_id'] ?? 0);
        if ($primary > 0 && array_key_exists('primary_unit_tenant', $row) && ($row['primary_unit_tenant'] === null || (int) $row['primary_unit_tenant'] !== $tenantId)) {
            $hit['unit_foreign'] = true;
        }
        if (isset($member['filled']['unit']) && $member['filled']['unit'] === false) {
            $hit['unit_missing'] = true;
        }
        $enlist = self::date($row['enlistment_date'] ?? null);
        $birth = self::date($row['birth_date'] ?? null);
        if ($enlist !== null) {
            if ($enlist > $now) {
                $hit['date_enlistment_future'] = true;
            }
            if ((int) $enlist->format('Y') < 1980) {
                $hit['date_enlistment_old'] = true;
            }
            if ($birth !== null && $enlist < $birth) {
                $hit['date_enlistment_before_birth'] = true;
            }
        }
        if ($birth !== null) {
            $age = $birth->diff($now);
            if ($birth > $now || $age->y < 10 || $age->y > 100) {
                $hit['date_birth_implausible'] = true;
            }
        }
        if ($qual !== null) {
            if ($qual['bad_range'] > 0) {
                $hit['qualification_dates'] = true;
            }
            if ($qual['future'] > 0) {
                $hit['qualification_future'] = true;
            }
        }
        if (array_key_exists('pp_id', $row) && !$member['has_profile']) {
            $hit['account_without_profile'] = true;
        }
        $created = self::date($row['created_at'] ?? null);
        if (!self::isFilled($row['last_login_at'] ?? null) && $created !== null && $created < $now->modify('-30 days')) {
            $hit['never_connected'] = true;
        }

        return $hit;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<int, array<string, mixed>> $members
     * @return list<array<string, mixed>>
     */
    private static function duplicateChecks(array $rows, array $members): array
    {
        $keys = [
            'duplicate_steam' => static fn (array $r): string => preg_replace('/\s+/', '', (string) ($r['steam_id'] ?? '')) ?? '',
            'duplicate_member_number' => static fn (array $r): string => self::norm($r['tenant_member_number'] ?? ''),
            'duplicate_callsign' => static fn (array $r): string => self::norm($r['callsign'] ?? ''),
            'duplicate_display_name' => static fn (array $r): string => self::norm($r['display_name'] ?? ''),
            'duplicate_identity' => static function (array $r): string {
                $first = self::norm($r['first_name'] ?? '');
                $last = self::norm($r['last_name'] ?? '');
                $birth = self::isFilled($r['birth_date'] ?? null) ? substr((string) $r['birth_date'], 0, 10) : '';

                return $first !== '' && $last !== '' && $birth !== '' ? $first . '|' . $last . '|' . $birth : '';
            },
        ];
        $out = [];
        foreach ($keys as $code => $keyOf) {
            $groups = [];
            foreach ($rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                $key = $keyOf($row);
                if ($id > 0 && $key !== '') {
                    $groups[$key][] = $id;
                }
            }
            foreach ($groups as $ids) {
                if (count($ids) < 2) {
                    continue;
                }
                foreach ($ids as $id) {
                    $out[] = self::finding($code, $members[$id], array_values(array_diff($ids, [$id])));
                }
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $member
     * @param list<int> $others autres membres concernés (doublons)
     * @return array{code: string, severity: string, user_id: int, name: string, others: list<int>}
     */
    private static function finding(string $code, array $member, array $others): array
    {
        return [
            'code' => $code,
            'severity' => DataQualityCatalog::CHECKS[$code]['severity'],
            'user_id' => (int) $member['id'],
            'name' => (string) $member['name'],
            'others' => array_slice($others, 0, 5),
        ];
    }

    private static function norm(mixed $v): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', is_scalar($v) ? (string) $v : '')));
    }

    private static function date(mixed $raw): ?DateTimeImmutable
    {
        if (!self::isFilled($raw)) {
            return null;
        }
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', substr(trim((string) $raw), 0, 10));

        return $dt === false ? null : $dt;
    }
}
