<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Normalisation des traces d’appareil (même contenu que le journal AppData Overwatch).
 */
final class AtakDeviceLog
{
    public const LEVEL_ERROR = 'error';
    public const LEVEL_WARN = 'warn';
    public const LEVEL_INFO = 'info';
    public const LEVEL_DEBUG = 'debug';

    public const SOURCE_MOD = 'mod';
    public const SOURCE_WEB = 'web';
    public const SOURCE_SYSTEM = 'system';

    /** @var list<string> */
    public const LEVELS = [
        self::LEVEL_ERROR,
        self::LEVEL_WARN,
        self::LEVEL_INFO,
        self::LEVEL_DEBUG,
    ];

    public static function normalizeLevel(string $raw): string
    {
        $key = strtoupper(trim($raw));

        return match ($key) {
            'ERROR', 'ERR', 'FATAL' => self::LEVEL_ERROR,
            'WARN', 'WARNING', 'ALERT' => self::LEVEL_WARN,
            'DEBUG', 'TRACE', 'DETAIL' => self::LEVEL_DEBUG,
            'BUG' => self::LEVEL_ERROR,
            default => self::LEVEL_INFO,
        };
    }

    public static function levelLabel(string $level): string
    {
        return match (self::normalizeLevel($level)) {
            self::LEVEL_ERROR => 'Erreur',
            self::LEVEL_WARN => 'Alerte',
            self::LEVEL_DEBUG => 'Détail',
            default => 'Information',
        };
    }

    public static function channelLabel(string $channel): string
    {
        $key = strtolower(trim($channel));

        return match ($key) {
            'etat', 'state', 'link', 'liaison' => 'Liaison',
            'boot', 'core' => 'Démarrage',
            'athena' => 'Athena',
            'zeus' => 'Zeus',
            'medical', 'med' => 'Santé',
            'terminal', 'device' => 'Appareil',
            'ace' => 'Accessoires',
            'compat' => 'Compatibilité',
            'markers' => 'Marqueurs',
            'tracking' => 'Suivi',
            'explosives' => 'Explosifs',
            'web', 'carte' => 'Carte web',
            default => (trim($channel) !== '' ? trim($channel) : 'Système'),
        };
    }

    public static function clip(string $value, int $max): string
    {
        $value = trim($value);
        if ($max < 1 || mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max);
    }

    /**
     * Découpe une ligne AppData `[COMSPEC Overwatch][INFO][Boot] message | détail`.
     *
     * @return array{level:string,channel:string,message:string,detail:string}
     */
    public static function parseAppDataLine(string $line): array
    {
        $line = trim($line);
        $level = self::LEVEL_INFO;
        $channel = 'Core';
        $message = $line;
        $detail = '';

        if (preg_match('/\[COMSPEC(?:\s+Overwatch)?\]\[([^\]]+)\]\[([^\]]+)\]\s*(.*)$/i', $line, $m) === 1) {
            $level = self::normalizeLevel((string) $m[1]);
            $channel = trim((string) $m[2]);
            $rest = (string) $m[3];
            $parts = preg_split('/\s+\|\s+/', $rest, 2);
            $message = trim((string) ($parts[0] ?? $rest));
            $detail = trim((string) ($parts[1] ?? ''));
        }

        if ($channel === '') {
            $channel = 'Core';
        }

        return [
            'level' => $level,
            'channel' => $channel,
            'message' => $message,
            'detail' => $detail,
        ];
    }

    /**
     * @param array<string, mixed> $raw
     * @return array{
     *   level:string,
     *   channel:string,
     *   message:string,
     *   detail:string,
     *   raw_line:string,
     *   logged_at:?string
     * }|null
     */
    public static function normalizeLine(array $raw): ?array
    {
        $line = self::clip((string) ($raw['line'] ?? $raw['raw'] ?? $raw['raw_line'] ?? ''), 1024);
        $message = self::clip((string) ($raw['message'] ?? $raw['msg'] ?? ''), 512);
        $detail = self::clip((string) ($raw['detail'] ?? $raw['detail_text'] ?? ''), 2000);
        $channel = self::clip((string) ($raw['channel'] ?? $raw['module'] ?? ''), 64);
        $levelRaw = trim((string) ($raw['level'] ?? $raw['severity'] ?? ''));

        if ($message === '' && $line !== '') {
            $parsed = self::parseAppDataLine($line);
            $levelRaw = $levelRaw !== '' ? $levelRaw : $parsed['level'];
            $channel = $channel !== '' ? $channel : $parsed['channel'];
            $message = $parsed['message'];
            if ($detail === '') {
                $detail = $parsed['detail'];
            }
        }

        $message = self::clip($message, 512);
        if ($message === '') {
            return null;
        }

        $loggedAt = trim((string) ($raw['logged_at'] ?? $raw['ts'] ?? $raw['at'] ?? ''));
        if ($loggedAt !== '' && strtotime($loggedAt) === false) {
            $loggedAt = '';
        }

        return [
            'level' => self::normalizeLevel($levelRaw !== '' ? $levelRaw : self::LEVEL_INFO),
            'channel' => $channel !== '' ? $channel : 'Core',
            'message' => $message,
            'detail' => self::clip($detail, 2000),
            'raw_line' => $line,
            'logged_at' => $loggedAt !== '' ? $loggedAt : null,
        ];
    }
}
