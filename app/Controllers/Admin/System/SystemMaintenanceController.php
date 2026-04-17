<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\MaintenanceRepository;
use App\Repositories\UserRepository;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;
use App\Support\MaintenanceGuard;
use RuntimeException;

final class SystemMaintenanceController
{
    public function __construct(
        private ?MaintenanceRepository $repo = null
    ) {
        $this->repo ??= new MaintenanceRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        if (!$this->repo->tableExists()) {
            Session::flash('error', 'Tables de maintenance absentes : exécutez la migration (app_maintenance).');

            return Response::view('layout.main', [
                'content' => 'admin.system.maintenance_index',
                'title' => 'Maintenance',
                'maintenanceRules' => [],
                'maintenanceTableMissing' => true,
            ]);
        }

        return Response::view('layout.main', [
            'content' => 'admin.system.maintenance_index',
            'title' => 'Maintenance',
            'maintenanceRules' => $this->repo->listAll(),
            'maintenanceTableMissing' => false,
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        if (!$this->repo->tableExists()) {
            return Response::redirect(url('admin/maintenance'));
        }

        return Response::view('layout.main', [
            'content' => 'admin.system.maintenance_form',
            'title' => 'Nouvelle règle de maintenance',
            'maintenanceRule' => null,
            'formAction' => url('admin/maintenance'),
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/maintenance'));
        }
        if (!$this->repo->tableExists()) {
            return Response::redirect(url('admin/maintenance'));
        }

        try {
            $data = $this->normalizeFormData($request);
            $actorId = Session::get('user_id') ? (int) Session::get('user_id') : null;
            $this->repo->create($data, $actorId, MaintenanceGuard::resolveClientIp());
            Session::flash('success', 'Règle créée.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        } catch (\Throwable) {
            Session::flash('error', 'Enregistrement impossible.');
        }

        return Response::redirect(url('admin/maintenance'));
    }

    public function edit(Request $request, array $params = []): Response
    {
        if (!$this->repo->tableExists()) {
            return Response::redirect(url('admin/maintenance'));
        }
        $id = (int) ($params['id'] ?? 0);
        $row = $id > 0 ? $this->repo->findById($id) : null;
        if (!$row) {
            Session::flash('error', 'Règle introuvable.');

            return Response::redirect(url('admin/maintenance'));
        }

        return Response::view('layout.main', [
            'content' => 'admin.system.maintenance_form',
            'title' => 'Modifier la règle #' . $id,
            'maintenanceRule' => $row,
            'formAction' => url('admin/maintenance/' . $id . '/update'),
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/maintenance'));
        }
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0 || !$this->repo->findById($id)) {
            Session::flash('error', 'Règle introuvable.');

            return Response::redirect(url('admin/maintenance'));
        }

        try {
            $data = $this->normalizeFormData($request);
            $actorId = Session::get('user_id') ? (int) Session::get('user_id') : null;
            $this->repo->update($id, $data, $actorId, MaintenanceGuard::resolveClientIp());
            Session::flash('success', 'Règle mise à jour.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        } catch (\Throwable) {
            Session::flash('error', 'Mise à jour impossible.');
        }

        return Response::redirect(url('admin/maintenance'));
    }

    public function delete(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/maintenance'));
        }
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return Response::redirect(url('admin/maintenance'));
        }

        try {
            $actorId = Session::get('user_id') ? (int) Session::get('user_id') : null;
            $this->repo->delete($id, $actorId, MaintenanceGuard::resolveClientIp());
            Session::flash('success', 'Règle supprimée.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        } catch (\Throwable) {
            Session::flash('error', 'Suppression impossible.');
        }

        return Response::redirect(url('admin/maintenance'));
    }

    public function toggle(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/maintenance'));
        }
        $id = (int) ($params['id'] ?? 0);
        $enabled = $request->input('enabled');
        $on = $enabled === '1' || $enabled === 1 || $enabled === true;

        if ($id <= 0) {
            return Response::redirect(url('admin/maintenance'));
        }

        try {
            $actorId = Session::get('user_id') ? (int) Session::get('user_id') : null;
            $this->repo->setEnabled($id, $on, $actorId, MaintenanceGuard::resolveClientIp());
            Session::flash(
                'success',
                $on
                    ? 'Règle activée. Le blocage public (y compris la page de connexion) ne s’applique que pendant le créneau début / fin, ou immédiatement si ces champs sont vides.'
                    : 'Maintenance désactivée.'
            );
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        } catch (\Throwable) {
            Session::flash('error', 'Changement d\'état impossible.');
        }

        return Response::redirect(url('admin/maintenance'));
    }

    public function notifyMembers(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/maintenance'));
        }
        if (!$this->repo->tableExists()) {
            return Response::redirect(url('admin/maintenance'));
        }
        $id = (int) ($params['id'] ?? 0);
        $rule = $id > 0 ? $this->repo->findById($id) : null;
        if (!$rule) {
            Session::flash('error', 'Règle introuvable.');

            return Response::redirect(url('admin/maintenance'));
        }

        $subject = trim((string) ($rule['notify_email_subject'] ?? ''));
        $message = trim((string) ($rule['notify_email_message'] ?? ''));
        if ($subject === '' || $message === '') {
            Session::flash(
                'error',
                'Renseignez d’abord l’objet et le texte du message : ouvrez « Modifier », rubrique diffusion aux membres.'
            );

            return Response::redirect(url('admin/maintenance'));
        }

        @set_time_limit(600);

        /** @var UserRepository $userRepo */
        $userRepo = Container::get(UserRepository::class);
        /** @var EmailService $emailService */
        $emailService = Container::get(EmailService::class);

        $totalRecipients = $userRepo->countDistinctActiveMemberEmailsPlatformWide();
        if ($totalRecipients < 1) {
            Session::flash('error', 'Aucun destinataire : aucun compte actif avec une adresse e-mail utilisable n’a été trouvé.');

            return Response::redirect(url('admin/maintenance'));
        }

        $heading = trim((string) ($rule['title'] ?? ''));
        if ($heading === '') {
            $heading = 'Information plateforme';
        }
        $pre = $message;
        if (mb_strlen($pre) > 100) {
            $pre = mb_substr($pre, 0, 97) . '…';
        }
        $bodyHtml = '<p class="text-slate-700">' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</p>';
        $html = email_html_layout(
            $pre,
            $heading,
            $bodyHtml,
            [
                'accent' => 'amber',
                'footer_note' => 'Vous recevez ce message car vous avez un compte actif sur le portail. Pour toute question, connectez-vous comme d’habitude ou contactez votre communauté.',
            ]
        );
        $textBody = $message . "\n\n— " . email_brand_name();

        $batch = 200;
        $sent = 0;
        $failed = 0;
        for ($offset = 0; $offset < $totalRecipients; $offset += $batch) {
            $emails = $userRepo->listDistinctActiveMemberEmailsPlatformWide($batch, $offset);
            foreach ($emails as $to) {
                $ok = $emailService->send(
                    EmailEvents::MAINTENANCE_MEMBER_BROADCAST,
                    $to,
                    $subject,
                    $html,
                    $textBody,
                    null,
                    null,
                    ['purpose' => 'maintenance_member_broadcast', 'maintenance_rule_id' => $id]
                );
                if ($ok) {
                    $sent++;
                } else {
                    $failed++;
                }
            }
        }

        try {
            $actorId = Session::get('user_id') ? (int) Session::get('user_id') : null;
            $this->repo->auditNotifyEmailBroadcast($id, [
                'recipients_estimate' => $totalRecipients,
                'sent' => $sent,
                'failed' => $failed,
            ], $actorId, MaintenanceGuard::resolveClientIp());
        } catch (\Throwable) {
        }

        $queued = filter_var((string) \env('MAIL_QUEUE', ''), FILTER_VALIDATE_BOOLEAN);
        if ($queued) {
            Session::flash(
                'success',
                "La diffusion a été enregistrée pour {$totalRecipients} adresse(s) distincte(s). Les envois seront traités selon la configuration du serveur de courrier."
            );
        } elseif ($failed === 0) {
            Session::flash('success', "Diffusion terminée : {$sent} message(s) envoyé(s) pour {$totalRecipients} adresse(s) distincte(s).");
        } else {
            Session::flash(
                'success',
                "Diffusion terminée avec des erreurs : {$sent} message(s) envoyé(s), {$failed} échec(s), sur {$totalRecipients} adresse(s) distincte(s)."
            );
        }

        return Response::redirect(url('admin/maintenance'));
    }

    public function audit(Request $request, array $params = []): Response
    {
        if (!$this->repo->tableExists()) {
            return Response::redirect(url('admin/maintenance'));
        }
        $id = (int) ($params['id'] ?? 0);
        $rule = $id > 0 ? $this->repo->findById($id) : null;
        if (!$rule) {
            Session::flash('error', 'Règle introuvable.');

            return Response::redirect(url('admin/maintenance'));
        }

        return Response::view('layout.main', [
            'content' => 'admin.system.maintenance_audit',
            'title' => 'Historique — règle #' . $id,
            'maintenanceRule' => $rule,
            'auditRows' => $this->repo->listAuditFor($id),
        ]);
    }

    /** @return array<string, mixed> */
    private function normalizeFormData(Request $request): array
    {
        $scope = trim((string) $request->input('scope', 'global'));
        if ($scope === '') {
            $scope = 'global';
        }

        $httpRaw = $request->input('http_status');
        $httpStatus = ($httpRaw === null || $httpRaw === '') ? 503 : (int) $httpRaw;
        if ($httpStatus < 100 || $httpStatus > 599) {
            $httpStatus = 503;
        }

        return [
            'scope' => $scope,
            'is_enabled' => $request->input('is_enabled') === '1' || $request->input('is_enabled') === 1,
            'title' => trim((string) $request->input('title', 'Maintenance en cours')) ?: 'Maintenance en cours',
            'message' => $this->optionalString($request->input('message')),
            'maintenance_code' => $this->optionalString($request->input('maintenance_code')),
            'starts_at' => $this->optionalDatetime($request->input('starts_at')),
            'ends_at' => $this->optionalDatetime($request->input('ends_at')),
            'allow_admin_bypass' => $request->input('allow_admin_bypass') === '1' || $request->input('allow_admin_bypass') === 1,
            'allowed_ips' => $this->optionalString($request->input('allowed_ips')),
            'allowed_roles' => $this->optionalString($request->input('allowed_roles')),
            'allowed_user_ids' => $this->normalizeAllowedUserIds($request->input('allowed_user_ids')),
            'message_preset' => $this->optionalString($request->input('message_preset')),
            'ui_variant' => $this->normalizeUiVariant($request->input('ui_variant')),
            'ui_animation' => $request->input('ui_animation') === '1' || $request->input('ui_animation') === 1,
            'notify_members_by_email' => $request->input('notify_members_by_email') === '1' || $request->input('notify_members_by_email') === 1,
            'notify_email_subject' => $this->optionalString($request->input('notify_email_subject')),
            'notify_email_message' => $this->optionalString($request->input('notify_email_message')),
            'redirect_url' => $this->optionalString($request->input('redirect_url')),
            'http_status' => $httpStatus,
            'priority' => (int) $request->input('priority', 100),
        ];
    }

    private function normalizeAllowedUserIds(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $ids = [];
        foreach (explode(',', $raw) as $piece) {
            $id = (int) trim($piece);
            if ($id > 0) {
                $ids[$id] = true;
            }
        }

        if ($ids === []) {
            return null;
        }

        $sorted = array_keys($ids);
        sort($sorted, SORT_NUMERIC);

        return implode(',', $sorted);
    }

    private function normalizeUiVariant(mixed $value): string
    {
        $variant = strtolower(trim((string) $value));
        $allowed = ['military', 'minimal', 'neon', 'status'];
        if (!in_array($variant, $allowed, true)) {
            return 'military';
        }

        return $variant;
    }

    private function optionalString(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }

    private function optionalDatetime(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        $s = trim((string) $v);
        if ($s === '') {
            return null;
        }
        $s = str_replace('T', ' ', $s);
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $s) === 1) {
            return $s . ':00';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $s) === 1) {
            return $s;
        }

        return $s;
    }
}
