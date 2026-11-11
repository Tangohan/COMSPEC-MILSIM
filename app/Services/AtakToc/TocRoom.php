<?php

declare(strict_types=1);

namespace App\Services\AtakToc;

/**
 * Salle TOC d'un camp, côté site : même modèle que la salle du jeu (fn_tocRoom, COMSPEC_ATAK_TocRoom_<camp>) en
 * objets JSON, et les mêmes actions, rejouées ici pour l'affichage immédiat d'une action du web.
 *
 * État : timers, log, excheck, requests, flash, posts, kills, sats, screens. Bornes du jeu : 16 chronos, 150 lignes de
 * main courante, 40 lignes d'EXCHECK, 40 demandes. Identifiants : ceux du jeu (T…, E…, R…) ou W… pour une ligne née sur
 * le web et pas encore reprise par le serveur du jeu.
 */
final class TocRoom
{
    public const MAX_TIMERS = 16;
    public const MAX_LOG = 150;
    public const MAX_EXCHECK = 40;
    public const MAX_REQUESTS = 40;

    public const ACTIONS = [
        'timerAdd', 'timerStart', 'timerStop', 'timerReset', 'timerDel',
        'log', 'exAdd', 'exDone', 'exUndo', 'exDel', 'reqSet', 'kill', 'flash', 'flashClear',
    ];

    public const REQUEST_STATUSES = ['RECUE', 'PRISE', 'REFUSEE', 'TERMINEE'];
    public const KILL_KEYS = ['wia', 'kia', 'ekia', 'ewia'];

    /** @return array<string, mixed> */
    public static function blank(): array
    {
        return ['timers' => [], 'log' => [], 'excheck' => [], 'requests' => [], 'flash' => null, 'posts' => [], 'kills' => [], 'sats' => [], 'screens' => [], 'clock' => null];
    }

    private static function s(mixed $v, int $max): string
    {
        return PlanValidator::text($v, $max);
    }

    private static function zulu(int $at): string
    {
        return gmdate('H:i:s', $at);
    }

