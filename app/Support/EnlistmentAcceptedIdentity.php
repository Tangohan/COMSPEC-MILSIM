<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Identité du nouveau membre après acceptation : celle du dossier de candidature,
 * jamais le pseudo de la personne qui a validé.
 */
final class EnlistmentAcceptedIdentity
{
    /**
     * Nom affiché issu du dossier (prénom + nom), vide si le dossier n’en a pas.
     *
     * @param array<string, mixed> $enlistmentRow
     */
    public static function formDisplayName(array $enlistmentRow): string
    {
        $first = self::meaningfulPart((string) ($enlistmentRow['first_name'] ?? ''));
        $last = self::meaningfulPart((string) ($enlistmentRow['last_name'] ?? ''));

        return trim($first . ' ' . $last);
    }

    /**
     * Indicatif du dossier, ou vide.
     *
     * @param array<string, mixed> $enlistmentRow
     */
    public static function formCallsign(array $enlistmentRow): string
    {
        return self::meaningfulPart((string) ($enlistmentRow['callsign'] ?? ''));
    }

    /**
     * @param array<string, mixed>|null $user
     * @return list<string>
     */
    public static function publicLabels(?array $user): array
    {
        if ($user === null) {
            return [];
        }
        $out = [];
        foreach (['display_name', 'callsign'] as $key) {
            $v = self::meaningfulPart((string) ($user[$key] ?? ''));
            if ($v !== '') {
                $out[] = $v;
            }
        }

        return $out;
    }

    public static function sameLabel(string $a, string $b): bool
    {
        $a = self::meaningfulPart($a);
        $b = self::meaningfulPart($b);
        if ($a === '' || $b === '') {
            return false;
        }
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($a, 'UTF-8') === mb_strtolower($b, 'UTF-8');
        }

        return strcasecmp($a, $b) === 0;
    }

    /**
     * @param list<string> $labels
     */
    public static function matchesAny(string $value, array $labels): bool
    {
        foreach ($labels as $label) {
            if (self::sameLabel($value, (string) $label)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Nom affiché à poser sur le compte membre, ou null pour ne rien changer.
     *
     * @param list<string> $reviewerLabels
     */
    public static function displayNameForNewMembership(
        string $currentDisplayName,
        string $formName,
        array $reviewerLabels,
        bool $memberIsReviewer
    ): ?string {
        if ($memberIsReviewer) {
            return null;
        }
        $current = self::meaningfulPart($currentDisplayName);
        $form = self::meaningfulPart($formName);
        if (self::matchesAny($current, $reviewerLabels)) {
            return $form !== '' ? $form : '';
        }
        if ($current === '' && $form !== '') {
            return $form;
        }

        return null;
    }

    /**
     * @param list<string> $reviewerLabels
     */
    public static function shouldClearCharacterName(
        string $characterName,
        array $reviewerLabels,
        bool $memberIsReviewer
    ): bool {
        if ($memberIsReviewer) {
            return false;
        }

        return self::matchesAny($characterName, $reviewerLabels);
    }

    private static function meaningfulPart(string $raw): string
    {
        $v = trim($raw);
        if ($v === '' || $v === '—' || $v === '-') {
            return '';
        }

        return $v;
    }
}
