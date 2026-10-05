<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Présentation « matériel réel » des terminaux, certificats et relais ATAK :
 * libellés, empreintes au format X.509, cycle de vie d'un certificat, carroyage, bilan radio.
 * Aucune écriture : uniquement des calculs sur les lignes déjà lues.
 */
final class AtakDevicePresenter
{
    /** Modèle affiché quand le jeu n'a pas encore remonté le sien (visuel du téléphone COMSPEC). */
    public const DEFAULT_PHONE_MODEL = 'Samsung Galaxy · coque tactique';

    /** Motifs de révocation RFC 5280 (§5.3.1), libellés français. */
    public const REVOCATION_REASONS = [
        'keyCompromise' => 'Clé compromise (appareil perdu, capturé ou fouillé)',
        'affiliationChanged' => 'Changement d’affectation de l’opérateur',
        'superseded' => 'Remplacé par un nouveau certificat',
        'cessationOfOperation' => 'Appareil retiré du service',
        'certificateHold' => 'Suspension temporaire',
        'unspecified' => 'Non précisé',
    ];

    /**
     * Le terminal du jeu est le téléphone Android COMSPEC : l'ancien module Connect s'annonçait « tablet ».
     *
     * @param array<string, mixed> $row
     */
    public static function terminalType(array $row): string
    {
        $type = strtolower(trim((string) ($row['terminal_type'] ?? '')));
        if ($type === 'tablet' && self::isGamePlatform((string) ($row['platform_label'] ?? ''))) {
            return 'phone';
        }

        return $type !== '' ? $type : 'phone';
    }

    public static function isGamePlatform(string $platform): bool
    {
        $p = strtolower(trim($platform));

        return $p !== '' && (str_starts_with($p, 'arma 3') || str_contains($p, 'comspec'));
    }

    public static function typeLabel(string $type): string
    {
        return match (strtolower(trim($type))) {
            'phone' => 'Téléphone Android',
            'tablet' => 'Tablette',
            'radio' => 'Radio',
            'vehicle' => 'Véhicule',
            'desktop' => 'Poste Arma 3',
            'web' => 'Session web',
            default => 'Terminal',
        };
    }

    /** « ab12… » → « AB:12:… » (empreinte lisible comme dans un navigateur). */
    public static function colonHex(?string $hex, int $maxBytes = 32): string
    {
        $clean = strtoupper(preg_replace('/[^0-9a-fA-F]/', '', (string) $hex) ?? '');
        if ($clean === '') {
            return '';
        }
        $pairs = str_split(substr($clean, 0, $maxBytes * 2), 2);

        return implode(':', $pairs);
    }

    /** Empreinte courte (8 premiers octets) pour les tableaux. */
    public static function shortFingerprint(?string $hex): string
    {
        $full = self::colonHex($hex, 8);

        return $full === '' ? '' : $full . '…';
    }

    /**
     * Cycle de vie d'un certificat : état, jours restants et part de la validité écoulée.
     *
     * @param array<string, mixed> $cert
     * @return array{state: string, label: string, tone: string, days_left: ?int, elapsed_pct: ?int, from: ?int, to: ?int}
     */
    public static function certificateLifetime(array $cert, ?int $now = null): array
    {
        $now ??= time();
        $status = strtolower(trim((string) ($cert['status'] ?? '')));
        $fromRaw = (string) ($cert['valid_from'] ?? $cert['issued_at'] ?? '');
        $toRaw = (string) ($cert['expires_at'] ?? '');
        $from = self::utcTimestamp($fromRaw);
        $to = self::utcTimestamp($toRaw);

        $daysLeft = $to !== null ? (int) floor(($to - $now) / 86400) : null;
        $elapsed = null;
        if ($from !== null && $to !== null && $to > $from) {
            $elapsed = (int) max(0, min(100, round(($now - $from) * 100 / ($to - $from))));
        }

        if ($status === 'revoked') {
            [$state, $label, $tone] = ['revoked', 'Révoqué', 'bad'];
        } elseif ($to !== null && $to <= $now) {
            [$state, $label, $tone] = ['expired', 'Expiré', 'bad'];
        } elseif ($from !== null && $from > $now) {
            [$state, $label, $tone] = ['pending', 'Pas encore valide', 'muted'];
        } elseif ($daysLeft !== null && $daysLeft <= 30) {
            [$state, $label, $tone] = ['expiring', 'Expire bientôt', 'warn'];
        } elseif ($status === '' && $to === null) {
            [$state, $label, $tone] = ['none', 'Aucun certificat', 'muted'];
        } else {
            [$state, $label, $tone] = ['valid', 'Valide', 'ok'];
        }

        return [
            'state' => $state,
            'label' => $label,
            'tone' => $tone,
            'days_left' => $daysLeft,
            'elapsed_pct' => $elapsed,
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * Dernier certificat joint à une ligne de terminal (colonnes certificate_*), sous la forme d'une ligne de certificat.
     *
     * @param array<string, mixed> $terminal
     * @return array<string, mixed>
     */
    public static function certificateOfTerminal(array $terminal): array
    {
        return [
            'certificate_ref' => $terminal['certificate_ref'] ?? null,
            'authority_label' => $terminal['certificate_authority'] ?? null,
            'fingerprint_sha256' => $terminal['certificate_fingerprint'] ?? null,
            'serial_number' => $terminal['certificate_serial'] ?? null,
            'common_name' => $terminal['certificate_common_name'] ?? null,
            'certificate_type' => $terminal['certificate_type'] ?? null,
            'status' => $terminal['certificate_status'] ?? null,
            'issued_at' => $terminal['certificate_issued_at'] ?? null,
            'valid_from' => $terminal['certificate_valid_from'] ?? null,
            'expires_at' => $terminal['certificate_expires_at'] ?? null,
            'callsign' => $terminal['operator_callsign'] ?? $terminal['callsign'] ?? null,
            'terminal_uid' => $terminal['terminal_uid'] ?? null,
        ];
    }

    /** Date MySQL enregistrée en UTC (UTC_TIMESTAMP / gmdate) ou ISO 8601 → horodatage. */
    public static function utcTimestamp(?string $raw): ?int
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $raw) === 1) {
            $raw .= ' UTC';
        }
        $ts = strtotime($raw);

