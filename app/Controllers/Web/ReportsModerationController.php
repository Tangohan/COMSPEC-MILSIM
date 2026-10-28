<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\ContentReportRepository;
use App\Repositories\UserRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\Community\CommunityReportNotificationService;
use App\Services\Moderation\ModerationService;

/**
 * Console /back-office/signalements : signalements membres (fiches, photos, formations, pages d’aide…).
 */
final class ReportsModerationController
{
    /** Suites proposées à la clôture d’un dossier. */
    public const FOLLOW_UPS = [
        'close' => 'Clôturer sans autre mesure',
        'request_correction' => 'Demander une correction du contenu',
        'watch_report' => 'Conserver sous surveillance',
        'escalate_support' => 'Escalader vers l’assistance plateforme',
    ];

    /** Sanctions sur le membre visé (permission admin.members.moderate). */
    public const SANCTIONS = [
        'sanction_warn' => 'Avertissement formel',
        'sanction_mute_24h' => 'Silence 24 h',
        'sanction_mute_7d' => 'Silence 7 jours',
        'sanction_mute_30d' => 'Silence 30 jours',
        'sanction_suspend_7d' => 'Suspension 7 jours',
        'sanction_suspend_30d' => 'Suspension 30 jours',
        'sanction_ban' => 'Exclusion définitive',
    ];

    public function __construct(
        private ContentReportRepository $reportRepository,
        private UserRepository $userRepository,
        private AuditService $auditService,
        private ModerationService $moderationService,
        private CommunityReportNotificationService $communityReportNotificationService,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1 || (int) Session::get('user_id') < 1) {
            return Response::redirect(url('login'));
        }

