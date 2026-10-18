<?php

declare(strict_types=1);

namespace App\Services\Atak;

use App\Services\Integrations\DiscordWebhookService;
use App\Services\Security\FileRateLimiter;

/**
 * Check de démarrage du mod ATAK natif (fn_startupCheck, commande DLL StartupCheck) relayé dans le salon Discord
 * de debug de la plateforme : un message court par joueur et par session, lisible par les joueurs (FR puis EN).
 *
 * Salon : webhook DISCORD_DEBUG_WEBHOOK dans le .env du VPS. Sans lui, le rapport est accepté et rien n'est posté.
 * Rien n'est enregistré en base. Aucune adresse IP, aucun UID Steam (le mod n'envoie qu'une empreinte), aucun lien :
 * les textes du mod sont nettoyés (liens, adresses IP, dossier utilisateur Windows, mentions) avant Discord.
 */
final class AtakStartupCheckService
{
    public const ENV_WEBHOOK = 'DISCORD_DEBUG_WEBHOOK';

    /** Étapes connues, dans l'ordre d'affichage : [libellé FR, libellé EN]. */
    public const STEPS = [
        'mod' => ['Mod COMSPEC ATAK', 'Mod'],
        'atak' => ['Téléphone ATAK', 'ATAK phone'],
        'dll' => ['DLL COMSPEC Link', 'Link DLL'],
        'ext' => ['Extension', 'Extension'],
        'athena' => ['Connexion Athena', 'Athena link'],
        'account' => ['Compte Athena', 'Athena account'],
        'link' => ['Liaison en jeu', 'In-game link'],
        'phone' => ['Numéro et IMEI', 'Number and IMEI'],
        'terminal' => ['Terminal', 'Terminal'],
        'update' => ['Mise à jour', 'Update'],
        'deps' => ['CBA · ACE · radio', 'CBA · ACE · radio'],
        'mods' => ['Mods COMSPEC', 'COMSPEC mods'],
        'perf' => ['FPS · ping', 'FPS · ping'],
        'errors' => ['Erreurs au chargement', 'Load errors'],
    ];

    private const ICONS = ['ok' => '✅', 'warn' => '⚠️', 'fail' => '❌', 'off' => '➖'];

    /** Déclencheur envoyé par le mod : [titre FR, titre EN]. « start » : mods d'avant le check à la liaison. */
    public const TRIGGERS = [
        'start' => ['Check de démarrage', 'Startup check'],
        'link' => ['Liaison en jeu', 'In-game link'],
        'link_failed' => ['Liaison impossible', 'Link failed'],
    ];

    /** Même rapport (joueur + session) : un seul message. Au plus 6 par joueur et par heure, 20 par minute en tout. */
    private const DEDUPE_SECONDS = 6 * 3600;
    private const PLAYER_MAX_PER_HOUR = 6;
    private const GLOBAL_MAX_PER_MINUTE = 20;

    /** @var \Closure(string, array<string, mixed>): array{ok:bool, error?:string} */
    private \Closure $sender;

    /**
     * @param (\Closure(string, array<string, mixed>): array{ok:bool, error?:string})|null $sender envoi Discord (tests)
     */
    public function __construct(
        private ?string $webhookUrl = null,
        private FileRateLimiter $limiter = new FileRateLimiter(),
        private DiscordWebhookService $discord = new DiscordWebhookService(),
        ?\Closure $sender = null
    ) {
        $this->sender = $sender ?? fn (string $url, array $embed): array => $this->discord->sendRawEmbed($url, $embed, 'Athena · Debug');
    }

