<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Dates d'une campagne de fiabilisation : ouverture annuelle automatique (jour et mois configurables, 1er décembre
 * par défaut), date limite (31 décembre par défaut) et rappels (J-14, J-3, jour J). Calcul pur, sans base ni horloge :
 * l'appelant fournit « aujourd'hui » (AAAA-MM-JJ).
 */
final class DataCampaignCalendar
{
    public const DEFAULT_OPEN_MD = '12-01';
    public const DEFAULT_CLOSE_MD = '12-31';

    /** Jours avant la date limite où un rappel part (0 = le jour de la date limite). */
    public const DEFAULT_REMINDER_DAYS = [14, 3, 0];

    /** Un rappel n'est plus envoyé s'il a plus de ce nombre de jours de retard sur sa date. */
    public const LATE_GRACE_DAYS = 1;

    /** « MM-JJ » valide, sinon le défaut. */
    public static function normalizeMd(string $raw, string $default): string
    {
        $raw = trim($raw);
        if (preg_match('/^(\d{1,2})-(\d{1,2})$/', $raw, $m) !== 1) {
            return $default;
        }
        $month = (int) $m[1];
        $day = (int) $m[2];
        if ($month < 1 || $month > 12 || $day < 1 || $day > 31 || !checkdate($month, $day, 2024)) {
            return $default;
        }

        return sprintf('%02d-%02d', $month, $day);
    }

    /** AAAA-MM-JJ d'une date « MM-JJ » dans une année (29 février : 28 les années non bissextiles). */
    public static function dateIn(int $year, string $md): string
    {
        $parts = explode('-', $md);
        $month = (int) ($parts[0] ?? 1);
        $day = (int) ($parts[1] ?? 1);
        if (!checkdate($month, $day, $year)) {
            $day = (int) date('t', (int) mktime(0, 0, 0, max(1, min(12, $month)), 1, $year));
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * Fenêtre automatique de l'année : ouverture et date limite. Si la limite tombe avant l'ouverture dans la même
     * année, elle est reportée à l'année suivante.
     *
     * @return array{opens_on: string, due_on: string}
     */
    public static function window(int $year, string $openMd, string $closeMd): array
    {
        $open = self::normalizeMd($openMd, self::DEFAULT_OPEN_MD);
        $close = self::normalizeMd($closeMd, self::DEFAULT_CLOSE_MD);
        $opens = self::dateIn($year, $open);
        $due = self::dateIn($year, $close);
        if ($due < $opens) {
            $due = self::dateIn($year + 1, $close);
        }

        return ['opens_on' => $opens, 'due_on' => $due];
    }

    /** Faut-il ouvrir la campagne annuelle automatique aujourd'hui ? (dans la fenêtre, avant la date limite) */
    public static function shouldAutoOpen(string $today, int $year, string $openMd, string $closeMd): bool
    {
        $w = self::window($year, $openMd, $closeMd);

        return $today >= $w['opens_on'] && $today <= $w['due_on'];
    }

    /**
     * Jours de rappel valides : entiers de 0 à 60, sans doublon, du plus lointain au plus proche.
     *
     * @return list<int>
     */
    public static function normalizeReminderDays(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : (preg_split('/[^0-9]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: []);
        }
        if (!is_array($raw)) {
            return self::DEFAULT_REMINDER_DAYS;
        }
        $days = [];
        foreach ($raw as $d) {
            if (is_numeric($d) && (int) $d >= 0 && (int) $d <= 60) {
                $days[(int) $d] = (int) $d;
            }
        }
        $out = array_values($days);
        rsort($out);

        return $out === [] ? self::DEFAULT_REMINDER_DAYS : $out;
    }

    /** Nom stable du rappel (clé d'unicité) : « j14 », « j3 », « j0 ». */
    public static function slot(int $daysBefore): string
    {
        return 'j' . max(0, $daysBefore);
    }

    /**
     * Rappels à envoyer aujourd'hui : ceux dont la date est atteinte, pas trop en retard, et pas antérieure à
     * l'ouverture de la campagne (une campagne ouverte à J-10 n'envoie pas le rappel de J-14).
     *
     * @param list<int> $days
     * @param list<string> $alreadyDone slots déjà traités
     * @return list<string> slots à envoyer, du plus lointain au plus proche
     */
    public static function dueSlots(string $today, string $openedOn, string $dueOn, array $days, array $alreadyDone): array
    {
        $out = [];
        $due = \DateTimeImmutable::createFromFormat('!Y-m-d', $dueOn);
        if ($due === false) {
            return [];
        }
        foreach ($days as $d) {
            $slot = self::slot($d);
            if (in_array($slot, $alreadyDone, true)) {
                continue;
            }
            $onDay = $due->modify('-' . $d . ' days');
            $on = $onDay->format('Y-m-d');
            if ($on < $openedOn || $today < $on) {
                continue;
            }
            if ($today > $onDay->modify('+' . self::LATE_GRACE_DAYS . ' days')->format('Y-m-d')) {
                continue;
            }
            $out[] = $slot;
        }

        return $out;
    }
}
