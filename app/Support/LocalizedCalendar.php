<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Noms de mois et de jours dans la langue du lecteur (locale() : fr ou en ; les langues de communauté se posent sur
 * l'anglais). Les dates composées côté PHP (« 12 mars 2026 ») échappaient au dictionnaire d'affichage et restaient en
 * français sur le site anglais.
 */
final class LocalizedCalendar
{
    private const MONTHS = [
        'fr' => ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'],
        'en' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
    ];

    private const MONTHS_SHORT = [
        'fr' => ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'],
        'en' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
    ];

    /** Index 0 = dimanche, comme date('w'). */
    private const DAYS = [
        'fr' => ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'],
        'en' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
    ];

    private const DAYS_SHORT = [
        'fr' => ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'],
        'en' => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
    ];

    public static function lang(): string
    {
        return function_exists('locale') && locale() === 'en' ? 'en' : 'fr';
    }

    /** Mois 1 à 12. */
    public static function month(int $month, bool $short = false): string
    {
        $list = ($short ? self::MONTHS_SHORT : self::MONTHS)[self::lang()];

        return $list[max(0, min(11, $month - 1))];
    }

    /** Jour 0 (dimanche, date('w')) à 6 ; 7 (dimanche ISO, date('N')) accepté. */
    public static function weekday(int $day, bool $short = false): string
    {
        $list = ($short ? self::DAYS_SHORT : self::DAYS)[self::lang()];

        return $list[(($day % 7) + 7) % 7];
    }

    /** « 12 mars 2026 à 14:05 » / « 12 March 2026 at 14:05 ». */
    public static function dateTime(int $day, int $month, string $year, string $time): string
    {
        return $day . ' ' . self::month($month) . ' ' . $year . (self::lang() === 'en' ? ' at ' : ' à ') . $time;
    }
}
