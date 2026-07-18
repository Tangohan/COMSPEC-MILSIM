<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PlatformAlertRepository;
use App\Repositories\UserRepository;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;
use App\Support\PlatformAlertPresentation;

final class SystemPlatformAlertsController
{
    public function __construct(
        private ?PlatformAlertRepository $alerts = null,
        private ?UserRepository $users = null,
        private ?EmailService $emailService = null,
    ) {
        $this->alerts ??= new PlatformAlertRepository();
        $this->users ??= new UserRepository();
    }

    private function email(): EmailService
    {
        return $this->emailService ??= \App\Core\Container::get(EmailService::class);
    }

    public function index(Request $request, array $params = []): Response
    {
        $gate = Gate::getInstance();
        $canManagePlatformAlerts = $gate->allows('admin.system');
        $isPlatformSupportReadOnly = $gate->allows('site.support') && !$canManagePlatformAlerts;

        $rows = $this->alerts->allOrdered();
        $now = date('Y-m-d H:i:s');
        $platformAlertRows = [];
        $stats = ['published' => 0, 'disabled' => 0, 'visible_now' => 0];
        foreach ($rows as $r) {
            if (!empty($r['is_active'])) {
                $stats['published']++;
            } else {
                $stats['disabled']++;
            }
            if (PlatformAlertPresentation::isPublishedVisibleNow($r, $now)) {
                $stats['visible_now']++;
            }
            $platformAlertRows[] = PlatformAlertPresentation::enrichRowForAdmin($r, $now);
        }

        return Response::view('layout.main', [
            'content' => 'admin.system.platform_alerts_index',
            'title' => 'Alertes plateforme',
            'platformAlertRows' => $platformAlertRows,
            'platformAlertStats' => $stats,
            'canManagePlatformAlerts' => $canManagePlatformAlerts,
            'isPlatformSupportReadOnly' => $isPlatformSupportReadOnly,
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        return Response::view('layout.main', [
            'content' => 'admin.system.platform_alerts_form',
            'title' => 'Nouvelle alerte plateforme',
            'platformAlert' => null,
            'formAction' => url('admin/system/alerts'),
            'formMethod' => 'post',
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('admin/system/alerts'));
        }
        $data = $this->normalize($request);
        if (($data['_error'] ?? '') !== '') {
            Session::flash('error', $data['_error']);

            return Response::redirect(url('admin/system/alerts/create'));
        }
        unset($data['_error']);
        $id = $this->alerts->insert($data);
        $sendMail = $request->input('send_email_now') === '1' || $request->input('send_email_now') === 'on';
        if ($sendMail && $id > 0) {
            $row = $this->alerts->findById($id);
            if ($row) {
                $this->broadcastEmail($row);
            }
        } else {
            Session::flash('success', 'Annonce créée.');
        }

        return Response::redirect(url('admin/system/alerts'));
    }

    public function edit(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $row = $id > 0 ? $this->alerts->findById($id) : null;
        if (!$row) {
            Session::flash('error', 'Annonce introuvable.');

            return Response::redirect(url('admin/system/alerts'));
        }

        return Response::view('layout.main', [
            'content' => 'admin.system.platform_alerts_form',
            'title' => 'Modifier l’annonce',
            'platformAlert' => $row,
            'formAction' => url('admin/system/alerts/' . $id . '/update'),
            'formMethod' => 'post',
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('admin/system/alerts'));
        }
        $id = (int) ($params['id'] ?? 0);
        $row = $id > 0 ? $this->alerts->findById($id) : null;
        if (!$row) {
            Session::flash('error', 'Annonce introuvable.');

            return Response::redirect(url('admin/system/alerts'));
        }
        $data = $this->normalize($request);
        if (($data['_error'] ?? '') !== '') {
            Session::flash('error', $data['_error']);

            return Response::redirect(url('admin/system/alerts/' . $id . '/edit'));
        }
        unset($data['_error']);
        $this->alerts->update($id, $data);
        Session::flash('success', 'Annonce enregistrée.');

        return Response::redirect(url('admin/system/alerts'));
    }

    public function delete(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('admin/system/alerts'));
        }
        $id = (int) ($params['id'] ?? 0);
        if ($id > 0 && $this->alerts->findById($id)) {
            $this->alerts->delete($id);
            Session::flash('success', 'Annonce supprimée.');
        }

        return Response::redirect(url('admin/system/alerts'));
    }