        return $ts === false ? null : $ts;
    }

    public static function revocationReasonLabel(?string $reason): string
    {
        $r = trim((string) $reason);
        if ($r === '') {
            return '';
        }

        return self::REVOCATION_REASONS[$r] ?? $r;
    }

    /**
     * Sujet X.509 d'un certificat (CN, OU, O) à partir de ce qui est connu.
     *
     * @param array<string, mixed> $cert
     */
    public static function subjectDn(array $cert, string $organisation = 'COMSPEC'): string
    {
        $cn = trim((string) ($cert['common_name'] ?? ''));
        if ($cn === '') {
            $cn = trim((string) ($cert['callsign'] ?? $cert['terminal_uid'] ?? $cert['certificate_ref'] ?? ''));
        }
        $ou = match (strtolower((string) ($cert['certificate_type'] ?? 'device'))) {
            'operator' => 'Opérateurs',
            'gateway' => 'Passerelles',
            'server' => 'Serveurs',
            'test' => 'Essais',
            default => 'Terminaux ATAK',
        };

        return 'CN=' . ($cn !== '' ? $cn : '—') . ', OU=' . $ou . ', O=' . $organisation;
    }

    /**
     * État de liaison d'un appareil d'après son dernier signe de vie.
     *
     * @return array{key: string, label: string, ago: string}
     */
    public static function linkState(?string $seenAt, ?int $now = null): array
    {
        $now ??= time();
        $ts = self::utcTimestamp($seenAt);
        if ($ts === null) {
            return ['key' => 'never', 'label' => 'Jamais vu', 'ago' => ''];
        }
        $ago = max(0, $now - $ts);
        $rel = match (true) {
            $ago < 60 => 'à l’instant',
            $ago < 3600 => 'il y a ' . intdiv($ago, 60) . ' min',
            $ago < 86400 => 'il y a ' . intdiv($ago, 3600) . ' h',
            default => 'il y a ' . intdiv($ago, 86400) . ' j',
        };
        if ($ago <= 180) {
            return ['key' => 'online', 'label' => 'En liaison', 'ago' => $rel];
        }
        if ($ago <= 3600) {
            return ['key' => 'idle', 'label' => 'Veille', 'ago' => $rel];
        }

        return ['key' => 'offline', 'label' => 'Hors liaison', 'ago' => $rel];
    }

    /** Carroyage Arma (100 m) : « 045 112 ». */
    public static function gridRef(mixed $x, mixed $y): string
    {
        if (!is_numeric($x) || !is_numeric($y)) {
            return '—';
        }

        return sprintf('%03d %03d', max(0, (int) floor((float) $x / 100)), max(0, (int) floor((float) $y / 100)));
    }

    /** Puissance d'émission en watts → dBm. */
    public static function wattsToDbm(mixed $watts): ?float
    {
        if (!is_numeric($watts) || (float) $watts <= 0) {
            return null;
        }

        return round(10 * log10((float) $watts * 1000), 1);
    }
}
