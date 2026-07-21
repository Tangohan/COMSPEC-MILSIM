<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Détecte et structure les alertes médicales / bilans WIA issus du canal tchat ATAK (mod Arma).
 */
final class MedicalAlertParser
{
    private const ALERT_PREFIX = 'ALERTE MÉDICALE';
    private const WIA_PREFIX = 'WIA|';

    /**
     * @return array{
     *   is_medical: bool,
     *   kind: string,
     *   severity: string,
     *   call_sign: string,
     *   label: string,
     *   heart_rate: ?int,
     *   blood_pct: ?int,
     *   grid: string,
     *   summary: string
     * }|null
     */
    public static function parse(?string $body): ?array
    {
        $body = trim((string) $body);
        if ($body === '') {
            return null;
        }

        if (str_starts_with($body, self::ALERT_PREFIX) || str_starts_with(mb_strtoupper($body), 'ALERTE MEDICALE')) {
            return self::parseAutoAlert($body);
        }

        if (str_starts_with(mb_strtoupper($body), 'WIA|')) {
            return self::parseWia($body);
        }

        return null;
    }

    public static function isMedicalMessage(?string $body): bool
    {
        return self::parse($body) !== null;
    }

    /**
     * @param array<string, mixed> $chatRow
     * @return array<string, mixed>|null
     */
    public static function enrichChatRow(array $chatRow): ?array
    {
        $parsed = self::parse(isset($chatRow['body']) ? (string) $chatRow['body'] : null);
        if ($parsed === null) {
            return null;
        }

        return array_merge($parsed, [
            'id' => $chatRow['id'] ?? null,
            'author' => (string) ($chatRow['author'] ?? ''),
            'body' => (string) ($chatRow['body'] ?? ''),
            'created_at' => (string) ($chatRow['created_at'] ?? ''),
            'map_id' => isset($chatRow['map_id']) ? (int) $chatRow['map_id'] : null,
        ]);
    }

    /**
     * Libellé métier français pour un état santé unité (extra.health).
     */
    public static function healthLabelFr(?string $health): string
    {
        $x = strtolower(trim((string) $health));
        return match ($x) {
            'ok', 'stable', 'healthy' => 'Opérationnel',
            'wounded', 'injured' => 'Blessé',
            'unconscious' => 'Inconscient',
            'cardiac_arrest', 'cardiac-arrest' => 'Arrêt cardiaque',
            'dead', 'kia' => 'Hors combat',
            'critical', 'incapacitated', 'down' => 'État critique',
            default => $health !== null && $health !== '' ? (string) $health : '',
        };
    }

    public static function isCriticalHealth(?string $health): bool
    {
        $x = strtolower(trim((string) $health));
        return in_array($x, [
            'unconscious',
            'cardiac_arrest',
            'cardiac-arrest',
            'critical',
            'incapacitated',
            'down',
            'dead',
            'kia',
            'wounded',
            'injured',
        ], true);
    }

    public static function isEmergencyHealth(?string $health): bool
    {
        $x = strtolower(trim((string) $health));
        return in_array($x, [
            'unconscious',
            'cardiac_arrest',
            'cardiac-arrest',
            'critical',
            'incapacitated',
            'down',
            'dead',
            'kia',
        ], true);
    }

    /**
     * @return array<string, mixed>
     */
    private static function parseAutoAlert(string $body): array
    {
        $parts = array_map('trim', explode('|', $body));
        // ALERTE MÉDICALE | Indicatif | Libellé | FC=… | Volume sanguin≈…% | Grille …
        $callSign = $parts[1] ?? '';
        $label = $parts[2] ?? 'Assistance médicale';
        $hrRaw = $parts[3] ?? '';
        $bloodRaw = $parts[4] ?? '';
        $gridRaw = $parts[5] ?? '';

        $heartRate = null;
        if (preg_match('/(\d+)/', $hrRaw, $m)) {
            $heartRate = (int) $m[1];
        }
        $bloodPct = null;
        if (preg_match('/(\d+)/', $bloodRaw, $m)) {
            $bloodPct = (int) $m[1];
        }
        $grid = trim(preg_replace('/^Grille\s+/iu', '', $gridRaw) ?? $gridRaw);

        $kind = 'medical_alert';
        $severity = 'urgent';
        $labelLower = mb_strtolower($label);
        if (str_contains($labelLower, 'arrêt cardiaque') || str_contains($labelLower, 'rythme à zéro') || ($heartRate !== null && $heartRate <= 0)) {
            $kind = 'cardiac_arrest';
            $severity = 'critical';
        } elseif (str_contains($labelLower, 'inconscient') || str_contains($labelLower, 'au sol')) {
            $kind = 'unconscious';
            $severity = 'critical';
        }

        $summaryParts = array_filter([
            $callSign !== '' ? $callSign : null,
            $label,
            $heartRate !== null ? ('FC ' . $heartRate) : null,
            $grid !== '' ? ('Grille ' . $grid) : null,
        ]);

        return [
            'is_medical' => true,
            'kind' => $kind,
            'severity' => $severity,
            'call_sign' => $callSign,
            'label' => $label,
            'heart_rate' => $heartRate,
            'blood_pct' => $bloodPct,
            'grid' => $grid,
            'summary' => implode(' — ', $summaryParts),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function parseWia(string $body): array
    {
        $parts = array_map('trim', explode('|', $body));
        // WIA|status|sang≈xx%|FC=yy
        $status = $parts[1] ?? 'Blessé';
        $bloodRaw = $parts[2] ?? '';
        $hrRaw = $parts[3] ?? '';
        $bloodPct = null;
        if (preg_match('/(\d+)/', $bloodRaw, $m)) {
            $bloodPct = (int) $m[1];
        }
        $heartRate = null;
        if (preg_match('/(\d+)/', $hrRaw, $m)) {
            $heartRate = (int) $m[1];
        }

        $label = 'Bilan santé — ' . $status;
        $summary = implode(' — ', array_filter([
            $label,
            $heartRate !== null ? ('FC ' . $heartRate) : null,
            $bloodPct !== null ? ('Sang ≈' . $bloodPct . ' %') : null,
        ]));

        return [
            'is_medical' => true,
            'kind' => 'wia_report',
            'severity' => 'attention',
            'call_sign' => '',
            'label' => $label,
            'heart_rate' => $heartRate,
            'blood_pct' => $bloodPct,
            'grid' => '',
            'summary' => $summary,
        ];
    }
}