    /**
     * @param array<string, mixed> $body champs envoyés par la DLL (chaînes)
     * @return array{ok:bool, status:string, http:int}
     *   status : posted, duplicate, throttled, no_channel, discord_failed, invalid
     */
    public function handle(array $body): array
    {
        $report = self::normalize($body);
        if ($report === null) {
            return ['ok' => false, 'status' => 'invalid', 'http' => 400];
        }
        $url = $this->webhookUrl ?? self::configuredWebhook();
        if ($url === '' || !$this->discord->isValidWebhookUrl($url)) {
            return ['ok' => true, 'status' => 'no_channel', 'http' => 200];
        }
        $player = $report['player_key'];
        if ($this->limiter->hit('atak_startup:' . $player . ':' . $report['session'], self::DEDUPE_SECONDS) > 1) {
            return ['ok' => true, 'status' => 'duplicate', 'http' => 200];
        }
        if ($this->limiter->hit('atak_startup_player:' . $player, 3600) > self::PLAYER_MAX_PER_HOUR
            || $this->limiter->hit('atak_startup_global', 60) > self::GLOBAL_MAX_PER_MINUTE) {
            return ['ok' => true, 'status' => 'throttled', 'http' => 200];
        }
        $sent = ($this->sender)($url, self::buildEmbed($report));
        if (!($sent['ok'] ?? false)) {
            error_log('[atak/startup-check] Discord : ' . (string) ($sent['error'] ?? 'échec'));

            return ['ok' => false, 'status' => 'discord_failed', 'http' => 502];
        }

        return ['ok' => true, 'status' => 'posted', 'http' => 200];
    }

    public static function configuredWebhook(): string
    {
        return trim((string) (($_ENV[self::ENV_WEBHOOK] ?? null) ?: (getenv(self::ENV_WEBHOOK) ?: '')));
    }

    /**
     * Champs utiles, nettoyés et bornés. null si ce n'est pas un rapport du mod.
     *
     * @param array<string, mixed> $body
     * @return array{atak:string, config:string, link:string, link_expected:string, arma:string, player:string,
     *   callsign:string, player_key:string, session:string, world:string, mission:string, mp:bool, trigger:string,
     *   steps:list<array{id:string, status:string, detail:string}>}|null
     */
    public static function normalize(array $body): ?array
    {
        $str = static fn (string $k, int $max): string => self::clean((string) (is_scalar($body[$k] ?? null) ? $body[$k] : ''), $max);
        $version = static fn (string $k): string => preg_match('/^[0-9][0-9A-Za-z.+-]{0,23}$/', trim((string) (is_scalar($body[$k] ?? null) ? $body[$k] : ''))) === 1
            ? trim((string) $body[$k]) : '';
        $atak = $version('atak');
        if ($atak === '') {
            return null;
        }
        $steps = [];
        foreach (array_keys(self::STEPS) as $id) {
            $raw = $body['step_' . $id] ?? null;
            if (!is_string($raw) || $raw === '') {
                continue;
            }
            [$status, $detail] = array_pad(explode('|', $raw, 2), 2, '');
            $status = strtolower(trim($status));
            if (!isset(self::ICONS[$status])) {
                continue;
            }
            $steps[] = ['id' => $id, 'status' => $status, 'detail' => self::clean($detail, 300)];
        }
        if ($steps === []) {
            return null;
        }
        $key = preg_replace('/[^A-Za-z0-9_-]/', '', (string) (is_scalar($body['player_key'] ?? null) ? $body['player_key'] : ''));
        $session = preg_replace('/[^A-Za-z0-9_-]/', '', (string) (is_scalar($body['session'] ?? null) ? $body['session'] : ''));
        $player = $str('player', 48);
        $trigger = strtolower(trim((string) (is_scalar($body['trigger'] ?? null) ? $body['trigger'] : '')));

        return [
            'atak' => $atak,
            'config' => $version('config'),
            'link' => $version('link'),
            'link_expected' => $version('link_expected'),
            'arma' => $version('arma'),
            'player' => $player !== '' ? $player : 'Joueur',
            'callsign' => $str('callsign', 32),
            'player_key' => substr((string) $key, 0, 64) ?: hash('sha256', 'anon:' . $player),
            'session' => substr((string) $session, 0, 64) ?: 'none',
            'world' => $str('world', 40),
            'mission' => $str('mission', 60),
            'mp' => in_array(strtolower(trim((string) (is_scalar($body['mp'] ?? null) ? $body['mp'] : ''))), ['1', 'true'], true),
            'trigger' => array_key_exists($trigger, self::TRIGGERS) ? $trigger : 'start',
            'steps' => $steps,
        ];
    }

