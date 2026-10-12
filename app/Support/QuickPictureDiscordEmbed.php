<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Embed Discord d'une photo Quick Picture (téléphone ATAK) ou d'un cliché du drone (app Drone) :
 * opérateur, grille, carte, date et heure en jeu et réelles, photo affichée dans l'embed.
 * Fiche du cliché (titre, description, SSE, confidentiel) : SSE → image floutée (jointe seulement),
 * confidentiel → aucune image, message, lien de lecture sur Athena et note de confidentialité.
 * Logique pure (testable).
 */
final class QuickPictureDiscordEmbed
{
    public const COLOR = 0x3B82F6;
    public const COLOR_SSE = 0xF59E0B;
    public const COLOR_CONFIDENTIAL = 0xDC2626;

    public const MODE_CLEAR = 'clear';
    public const MODE_SSE = 'sse';
    public const MODE_CONFIDENTIAL = 'confidential';

    public const CONFIDENTIAL_NOTE = 'Cliché confidentiel : image non publiée sur Discord. Lecture sur Athena réservée aux personnels habilités (accès SSE « confidentiel » ou plus), ne pas diffuser.';

    /**
     * Traitement Discord du cliché : confidentiel (aucune image) l'emporte sur SSE (image floutée).
     * Drapeaux de la fiche (COMSPEC Link 2.0.66+) ou, DLL plus ancienne, étiquettes tassées dans la légende.
     *
     * @param array<string, mixed> $meta
     */
    public static function mode(array $meta): string
    {
        $caption = (string) ($meta['caption'] ?? '');
        if (!empty($meta['is_confidential']) || preg_match('/\[CONFIDENTIEL\]/iu', $caption) === 1) {
            return self::MODE_CONFIDENTIAL;
        }
        if (!empty($meta['is_sse']) || trim((string) ($meta['sse_case_ref'] ?? '')) !== '' || preg_match('/\[SSE(?:\s[^\]]*)?\]/iu', $caption) === 1) {
            return self::MODE_SSE;
        }

        return self::MODE_CLEAR;
    }

    /**
     * Cliché pris avec la caméra du drone (fn_dronePhoto : appareil UAV, source « drone », flux drone:<netId>).
     *
     * @param array<string, mixed> $meta
     */
    public static function isDronePhoto(array $meta): bool
    {
        if (strtolower(trim((string) ($meta['photo_source'] ?? ''))) === 'drone') {
            return true;
        }
        if (strtoupper(trim((string) ($meta['device_type'] ?? ''))) === 'DRONE') {
            return true;
        }

        return str_starts_with(strtolower(trim((string) ($meta['feed_id'] ?? ''))), 'drone:');
    }

