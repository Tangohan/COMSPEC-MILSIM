<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Core\Database;
use App\Services\Audit\AuditAction;
use PDO;

/**
 * « Mon compte » : dernières actions sensibles du compte, lues dans audit_logs
 * (connexions, déconnexions, mot de passe, double vérification, liaison Steam).
 * Lecture seule ; ne bloque jamais la page si la table manque.
 */
final class AccountActivityService
{
    /** @var array<string, array{label: string, tone: string}> */
    private const ACTIONS = [
        AuditAction::AUTH_LOGIN_SUCCESS => ['label' => 'Connexion', 'tone' => 'ok'],
        AuditAction::AUTH_LOGOUT => ['label' => 'Déconnexion', 'tone' => 'muted'],
        AuditAction::AUTH_PASSWORD_CHANGED => ['label' => 'Mot de passe modifié', 'tone' => 'warn'],
        AuditAction::AUTH_PASSWORD_RESET_COMPLETED => ['label' => 'Mot de passe réinitialisé', 'tone' => 'warn'],
        AuditAction::AUTH_SESSIONS_REVOKED => ['label' => 'Autres sessions fermées', 'tone' => 'warn'],
        AuditAction::AUTH_TOTP_ENABLED => ['label' => 'Application d’authentification activée', 'tone' => 'ok'],
        AuditAction::AUTH_TOTP_DISABLED => ['label' => 'Application d’authentification désactivée', 'tone' => 'warn'],
        AuditAction::AUTH_EMAIL_LOGIN_OTP_TOGGLED => ['label' => 'Code par e-mail modifié', 'tone' => 'muted'],
        AuditAction::USER_STEAM_LINKED => ['label' => 'Compte Steam lié', 'tone' => 'ok'],
    ];

    public function __construct(private ?PDO $pdo = null)
    {
    }

    /**
     * @return list<array{label: string, tone: string, when: string, device: string, ip: string}>
     */
    public function recentForUser(int $userId, string $timezone = 'Europe/Paris', int $limit = 8): array
    {
        if ($userId < 1) {
            return [];
        }
        try {
            $pdo = $this->pdo ?? Database::getPdo();
            $actions = array_keys(self::ACTIONS);
            $in = implode(',', array_fill(0, count($actions), '?'));
            $stmt = $pdo->prepare(
                "SELECT action, ip, user_agent, created_at FROM audit_logs
                 WHERE user_id = ? AND action IN ($in)
                 ORDER BY id DESC LIMIT " . max(1, min(30, $limit))
            );
            $stmt->execute(array_merge([$userId], $actions));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $meta = self::ACTIONS[(string) ($row['action'] ?? '')] ?? null;
            if ($meta === null) {
                continue;
            }
            $out[] = [
                'label' => $meta['label'],
                'tone' => $meta['tone'],
                'when' => self::formatWhen((string) ($row['created_at'] ?? ''), $timezone),
                'device' => self::deviceLabel((string) ($row['user_agent'] ?? '')),
                'ip' => self::maskIp((string) ($row['ip'] ?? '')),
            ];
        }

        return $out;
    }

    public static function deviceLabel(string $userAgent): string
    {
        $ua = trim($userAgent);
        if ($ua === '') {
            return 'Appareil inconnu';
        }
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Chrome/') || str_contains($ua, 'CriOS/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => '',
        };
        $os = match (true) {
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => '',
        };
        if ($browser === '' && $os === '') {
            return 'Autre appareil';
        }

        return trim($browser . ($browser !== '' && $os !== '' ? ' sur ' : '') . $os);
    }

    /** Adresse IP tronquée : assez pour reconnaître son réseau, pas plus. */
    public static function maskIp(string $ip): string
    {
        $ip = trim($ip);
        if ($ip === '') {
            return '—';
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $p = explode('.', $ip);

            return $p[0] . '.' . $p[1] . '.•.•';
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $p = explode(':', $ip);

            return ($p[0] ?? '') . ':' . ($p[1] ?? '') . ':…';
        }

        return '—';
    }

    private static function formatWhen(string $raw, string $timezone): string
    {
        if ($raw === '' || $raw === '0000-00-00 00:00:00') {
            return '—';
        }
        try {
            $dt = (new \DateTimeImmutable($raw))->setTimezone(new \DateTimeZone($timezone !== '' ? $timezone : 'Europe/Paris'));
        } catch (\Throwable) {
            return $raw;
        }

        return $dt->format('d/m/Y à H:i');
    }
}
