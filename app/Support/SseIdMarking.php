<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Marquage « document de jeu » des images exportées par l'atelier de papiers.
 *
 * Trois modes, choisis par communauté (sse_file_settings.settings_json, clé papers_marking) :
 * - discreet (défaut) : micro-texte en bas de chaque face, faible contraste, lisible à l'agrandissement ;
 * - standard : ancien rendu (filigrane diagonal SIMULATION et bandeau pleine largeur) ;
 * - none : aucune marque visible, mention conservée dans les métadonnées du fichier. Refusé pour les pays réels
 *   stylisés (real_sim) : ceux-là retombent sur « discreet » quoi que dise le réglage.
 *
 * La mention figure dans tous les cas dans les métadonnées (PNG tEXt, voir pngWithMention()).
 */
final class SseIdMarking
{
    public const DISCREET = 'discreet';
    public const STANDARD = 'standard';
    public const NONE = 'none';
    public const MODES = [self::DISCREET, self::STANDARD, self::NONE];

    /** Mention discrète incrustée dans l'image et dans les métadonnées. */
    public const MENTION = 'Document de jeu – sans valeur · Game document – no value';

    /** Même mention pour les métadonnées (PNG tEXt est en Latin-1 : tiret simple). */
    public const META = 'Document de jeu - sans valeur / Game document - no value';

    public static function normalize(mixed $mode): string
    {
        $m = is_string($mode) ? $mode : '';

        return in_array($m, self::MODES, true) ? $m : self::DISCREET;
    }

    /** Mode réellement appliqué : un pays réel stylisé n'est jamais sans marquage visible. */
    public static function effective(mixed $setting, bool $realCountry): string
    {
        $mode = self::normalize($setting);

        return $realCountry && $mode === self::NONE ? self::DISCREET : $mode;
    }

    /** Le réglage « aucun marquage » est-il possible pour ce pays ? */
    public static function canDisableVisible(bool $realCountry): bool
    {
        return !$realCountry;
    }

    /** Insère un bloc tEXt (Description, Comment, Copyright) dans un PNG, juste après l'IHDR. */
    public static function pngWithMention(string $png): string
    {
        if (strlen($png) < 33 || !str_starts_with($png, "\x89PNG\r\n\x1a\n")) {
            return $png;
        }
        $chunks = '';
        foreach (['Description' => self::META, 'Comment' => self::META, 'Source' => 'COMSPEC Athena - atelier de papiers fictifs'] as $key => $text) {
            $data = $key . "\0" . self::latin1($text);
            $chunks .= pack('N', strlen($data)) . 'tEXt' . $data . pack('N', crc32('tEXt' . $data));
        }

        return substr($png, 0, 33) . $chunks . substr($png, 33);
    }

    public static function hasMention(string $png): bool
    {
        return str_contains($png, "tEXt\x44escription\0" . self::latin1(self::META));
    }

    private static function latin1(string $s): string
    {
        return (string) mb_convert_encoding($s, 'ISO-8859-1', 'UTF-8');
    }
}