    /**
     * @param array<string, mixed> $meta données de la photo (author_callsign, unit_name, grid_ref, heading, caption…)
     * @param array{
     *   attachment?:string, image_url?:string, read_url?:string, map_label?:string, world_name?:string, location?:string,
     *   game_date?:string, game_time?:string, game_time_approx?:bool, real_unix?:int, timezone?:string
     * } $ctx
     * @return array<string, mixed>
     */
    public static function build(array $meta, array $ctx): array
    {
        $author = self::clean((string) ($meta['author_callsign'] ?? $meta['author'] ?? ''));
        if ($author === '' || strcasecmp($author, 'Unknown') === 0) {
            $author = 'Opérateur';
        }
        $unit = self::clean((string) ($meta['unit_name'] ?? ''));
        $grid = self::clean((string) ($meta['grid_ref'] ?? ''));
        $heading = isset($meta['heading']) && is_numeric($meta['heading']) ? (float) $meta['heading'] : null;
        $mode = self::mode($meta);
        $drone = self::isDronePhoto($meta);
        // Étiquettes [CONFIDENTIEL] / [SSE dossier] de l'ancienne légende tassée : reprises par le mode.
        $rawCaption = trim((string) preg_replace('/\[(?:CONFIDENTIEL|SSE(?:\s[^\]]*)?)\]\s*/iu', '', (string) ($meta['caption'] ?? '')));
        $photoTitle = self::clean((string) ($meta['title'] ?? ''));
        $description = self::cleanText((string) ($meta['description'] ?? ''));
        $caption = $description !== '' ? $description : self::meaningfulCaption($rawCaption, [$author, $unit, $grid, $photoTitle]);
        if ($heading === null) {
            $heading = self::headingFromCaption($rawCaption);
        }

        $fields = [];
        $fields[] = ['name' => 'Opérateur', 'value' => $author, 'inline' => true];
        $droneName = self::clean((string) ($meta['drone_name'] ?? ''));
        if ($drone && $droneName !== '') {
            $fields[] = ['name' => 'Drone', 'value' => $droneName, 'inline' => true];
        }
        if ($unit !== '' && strcasecmp($unit, $author) !== 0) {
            $fields[] = ['name' => 'Unité', 'value' => $unit, 'inline' => true];
        }
        $pos = [];
        if ($grid !== '') {
            $pos[] = 'Grille ' . $grid;
        }
        if ($heading !== null) {
            $pos[] = 'cap ' . str_pad((string) ((int) round(fmod($heading + 360.0, 360.0)) % 360), 3, '0', STR_PAD_LEFT) . '°';
        }
        if ($pos !== []) {
            $fields[] = ['name' => 'Position', 'value' => implode(' · ', $pos), 'inline' => true];
        }

        $mapLabel = self::clean((string) ($ctx['map_label'] ?? ''));
        $world = self::clean((string) ($ctx['world_name'] ?? ''));
        $zone = $mapLabel !== '' ? $mapLabel : $world;
        if ($world !== '' && $mapLabel !== '' && stripos($mapLabel, $world) === false && stripos($world, $mapLabel) === false) {
            $zone = $mapLabel . ' (' . $world . ')';
        }
        $location = self::clean((string) ($ctx['location'] ?? ''));
        if ($zone !== '' || $location !== '') {
            $fields[] = [
                'name' => 'Zone',
                'value' => $location !== '' && $zone !== '' ? $location . ' — ' . $zone : ($location !== '' ? $location : $zone),
                'inline' => true,
            ];
        }

        $gameDate = self::formatGameDate((string) ($ctx['game_date'] ?? ''));
        $gameTime = self::formatClock((string) ($ctx['game_time'] ?? ''));
        if ($gameDate !== '' || $gameTime !== '') {
            $value = trim($gameDate . ($gameDate !== '' && $gameTime !== '' ? ' à ' : '') . ($gameTime !== '' ? (!empty($ctx['game_time_approx']) ? '≈ ' : '') . $gameTime : ''));
            $fields[] = ['name' => 'Date / heure en jeu', 'value' => $value, 'inline' => true];
        }

        $realUnix = (int) ($ctx['real_unix'] ?? 0);
        if ($realUnix > ReconCapturedAt::MIN_UNIX) {
            $tz = self::timezone((string) ($ctx['timezone'] ?? ''));
            $local = (new \DateTimeImmutable('@' . $realUnix))->setTimezone($tz);
            // <t:…> : Discord affiche l'heure dans le fuseau de chaque lecteur ; le texte entre parenthèses reste lisible partout.
            $fields[] = [
                'name' => 'Date / heure réelle',
                'value' => '<t:' . $realUnix . ':f> (' . $local->format('d/m/Y H:i') . ')',
                'inline' => true,
            ];
        }

        // Téléphone de l'opérateur (IMEI, numéro, modèle et version de l'ATAK…), masqué : spoiler Discord
        // à cliquer pour lire. Pas pour un cliché confidentiel (message, lien et note seulement).
        $ident = self::identLines(is_array($meta['device_ident'] ?? null) ? $meta['device_ident'] : []);
        if ($ident !== '' && $mode !== self::MODE_CONFIDENTIAL) {
            $fields[] = ['name' => $drone ? 'Terminal de contrôle' : 'Téléphone', 'value' => $ident, 'inline' => false];
        }

        $attachment = trim((string) ($ctx['attachment'] ?? ''));
        $imageUrl = trim((string) ($ctx['image_url'] ?? ''));
        if ($mode === self::MODE_SSE) {
            $ref = self::clean((string) ($meta['sse_case_ref'] ?? ''));
            $fields[] = [
                'name' => 'SSE',
                'value' => ($ref !== '' ? 'Dossier ' . $ref : 'Cliché SSE') . ' · '
                    . ($attachment !== '' ? 'image floutée sur Discord, version nette sur Athena' : 'image non jointe, à consulter sur Athena'),
                'inline' => false,
            ];
        }
        if ($mode === self::MODE_CONFIDENTIAL) {
            $readUrl = trim((string) ($ctx['read_url'] ?? ''));
            if ($readUrl !== '' && preg_match('#^https?://#i', $readUrl) === 1) {
                $fields[] = ['name' => 'Lecture sur Athena', 'value' => mb_substr($readUrl, 0, 1000), 'inline' => false];
            }
            $fields[] = ['name' => 'Confidentialité', 'value' => self::CONFIDENTIAL_NOTE, 'inline' => false];
        }

        $label = $drone ? 'Cliché drone' : 'Quick Picture';
        $tag = match ($mode) {
            self::MODE_CONFIDENTIAL => '[CONFIDENTIEL] ',
            self::MODE_SSE => '[SSE] ',
            default => '',
        };
        $embed = [
            'title' => mb_substr($tag . $label . ' — ' . ($photoTitle !== '' ? $photoTitle : $author), 0, 256),
            'color' => match ($mode) {
                self::MODE_CONFIDENTIAL => self::COLOR_CONFIDENTIAL,
                self::MODE_SSE => self::COLOR_SSE,
                default => self::COLOR,
            },
            'fields' => $fields,
            'footer' => ['text' => $drone ? 'Athena · Drone ATAK' : 'Athena · Téléphone ATAK'],
        ];
        if ($caption !== '') {
            $embed['description'] = mb_substr($caption, 0, 1000);
        }
        if ($realUnix > ReconCapturedAt::MIN_UNIX) {
            $embed['timestamp'] = gmdate('Y-m-d\TH:i:s\Z', $realUnix);
        }
        // Confidentiel : jamais d'image. SSE : seulement la copie floutée jointe (le lien public serait net).
        if ($mode === self::MODE_CONFIDENTIAL) {
            return $embed;
        }
        if ($attachment !== '') {
            $embed['image'] = ['url' => 'attachment://' . $attachment];
        } elseif ($mode === self::MODE_CLEAR && $imageUrl !== '' && str_starts_with($imageUrl, 'https://')) {
            $embed['image'] = ['url' => $imageUrl];
        }

        return $embed;
    }

