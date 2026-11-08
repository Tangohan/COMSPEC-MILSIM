<?php

declare(strict_types=1);

namespace App\Services\Watch;

/**
 * Règles simples et explicables sur les signaux des envois du mod (ModSignals). Sans base, sans horloge cachée : les
 * événements, l'état de la fenêtre courante, les seuils (WatchSettings) et l'heure sont passés en paramètres.
 *
 * Aucune règle ne bloque ni ne sanctionne : chacune produit une détection (règle, communauté, joueur, valeurs mesurées)
 * qu'un humain décide dans l'administration de la plateforme.
 *
 * Règles :
 *  - position_impossible       déplacements sans rapport avec une vitesse possible, répétés dans la fenêtre ;
 *  - position_hors_carte       coordonnées au-delà de l'étendue d'une carte, répétées ;
 *  - volume_anormal            nombre d'envois par minute très au-dessus de l'usage, sur plusieurs minutes ;
 *  - identifiant_multi_steam   un même jeton de compte ou de session présenté avec plusieurs Steam ;
 *  - identifiant_steam_decale  Steam déclaré différent du Steam lié au jeton, répété ;
 *  - ecritures_refusees        écritures refusées en rafale ;
 *  - version_incoherente       version annoncée par l'en-tête différente de celle déclarée, ou versions qui changent.
 */
final class ModAbuseDetector
{
    /** Fenêtre d'observation (secondes). */
    public const WINDOW = 600;

    /** Plafond de clés suivies par section de l'état : un afflux d'identifiants ne fait pas grossir le fichier sans fin. */
    public const MAX_KEYS = 4000;

    /** Écart minimal (mètres) et durée minimale/maximale (secondes) entre deux positions pour juger une vitesse. */
    public const MIN_JUMP_M = 300;
    public const MIN_DT = 1;
    public const MAX_DT = 180;

    /** @return array<string, array<string, mixed>> */
    public static function emptyState(): array
    {
        return ['pos' => [], 'jump' => [], 'oob' => [], 'vol' => [], 'cred' => [], 'ref' => [], 'ver' => [], 'vm' => []];
    }

    /**
     * @param iterable<list<string>> $rows lignes du tampon « mod », déjà découpées (un tableau est trié par heure ; un flux
     *                                     est lu dans l'ordre d'arrivée, déjà quasi chronologique)
     * @param array<string, mixed> $state état précédent (emptyState() au premier passage)
     * @param array<string, float|int> $settings
     * @return array{detections: list<array<string, mixed>>, state: array<string, mixed>}
     */
    public static function analyse(iterable $rows, array $state, array $settings, int $now): array
    {
        $state = array_merge(self::emptyState(), $state);
        if (is_array($rows)) {
            usort($rows, static fn (array $a, array $b): int => (int) ($a[1] ?? 0) <=> (int) ($b[1] ?? 0));
        }
        $out = [];
        $touched = [];
        foreach ($rows as $r) {
            $type = $r[0] ?? '';
            $ts = (int) ($r[1] ?? 0);
            $tenant = (int) ($r[2] ?? 0);
            if ($ts < 1 || $tenant < 1) {
                continue;
            }
            if ($type === 'P') {
                self::onPosition($state, $touched, $tenant, (string) ($r[3] ?? ''), (int) ($r[4] ?? 1), (float) ($r[5] ?? 0), (float) ($r[6] ?? 0), $ts, $settings);
            } elseif ($type === 'S') {
                self::onActor($state, $touched, $tenant, $r, $ts);
            } elseif ($type === 'R') {
                self::onRefusal($state, $touched, $tenant, (string) ($r[3] ?? '-'), (string) ($r[4] ?? '-'), $ts);
            }
        }
        $state = self::prune($state, $now);
        foreach (array_keys($touched) as $key) {
            foreach (self::evaluateKey($state, (string) $key, $settings, $now) as $detection) {
                $out[] = $detection;
            }
        }

        return ['detections' => $out, 'state' => $state];
    }

