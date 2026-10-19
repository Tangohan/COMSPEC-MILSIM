<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Services\Atak\AtakStartupCheckService;
use App\Services\Monitoring\AtakModReportGravity;
use App\Services\Security\FileRateLimiter;
use App\Support\DiscordWebhookCatalog;

/**
 * Copie d'un nouveau signalement du mod dans le salon Discord de la communauté du joueur
 * (Intégrations > Discord > « Signalements du mod »). Le ticket de l'équipe de développement de la plateforme
 * (AtakModReportDiscordTicketService) est à part et ne change pas.
 *
 * Postés : les nouveaux rapports (pas le compteur ×N) de gravité moyenne ou plus. Aucun Steam, e-mail,
 * adresse IP ni lien. Au plus 30 messages par heure et par communauté (tempête d'erreurs).
 */
final class CommunityModReportRelay
{
    private const PER_TENANT_PER_HOUR = 30;

    private const GRAVITY_EN = [
        AtakModReportGravity::CRITICAL => 'Critical',
        AtakModReportGravity::HIGH => 'High',
        AtakModReportGravity::NORMAL => 'Medium',
        AtakModReportGravity::LOW => 'Low',
    ];

    private const GRAVITY_ICON = [
        AtakModReportGravity::CRITICAL => '🔴',
        AtakModReportGravity::HIGH => '🟠',
        AtakModReportGravity::NORMAL => '🟡',
        AtakModReportGravity::LOW => '⚪',
    ];

    public function __construct(
        private ?DiscordEventRelayService $relay = null,
        private ?FileRateLimiter $limiter = null
    ) {
    }

    /**
     * Lance l'envoi après la réponse au jeu. Jamais bloquant.
     *
     * @param array<string, mixed> $report
     */
    public static function defer(int $tenantId, array $report): void
    {
        if ($tenantId < 2) {
            return;
        }
        register_shutdown_function(static function () use ($tenantId, $report): void {
            try {
                if (function_exists('fastcgi_finish_request')) {
                    @fastcgi_finish_request();
                }
                (new self())->relay($tenantId, $report);
            } catch (\Throwable $e) {
                error_log('[atak/mod-reports] discord communauté ' . $e->getMessage());
            }
        });
    }

    /**
     * @param array<string, mixed> $report gravity, source, channel, message, callsign, player_name, mod_version, extension_version
     * @return string sent, skipped_low, off, throttled, failed
     */
    public function relay(int $tenantId, array $report): string
    {
        if (!AtakModReportGravity::mailsPlayer(isset($report['gravity']) ? (string) $report['gravity'] : null)) {
            return 'skipped_low';
        }
        $relay = $this->relay ??= new DiscordEventRelayService();
        if ($relay->resolveUrl($tenantId, DiscordWebhookCatalog::KEY_ATAK_MOD_REPORTS) === null) {
            return 'off';
        }
        $limiter = $this->limiter ??= new FileRateLimiter();
        if ($limiter->hit('atak_mod_reports_tenant:' . $tenantId, 3600) > self::PER_TENANT_PER_HOUR) {
            return 'throttled';
        }
        $result = $relay->notify($tenantId, DiscordWebhookCatalog::KEY_ATAK_MOD_REPORTS, self::message($report), 'Athena · ATAK');

        return !empty($result['ok']) ? 'sent' : 'failed';
    }

    /**
     * Texte Discord bilingue, nettoyé (liens, adresses IP, mentions, mise en forme).
     *
     * @param array<string, mixed> $report
     */
    public static function message(array $report): string
    {
        $gravity = AtakModReportGravity::normalize(isset($report['gravity']) ? (string) $report['gravity'] : null) ?? AtakModReportGravity::NORMAL;
        $clean = static fn (mixed $v, int $max): string => AtakStartupCheckService::clean((string) (is_scalar($v) ? $v : ''), $max);
        $who = $clean($report['callsign'] ?? '', 40);
        if ($who === '') {
            $who = $clean($report['player_name'] ?? '', 60);
        }
        $player = AtakModReportGravity::isPlayerReport(isset($report['source']) ? (string) $report['source'] : null);
        $kindFr = $player ? 'Signalement joueur' : 'Erreur du mod';
        $kindEn = $player ? 'Player report' : 'Mod error';

        $lines = [
            (self::GRAVITY_ICON[$gravity] ?? '🟡') . ' **' . $kindFr . ' · ' . $kindEn . '** — '
                . AtakModReportGravity::label($gravity) . ' / ' . (self::GRAVITY_EN[$gravity] ?? 'Medium'),
        ];
        $meta = array_filter([
            $who !== '' ? $who : null,
            ($channel = $clean($report['channel'] ?? '', 40)) !== '' ? $channel : null,
        ]);
        if ($meta !== []) {
            $lines[] = implode(' · ', $meta);
        }
        $message = $clean($report['message'] ?? '', 400);
        if ($message !== '') {
            $lines[] = '> ' . $message;
        }
        $versions = array_filter([
            ($mod = $clean($report['mod_version'] ?? '', 24)) !== '' ? 'ATAK ' . $mod : null,
            ($ext = $clean($report['extension_version'] ?? '', 24)) !== '' ? 'Link ' . $ext : null,
        ]);
        if ($versions !== []) {
            $lines[] = '-# ' . implode(' · ', $versions) . ' · suivi par l’équipe Athena / tracked by the Athena team';
        }

        return implode("\n", $lines);
    }
}
