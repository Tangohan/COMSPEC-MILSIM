<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Repositories\AtakModReportRepository;
use App\Repositories\ForumNotificationRepository;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;

/**
 * Réponse de l'exploitation au joueur qui a envoyé un rapport depuis le mod (page /admin/atak-mod-reports).
 *
 * Le joueur n'est connu que par son Steam : on le prévient sur Athena (centre d'activité) et par e-mail
 * quand ce Steam est lié à un compte. La réponse reste toujours enregistrée sur le rapport.
 * Pas de message dans le jeu : le téléphone ne relève pas encore de messages du site (il faudrait une
 * commande « notif » côté mod).
 */
final class AtakModReportReplyService
{
    public const NOTIFICATION_TYPE = 'atak_mod_report_reply';

    /** @var \Closure(string, array<string, mixed>, ?int, array<string, mixed>): bool */
    private \Closure $sendMail;

    /**
     * @param (\Closure(string, array<string, mixed>, ?int, array<string, mixed>): bool)|null $sendMail
     *        envoi de l'e-mail (destinataire, variables du modèle, communauté, résumé) ; EmailService par défaut
     */
    public function __construct(
        private AtakModReportRepository $reports,
        private ?ForumNotificationRepository $notifications = null,
        ?\Closure $sendMail = null
    ) {
        $this->sendMail = $sendMail ?? static function (string $to, array $vars, ?int $tenantId, array $summary): bool {
            return \App\Core\Container::get(EmailService::class)->sendTemplated(
                EmailEvents::ATAK_MOD_REPORT_REPLY,
                'atak_mod_report_reply',
                $to,
                'Réponse à votre signalement COMSPEC ATAK',
                $vars,
                $tenantId,
                null,
                $summary
            );
        };
    }

    /** Message proposé quand on marque un rapport corrigé et qu'on prévient le joueur. */
    public static function defaultFixedMessage(): string
    {
        return 'Merci pour votre signalement. Le problème est corrigé : la correction arrive avec la prochaine mise à jour du mod COMSPEC ATAK.';
    }

    /**
     * @param array<string, mixed> $report ligne atak_mod_reports
     * @param array{user_id:int, tenant_id:int, email:string, name:string}|null $account compte lié au Steam
     * @return array{site:bool, email:bool, channels:list<string>}
     */
    public function reply(array $report, string $message, ?array $account, bool $viaSite, bool $viaEmail, string $by): array
    {
        $message = trim($message);
        $site = false;
        $mail = false;
        if ($message !== '' && $account !== null) {
            if ($viaSite) {
                $site = $this->notifySite($account, [$report], $message);
            }
            if ($viaEmail) {
                $mail = $this->sendEmail($account, [$report], $message);
            }
        }
        $channels = array_values(array_filter([$site ? 'site' : '', $mail ? 'email' : '']));
        if ($message !== '') {
            $this->reports->saveReply((int) ($report['id'] ?? 0), $message, $by, $channels);
        }

        return ['site' => $site, 'email' => $mail, 'channels' => $channels];
    }

    /**
     * Prévient chaque joueur une seule fois pour un lot de rapports (action groupée « corrigé »).
     *
     * @param list<array<string, mixed>> $reports
     * @return array{players:int, site:int, email:int, unreachable:int}
     */
    public function notifyMany(array $reports, string $message, string $by): array
    {
        $bySteam = [];
        $noSteam = 0;
        foreach ($reports as $report) {
            $steam = trim((string) ($report['steam_uid'] ?? ''));
            if ($steam === '') {
                $noSteam++;
                continue;
            }
            $bySteam[$steam][] = $report;
        }
        $accounts = $this->reports->linkedAccountsBySteam(array_keys($bySteam));
        $stats = ['players' => 0, 'site' => 0, 'email' => 0, 'unreachable' => $noSteam];
        foreach ($bySteam as $steam => $group) {
            $account = $accounts[$steam] ?? null;
            if ($account === null) {
                $stats['unreachable'] += count($group);
                continue;
            }
            $stats['players']++;
            $site = $this->notifySite($account, $group, $message);
            $mail = $this->sendEmail($account, $group, $message);
            $stats['site'] += $site ? 1 : 0;
            $stats['email'] += $mail ? 1 : 0;
            $channels = array_values(array_filter([$site ? 'site' : '', $mail ? 'email' : '']));
            foreach ($group as $report) {
                $this->reports->saveReply((int) ($report['id'] ?? 0), $message, $by, $channels);
            }
        }

        return $stats;
    }

    /**
     * @param array{user_id:int, tenant_id:int, email:string, name:string} $account
     * @param list<array<string, mixed>> $reports
     */
    private function notifySite(array $account, array $reports, string $message): bool
    {
        if ($account['user_id'] < 1 || $account['tenant_id'] < 1) {
            return false;
        }
        try {
            $repo = $this->notifications ?? new ForumNotificationRepository();
            if (!$repo->tableExists()) {
                return false;
            }

            return $repo->create($account['tenant_id'], $account['user_id'], self::NOTIFICATION_TYPE, [
                'summary' => self::clip($message, 160),
                'report' => self::clip(self::reportsLabel($reports), 120),
                'href' => url('activite'),
            ]) !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array{user_id:int, tenant_id:int, email:string, name:string} $account
     * @param list<array<string, mixed>> $reports
     */
    private function sendEmail(array $account, array $reports, string $message): bool
    {
        if ($account['email'] === '') {
            return false;
        }
        $first = $reports[0] ?? [];
        $name = $account['name'] !== '' ? $account['name']
            : (trim((string) ($first['callsign'] ?? '')) ?: (trim((string) ($first['player_name'] ?? '')) ?: 'opérateur'));
        try {
            return ($this->sendMail)(
                $account['email'],
                [
                    'displayName' => $name,
                    'reportLabel' => self::reportsLabel($reports),
                    'replyText' => $message,
                    'activityUrl' => url('activite'),
                ],
                $account['tenant_id'] > 0 ? $account['tenant_id'] : null,
                ['purpose' => 'atak_mod_report_reply', 'report_ids' => array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $reports)]
            );
        } catch (\Throwable) {
            return false;
        }
    }

    /** @param list<array<string, mixed>> $reports */
    public static function reportsLabel(array $reports): string
    {
        $messages = [];
        foreach ($reports as $report) {
            $m = self::clip((string) ($report['message'] ?? ''), 90);
            if ($m !== '' && !in_array($m, $messages, true)) {
                $messages[] = $m;
            }
        }
        if ($messages === []) {
            return 'Signalement envoyé depuis le jeu';
        }
        $label = '« ' . $messages[0] . ' »';
        if (count($messages) > 1) {
            $label .= ' et ' . (count($messages) - 1) . ' autre(s)';
        }

        return $label;
    }

    private static function clip(string $s, int $max): string
    {
        $s = trim((string) preg_replace('/\s+/u', ' ', $s));

        return mb_strlen($s) > $max ? rtrim(mb_substr($s, 0, $max - 1)) . '…' : $s;
    }
}