    /**
     * Légende utile seulement : la légende auto du téléphone (« TA1 · 1519 1730 · 146° ») répète
     * l'indicatif, la grille et le cap déjà affichés dans les champs.
     *
     * @param list<string> $known
     */
    public static function meaningfulCaption(string $caption, array $known): string
    {
        $caption = self::clean($caption);
        if ($caption === '') {
            return '';
        }
        $norm = static fn (string $v): string => strtolower((string) preg_replace('/\s+/u', '', $v));
        $knownNorm = array_filter(array_map($norm, $known), static fn (string $v): bool => $v !== '');
        $parts = preg_split('/\s*[·|•]\s*/u', $caption) ?: [$caption];
        $keep = [];
        foreach ($parts as $part) {
            $p = trim($part);
            if ($p === '') {
                continue;
            }
            if (in_array($norm($p), $knownNorm, true)) {
                continue;
            }
            if (preg_match('/^(?:cap\s*)?\d{1,3}(?:[.,]\d+)?\s*°$/iu', $p) === 1) {
                continue;
            }
            if (preg_match('/^\d{3,5}\s+\d{3,5}$/', $p) === 1) {
                continue;
            }
            $keep[] = $p;
        }

        return implode(' · ', $keep);
    }

    public static function headingFromCaption(string $caption): ?float
    {
        if (preg_match('/(?:^|[·|•\s])(\d{1,3}(?:[.,]\d+)?)\s*°/u', $caption, $m) === 1) {
            $v = (float) str_replace(',', '.', $m[1]);

            return $v >= 0 && $v <= 360 ? $v : null;
        }

        return null;
    }