    /** @param array<string, mixed> $state @param array<string, true> $touched */
    private static function onPosition(array &$state, array &$touched, int $tenant, string $steam, int $map, float $x, float $y, int $ts, array $settings): void
    {
        if ($steam === '' || $steam === '-') {
            return;
        }
        $key = $tenant . ':' . $steam;
        $touched[$key] = true;
        $limit = (float) $settings[WatchSettings::POS_LIMIT_M];
        if (abs($x) > $limit || abs($y) > $limit) {
            $state['oob'][$key][] = [$ts, (int) $x, (int) $y];
        }
        $prev = $state['pos'][$key] ?? null;
        if (is_array($prev) && (int) $prev[1] === $map) {
            $dt = $ts - (int) $prev[0];
            if ($dt >= self::MIN_DT && $dt <= self::MAX_DT) {
                $dist = hypot($x - (float) $prev[2], $y - (float) $prev[3]);
                if ($dist >= self::MIN_JUMP_M) {
                    $speed = $dist / $dt;
                    if ($speed > (float) $settings[WatchSettings::SPEED_MPS]) {
                        $state['jump'][$key][] = [$ts, (int) round($speed), (int) round($dist), $dt];
                    }
                }
            }
        }
        $state['pos'][$key] = [$ts, $map, $x, $y];
    }

    /** @param array<string, mixed> $state @param array<string, true> $touched @param list<string> $r */
    private static function onActor(array &$state, array &$touched, int $tenant, array $r, int $ts): void
    {
        $steam = (string) ($r[3] ?? '-');
        $kind = (string) ($r[4] ?? '-');
        $fp = (string) ($r[5] ?? '-');
        $bound = (string) ($r[6] ?? '-');
        $uaVersion = (string) ($r[7] ?? '-');
        $declared = (string) ($r[8] ?? '-');
        $actorKey = $tenant . ':' . ($steam !== '-' ? $steam : '?');
        $minute = intdiv($ts, 60) * 60;
        $state['vol'][$actorKey][$minute] = (int) ($state['vol'][$actorKey][$minute] ?? 0) + 1;
        $touched[$actorKey] = true;

        if ($steam !== '-' && in_array($kind, [ModSignals::CRED_ACCOUNT_TOKEN, ModSignals::CRED_GAME_SESSION], true) && $fp !== '-') {
            $credKey = 'c' . $tenant . ':' . $fp;
            $state['cred'][$credKey]['s'][$steam] = $ts;
            if ($bound !== '-' && $bound !== $steam) {
                $state['cred'][$credKey]['m'][] = [$ts, $steam, $bound];
            }
            $state['cred'][$credKey]['u'] = $steam;
            $touched[$credKey] = true;
        }
        if ($steam !== '-') {
            $a = $uaVersion !== '-' ? \App\Support\GameClientVersionGate::normalizeVersion($uaVersion) : null;
            $b = $declared !== '-' ? \App\Support\GameClientVersionGate::normalizeVersion($declared) : null;
            if ($a !== null) {
                $state['ver'][$actorKey][$a] = $ts;
            }
            if ($a !== null && $b !== null && \App\Support\GameClientVersionGate::compareVersions($a, $b) !== 0) {
                $state['vm'][$actorKey][] = [$ts, $a, $b];
            }
        }
    }

