<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PlatformAccountBanRepository;
use App\Repositories\PlatformBanAppealRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Account\PlatformAccountBanService;
use App\Services\Account\PlatformAutomaticBanService;
use App\Services\Account\PlatformBanNotice;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Support\PortalAccessChoice;

final class SystemPlatformBansController
{
    private const PATH = 'admin/sanctions';

    public function __construct(
        private ?PlatformAccountBanRepository $bans = null,
        private ?PlatformBanAppealRepository $appeals = null,
        private ?PlatformAccountBanService $service = null,
    ) {
        $this->bans ??= new PlatformAccountBanRepository();
        $this->appeals ??= new PlatformBanAppealRepository();
        $this->service ??= new PlatformAccountBanService();
    }

    public function index(Request $request, array $params = []): Response
    {
        $this->service->expireDue();
        $ready = $this->featureReady();
        $reviewed = $request->query('recours') === 'traites';
        $focusedAppeal = (int) $request->query('contestation');
        $tenant = $ready ? $this->tenant((int) $request->query('organisation')) : null;
        $targets = $tenant !== null ? $this->targets((int) $tenant['id']) : [];
        $active = $this->bans->listActive(50);

        return Response::view('layout.main', [
            'title' => 'Bannissements et contestations',
            'content' => 'admin.system.platform_bans',
            'backOfficePageCss' => ['platform-admin.css', 'platform-admin-users.css', 'platform-admin-sanctions.css', 'platform-admin-picker.css'],
            'workflowReady' => $ready,
            'supportsEnd' => $this->bans->supportsEndDate(),
            'activeBans' => $active,
            'activeCount' => $this->bans->countActive(),
            'tenants' => $ready ? Container::get(TenantRepository::class)->listBasicAll() : [],
            'selectedTenant' => $tenant,
            'targets' => $targets,
            'targetsSignature' => self::targetsSignature($targets),
            'alreadyBanned' => count($this->bans->activeByEmails($targets)),
            'batchNonce' => bin2hex(random_bytes(16)),
            'batches' => $ready ? $this->bans->recentBatches() : [],
            'appeals' => $ready ? $this->appeals->listForReview($reviewed, 50, $focusedAppeal) : [],
            'showReviewed' => $reviewed,
            'focusedAppeal' => $focusedAppeal,
            'autoRule' => (new PlatformAutomaticBanService())->settings(),
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function banOrganization(Request $request, array $params = []): Response
    {
        $tenantId = (int) $request->inputString('organisation');
        $back = $this->back($tenantId, 'organisation');
        $actor = $this->verifiedActor($request);
        if ($actor === 0 || !$this->featureReady()) {
            Session::flash('error', 'L’action est indisponible. Vérifiez votre session et l’état des migrations.');

            return $back;
        }
        $tenant = $this->tenant($tenantId);
        $reason = trim($request->inputString('reason'));
        $end = PlatformAccountBanService::resolveEnd($request->inputString('duree', '7j'), $request->inputString('fin'));
        if ($tenant === null || $request->inputString('confirm_ban') !== '1'
            || mb_strlen($reason) < PlatformAccountBanService::MIN_REASON_LENGTH || !$end['ok']) {
            Session::flash('error', $end['error'] ?? 'Vérifiez la communauté, le motif et la confirmation.');

            return $back;
        }
        $targets = $this->targets($tenantId);
        $signature = self::targetsSignature($targets);
        $nonce = $request->inputString('batch_nonce');
        if ($targets === [] || !hash_equals($signature, $request->inputString('targets_signature'))
            || preg_match('/^[a-f0-9]{32}$/D', $nonce) !== 1) {
            Session::flash('error', 'La liste des comptes a changé ou l’aperçu a expiré. Contrôlez le nouvel aperçu avant de confirmer.');

            return $back;
        }

        $batch = substr(hash_hmac('sha256', $tenantId . ':' . $signature . ':' . $nonce, Csrf::token()), 0, 32);
        $notice = new PlatformBanNotice($request->inputString('rule_reference'), $request->inputString('public_reason'));
        $endDate = $end['endsAt'] ?? null;
        $steam = $request->inputString('block_steam') === '1';
        $applied = $accounts = $skipped = $failed = 0;
        foreach ($targets as $email) {
            try {
                $result = $this->service->ban($email, $reason, [], $steam, $actor, $endDate, $notice, $tenantId, $batch);
                if (!$result['ok']) {
                    $skipped++;
                    continue;
                }
                $applied++;
                $accounts += (int) $result['accounts'];
                AuditService::logPlatformAction(AuditAction::USER_BANNED, [
                    'ban_id' => $result['ban_id'],
                    'organisation' => $tenantId,
                    'batch' => $batch,
                    'ends_at' => $result['ends_at'],
                ], $tenantId);
            } catch (\Throwable $e) {
                $failed++;
                error_log('[ban.organization] ' . $e->getMessage());
            }
        }
        Session::flash('success', sprintf('%d personne(s) bannie(s), %d fiche(s) désactivée(s), %d personne(s) ignorée(s).', $applied, $accounts, $skipped));
        if ($failed > 0) {
            Session::flash('error', sprintf('%d dossier(s) n’ont pas abouti. Relancez après vérification de la liste ; les décisions déjà appliquées sont conservées.', $failed));
        }

        return $back;
    }

    public function liftBatch(Request $request, array $params = []): Response
    {
        $actor = $this->verifiedActor($request);
        $batch = $request->inputString('batch');
        $note = trim($request->inputString('note'));
        if ($actor === 0 || !$this->featureReady() || preg_match('/^[a-f0-9]{32}$/D', $batch) !== 1
            || $request->inputString('confirm_lift') !== '1' || mb_strlen($note) < 5) {
            Session::flash('error', 'Indiquez le motif et confirmez la levée du lot.');

            return $this->back(0, 'lots');
        }
        $lifted = $failed = 0;
        foreach ($this->bans->activeBatch($batch) as $ban) {
            try {
                if ($this->service->lift((string) $ban['email_norm'], $actor, $note, (int) $ban['id'])['ok']) {
                    $lifted++;
                    AuditService::logPlatformAction(AuditAction::USER_UNBANNED, ['ban_id' => (int) $ban['id'], 'batch' => $batch]);
                }
            } catch (\Throwable $e) {
                $failed++;
                error_log('[ban.batch.lift] ' . $e->getMessage());
            }
        }
        Session::flash('success', $lifted . ' décision(s) du lot levée(s).');
        if ($failed > 0) {
            Session::flash('error', $failed . ' décision(s) n’ont pas pu être levées. Vous pouvez réessayer.');
        }

        return $this->back(0, 'lots');
    }

    public function review(Request $request, array $params = []): Response
    {
        $actor = $this->verifiedActor($request);
        $banId = (int) ($params['id'] ?? 0);
        $decision = $request->inputString('decision');
        $reply = trim($request->inputString('public_reply'));
        if ($actor === 0 || !$this->featureReady() || $banId < 1 || !in_array($decision, ['lift', 'uphold'], true)
            || mb_strlen($reply) < 5 || mb_strlen($reply) > 2000) {
            Session::flash('error', 'Choisissez une décision et rédigez une réponse entre 5 et 2 000 caractères.');

            return $this->back(0, 'contestations');
        }

        $pdo = Database::getPdo();
        try {
            if ($decision === 'lift') {
                $this->service->prepareWrites();
            }
            $pdo->beginTransaction();
            if (!$this->appeals->decide($banId, $actor, $decision, $reply)) {
                $pdo->rollBack();
                Session::flash('error', 'Cette contestation est introuvable ou a déjà été traitée.');

                return $this->back(0, 'contestations');
            }
            $ban = $this->bans->findById($banId);
            if ($decision === 'lift' && $ban !== null) {
                $this->service->lift((string) $ban['email_norm'], $actor, 'Contestation acceptée : ' . $reply, $banId);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[ban.appeal.review] ' . $e->getMessage());
            Session::flash('error', 'La décision n’a pas pu être enregistrée. Réessayez.');

            return $this->back(0, 'contestations');
        }

        AuditService::logPlatformAction(AuditAction::USER_BAN_APPEAL_REVIEWED, ['ban_id' => $banId, 'decision' => $decision]);
        Session::flash('success', 'Décision et réponse enregistrées. La personne peut les consulter via son lien confidentiel.');

        return $this->back(0, 'contestations');
    }

    public function saveAutomation(Request $request, array $params = []): Response
    {
        if ($this->verifiedActor($request) === 0 || !$this->featureReady()) {
            Session::flash('error', 'L’action est indisponible. Vérifiez votre session et l’état des migrations.');

            return $this->back(0, 'automatique');
        }
        $rule = new PlatformAutomaticBanService();
        $settings = $rule->save(
            $request->inputString('enabled') === '1',
            (int) $request->inputString('threshold'),
            (int) $request->inputString('window_days'),
            $request->inputString('duration')
        );
        if ($settings === null) {
            Session::flash('error', 'Seuil, période ou durée invalide, ou réglages indisponibles.');
        } else {
            AuditService::logPlatformAction(AuditAction::USER_AUTO_BAN_RULE_CHANGED, $settings);
            Session::flash('success', 'Règle de bannissement automatique enregistrée.');
        }

        return $this->back(0, 'automatique');
    }

    private function featureReady(): bool
    {
        return $this->bans->supportsNotices() && $this->appeals->ready();
    }

    private function verifiedActor(Request $request): int
    {
        return Csrf::verify($request) ? max(0, (int) Session::get('user_id')) : 0;
    }

    /** @return array<string, mixed>|null */
    private function tenant(int $id): ?array
    {
        $tenant = $id > 1 ? Container::get(TenantRepository::class)->findById($id) : null;

        return PortalAccessChoice::isPlaceholderTenant($tenant) ? null : $tenant;
    }

    /** @return list<string> */
    private function targets(int $tenantId): array
    {
        return Container::get(UserRepository::class)->listBanTargetEmailsForTenant($tenantId);
    }

    /** @param list<string> $targets */
    private static function targetsSignature(array $targets): string
    {
        sort($targets, SORT_STRING);

        return hash('sha256', implode("\n", $targets));
    }

    private function back(int $tenantId, string $anchor): Response
    {
        return Response::redirect(url(self::PATH) . ($tenantId > 0 ? '?organisation=' . $tenantId : '') . '#' . $anchor, 303);
    }
}
