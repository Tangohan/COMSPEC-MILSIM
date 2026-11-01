<?php

declare(strict_types=1);

namespace App\Services\Platform;

/**
 * Phrases produites à partir de modèles rédactionnels et de données vérifiées.
 */
final class AthenaHeritageNarration
{
    /**
     * @param array<string, scalar|null> $vars
     */
    public static function render(string $templateKey, array $vars = []): string
    {
        $template = (string) __($templateKey);
        if ($template === '' || $template === $templateKey) {
            $template = self::fallback($templateKey);
        }
        foreach ($vars as $key => $value) {
            $template = str_replace(':' . $key, (string) $value, $template);
        }

        return $template;
    }

    /** Jalons « premier … » : clé du jalon => [clé du titre, clé du récit]. */
    private const FIRSTS = [
        'first_arma_operator' => ['site.hist_ms_first_arma_t', 'site.hist_narr_first_arma'],
        'first_authenticated_terminal' => ['site.hist_ms_first_terminal_t', 'site.hist_narr_first_terminal'],
        'first_tactical_relay' => ['site.hist_ms_first_relay_t', 'site.hist_narr_first_relay'],
        'first_atak_message' => ['site.hist_ms_first_atak_msg_t', 'site.hist_narr_first_atak_msg'],
        'first_sse_mission' => ['site.hist_ms_first_sse_t', 'site.hist_narr_first_sse'],
        'first_drone_flight' => ['site.hist_ms_first_drone_t', 'site.hist_narr_first_drone'],
        'first_translation_pack' => ['site.hist_ms_first_translation_t', 'site.hist_narr_first_translation'],
    ];

    /**
     * Titre, récit et valeur d'un jalon réécrits dans la langue du lecteur à partir de sa clé. Les jalons sont
     * enregistrés en base dans la langue du moment de leur détection (le plus souvent le français) : sans cela, la
     * frise anglaise gardait « Première mission SSE ». Un jalon inconnu (saisi à la main) est rendu tel quel.
     *
     * @param array<string, mixed> $m ligne athena_heritage_milestones ou jalon calculé
     * @return array<string, mixed>
     */
    public static function localizeMilestone(array $m): array
    {
        $key = (string) ($m['milestone_key'] ?? $m['key'] ?? '');
        $ctx = $m['context_json'] ?? null;
        if (is_string($ctx)) {
            $ctx = json_decode($ctx, true);
        }
        $ctx = is_array($ctx) ? $ctx : [];
        $texts = null;
        if (preg_match('/^players_(\d+)$/', $key, $mm) === 1) {
            $v = self::formatInt((int) $mm[1]);
            $texts = [__('site.hist_ms_players_title', ['value' => $v]), self::render('site.hist_narr_players', ['value' => $v]),
                __('site.hist_val_operators', ['value' => $v])];
        } elseif (preg_match('/^km_(\d+)$/', $key, $mm) === 1) {
            $v = self::formatInt((int) $mm[1]);
            $texts = [__('site.hist_ms_km_title', ['value' => $v]), self::render('site.hist_narr_km', ['value' => $v]), $v . ' km'];
        } elseif (preg_match('/^playtime_hours_(\d+)$/', $key, $mm) === 1) {
            $v = self::formatInt((int) $mm[1]);
            $texts = [__('site.hist_ms_playtime_title', ['value' => $v]), self::render('site.hist_narr_playtime', ['value' => $v]), $v . ' h'];
        } elseif (isset(self::FIRSTS[$key])) {
            [$t, $n] = self::FIRSTS[$key];
            $texts = [__($t), self::render($n), $m['value_label'] ?? null];
        } elseif ($key === 'first_international_org') {
            $cc = strtoupper(trim((string) ($ctx['country_code'] ?? $m['value_label'] ?? '')));
            $texts = [__('site.hist_ms_first_intl_t'), self::render('site.hist_narr_intl', ['country' => $cc !== '' ? ' (' . $cc . ')' : '']),
                $cc !== '' ? $cc : null];
        } elseif ($key === 'first_ops_50_players') {
            $players = (int) ($ctx['players'] ?? $m['value_numeric'] ?? 50);
            $texts = [__('site.hist_ms_ops_50_t'), self::render('site.hist_narr_ops_50', ['value' => self::formatInt(max(50, $players))]),
                __('site.hist_val_players', ['value' => self::formatInt($players)])];
        } elseif (preg_match('/^version_(\d+)_(\d+)_(\d+)$/', $key, $mm) === 1) {
            [$title, $summary] = self::versionTexts($mm[1] . '.' . $mm[2] . '.' . $mm[3]);
            $texts = [$title, $summary, $m['value_label'] ?? null];
        }
        if ($texts === null || str_starts_with((string) $texts[0], 'site.')) {
            return $m;
        }
        $m['title'] = (string) $texts[0];
        $m['narrative'] = (string) $texts[1];
        $m['value_label'] = $texts[2];

        return $m;
    }

    /**
     * Titre et résumé d'une version majeure dans la langue du lecteur.
     *
     * @return array{0: string, 1: string}
     */
    public static function versionTexts(string $version): array
    {
        $meta = null;
        foreach (\App\Support\AthenaHeritageMajorVersions::catalog() as $entry) {
            if ($entry['version'] === $version) {
                $meta = $entry;
                break;
            }
        }
        $title = $meta !== null ? (string) __($meta['title_key']) : '';
        if ($title === '' || str_starts_with($title, 'site.')) {
            $title = 'ATHENA ' . $version;
        }
        $summary = $meta !== null ? (string) __($meta['summary_key']) : '';
        if ($summary === '' || str_starts_with($summary, 'site.')) {
            $summary = self::render('site.hist_narr_version', ['version' => $version]);
        }

        return [$title, $summary];
    }

    /** Nombre avec le séparateur de milliers de la langue du lecteur (10 000 / 10,000). */
    public static function formatInt(int|float $n): string
    {
        $en = function_exists('locale') && locale() === 'en';

        return number_format((int) $n, 0, $en ? '.' : ',', $en ? ',' : "\u{202F}");
    }

    private static function fallback(string $key): string
    {
        return match ($key) {
            'site.hist_narr_players' => 'Avec :value opérateurs enregistrés, ATHENA franchit une nouvelle étape de son déploiement international.',
            'site.hist_narr_km' => 'Les communautés COMSPEC ont cumulé :value km parcourus sur le terrain virtuel.',
            'site.hist_narr_playtime' => 'Les opérateurs ont cumulé :value heures de jeu sur ATHENA.',
            'site.hist_narr_intl' => 'Première organisation internationale enregistrée:country.',
            'site.hist_narr_first_arma' => 'Premier opérateur connecté depuis Arma 3.',
            'site.hist_narr_first_terminal' => 'Premier terminal authentifié sur le réseau ATHENA.',
            'site.hist_narr_first_relay' => 'Premier relais tactique déployé.',
            'site.hist_narr_first_atak_msg' => 'Premier message ATAK transmis.',
            'site.hist_narr_first_sse' => 'Première mission SSE ouverte.',
            'site.hist_narr_first_drone' => 'Premier vol de drone enregistré.',
            'site.hist_narr_first_translation' => 'Premier pack de traduction publié.',
            'site.hist_narr_ops_50' => 'Première opération réunissant :value joueurs.',
            'site.hist_narr_version' => 'Publication de la version majeure :version — jalon du patrimoine COMSPEC.',
            default => 'Événement ATHENA enregistré.',
        };
    }
}