    /** @param array<string, mixed> $state @param array<string, true> $touched */
    private static function onRefusal(array &$state, array &$touched, int $tenant, string $steam, string $reason, int $ts): void
    {
        $key = $tenant . ':' . ($steam !== '-' ? $steam : '?');
        $minute = intdiv($ts, 60) * 60;
        $state['ref'][$key][$minute][$reason] = (int) ($state['ref'][$key][$minute][$reason] ?? 0) + 1;
        // Un refus compte aussi dans le volume d'envois de l'acteur.
        $state['vol'][$key][$minute] = (int) ($state['vol'][$key][$minute] ?? 0) + 1;
        $touched[$key] = true;
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, float|int> $settings
     * @return list<array<string, mixed>>
     */
    private static function evaluateKey(array &$state, string $key, array $settings, int $now): array
    {
        $out = [];
        $from = $now - self::WINDOW;
        $isCred = str_starts_with($key, 'c');
        if ($isCred) {
            $tenant = (int) substr($key, 1, (int) strpos($key, ':') - 1);
            $entry = $state['cred'][$key] ?? null;
            if (!is_array($entry)) {
                return [];
            }
            $steams = array_filter((array) ($entry['s'] ?? []), static fn ($ts): bool => (int) $ts >= $from);
            if (count($steams) >= (int) $settings[WatchSettings::CRED_STEAMS]) {
                $out[] = self::detection('identifiant_multi_steam', $tenant, null, $key, [
                    'steams_distincts' => count($steams),
                    'fenetre_min' => intdiv(self::WINDOW, 60),
                    'dernier_steam' => (string) ($entry['u'] ?? ''),
                ], array_key_last($steams) !== null ? (string) array_key_last($steams) : null);
                $state['cred'][$key]['s'] = [];
            }
            $mismatch = array_values(array_filter((array) ($entry['m'] ?? []), static fn ($m): bool => (int) $m[0] >= $from));
            if (count($mismatch) >= 3) {
                $last = $mismatch[count($mismatch) - 1];
                $out[] = self::detection('identifiant_steam_decale', $tenant, (string) $last[1], $key, [
                    'occurrences' => count($mismatch),
                    'fenetre_min' => intdiv(self::WINDOW, 60),
                ]);
                $state['cred'][$key]['m'] = [];
            }

            return $out;
        }

        [$tenantRaw, $steam] = array_pad(explode(':', $key, 2), 2, '');
        $tenant = (int) $tenantRaw;
        $steamOrNull = $steam !== '' && $steam !== '?' ? $steam : null;

        $jumps = array_values(array_filter((array) ($state['jump'][$key] ?? []), static fn ($j): bool => (int) $j[0] >= $from));
        if (count($jumps) >= (int) $settings[WatchSettings::SPEED_HITS]) {
            $max = 0;
            $maxDist = 0;
            foreach ($jumps as $j) {
                $max = max($max, (int) $j[1]);
                $maxDist = max($maxDist, (int) $j[2]);
            }
            $out[] = self::detection('position_impossible', $tenant, $steamOrNull, $key, [
                'sauts' => count($jumps),
                'vitesse_max_ms' => $max,
                'distance_max_m' => $maxDist,
                'seuil_vitesse_ms' => (int) $settings[WatchSettings::SPEED_MPS],
                'fenetre_min' => intdiv(self::WINDOW, 60),
            ]);
            $state['jump'][$key] = [];
        }

        $oob = array_values(array_filter((array) ($state['oob'][$key] ?? []), static fn ($o): bool => (int) $o[0] >= $from));
        if (count($oob) >= 3) {
            $far = 0;
            foreach ($oob as $o) {
                $far = max($far, abs((int) $o[1]), abs((int) $o[2]));
            }
            $out[] = self::detection('position_hors_carte', $tenant, $steamOrNull, $key, [
                'positions' => count($oob),
                'coordonnee_max_m' => $far,
                'limite_m' => (int) $settings[WatchSettings::POS_LIMIT_M],
                'fenetre_min' => intdiv(self::WINDOW, 60),
            ]);
            $state['oob'][$key] = [];
        }

        // Sans Steam, la clé regroupe tous les envois anonymes de la communauté : le volume n'y dit rien d'un joueur.
        $perMinute = $steamOrNull === null
            ? []
            : array_filter((array) ($state['vol'][$key] ?? []), static fn ($n, $minute): bool => (int) $minute >= $now - 300, ARRAY_FILTER_USE_BOTH);
        $over = array_filter($perMinute, static fn ($n): bool => (int) $n >= (int) $settings[WatchSettings::VOLUME_PER_MIN]);
        if (count($over) >= 2) {
            $out[] = self::detection('volume_anormal', $tenant, $steamOrNull, $key, [
                'minutes_au_dessus' => count($over),
                'maximum_par_minute' => (int) max($over),
                'seuil_par_minute' => (int) $settings[WatchSettings::VOLUME_PER_MIN],
                'fenetre_min' => 5,
            ]);
            $state['vol'][$key] = [];
        }

        $refusals = (array) ($state['ref'][$key] ?? []);
        foreach ($refusals as $minute => $reasons) {
            if ((int) $minute < $now - 300) {
                continue;
            }
            $total = (int) array_sum((array) $reasons);
            if ($total >= (int) $settings[WatchSettings::REFUSALS_PER_MIN]) {
                arsort($reasons);
                $out[] = self::detection('ecritures_refusees', $tenant, $steamOrNull, $key, [
                    'refus_dans_la_minute' => $total,
                    'motifs' => array_slice($reasons, 0, 3, true),
                    'seuil_par_minute' => (int) $settings[WatchSettings::REFUSALS_PER_MIN],
                ]);
                $state['ref'][$key] = [];
                break;
            }
        }

        $mism = array_values(array_filter((array) ($state['vm'][$key] ?? []), static fn ($m): bool => (int) $m[0] >= $from));
        $versions = array_filter((array) ($state['ver'][$key] ?? []), static fn ($ts): bool => (int) $ts >= $from);
        if (count($mism) >= 3) {
            $last = $mism[count($mism) - 1];
            $out[] = self::detection('version_incoherente', $tenant, $steamOrNull, $key, [
                'constat' => 'en-tête et déclaration différents',
                'occurrences' => count($mism),
                'version_en_tete' => (string) $last[1],
                'version_declaree' => (string) $last[2],
            ]);
            $state['vm'][$key] = [];
        } elseif (count($versions) >= (int) $settings[WatchSettings::VERSION_CHANGES]) {
            $out[] = self::detection('version_incoherente', $tenant, $steamOrNull, $key, [
                'constat' => 'versions qui changent',
                'versions_distinctes' => count($versions),
                'versions' => array_slice(array_keys($versions), 0, 5),
                'fenetre_min' => intdiv(self::WINDOW, 60),
            ]);
            $state['ver'][$key] = [];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $measured
     * @return array<string, mixed>
     */
    private static function detection(string $rule, int $tenant, ?string $steam, string $key, array $measured, ?string $lastSteam = null): array
    {
        return [
            'rule' => $rule,
            'tenant_id' => $tenant,
            'steam_uid' => $steam ?? $lastSteam,
            'key' => $key,
            'measured' => $measured,
        ];
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private static function prune(array $state, int $now): array
    {
        $from = $now - self::WINDOW;
        foreach (['jump', 'oob', 'vm'] as $section) {
            foreach ($state[$section] as $key => $list) {
                $list = array_values(array_filter((array) $list, static fn ($e): bool => (int) $e[0] >= $from));
                if ($list === []) {
                    unset($state[$section][$key]);
                } else {
                    $state[$section][$key] = $list;
                }
            }
        }
        foreach (['vol', 'ref'] as $section) {
            foreach ($state[$section] as $key => $minutes) {
                $minutes = array_filter((array) $minutes, static fn ($v, $minute): bool => (int) $minute >= $now - 300, ARRAY_FILTER_USE_BOTH);
                if ($minutes === []) {
                    unset($state[$section][$key]);
                } else {
                    $state[$section][$key] = $minutes;
                }
            }
        }
        foreach ($state['ver'] as $key => $versions) {
            $versions = array_filter((array) $versions, static fn ($ts): bool => (int) $ts >= $from);
            if ($versions === []) {
                unset($state['ver'][$key]);
            } else {
                $state['ver'][$key] = $versions;
            }
        }
        foreach ($state['cred'] as $key => $entry) {
            $entry['s'] = array_filter((array) ($entry['s'] ?? []), static fn ($ts): bool => (int) $ts >= $from);
            $entry['m'] = array_values(array_filter((array) ($entry['m'] ?? []), static fn ($m): bool => (int) $m[0] >= $from));
            if ($entry['s'] === [] && $entry['m'] === []) {
                unset($state['cred'][$key]);
            } else {
                $state['cred'][$key] = $entry;
            }
        }
        foreach ($state['pos'] as $key => $p) {
            if ((int) $p[0] < $now - self::MAX_DT * 2) {
                unset($state['pos'][$key]);
            }
        }
        foreach (array_keys($state) as $section) {
            if (count($state[$section]) > self::MAX_KEYS) {
                $state[$section] = array_slice($state[$section], -self::MAX_KEYS, null, true);
            }
        }

        return $state;
    }
}