    public function sendEmail(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('admin/system/alerts'));
        }
        if (!Gate::getInstance()->allows('admin.system')) {
            Session::flash('error', 'Action réservée aux administrateurs plateforme.');

            return Response::redirect(url('admin/system/alerts'));
        }
        $id = (int) ($params['id'] ?? 0);
        $row = $id > 0 ? $this->alerts->findById($id) : null;
        if (!$row) {
            Session::flash('error', 'Annonce introuvable.');

            return Response::redirect(url('admin/system/alerts'));
        }
        $this->broadcastEmail($row);

        return Response::redirect(url('admin/system/alerts'));
    }

    /** @param array<string, mixed> $row */
    private function broadcastEmail(array $row): void
    {
        $aud = PlatformAlertPresentation::audienceSummary($row['audience_json'] ?? null);
        $totalRecipients = $this->users->countDistinctActiveMemberEmailsPlatformWide();
        if ($totalRecipients < 1) {
            Session::flash('error', 'Aucun destinataire : aucun compte actif avec une adresse e-mail utilisable.');

            return;
        }

        $title = trim((string) ($row['title'] ?? 'Annonce Athena'));
        $body = trim((string) ($row['body'] ?? ''));
        $ctaLabel = isset($row['cta_label']) ? trim((string) $row['cta_label']) : '';
        $ctaUrl = isset($row['cta_url']) ? trim((string) $row['cta_url']) : '';
        if ($ctaUrl !== '' && str_starts_with($ctaUrl, '/')) {
            $base = rtrim((string) env('APP_URL', ''), '/');
            $path = function_exists('url') ? url(ltrim($ctaUrl, '/')) : $ctaUrl;
            if ($base !== '' && !preg_match('#^https?://#i', (string) $path)) {
                $ctaUrl = $base . (str_starts_with((string) $path, '/') ? $path : '/' . $path);
            } else {
                $ctaUrl = (string) $path;
            }
        }

        $subject = 'Annonce Athena — ' . $title;
        $email = $this->email();
        $templateVars = [
            'title' => $title,
            'body' => $body,
            'ctaLabel' => $ctaLabel !== '' ? $ctaLabel : null,
            'ctaUrl' => $ctaUrl !== '' ? $ctaUrl : null,
        ];

        $batch = 200;
        $sent = 0;
        $failed = 0;
        for ($offset = 0; $offset < $totalRecipients; $offset += $batch) {
            $emails = $this->users->listDistinctActiveMemberEmailsPlatformWide($batch, $offset);
            foreach ($emails as $to) {
                $ok = $email->sendTemplated(
                    EmailEvents::PLATFORM_ALERT_BROADCAST,
                    'platform_alert_broadcast',
                    $to,
                    $subject,
                    $templateVars,
                    null,
                    null,
                    [
                        'purpose' => 'platform_alert_broadcast',
                        'alert_id' => (int) ($row['id'] ?? 0),
                        'audience' => $aud,
                    ]
                );
                if ($ok) {
                    $sent++;
                } else {
                    $failed++;
                }
            }
        }

        $this->alerts->markEmailBroadcast((int) ($row['id'] ?? 0), $sent);

        $queued = filter_var((string) env('MAIL_QUEUE', ''), FILTER_VALIDATE_BOOLEAN);
        if ($queued) {
            Session::flash(
                'success',
                "Annonce enregistrée. Diffusion e-mail planifiée pour {$totalRecipients} adresse(s)."
            );
        } elseif ($failed === 0) {
            Session::flash('success', "Diffusion e-mail terminée : {$sent} message(s) envoyé(s).");
        } else {
            Session::flash(
                'success',
                "Diffusion e-mail terminée : {$sent} envoyé(s), {$failed} échec(s), sur {$totalRecipients} adresse(s)."
            );
        }
    }

    /** @return array<string, mixed> */
    private function normalize(Request $request): array
    {
        $kind = trim((string) $request->input('kind', 'info'));
        if (!in_array($kind, ['discount', 'novelty', 'info', 'urgent'], true)) {
            $kind = 'info';
        }
        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            return ['_error' => 'Le titre est obligatoire.'];
        }
        $body = trim((string) $request->input('body', ''));
        $ctaLabel = trim((string) $request->input('cta_label', ''));
        $ctaUrlRaw = trim((string) $request->input('cta_url', ''));
        $ctaUrl = $ctaUrlRaw === '' ? null : $this->sanitizeUrl($ctaUrlRaw);
        if ($ctaUrl === false) {
            return ['_error' => 'Lien cible invalide : utilisez une adresse en https ou un chemin interne commençant par /.'];
        }
        $coupon = trim((string) $request->input('coupon_code', ''));
        $starts = $this->parseDt($request->input('starts_at'));
        $ends = $this->parseDt($request->input('ends_at'));
        if ($starts !== null && $ends !== null && $ends < $starts) {
            return ['_error' => 'La date de fin doit être postérieure au début.'];
        }

        $audience = [
            'guest' => $request->input('aud_guest') === '1' || $request->input('aud_guest') === 'on',
            'authenticated' => $request->input('aud_auth') === '1' || $request->input('aud_auth') === 'on',
            'free' => $request->input('aud_free') === '1' || $request->input('aud_free') === 'on',
            'paid' => $request->input('aud_paid') === '1' || $request->input('aud_paid') === 'on',
        ];

        // Décocher « autoriser le masquage » = interdire (dismissible = 0)
        $allowDismiss = $request->input('dismissible') === '1' || $request->input('dismissible') === 'on';

        return [
            'kind' => $kind,
            'display_style' => \App\Support\AlertDisplayStyle::sanitizePlatform(
                (string) $request->input('display_style', \App\Support\AlertDisplayStyle::CLASSIC)
            ),
            'title' => $title,
            'body' => $body === '' ? null : $body,
            'cta_label' => $ctaLabel === '' ? null : $ctaLabel,
            'cta_url' => $ctaUrl,
            'coupon_code' => $coupon === '' ? null : $coupon,
            'starts_at' => $starts,
            'ends_at' => $ends,
            'sort_order' => (int) $request->input('sort_order', 0),
            'is_active' => $request->input('is_active') === '1' || $request->input('is_active') === 'on',
            'dismissible' => $allowDismiss,
            'audience_json' => $audience,
            '_error' => '',
        ];
    }

    private function parseDt(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $s = trim((string) $raw);
        $t = strtotime($s);

        return $t !== false ? date('Y-m-d H:i:s', $t) : null;
    }

    /** @return string|null|false null si vide, false si invalide */
    private function sanitizeUrl(string $url): string|false|null
    {
        if ($url === '') {
            return null;
        }
        if ($url[0] === '/') {
            return $url;
        }
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        return false;
    }
}
