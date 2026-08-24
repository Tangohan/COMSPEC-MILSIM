<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Règles métier des échéances roleplay (entretien, médical, rotation).
 */
final class RoleplayDeadlinePolicy
{
    /** @var list<string> */
    public const BLOOD_TYPES = ['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'Inconnu'];

    /** @var array<string, string> */
    public const ROTATION_KINDS = [
        'service' => 'Service',
        'advancement' => 'Avancement',
        'training' => 'Formation',
        'evaluation' => 'Notation',
    ];

    public static function normalizeBloodType(string $raw): string
    {
        $v = strtoupper(trim($raw));
        $v = str_replace(['POSITIF', 'POSITIVE', 'POS.', 'POS'], '+', $v);
        $v = str_replace(['NEGATIF', 'NEGATIVE', 'NEG.', 'NEG'], '-', $v);
        $v = preg_replace('/\s+/', '', $v) ?? $v;
        $v = str_replace(['PLUS'], '+', $v);
        $v = str_replace(['MOINS'], '-', $v);

        $map = [
            'O+' => 'O+', '0+' => 'O+', '1' => 'O+',
            'O-' => 'O-', '0-' => 'O-', '0' => 'O-',
            'A+' => 'A+', '3' => 'A+',
            'A-' => 'A-', '2' => 'A-',
            'B+' => 'B+', '5' => 'B+',
            'B-' => 'B-', '4' => 'B-',
            'AB+' => 'AB+', '7' => 'AB+',
            'AB-' => 'AB-', '6' => 'AB-',
            'INCONNU' => 'Inconnu', 'UNKNOWN' => 'Inconnu', '?' => 'Inconnu',
        ];
        if (isset($map[$v])) {
            return $map[$v];
        }
        if (in_array($raw, self::BLOOD_TYPES, true)) {
            return $raw;
        }

        return '';
    }

    public static function bloodTypeChanged(?string $previous, string $current): bool
    {
        $prev = self::normalizeBloodType((string) $previous);
        $cur = self::normalizeBloodType($current);
        if ($cur === '' || $cur === 'Inconnu') {
            return false;
        }
        if ($prev === '' || $prev === 'Inconnu') {
            return true;
        }

        return $prev !== $cur;
    }

    /**
     * Le bilan médical doit (re)confirmer le groupe si le dossier, Arma ou le dernier
     * constat ne sont pas alignés.
     */
    public static function bloodTypeNeedsConfirmation(?string $dossier, ?string $confirmed, ?string $arma): bool
    {
        $d = self::normalizeBloodType((string) $dossier);
        $c = self::normalizeBloodType((string) $confirmed);
        $a = self::normalizeBloodType((string) $arma);
        if ($d === '' && $c === '' && $a === '') {
            return true;
        }
        $known = array_values(array_filter([$d, $c, $a], static fn (string $v): bool => $v !== '' && $v !== 'Inconnu'));
        if ($known === []) {
            return true;
        }
        $first = $known[0];
        foreach ($known as $item) {
            if ($item !== $first) {
                return true;
            }
        }

        return $c === '';
    }

    public static function suggestedBloodType(?string $dossier, ?string $confirmed, ?string $arma): string
    {
        foreach ([$arma, $dossier, $confirmed] as $candidate) {
            $n = self::normalizeBloodType((string) $candidate);
            if ($n !== '') {
                return $n;
            }
        }

        return '';
    }

    public static function normalizeRotationKind(?string $raw): string
    {
        $v = strtolower(trim((string) $raw));
        $aliases = [
            'service' => 'service',
            'rotation' => 'service',
            'advancement' => 'advancement',
            'avancement' => 'advancement',
            'training' => 'training',
            'formation' => 'training',
            'evaluation' => 'evaluation',
            'notation' => 'evaluation',
            'grading' => 'evaluation',
        ];

        return $aliases[$v] ?? 'service';
    }

    public static function rotationKindLabel(string $kind): string
    {
        $n = self::normalizeRotationKind($kind);

        return self::ROTATION_KINDS[$n] ?? self::ROTATION_KINDS['service'];
    }

    /**
     * Un entretien réalisé est exigé avant chaque nouvelle rotation
     * (y compris après une rotation déjà effectuée).
     */
    public static function canProceedWithRotation(?string $interviewCompletedAt, ?string $rotationCompletedAt): bool
    {
        $interviewTs = self::timestamp($interviewCompletedAt);
        if ($interviewTs === null) {
            return false;
        }
        $rotationTs = self::timestamp($rotationCompletedAt);
        if ($rotationTs === null) {
            return true;
        }

        return $interviewTs > $rotationTs;
    }

    private static function timestamp(?string $raw): ?int
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        $ts = strtotime($raw);

        return $ts !== false ? $ts : null;
    }
}