    /**
     * Instantané envoyé par le jeu (fn_tocWeb « push ») => état du site. Les UID des joueurs ne sont pas gardés.
     *
     * @param array<string, mixed> $room
     * @return array<string, mixed>
     */
    public static function fromGame(array $room, int $now): array
    {
        $st = self::blank();
        $arr = static fn (string $k): array => is_array($room[$k] ?? null) ? $room[$k] : [];
        foreach (array_slice($arr('timers'), 0, self::MAX_TIMERS) as $t) {
            if (!is_array($t) || count($t) < 6) {
                continue;
            }
            $kind = in_array($t[2] ?? '', ['UP', 'DOWN', 'AT'], true) ? $t[2] : 'UP';
            $st['timers'][] = [
                'id' => self::s($t[0], 24), 'name' => self::s($t[1], 24), 'kind' => $kind,
                'value' => (int) max(0, min(86400, (float) $t[3])), 'running' => (bool) $t[4],
                'acc' => (int) max(0, round((float) $t[5])), 'base_at' => $now, 'pending' => false,
            ];
        }
        foreach (array_slice($arr('log'), -self::MAX_LOG) as $l) {
            if (is_array($l) && count($l) >= 4) {
                $st['log'][] = ['t' => self::s($l[0], 8), 'kind' => mb_strtoupper(self::s($l[1], 12)), 'author' => self::s($l[2], 60), 'text' => self::s($l[3], 200), 'pending' => false];
            }
        }
        foreach (array_slice($arr('excheck'), 0, self::MAX_EXCHECK) as $e) {
            if (is_array($e) && count($e) >= 7) {
                $st['excheck'][] = [
                    'id' => self::s($e[0], 24), 'n' => (int) $e[1], 'ev' => self::s($e[2], 60), 'cw' => mb_strtoupper(self::s($e[3], 24)),
                    'el' => mb_strtoupper(self::s($e[4], 24)), 'at' => self::s($e[5], 8), 'done' => self::s($e[6], 8), 'pending' => false,
                ];
            }
        }
        foreach (array_slice($arr('requests'), 0, self::MAX_REQUESTS) as $r) {
            if (!is_array($r) || count($r) < 9) {
                continue;
            }
            $pos = is_array($r[9] ?? null) && count($r[9]) >= 2 && is_numeric($r[9][0]) && is_numeric($r[9][1]) ? [round((float) $r[9][0]), round((float) $r[9][1])] : null;
            $status = mb_strtoupper(self::s($r[7], 12));
            $st['requests'][] = [
                'id' => self::s($r[0], 24), 't' => self::s($r[1], 8), 'kind' => mb_strtoupper(self::s($r[2], 16)), 'by' => self::s($r[3], 60),
                'grid' => self::s($r[5], 12), 'text' => self::s($r[6], 200),
                'status' => in_array($status, self::REQUEST_STATUSES, true) ? $status : 'RECUE', 'answer' => self::s($r[8], 160),
                'pos' => $pos, 'pending' => false,
            ];
        }
        $f = $arr('flash');
        if (count($f) >= 3 && trim((string) $f[0]) !== '') {
            $st['flash'] = ['text' => self::s($f[0], 160), 'author' => self::s($f[1], 60), 't' => self::s($f[2], 8), 'at' => $now, 'pending' => false];
        }
        foreach (array_slice($arr('posts'), 0, 8) as $p) {
            if (is_array($p) && count($p) >= 4) {
                $st['posts'][] = ['post' => self::s($p[0], 40), 'op' => self::s($p[1], 60), 't' => self::s($p[3], 8)];
            }
        }
        foreach (array_slice($arr('kills'), 0, 60) as $k) {
            if (is_array($k) && count($k) >= 5) {
                $st['kills'][] = ['el' => self::s($k[0], 40), 'wia' => max(0, (int) $k[1]), 'kia' => max(0, (int) $k[2]), 'ekia' => max(0, (int) $k[3]), 'ewia' => max(0, (int) $k[4])];
            }
        }
        foreach (array_slice($arr('sats'), 0, 2) as $i => $s) {
            if (is_array($s) && count($s) >= 4) {
                $st['sats'][] = [
                    'n' => $i + 1, 'x' => (float) $s[0], 'y' => (float) $s[1], 'hw' => (float) max(31.25, min(1000.0, (float) $s[2])),
                    'sensor' => self::s($s[3], 8), 'follow' => self::s($s[4] ?? '', 2), 'op' => self::s($s[6] ?? '', 60),
                ];
            }
        }
        $local = self::s($room['local'] ?? '', 8);
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $local) === 1) {
            $st['clock'] = ['local' => $local, 'date' => self::s($room['date'] ?? '', 10), 'at' => $now];
        }
        foreach (array_slice($arr('screens'), 0, 40) as $s) {
            if (is_array($s) && count($s) >= 2) {
                $st['screens'][] = ['name' => self::s($s[0], 40), 'display' => mb_strtoupper(self::s($s[1], 12)), 'hall' => self::s($s[2] ?? '', 40)];
            }
        }

        return $st;
    }

    /**
     * Valide une action du web. Retour : [arguments nettoyés|null, raison].
     *
     * @return array{0: array<string, mixed>|null, 1: string}
     */
    public static function validateAction(string $act, mixed $args): array
    {
        if (!in_array($act, self::ACTIONS, true)) {
            return [null, 'action_unknown'];
        }
        $a = is_array($args) ? $args : [];
        $id = static fn (): ?string => preg_match('/^[A-Za-z0-9_-]{1,24}$/', (string) ($a['id'] ?? '')) === 1 ? (string) $a['id'] : null;
        switch ($act) {
            case 'timerAdd':
                $name = mb_strtoupper(self::s($a['name'] ?? '', 24));
                $kind = in_array($a['kind'] ?? '', ['UP', 'DOWN', 'AT'], true) ? $a['kind'] : 'UP';
                $value = (int) ($a['value'] ?? 0);
                if ($name === '') {
                    return [null, 'name_required'];
                }
                if ($kind === 'DOWN' && ($value < 1 || $value > 86400)) {
                    return [null, 'value_invalid'];
                }
                if ($kind === 'AT' && ($value < 0 || $value > 1439)) {
                    return [null, 'value_invalid'];
                }

                return [['name' => $name, 'kind' => $kind, 'value' => $kind === 'UP' ? 0 : $value], ''];
            case 'timerStart':
            case 'timerStop':
            case 'timerReset':
            case 'timerDel':
            case 'exDone':
            case 'exUndo':
            case 'exDel':
                return ($i = $id()) !== null ? [['id' => $i], ''] : [null, 'id_invalid'];
            case 'log':
                $text = self::s($a['text'] ?? '', 200);

                return $text !== '' ? [['text' => $text], ''] : [null, 'text_required'];
            case 'exAdd':
                $ev = self::s($a['ev'] ?? '', 60);
                $at = self::s($a['at'] ?? '', 8);
                if ($ev === '') {
                    return [null, 'text_required'];
                }
                if ($at !== '' && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $at) !== 1) {
                    return [null, 'time_invalid'];
                }

                return [['ev' => $ev, 'cw' => mb_strtoupper(self::s($a['cw'] ?? '', 24)), 'el' => mb_strtoupper(self::s($a['el'] ?? '', 24)), 'at' => $at], ''];
            case 'reqSet':
                $status = strtoupper(is_string($a['status'] ?? null) ? $a['status'] : '');
                if (($i = $id()) === null || !in_array($status, self::REQUEST_STATUSES, true)) {
                    return [null, 'request_invalid'];
                }

                return [['id' => $i, 'status' => $status, 'answer' => self::s($a['answer'] ?? '', 160)], ''];
            case 'kill':
                $el = mb_strtoupper(self::s($a['el'] ?? '', 40));
                $key = (string) ($a['key'] ?? '');
                $n = (int) ($a['n'] ?? 0);
                if ($el === '' || !in_array($key, self::KILL_KEYS, true) || $n === 0 || abs($n) > 50) {
                    return [null, 'kill_invalid'];
                }

                return [['el' => $el, 'key' => $key, 'n' => $n], ''];
            case 'flash':
                $text = self::s($a['text'] ?? '', 160);

                return $text !== '' ? [['text' => $text], ''] : [null, 'text_required'];
            case 'flashClear':
                return [[], ''];
        }

        return [null, 'action_unknown'];
    }

    /**
     * Applique une action (déjà validée) à l'état, comme le serveur du jeu ; les lignes ajoutées sont marquées
     * « pending » tant que le jeu ne les a pas reprises.
     *
     * @param array<string, mixed> $st
     * @param array<string, mixed> $a
     * @return array<string, mixed>
     */
    public static function apply(array $st, string $act, array $a, string $who, int $at, string $webId): array
    {
        $z = self::zulu($at);
        $hm = substr($z, 0, 5);
        $logLine = static function (array &$st, string $kind, string $text) use ($z, $who): void {
            $st['log'][] = ['t' => $z, 'kind' => $kind, 'author' => $who, 'text' => $text, 'pending' => true];
            if (count($st['log']) > self::MAX_LOG) {
                $st['log'] = array_slice($st['log'], -self::MAX_LOG);
            }
        };
        $findIdx = static function (array $list, string $id): int {
            foreach ($list as $i => $row) {
                if (($row['id'] ?? '') === $id) {
                    return $i;
                }
            }

            return -1;
        };
        switch ($act) {
            case 'timerAdd':
                if (count($st['timers']) < self::MAX_TIMERS) {
                    $st['timers'][] = ['id' => $webId, 'name' => $a['name'], 'kind' => $a['kind'], 'value' => $a['value'], 'running' => false, 'acc' => 0, 'base_at' => $at, 'pending' => true];
                }
                break;
            case 'timerStart':
            case 'timerStop':
            case 'timerReset':
            case 'timerDel':
                $i = $findIdx($st['timers'], $a['id']);
                if ($i < 0) {
                    break;
                }
                $t = $st['timers'][$i];
                $elapsed = $t['running'] ? max(0, $at - (int) $t['base_at']) : 0;
                if ($act === 'timerDel') {
                    array_splice($st['timers'], $i, 1);
                    break;
                }
                if ($act === 'timerStart' && !$t['running']) {
                    $t['running'] = true;
                    $t['base_at'] = $at;
                    $logLine($st, 'CHRONO', $t['name'] . ' lancé');
                } elseif ($act === 'timerStop' && $t['running']) {
                    $t['acc'] += $elapsed;
                    $t['running'] = false;
                    $t['base_at'] = $at;
                    $logLine($st, 'CHRONO', $t['name'] . ' arrêté');
                } elseif ($act === 'timerReset') {
                    $t['acc'] = 0;
                    $t['base_at'] = $at;
                }
                $t['pending'] = true;
                $st['timers'][$i] = $t;
                break;
            case 'log':
                $logLine($st, 'NOTE', $a['text']);
                break;
            case 'exAdd':
                if (count($st['excheck']) < self::MAX_EXCHECK) {
                    $n = 0;
                    foreach ($st['excheck'] as $e) {
                        $n = max($n, (int) $e['n']);
                    }
                    $st['excheck'][] = ['id' => $webId, 'n' => $n + 1, 'ev' => $a['ev'], 'cw' => $a['cw'], 'el' => $a['el'], 'at' => $a['at'], 'done' => '', 'pending' => true];
                }
                break;
            case 'exDone':
            case 'exUndo':
            case 'exDel':
                $i = $findIdx($st['excheck'], $a['id']);
                if ($i < 0) {
                    break;
                }
                if ($act === 'exDel') {
                    array_splice($st['excheck'], $i, 1);
                    break;
                }
                $e = $st['excheck'][$i];
                $e['done'] = $act === 'exDone' ? $hm : '';
                $e['pending'] = true;
                $st['excheck'][$i] = $e;
                if ($act === 'exDone') {
                    $logLine($st, 'EXCHECK', trim(($e['cw'] !== '' ? $e['cw'] . ' · ' : '') . $e['ev'] . ($e['el'] !== '' ? ' · ' . $e['el'] : '')));
                }
                break;
            case 'reqSet':
                $i = $findIdx($st['requests'], $a['id']);
                if ($i < 0) {
                    break;
                }
                $r = $st['requests'][$i];
                $r['status'] = $a['status'];
                $r['answer'] = $a['answer'];
                $r['pending'] = true;
                $st['requests'][$i] = $r;
                $logLine($st, 'DEMANDE', $r['kind'] . ' ' . $r['by'] . ' : ' . $a['status'] . ($a['answer'] !== '' ? ' · ' . $a['answer'] : ''));
                break;
            case 'kill':
                $found = false;
                foreach ($st['kills'] as $i => $k) {
                    if (strtoupper((string) $k['el']) === $a['el']) {
                        $st['kills'][$i][$a['key']] = max(0, (int) $k[$a['key']] + $a['n']);
                        $found = true;
                    }
                }
                if (!$found && $a['n'] > 0) {
                    $row = ['el' => $a['el'], 'wia' => 0, 'kia' => 0, 'ekia' => 0, 'ewia' => 0];
                    $row[$a['key']] = $a['n'];
                    $st['kills'][] = $row;
                }
                break;
            case 'flash':
                $st['flash'] = ['text' => $a['text'], 'author' => $who, 't' => substr($z, 0, 5), 'at' => $at, 'pending' => true];
                $logLine($st, 'FLASH', $a['text']);
                break;
            case 'flashClear':
                $st['flash'] = null;
                break;
        }

        return $st;
    }

    /**
     * Valeur affichée d'un chrono à l'instant $now : [texte, état RUN | STOP | OVER].
     *
     * @param array<string, mixed> $t
     * @return array{0: string, 1: string}
     */
    public static function timerText(array $t, int $now): array
    {
        $hms = static function (int $v): string {
            $v = abs($v);

            return sprintf('%02d:%02d:%02d', intdiv($v, 3600), intdiv($v % 3600, 60), $v % 60);
        };
        $e = (int) $t['acc'] + ($t['running'] ? max(0, $now - (int) $t['base_at']) : 0);
        $run = $t['running'] ? 'RUN' : 'STOP';
        switch ($t['kind']) {
            case 'DOWN':
                $r = (int) $t['value'] - $e;

                return $r >= 0 ? ['-' . $hms($r), $run] : ['+' . $hms($r), 'OVER'];
            case 'AT':
                $sec = $now % 86400;
                $d = (int) $t['value'] * 60 - $sec;
                if ($d > 43200) {
                    $d -= 86400;
                }
                if ($d < -43200) {
                    $d += 86400;
                }

                return $d > 0 ? ['H-' . $hms($d), 'RUN'] : ['H+' . $hms($d), 'OVER'];
            default:
                return ['+' . $hms($e), $run];
        }
    }

    /**
     * Fil WC1 d'une action : [commande, référence, arguments ASCII]. Textes : accents retirés (le fil est en ASCII).
     *
     * @param array<string, mixed> $a
     * @return array{0: string, 1: string, 2: array<string, scalar>}
     */
    public static function wire(string $act, array $a, string $side, string $webId, string $who): array
    {
        $txt = static fn (string $s, int $max): string => PlanGameMapper::ascii($s, $max);
        $args = ['s' => $side, 'w' => $webId, 'by' => $txt($who, 40)];
        $ref = (string) ($a['id'] ?? '-');
        switch ($act) {
            case 'timerAdd':
                $args += ['n' => $txt($a['name'], 24), 'k' => $a['kind'], 'v' => (int) $a['value']];
                break;
            case 'log':
            case 'flash':
                $args['t'] = $txt($a['text'], 120);
                break;
            case 'exAdd':
                $args += ['ev' => $txt($a['ev'], 60), 'cw' => $txt($a['cw'], 24), 'el' => $txt($a['el'], 24), 'at' => $a['at'] !== '' ? $a['at'] : '-'];
                break;
            case 'reqSet':
                $args += ['st' => $a['status'], 'an' => $a['answer'] !== '' ? $txt($a['answer'], 110) : '-'];
                break;
            case 'kill':
                $args += ['el' => $txt($a['el'], 40), 'k' => $a['key'], 'v' => (int) $a['n']];
                break;
        }

        return [strtolower($act), $ref, $args];
    }
}