        $pendingReports = $this->withTargets($this->reportRepository->listPending($tenantId), $tenantId);
        $handledReports = $this->withTargets($this->reportRepository->listHandled($tenantId, 15), $tenantId);
        $ids = array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), array_merge($pendingReports, $handledReports));

        return Response::view('layout.main', [
            'content' => 'admin.reports_moderation',
            'title' => 'Signalements',
            'pendingReports' => $pendingReports,
            'handledReports' => $handledReports,
            'reportTimelines' => $this->reportRepository->timelineByReportIds($tenantId, $ids, 8),
            'canSanctionMembers' => self::maySanctionMembers(),
        ]);
    }

    public function handleReport(Request $request, array $params = []): Response
    {
        [$tenantId, $userId, $report, $error] = $this->loadReport($request, $params);
        if ($error !== null) {
            return $error;
        }
        if ((string) ($report['status'] ?? '') !== 'pending') {
            return $this->back('error', 'Ce dossier est déjà clos.');
        }
        $id = (int) $report['id'];

        $followUp = strtolower(trim((string) $request->input('follow_up', 'close')));
        if (!isset(self::FOLLOW_UPS[$followUp]) && !isset(self::SANCTIONS[$followUp])) {
            return $this->back('error', 'Cette mesure n’est pas disponible.');
        }
        if ($followUp === 'sanction_ban' && (string) $request->input('confirm_permanent_ban') !== '1') {
            return $this->back('error', 'Pour une exclusion définitive, confirmez l’action dans le formulaire.');
        }
        $note = mb_substr(trim((string) $request->input('moderator_note', '')), 0, 500);

        $outcome = null;
        try {
            if (isset(self::SANCTIONS[$followUp])) {
                $outcome = $this->applySanction($followUp, $report, $tenantId, $userId, $note);
            }
            $this->logModeration($tenantId, $userId, $id, $followUp, $note);
        } catch (\Throwable) {
            return $this->back('error', 'La mesure n’a pas pu être enregistrée. Vérifiez les droits ou le membre visé.');
        }

        $this->reportRepository->markHandled($id, $tenantId, $userId);
        $this->reportRepository->saveResolution($id, $tenantId, $followUp, $note);
        $label = $followUp === 'close'
            ? 'Dossier clôturé sans mesure supplémentaire'
            : 'Dossier clôturé — ' . (self::FOLLOW_UPS[$followUp] ?? self::SANCTIONS[$followUp]);
        $this->timeline($tenantId, $id, $userId, 'report_closed', $label, $note);

        try {
            $this->communityReportNotificationService->notifyReportHandled($tenantId, $id, (int) ($report['reporter_id'] ?? 0), $userId);
        } catch (\Throwable) {
        }

        return $this->back('success', trim(($outcome ?? '') . ' Dossier clôturé.'));
    }

    /**
     * Remet un dossier clos dans la file d’attente et alerte l’équipe (e-mails selon préférences).
     */
    public function reopenReport(Request $request, array $params = []): Response
    {
        [$tenantId, $userId, $report, $error] = $this->loadReport($request, $params);
        if ($error !== null) {
            return $error;
        }
        if ((string) ($report['status'] ?? '') !== 'handled') {
            return $this->back('error', 'Ce dossier n’est pas clos.');
        }
        $id = (int) $report['id'];
        $note = mb_substr(trim((string) $request->input('reopen_note', '')), 0, 500);

        if (!$this->reportRepository->reopenToPending($id, $tenantId)) {
            return $this->back('error', 'La réouverture n’a pas pu être enregistrée.');
        }
        $this->timeline($tenantId, $id, $userId, 'report_reopened', 'Dossier rouvert et remis en file d’attente', $note);

        $reason = trim((string) ($report['reason'] ?? ''));
        try {
            $this->communityReportNotificationService->notifyReportReopened(
                $tenantId,
                $id,
                $userId,
                (int) ($report['reporter_id'] ?? 0),
                $reason !== '' ? $reason : 'Dossier rouvert',
                $request->input('notify_reporter') === '1',
                $note !== '' ? $note : null
            );
        } catch (\Throwable) {
        }

        return $this->back('success', 'Le dossier a été rouvert. Les modérateurs habilités ont été alertés.');
    }

    public function claimReport(Request $request, array $params = []): Response
    {
        return $this->setClaimState($request, $params, true);
    }

    public function unclaimReport(Request $request, array $params = []): Response
    {
        return $this->setClaimState($request, $params, false);
    }

    public function addReportComment(Request $request, array $params = []): Response
    {
        [$tenantId, $userId, $report, $error] = $this->loadReport($request, $params);
        if ($error !== null) {
            return $error;
        }
        $comment = mb_substr(trim((string) $request->input('timeline_comment', '')), 0, 1200);
        if ($comment === '') {
            return $this->back('error', 'Le commentaire est vide.');
        }
        try {
            $this->reportRepository->addTimelineEvent($tenantId, (int) $report['id'], $userId, 'comment', 'Commentaire modération', $comment);
        } catch (\Throwable) {
            return $this->back('error', 'Impossible d’enregistrer le commentaire.');
        }

        return $this->back('success', 'Commentaire ajouté au dossier.');
    }

    private function setClaimState(Request $request, array $params, bool $claim): Response
    {
        [$tenantId, $userId, $report, $error] = $this->loadReport($request, $params);
        if ($error !== null) {
            return $error;
        }
        if ((string) ($report['status'] ?? '') !== 'pending') {
            return $this->back('error', 'Dossier non disponible.');
        }
        $id = (int) $report['id'];
        $ok = $claim
            ? $this->reportRepository->assignTo($id, $tenantId, $userId)
            : $this->reportRepository->unassign($id, $tenantId);
        if (!$ok) {
            return $this->back('error', 'Action impossible sur l’affectation.');
        }
        $this->timeline(
            $tenantId,
            $id,
            $userId,
            $claim ? 'claimed' : 'released',
            $claim ? 'Dossier pris en charge' : 'Dossier remis dans la file',
            ''
        );

        return $this->back('success', $claim ? 'Dossier pris en charge.' : 'Affectation retirée.');
    }

    /**
     * @return array{0: int, 1: int, 2: array<string, mixed>, 3: ?Response}
     */
    private function loadReport(Request $request, array $params): array
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return [0, 0, [], Response::redirect(url('login'))];
        }
        if ($request->method() !== 'POST' || !Csrf::validate((string) $request->input('_csrf_token'))) {
            return [0, 0, [], $this->back('error', 'Requête invalide.')];
        }
        $report = $this->reportRepository->findById((int) ($params['id'] ?? 0), $tenantId);
        if ($report === null) {
            return [0, 0, [], $this->back('error', 'Dossier introuvable.')];
        }

        return [$tenantId, $userId, $report, null];
    }

    /**
     * Applique une sanction au membre visé par le signalement. Renvoie le message de confirmation.
     */
    private function applySanction(string $action, array $report, int $tenantId, int $actorUserId, string $note): string
    {
        if (!self::maySanctionMembers()) {
            throw new \RuntimeException('sanction denied');
        }
        $targetId = moderation_report_resolve_target_user_id($report);
        if ($targetId === null || $targetId < 1 || $targetId === $actorUserId || $targetId === (int) ($report['reporter_id'] ?? 0)) {
            throw new \RuntimeException('invalid target');
        }
        if (!$this->userRepository->findById($targetId, $tenantId)) {
            throw new \RuntimeException('user missing');
        }
        [$type, $expires] = match ($action) {
            'sanction_warn' => ['warn', null],
            'sanction_mute_24h' => ['mute', new \DateTimeImmutable('+24 hours')],
            'sanction_mute_7d' => ['mute', new \DateTimeImmutable('+7 days')],
            'sanction_mute_30d' => ['mute', new \DateTimeImmutable('+30 days')],
            'sanction_suspend_7d' => ['suspend', new \DateTimeImmutable('+7 days')],
            'sanction_suspend_30d' => ['suspend', new \DateTimeImmutable('+30 days')],
            'sanction_ban' => ['ban', null],
            default => throw new \InvalidArgumentException('sanction'),
        };
        $reason = 'Suite au signalement n° ' . (int) $report['id'] . '.' . ($note !== '' ? "\n" . $note : '');
        $this->moderationService->applySanction($tenantId, $actorUserId, $targetId, $type, $reason, $expires, []);

        return match ($type) {
            'warn' => 'Un avertissement formel a été enregistré sur la fiche du membre.',
            'mute' => 'Un silence temporaire a été appliqué sur le compte du membre visé.',
            'suspend' => 'Une suspension temporaire a été appliquée sur le compte du membre visé.',
            default => 'Une exclusion définitive a été enregistrée sur le compte du membre visé.',
        };
    }

    private function logModeration(int $tenantId, int $actorUserId, int $reportId, string $followUp, string $note): void
    {
        if ($followUp === 'close') {
            return;
        }
        $payload = ['report_id' => $reportId, 'follow_up' => $followUp];
        if ($note !== '') {
            $payload['detail'] = $note;
        }
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->auditService->log(
            AuditAction::REPORT_MODERATION,
            $tenantId,
            $actorUserId,
            'content_report_resolution',
            $reportId,
            null,
            $json !== false ? $json : $followUp
        );
    }

    private function timeline(int $tenantId, int $reportId, int $userId, string $type, string $label, string $detail): void
    {
        try {
            $this->reportRepository->addTimelineEvent($tenantId, $reportId, $userId, $type, $label, $detail !== '' ? $detail : null);
        } catch (\Throwable) {
        }
    }

    /**
     * @param list<array<string, mixed>> $reports
     * @return list<array<string, mixed>>
     */
    private function withTargets(array $reports, int $tenantId): array
    {
        $names = [];
        foreach ($reports as &$report) {
            $targetId = moderation_report_resolve_target_user_id($report);
            $report['target_user_id'] = $targetId;
            if ($targetId !== null && $targetId > 0) {
                if (!array_key_exists($targetId, $names)) {
                    $user = $this->userRepository->findById($targetId, $tenantId);
                    $names[$targetId] = $user ? trim((string) ($user['display_name'] ?? '')) : '';
                }
                $report['target_name'] = $names[$targetId] !== '' ? $names[$targetId] : 'Membre nº ' . $targetId;
            }
        }
        unset($report);

        return $reports;
    }

    private static function maySanctionMembers(): bool
    {
        return function_exists('can') && can('admin.members.moderate');
    }

    private function back(string $type, string $message): Response
    {
        Session::flash($type, $message);

        return Response::redirect(url('back-office/signalements'));
    }
}