    /**
     * @param array{atak:string, config:string, link:string, link_expected:string, arma:string, player:string,
     *   callsign:string, world:string, mission:string, mp:bool, trigger?:string,
     *   steps:list<array{id:string, status:string, detail:string}>} $report
     * @return array<string, mixed>
     */
    public static function buildEmbed(array $report): array
    {
        $fails = count(array_filter($report['steps'], static fn (array $s): bool => $s['status'] === 'fail'));
        $warns = count(array_filter($report['steps'], static fn (array $s): bool => $s['status'] === 'warn'));
        $icon = $fails > 0 ? '❌' : ($warns > 0 ? '⚠️' : '✅');
        $who = $report['player'] . ($report['callsign'] !== '' ? ' (' . $report['callsign'] . ')' : '');

        $lines = [];
        foreach ($report['steps'] as $step) {
            [$fr, $en] = self::STEPS[$step['id']];
            $label = $fr === $en ? $fr : $fr . ' / ' . $en;
            $lines[] = self::ICONS[$step['status']] . ' **' . $label . '**' . ($step['detail'] !== '' ? ' · ' . $step['detail'] : '');
        }
        if ($fails === 0 && $warns === 0) {
            $summary = 'Tout est bon. · All good.';
        } else {
            $fr = [];
            $en = [];
            if ($fails > 0) {
                $fr[] = $fails . ' erreur' . ($fails > 1 ? 's' : '');
                $en[] = $fails . ' error' . ($fails > 1 ? 's' : '');
            }
            if ($warns > 0) {
                $fr[] = $warns . ' avertissement' . ($warns > 1 ? 's' : '');
                $en[] = $warns . ' warning' . ($warns > 1 ? 's' : '');
            }
            $summary = implode(', ', $fr) . '. · ' . implode(', ', $en) . '.';
        }

        $versions = 'ATAK ' . $report['atak'];
        if ($report['config'] !== '' && $report['config'] !== $report['atak']) {
            $versions .= ' (config ' . $report['config'] . ')';
        }
        $versions .= ' · Link ' . ($report['link'] !== '' ? $report['link'] : '—');
        if ($report['arma'] !== '') {
            $versions .= ' · Arma ' . $report['arma'];
        }
        $where = trim($report['world'] . ($report['mission'] !== '' ? ' · ' . $report['mission'] : ''), ' ·');
        $fields = [
            ['name' => 'Joueur / Player', 'value' => $who, 'inline' => true],
            ['name' => 'Versions', 'value' => $versions, 'inline' => true],
        ];
        if ($where !== '') {
            $fields[] = ['name' => 'Carte / Map', 'value' => $where . ($report['mp'] ? '' : ' (solo)'), 'inline' => true];
        }

        [$titleFr, $titleEn] = self::TRIGGERS[$report['trigger'] ?? 'start'] ?? self::TRIGGERS['start'];

        return [
            'title' => mb_substr($icon . ' ' . $titleFr . ' · ' . $who, 0, 256),
            'description' => mb_substr($summary . "\n\n" . implode("\n", $lines), 0, 4000),
            'color' => $fails > 0 ? 0xE5483A : ($warns > 0 ? 0xF2AB33 : 0x5CC76B),
            'fields' => $fields,
            'footer' => ['text' => $titleFr . ' · ' . $titleEn],
            'timestamp' => gmdate('c'),
        ];
    }

    /**
     * Texte du jeu prêt pour Discord : une ligne, sans lien, sans adresse IP complète, sans dossier utilisateur
     * Windows ni mention, mise en forme Discord neutralisée.
     */
    public static function clean(string $text, int $max): string
    {
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = (string) @mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
        }
        $text = (string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text);
        $text = (string) preg_replace('#\b(?:https?://|www\.)\S+#iu', '[lien retiré]', $text);
        $text = (string) preg_replace('/\b(\d{1,3})\.(\d{1,3})\.\d{1,3}\.\d{1,3}\b/', '$1.$2.x.x', $text);
        $text = (string) preg_replace('#([A-Za-z]:[\\\\/](?:Users|Utilisateurs|Documents and Settings)[\\\\/])[^\\\\/\s]+#iu', '$1…', $text);
        $text = str_replace(['@everyone', '@here'], ["@\u{200B}everyone", "@\u{200B}here"], $text);
        $text = (string) preg_replace('/<[@#][!&]?\d+>/', '', $text);
        $text = addcslashes($text, '*_~`|>');
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return mb_strlen($text) > $max ? rtrim(mb_substr($text, 0, $max - 1)) . '…' : $text;
    }
}
