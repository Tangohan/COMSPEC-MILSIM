<?php

declare(strict_types=1);

namespace App\Support;

/**
 * « Service à informer » d'un avis ou d'une fiche de vigilance : instantané du jour de l'émission (le document garde son nom, son
 * sigle, son canal et son délai même si le service est modifié ensuite), et adresse de signalement à jeton opaque.
 *
 * Le service vient du catalogue des services demandeurs (config/sse_wanted_services.php et services de la communauté) ou d'une
 * saisie libre « Autre… » soumise à la liste de refus des noms réels. Coordonnées fictives seulement (ligne 555-01xx, domaine
 * « .invalid ») quand la communauté n'a rien réglé.
 */
final class SseAlertReportTo
{
    public const DEFAULT_DELAY = 'now';

    /** Canal livré quand ni le service ni la communauté n'en donnent : valeurs réservées à la fiction. */
    public const FALLBACK_CHANNEL = 'Ligne 555-0100 · signalement@coordination.invalid';

    /**
     * Instantané du service à informer.
     *
     * @param array<string, mixed> $config réglages de la communauté (SseWantedSettingsRepository::get)
     * @param array{service?: mixed, other?: mixed, channel?: mixed, delay?: mixed} $input code du catalogue, ou nom libre « Autre… »
     * @return array{report: array<string, mixed>, errors: list<string>}
     */
    public static function resolve(array $config, array $input = []): array
    {
        $errors = [];
        $code = strtoupper(SseWantedCatalog::line($input['service'] ?? '', 30));
        $other = SseWantedCatalog::line($input['other'] ?? '', 120);
        $service = null;
        if ($code !== '' && $code !== 'OTHER') {
            $service = SseWantedServices::find($code, $config);
            if ($service === null) {
                $errors[] = 'Service à informer inconnu : choisissez un service de la liste ou « Autre ».';
            }
        } elseif ($other === '') {
            $default = SseWantedServices::defaultCode($config);
            $service = $default !== '' ? SseWantedServices::find($default, $config) : null;
        }
        $delay = isset(SseAlertLevel::DELAYS[(string) ($input['delay'] ?? '')]) ? (string) $input['delay'] : (isset(SseAlertLevel::DELAYS[(string) ($config['report_delay'] ?? '')]) ? (string) $config['report_delay'] : self::DEFAULT_DELAY);
        $channel = SseWantedCatalog::line($input['channel'] ?? '', 200);
        if ($service !== null) {
            $palette = SseWantedServices::palette();
            $report = [
                'code' => (string) $service['code'], 'fr' => (string) $service['fr'], 'en' => (string) $service['en'], 'ac' => (string) $service['ac'],
                'auth_fr' => (string) $service['auth_fr'], 'auth_en' => (string) $service['auth_en'],
                'channel' => $channel !== '' ? $channel : SseWantedServices::contactLine($service),
                'color' => $palette[(string) $service['color']] ?? '#1F3A5F', 'emblem' => (string) $service['emblem'],
                'desc_fr' => (string) ($service['desc_fr'] ?? ''), 'desc_en' => (string) ($service['desc_en'] ?? ''),
                'address' => (string) ($service['print_address'] ?? ''), 'emblem_ref' => (string) ($service['emblem_ref'] ?? ''),
            ];
        } else {
            $name = $other !== '' ? $other : SseWantedCatalog::line($config['contact_label'] ?? SseWantedCatalog::DEFAULTS['contact_label'], 120);
            $report = [
                'code' => '', 'fr' => $name, 'en' => $name, 'ac' => '',
                'auth_fr' => (string) ($config['authority_fr'] ?? SseWantedCatalog::DEFAULTS['authority_fr']), 'auth_en' => (string) ($config['authority_en'] ?? SseWantedCatalog::DEFAULTS['authority_en']),
                'channel' => $channel !== '' ? $channel : SseWantedCatalog::line($config['contact_detail'] ?? '', 200),
                'color' => '#1F3A5F', 'emblem' => 'hex', 'desc_fr' => '', 'desc_en' => '', 'address' => '', 'emblem_ref' => '',
            ];
        }
        if ($report['channel'] === '') {
            $report['channel'] = SseWantedCatalog::line($config['report_channel'] ?? '', 200);
        }
        if ($report['channel'] === '') {
            $report['channel'] = self::FALLBACK_CHANNEL;
        }
        $report['delay'] = $delay;
        foreach ([$report['fr'], $report['channel']] as $text) {
            $hit = SseWantedRealNames::find((string) $text);
            if ($hit !== null) {
                $errors[] = 'Le service à informer ne cite pas d’organisation réelle (« ' . $hit . ' ») : utilisez un service fictif.';
                break;
            }
        }

        return ['report' => $report, 'errors' => $errors];
    }

    /**
     * Instantané relu (document déjà émis) : champs bornés, jamais d'erreur.
     *
     * @return array<string, string>
     */
    public static function read(mixed $stored): array
    {
        $s = is_array($stored) ? $stored : [];
        $line = static fn (string $k, int $max): string => SseWantedCatalog::line($s[$k] ?? '', $max);
        $hex = $line('color', 7);

        return [
            'code' => $line('code', 30), 'fr' => $line('fr', 120), 'en' => $line('en', 120) !== '' ? $line('en', 120) : $line('fr', 120), 'ac' => $line('ac', 16),
            'auth_fr' => $line('auth_fr', 160), 'auth_en' => $line('auth_en', 160), 'channel' => $line('channel', 200),
            'delay' => isset(SseAlertLevel::DELAYS[$line('delay', 8)]) ? $line('delay', 8) : self::DEFAULT_DELAY,
            'color' => preg_match('/^#[0-9A-Fa-f]{6}$/', $hex) === 1 ? strtoupper($hex) : '#1F3A5F', 'emblem' => $line('emblem', 12) !== '' ? $line('emblem', 12) : 'hex',
            'desc_fr' => $line('desc_fr', 300), 'desc_en' => $line('desc_en', 300), 'address' => SseWantedCatalog::text($s['address'] ?? '', 300), 'emblem_ref' => $line('emblem_ref', 60),
        ];
    }

    /** « Nom (SIGLE) » dans la langue. @param array<string, mixed> $report */
    public static function label(array $report, string $lang = 'fr'): string
    {
        $name = (string) ($report[$lang === 'en' ? 'en' : 'fr'] ?? '');
        $ac = (string) ($report['ac'] ?? '');

        return $name !== '' && $ac !== '' ? $name . ' (' . $ac . ')' : $name;
    }

    public static function delayLabel(string $delay, string $lang = 'fr'): string
    {
        return (SseAlertLevel::DELAYS[$delay] ?? SseAlertLevel::DELAYS[self::DEFAULT_DELAY])[$lang === 'en' ? 1 : 0];
    }

    /** Chemin de la page de signalement d'un jeton (sans domaine). */
    public static function path(string $token): string
    {
        return 'atak/sse/signalement/' . $token;
    }

    /** Adresse absolue de la page de signalement, ou vide sans jeton. */
    public static function url(?string $token): string
    {
        if ($token === null || $token === '') {
            return '';
        }

        return function_exists('url') ? (string) url(self::path($token)) : '/' . self::path($token);
    }
}
