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

    /** Fenêtre d’affichage des alertes actives (secondes). */
    public const ACTIVE_WINDOW_SECONDS = 30 * 60;

    /** Statuts de triage métier (clés techniques → libellés via triageLabelFr). */
    public const TRIAGE_STATUSES = [
        'a_secourir',
        'en_cours',
        'traite',
        'kia',
        'annule',
    ];

    /** Statuts considérés comme clôturés (badge jusqu’à expiration de la fenêtre). */
    public const TRIAGE_RESOLVED = [
        'traite',
        'kia',
        'annule',
    ];

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

        // Journal radio : [HH:MM:SS][CHANNEL][PRIO][KIND] texte
        $body = self::stripCommsPrefix($body);

        $upperFolded = self::foldMedicalPrefix(mb_strtoupper($body));
        if (str_starts_with($body, self::ALERT_PREFIX)
            || str_starts_with($upperFolded, 'ALERTE MEDICALE')
            || str_starts_with(mb_strtoupper($body), 'ALERTE MEDICALE')) {
            return self::parseAutoAlert($body);
        }

        if (str_starts_with($upperFolded, 'WIA|') || str_starts_with(mb_strtoupper($body), 'WIA|')) {
            return self::parseWia($body);
        }

        // Format journal / Liaison : « Assistance médicale — Indicatif — … — FC … »
        $human = self::parseHumanAssistance($body);
        if ($human !== null) {
            return $human;
        }

        return null;
    }

    /**
     * Retire le préfixe radio jeu s’il est présent.
     */
    private static function stripCommsPrefix(string $body): string
    {
        if (preg_match(
            '/^\[\d{1,2}:\d{2}:\d{2}\]\[[A-Za-z0-9_]+\]\[[A-Za-z0-9_]+\]\[[A-Za-z0-9_]+\]\s*([\s\S]+)$/u',
            $body,
            $m
        )) {
            return trim((string) ($m[1] ?? $body));
        }

        return $body;
    }

    /**
     * Parse le libellé métier affiché dans Liaison / toast
     * (« Assistance médicale — NewPI — Au sol — inconscient — FC 95 — Grille … »).
     *
     * @return array<string, mixed>|null
     */
    private static function parseHumanAssistance(string $body): ?array
    {
        $folded = self::foldMedicalPrefix(mb_strtoupper($body));
        if (!str_starts_with($folded, 'ASSISTANCE MEDICALE')) {
            return null;
        }

        // Découpe sur tirets longs / courts entourés d’espaces.
        $parts = preg_split('/\s*[—–\-]+\s*/u', $body) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts), static fn (string $p): bool => $p !== ''));
        if (count($parts) < 2) {
            return null;
        }

        // parts[0] = « Assistance médicale »
        $callSign = $parts[1] ?? '';
        $fcIdx = null;
        $gridIdx = null;
        foreach ($parts as $i => $p) {
            if ($i < 2) {
                continue;
            }
            if ($fcIdx === null && preg_match('/^FC\s*[=:]?\s*\d+/iu', $p) === 1) {
                $fcIdx = $i;
            }
            if ($gridIdx === null && preg_match('/^Grille\b/iu', $p) === 1) {
                $gridIdx = $i;
            }
        }

        $labelEnd = $fcIdx ?? $gridIdx ?? count($parts);
        $labelParts = array_slice($parts, 2, max(0, $labelEnd - 2));
        $label = $labelParts !== [] ? implode(' — ', $labelParts) : 'Assistance médicale';

        $heartRate = null;
        if ($fcIdx !== null && preg_match('/(\d+)/', $parts[$fcIdx], $m)) {
            $heartRate = (int) $m[1];
        }
        $grid = '';
        if ($gridIdx !== null) {
            $grid = trim(preg_replace('/^Grille\s+/iu', '', $parts[$gridIdx]) ?? $parts[$gridIdx]);
        }

        // Si l’indicatif ressemble à un état (pas un callsign), bascule.
        $csLower = mb_strtolower($callSign);
        if ($callSign !== '' && (
            str_contains($csLower, 'inconscient')
            || str_contains($csLower, 'au sol')
            || str_contains($csLower, 'arrêt')
            || str_contains($csLower, 'arret')
            || str_contains($csLower, 'cardiaque')
        )) {
            $label = $label === 'Assistance médicale'
                ? $callSign
                : ($callSign . ' — ' . $label);
            $callSign = '';
        }

        $kind = 'medical_alert';
        $severity = 'urgent';
        $labelFolded = preg_replace('/[\x{2013}\x{2014}\-]+/u', ' ', mb_strtolower($label . ' ' . $body)) ?? '';
        $isDeath = str_contains($labelFolded, 'arrêt cardiaque')
            || str_contains($labelFolded, 'arret cardiaque')
            || str_contains($labelFolded, 'rythme à zéro')
            || str_contains($labelFolded, 'rythme a zero')
            || str_contains($labelFolded, 'kia')
            || str_contains($labelFolded, 'hors combat')
            || preg_match('/\bmort\b/u', $labelFolded) === 1
            || str_contains($labelFolded, 'dead')
            || preg_match('/\bfc\s*[=:]?\s*0\b/u', $labelFolded) === 1
            || ($heartRate !== null && $heartRate <= 0);
        if ($isDeath) {
            $kind = 'cardiac_arrest';
            $severity = 'critical';
        } elseif (str_contains($labelFolded, 'inconscient') || str_contains($labelFolded, 'au sol')) {
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
            'blood_pct' => null,
            'grid' => $grid,
            'summary' => implode(' — ', $summaryParts),
        ];
    }

    /**
     * Normalise accents / formes Unicode pour détecter « ALERTE MÉDICALE » même en NFD.
     */
    private static function foldMedicalPrefix(string $upper): string
    {
        if (class_exists(\Normalizer::class)) {
            $nfd = \Normalizer::normalize($upper, \Normalizer::FORM_D);
            if (is_string($nfd) && $nfd !== '') {
                $upper = $nfd;
            }
        }
        // Retire les diacritiques combinants (NFD) + variantes courantes.
        $upper = preg_replace('/\p{Mn}+/u', '', $upper) ?? $upper;

        return strtr($upper, [
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ä' => 'A',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Ö' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C',
        ]);
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
     * Opérateur rétabli (conscient) : l’alerte KO / arrêt cardiaque peut être clôturée.
     * Inclut « blessé » encore debout — ce n’est plus une urgence « au sol ».
     */
    public static function isRecoveredHealth(?string $health): bool
    {
        $x = strtolower(trim((string) $health));

        return in_array($x, [
            'ok',
            'stable',
            'healthy',
            'wounded',
            'injured',
        ], true);
    }

    public static function normalizeTriageStatus(?string $status): string
    {
        $x = strtolower(trim((string) $status));
        $aliases = [
            'a_secourir' => 'a_secourir',
            'a-secourir' => 'a_secourir',
            'secourir' => 'a_secourir',
            'open' => 'a_secourir',
            'pending' => 'a_secourir',
            'new' => 'a_secourir',
            'en_cours' => 'en_cours',
            'en-cours' => 'en_cours',
            'encours' => 'en_cours',
            'in_progress' => 'en_cours',
            'exec' => 'en_cours',
            'traite' => 'traite',
            'traité' => 'traite',
            'traitee' => 'traite',
            'traitée' => 'traite',
            'done' => 'traite',
            'resolved' => 'traite',
            'closed' => 'traite',
            'kia' => 'kia',
            'dead' => 'kia',
            'morts' => 'kia',
            'hors_combat' => 'kia',
            'annule' => 'annule',
            'annulé' => 'annule',
            'annulee' => 'annule',
            'annulée' => 'annule',
            'cancelled' => 'annule',
            'canceled' => 'annule',
            'cancel' => 'annule',
        ];
        if (isset($aliases[$x])) {
            return $aliases[$x];
        }

        return in_array($x, self::TRIAGE_STATUSES, true) ? $x : 'a_secourir';
    }

    public static function triageLabelFr(?string $status): string
    {
        return match (self::normalizeTriageStatus($status)) {
            'en_cours' => 'En cours',
            'traite' => 'Traité',
            'kia' => 'KIA',
            'annule' => 'Annulé',
            default => 'À secourir',
        };
    }

    public static function isResolvedTriage(?string $status): bool
    {
        return in_array(self::normalizeTriageStatus($status), self::TRIAGE_RESOLVED, true);
    }

    public static function isValidTriageStatus(?string $status): bool
    {
        $raw = strtolower(trim((string) $status));
        if ($raw === '') {
            return false;
        }
        $normalized = self::normalizeTriageStatus($raw);
        // Si l'entrée brute n'était pas reconnue, normalize renvoie a_secourir par défaut :
        // on accepte seulement si l'entrée était déjà un statut (ou alias) connu.
        if (in_array($raw, self::TRIAGE_STATUSES, true)) {
            return true;
        }
        $knownAliases = [
            'a-secourir', 'secourir', 'open', 'pending', 'new',
            'en-cours', 'encours', 'in_progress', 'exec',
            'traité', 'traitee', 'traitée', 'done', 'resolved', 'closed',
            'dead', 'morts', 'hors_combat',
            'annulé', 'annulee', 'annulée', 'cancelled', 'canceled', 'cancel',
        ];

        return in_array($raw, $knownAliases, true) && in_array($normalized, self::TRIAGE_STATUSES, true);
    }

    /**
     * Alerte encore dans la fenêtre active (basée sur created_at).
     *
     * Les datetime MySQL sont souvent sans fuseau : un écart PHP↔MySQL (UTC vs Europe/Paris)
     * faisait exclure à tort des alertes fraîches de l’onglet Assistances alors qu’elles
     * restaient visibles dans le journal / tchat.
     */
    public static function isWithinActiveWindow(?string $createdAt, ?int $nowTs = null): bool
    {
        $createdAt = trim((string) $createdAt);
        if ($createdAt === '') {
            return true;
        }
        $ts = strtotime($createdAt);
        if ($ts === false) {
            return true;
        }
        $now = $nowTs ?? time();
        $age = $now - $ts;
        // « Dans le futur » → interprétation fuseau ; on garde l’alerte.
        if ($age < 0) {
            return true;
        }
        // Fenêtre métier + marge fuseau (UTC ↔ Europe/Paris été ≈ 2 h).
        $tzGraceSeconds = 3 * 3600;

        return $age < (self::ACTIVE_WINDOW_SECONDS + $tzGraceSeconds);
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
        $posRaw = $parts[6] ?? '';

        $heartRate = null;
        if (preg_match('/(\d+)/', $hrRaw, $m)) {
            $heartRate = (int) $m[1];
        }
        $bloodPct = null;
        if (preg_match('/(\d+)/', $bloodRaw, $m)) {
            $bloodPct = (int) $m[1];
        }
        $grid = trim(preg_replace('/^Grille\s+/iu', '', $gridRaw) ?? $gridRaw);

        $posX = null;
        $posY = null;
        if (preg_match('/POS\s+(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)/iu', $posRaw . ' ' . $body, $pm)) {
            $posX = (float) $pm[1];
            $posY = (float) $pm[2];
        }

        $kind = 'medical_alert';
        $severity = 'urgent';
        $labelLower = mb_strtolower($label);
        $labelFolded = preg_replace('/[\x{2013}\x{2014}\-]+/u', ' ', $labelLower) ?? $labelLower;
        $isDeath = str_contains($labelFolded, 'arrêt cardiaque')
            || str_contains($labelFolded, 'arret cardiaque')
            || str_contains($labelFolded, 'rythme à zéro')
            || str_contains($labelFolded, 'rythme a zero')
            || str_contains($labelFolded, 'kia')
            || str_contains($labelFolded, 'hors combat')
            || preg_match('/\bmort\b/u', $labelFolded) === 1
            || str_contains($labelFolded, 'dead')
            || preg_match('/\bfc\s*[=:]?\s*0\b/u', $labelFolded) === 1
            || ($heartRate !== null && $heartRate <= 0);
        if ($isDeath) {
            $kind = 'cardiac_arrest';
            $severity = 'critical';
        } elseif (str_contains($labelFolded, 'inconscient') || str_contains($labelFolded, 'au sol')) {
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
            'pos_x' => $posX,
            'pos_y' => $posY,
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