    /** Heure du jour en jeu (heures décimales Arma, ex. 14.5) → « 14:30 ». */
    public static function clockFromDaytime(mixed $daytime): string
    {
        if ($daytime === null || $daytime === '' || !is_numeric($daytime)) {
            return '';
        }
        $h = fmod((float) $daytime, 24.0);
        if ($h < 0) {
            $h += 24.0;
        }
        $minutes = (int) floor($h * 60 + 0.5) % 1440;

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /** « 2035-06-24 », « 2035,6,24 », « [2035,6,24,14,30] » → « 24/06/2035 ». */
    public static function formatGameDate(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (preg_match('/(\d{4})\D+(\d{1,2})\D+(\d{1,2})/', $raw, $m) === 1) {
            $y = (int) $m[1];
            $mo = (int) $m[2];
            $d = (int) $m[3];
            if ($y >= 1900 && $y <= 2200 && checkdate($mo, $d, $y)) {
                return sprintf('%02d/%02d/%04d', $d, $mo, $y);
            }
        }

        return '';
    }

    /** « 14:30 », « 14h30 », « 14.5 » → « 14:30 ». */
    public static function formatClock(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (preg_match('/^(\d{1,2})\s*[:h]\s*(\d{2})/i', $raw, $m) === 1) {
            $h = (int) $m[1];
            $mi = (int) $m[2];

            return $h < 24 && $mi < 60 ? sprintf('%02d:%02d', $h, $mi) : '';
        }
        if (is_numeric($raw)) {
            return self::clockFromDaytime($raw);
        }

        return '';
    }

    /**
     * Lignes masquées (||…||) de l'identité du téléphone.
     *
     * @param array<string, mixed> $ident
     */
    public static function identLines(array $ident): string
    {
        $labels = ['model' => 'Appareil', 'number' => 'Numéro', 'imei' => 'IMEI', 'mac' => 'MAC', 'battery' => 'Batterie'];
        $out = [];
        foreach ($labels as $key => $label) {
            $v = self::clean((string) ($ident[$key] ?? ''));
            if ($v === '') {
                continue;
            }
            // Le texte ne doit pas fermer le spoiler lui-même.
            $v = str_replace('|', '/', $v);
            $out[] = $label . ' ||' . mb_substr($v, 0, 80) . '||';
        }

        return mb_substr(implode("\n", $out), 0, 1000);
    }

    private static function clean(string $v): string
    {
        $v = (string) preg_replace('/[\x00-\x1F\x7F]/u', ' ', $v);
        $v = (string) preg_replace('/@(everyone|here)\b/iu', '', $v);
        $v = trim((string) preg_replace('/\s+/u', ' ', $v));

        return mb_substr($v, 0, 200);
    }

    /** Texte libre multi-lignes (description de la fiche) : retours à la ligne gardés, mentions de masse retirées. */
    private static function cleanText(string $v): string
    {
        $v = str_replace(["\r\n", "\r"], "\n", $v);
        $v = (string) preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/u', ' ', $v);
        $v = (string) preg_replace('/@(everyone|here)\b/iu', '', $v);
        $v = trim((string) preg_replace('/[ \t]+/u', ' ', $v));

        return mb_substr($v, 0, 1000);
    }

    private static function timezone(string $name): \DateTimeZone
    {
        foreach ([$name, date_default_timezone_get(), 'Europe/Paris'] as $candidate) {
            if ($candidate === '') {
                continue;
            }
            try {
                return new \DateTimeZone($candidate);
            } catch (\Throwable) {
            }
        }

        return new \DateTimeZone('UTC');
    }
}
