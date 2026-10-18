<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\EmailDeliveryRepository;
use App\Repositories\EmailDigestRepository;
use App\Repositories\PlatformSettingsRepository;
use App\Services\Email\MailBudget;
use App\Services\Email\MailBudgetGate;
use App\Services\Email\OpsMailDigestService;
use App\Services\EmailService;

/**
 * /admin/system/e-mails : volume des e-mails automatiques, quota quotidien du fournisseur,
 * récapitulatif des alertes d'exploitation et types coupés (administrateur de la plateforme).
 */
final class SystemMailTrafficController
{
    public function __construct(
        private ?EmailDeliveryRepository $deliveries = null,
        private ?EmailDigestRepository $digest = null,
        private ?PlatformSettingsRepository $settings = null,
    ) {
        $this->deliveries ??= Container::get(EmailDeliveryRepository::class);
        $this->digest ??= new EmailDigestRepository();
        $this->settings ??= new PlatformSettingsRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        $gate = new MailBudgetGate($this->deliveries, $this->settings);
        $config = $gate->config();
        $error = null;
        $sentToday = 0;
        $daily = [];
        $events = [];
        $failures = [];
        $failedToday = 0;
        try {
            $sentToday = $gate->sentToday();
            $daily = $this->deliveries->dailyCounts(14);
            $failures = $this->deliveries->recentFailures(12);
            $opsInboxes = MailBudgetGate::opsInboxes();
            $since = date('Y-m-d 00:00:00', strtotime('-6 days'));
            foreach ($this->deliveries->countByEventAndStatusSince($since) as $r) {
                $code = $r['event_code'];
                $events[$code] ??= [
                    'code' => $code,
                    'label' => MailBudget::label($code),
                    'priority' => MailBudget::priority($code, '', $opsInboxes),
                    'sent' => 0, 'digest' => 0, 'deferred' => 0, 'muted' => 0, 'failed' => 0, 'total' => 0,
                ];
                $bucket = in_array($r['status'], ['sent', 'digest', 'deferred', 'muted', 'failed'], true) ? $r['status'] : 'sent';
                $events[$code][$bucket] += $r['n'];
                $events[$code]['total'] += $r['n'];
            }
            foreach ($this->deliveries->countByEventAndStatusSince(date('Y-m-d 00:00:00')) as $r) {
                if ($r['status'] === 'failed') {
                    $failedToday += $r['n'];
                }
            }
        } catch (\Throwable) {
            $error = 'Le journal des envois (table email_deliveries) n’est pas lisible sur cette base.';
        }
        // Types coupés sans envoi récent : restent visibles pour pouvoir les réactiver.
        foreach ($config['muted'] as $code) {
            $events[$code] ??= [
                'code' => $code, 'label' => MailBudget::label($code), 'priority' => MailBudget::PRIORITY_NORMAL,
                'sent' => 0, 'digest' => 0, 'deferred' => 0, 'muted' => 0, 'failed' => 0, 'total' => 0,
            ];
        }
        uasort($events, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        $cfg = \email_config();
        $smtp = is_array($cfg['smtp'] ?? null) ? $cfg['smtp'] : [];

        return Response::view('layout.main', [
            'title' => 'Envois d’e-mails',
            'content' => 'admin.system.mail_traffic',
            'isPlatformAdminShell' => true,
            'backOfficePageCss' => ['platform-admin.css'],
            'mailError' => $error,
            'mailConfig' => $config,
            'mailSentToday' => $sentToday,
            'mailFailedToday' => $failedToday,
            'mailDeferredPending' => $this->countDeferredPending(),
            'mailDigestPending' => $this->digest->countPending(),
            'mailDaily' => $daily,
            'mailEvents' => array_values($events),
            'mailFailures' => $failures,
            'mailTransport' => [
                'mailer' => (string) ($cfg['default_mailer'] ?? 'file'),
                'host' => (string) ($smtp['host'] ?? ''),
                'port' => (int) ($smtp['port'] ?? 0),
                'from' => (string) ($cfg['from_address'] ?? ''),
            ],
            'mailOpsInboxes' => MailBudgetGate::opsInboxes(),
        ]);
    }

    public function saveSettings(Request $request, array $params = []): Response
    {
        $back = url('admin/system/e-mails');
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        $limit = max(0, min(100000, (int) $request->input('daily_limit', MailBudget::DEFAULT_DAILY_LIMIT)));
        $reserve = max(0, min(1000, (int) $request->input('critical_reserve', MailBudget::DEFAULT_RESERVE)));
        $digest = (int) $request->input('digest_minutes', MailBudget::DEFAULT_DIGEST_MINUTES);
        if (!in_array($digest, MailBudget::DIGEST_CHOICES, true)) {
            $digest = MailBudget::DEFAULT_DIGEST_MINUTES;
        }
        $mutedRaw = $request->input('muted', []);
        $muted = MailBudget::parseMuted(implode(',', is_array($mutedRaw) ? array_map('strval', $mutedRaw) : []));

        try {
            $this->settings->setMany([
                MailBudget::SETTING_DAILY_LIMIT => (string) $limit,
                MailBudget::SETTING_RESERVE => (string) $reserve,
                MailBudget::SETTING_DIGEST_MINUTES => (string) $digest,
                MailBudget::SETTING_MUTED => implode(',', $muted),
            ]);
            Session::flash('success', 'Réglages des envois enregistrés.');
        } catch (\Throwable) {
            Session::flash('error', 'Les réglages n’ont pas pu être enregistrés (table platform_settings absente ?).');
        }

        return Response::redirect($back);
    }

    /** Envoie tout de suite le récapitulatif des alertes en attente. */
    public function sendDigestNow(Request $request, array $params = []): Response
    {
        $back = url('admin/system/e-mails');
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        try {
            $service = new OpsMailDigestService(
                $this->digest,
                Container::get(EmailService::class),
                new MailBudgetGate($this->deliveries, $this->settings)
            );
            $r = $service->sendDue(true);
            Session::flash('success', $r['items'] === 0
                ? 'Aucune alerte en attente.'
                : sprintf('%d alerte(s) envoyée(s) dans %d récapitulatif(s).', $r['items'], $r['emails']));
        } catch (\Throwable) {
            Session::flash('error', 'Le récapitulatif n’a pas pu être envoyé.');
        }

        return Response::redirect($back);
    }

    private function countDeferredPending(): int
    {
        try {
            $st = Database::getPdo()->query(
                "SELECT COUNT(*) FROM async_jobs WHERE job_type = 'email_send' AND reserved_at IS NULL"
            );

            return $st ? (int) $st->fetchColumn() : 0;
        } catch (\Throwable) {
            return 0;
        }
    }
}
