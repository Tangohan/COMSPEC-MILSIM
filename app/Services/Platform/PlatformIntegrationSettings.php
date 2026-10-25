<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Repositories\PlatformSettingsRepository;
use App\Support\DiscordChannelInput;

/**
 * Salons Discord propres à la plateforme (pas à une communauté), réglés depuis Administration › Intégrations de la plateforme
 * par l'administrateur de la plateforme et enregistrés en base (platform_settings) : plus aucune clé à mettre
 * dans le .env du VPS.
 *
 * - mod_reports : tickets des signalements du mod pour l'équipe de développement (salon forum de préférence).
 * - startup_check : copie de tous les checks de démarrage du mod, toutes communautés confondues.
 *
 * Ancien réglage .env (DISCORD_MOD_REPORTS_WEBHOOK, DISCORD_DEBUG_WEBHOOK) : lu seulement si rien n'est enregistré ici.
 */
final class PlatformIntegrationSettings
{
    public const MOD_REPORTS = 'mod_reports';
    public const STARTUP_CHECK = 'startup_check';

    /** Clé platform_settings et ancienne variable .env de chaque salon. */
    public const CHANNELS = [
        self::MOD_REPORTS => ['setting' => 'discord.mod_reports_webhook', 'env' => 'DISCORD_MOD_REPORTS_WEBHOOK'],
        self::STARTUP_CHECK => ['setting' => 'discord.startup_check_webhook', 'env' => 'DISCORD_DEBUG_WEBHOOK'],
    ];

    /** @var array<string, string> lecture de la requête courante */
    private static array $cache = [];

    /**
     * @param (\Closure(string): string)|null $read lecture platform_settings (tests)
     * @param (\Closure(array<string, string>): void)|null $write écriture platform_settings (tests)
     */
    public function __construct(
        private ?\Closure $read = null,
        private ?\Closure $write = null
    ) {
    }

    /** Lien webhook actif du salon : réglage enregistré, sinon ancienne variable .env, sinon ''. */
    public static function webhook(string $channel): string
    {
        if (!isset(self::CHANNELS[$channel])) {
            return '';
        }
        if (!array_key_exists($channel, self::$cache)) {
            $self = new self();
            $saved = $self->saved($channel);
            self::$cache[$channel] = $saved !== '' ? $saved : self::envValue(self::CHANNELS[$channel]['env']);
        }

        return self::$cache[$channel];
    }

    /**
     * État affichable de chaque salon : lien enregistré, et si l'ancien .env sert encore.
     *
     * @return array<string, array{url:string, from_env:bool}>
     */
    public function state(): array
    {
        $out = [];
        foreach (self::CHANNELS as $channel => $meta) {
            $saved = $this->saved($channel);
            $env = self::envValue($meta['env']);
            $out[$channel] = [
                'url' => $saved,
                'from_env' => $saved === '' && $env !== '',
            ];
        }

        return $out;
    }

    /**
     * @param array<string, string> $input lien par salon ('' = aucun salon)
     * @return array{ok:bool, message:string}
     */
    public function save(array $input): array
    {
        $labels = [
            self::MOD_REPORTS => 'Signalements du mod',
            self::STARTUP_CHECK => 'Checks de démarrage',
        ];
        $pairs = [];
        foreach (self::CHANNELS as $channel => $meta) {
            $raw = trim((string) ($input[$channel] ?? ''));
            if ($raw !== '') {
                $problem = DiscordChannelInput::problem($raw, '« ' . $labels[$channel] . ' »');
                if ($problem !== null) {
                    return ['ok' => false, 'message' => $problem];
                }
                $raw = DiscordChannelInput::webhookUrl($raw);
            }
            $pairs[$meta['setting']] = $raw;
        }
        try {
            $this->writer()($pairs);
        } catch (\Throwable) {
            return ['ok' => false, 'message' => 'Les salons de la plateforme n’ont pas pu être enregistrés (table platform_settings absente ?).'];
        }
        self::$cache = [];

        return ['ok' => true, 'message' => 'Les salons Discord de la plateforme ont été enregistrés.'];
    }

    private function saved(string $channel): string
    {
        try {
            return trim(($this->reader())(self::CHANNELS[$channel]['setting']));
        } catch (\Throwable) {
            return '';
        }
    }

    /** @return \Closure(string): string */
    private function reader(): \Closure
    {
        return $this->read ??= static fn (string $key): string => (new PlatformSettingsRepository())->get($key, '');
    }

    /** @return \Closure(array<string, string>): void */
    private function writer(): \Closure
    {
        return $this->write ??= static function (array $pairs): void {
            $repo = new PlatformSettingsRepository();
            if (!$repo->tableExists()) {
                throw new \RuntimeException('platform_settings');
            }
            $repo->setMany($pairs);
        };
    }

    private static function envValue(string $key): string
    {
        return trim((string) (($_ENV[$key] ?? null) ?: (getenv($key) ?: '')));
    }

    /** Tests : oublie les lectures de la requête courante. */
    public static function flush(): void
    {
        self::$cache = [];
    }
}
