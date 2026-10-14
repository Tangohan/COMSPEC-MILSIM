<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakModReportRepository;
use App\Services\Auth\AuthService;
use App\Services\Monitoring\AtakModReportGravity as Gravity;
use App\Services\Monitoring\AtakModReportReplyService;

/**
 * Signalements et erreurs remontés par le mod COMSPEC ATAK : suivi, actions groupées, réponse au joueur.
 */
final class AdminAtakModReportsController
{
    private const REDIRECT = 'admin/atak-mod-reports';

    /** Actions groupées : clé du formulaire → statut cible (null = suppression). */
    private const BULK_ACTIONS = [
        'in_progress' => AtakModReportRepository::STATUS_IN_PROGRESS,
        'fixed' => AtakModReportRepository::STATUS_FIXED,
        'dismissed' => AtakModReportRepository::STATUS_DISMISSED,
        'new' => AtakModReportRepository::STATUS_NEW,
        'delete' => null,
    ];

    public function __construct(
        private AtakModReportRepository $modReportRepository,
        private AuthService $authService,
        private ?AtakModReportReplyService $replyService = null,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        [$severityFilter, $statusFilter, $search, $gravityFilter] = $this->filters(
            (string) ($request->query('severity') ?? ''),
            (string) ($request->query('status') ?? ''),
            (string) ($request->query('q') ?? ''),
            (string) ($request->query('gravity') ?? '')
        );
        // Par défaut, les plus graves d'abord ; « recent » pour l'ordre d'arrivée.
        $sort = (string) ($request->query('sort') ?? '') === AtakModReportRepository::SORT_RECENT
            ? AtakModReportRepository::SORT_RECENT
            : AtakModReportRepository::SORT_GRAVITY;
        $rows = $this->modReportRepository->listRecent(200, $severityFilter, $statusFilter, $search, $gravityFilter, $sort);
        $total = $this->modReportRepository->countAll($severityFilter, $statusFilter, $search, $gravityFilter);
        $statusCounts = $this->modReportRepository->countByStatus();
        $steams = array_map(static fn (array $r): string => (string) ($r['steam_uid'] ?? ''), $rows);

        return Response::view('layout.main', [
            'title' => 'Signalements du mod',
            'content' => 'admin.atak-mod-reports.index',
            'isPlatformAdminShell' => true,
            'backOfficePageCss' => ['admin-atak-mod-reports.css'],
            'rows' => $rows,
            'total' => $total,
            'totalAll' => array_sum($statusCounts),
            'severityFilter' => $severityFilter ?? '',
            'statusFilter' => $statusFilter ?? '',
            'searchFilter' => $search ?? '',
            'gravityFilter' => $gravityFilter ?? '',
            'sort' => $sort,
            'statusCounts' => $statusCounts,
            'gravityCounts' => $this->modReportRepository->countOpenByGravity(),
            'linkedAccounts' => $this->modReportRepository->linkedAccountsBySteam($steams),
        ]);
    }

    public function updateStatus(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard($request)) !== null) {
            return $guard;
        }

        $id = (int) $request->input('report_id');
        $status = (string) $request->input('workflow_status');
        if ($this->modReportRepository->updateWorkflowStatus($id, $status)) {
            $label = AtakModReportRepository::statusLabel(
                $this->modReportRepository->normalizeWorkflowStatus($status)
            );
            Session::flash('success', 'Statut mis à jour : ' . $label . '.');
        } else {
            Session::flash('error', 'Rapport introuvable ou statut inchangé.');
        }

        return $this->back($request, $id);
    }

    public function delete(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard($request)) !== null) {
            return $guard;
        }

        $id = (int) $request->input('report_id');
        if ($this->modReportRepository->deleteById($id)) {
            Session::flash('success', 'Rapport supprimé.');
        } else {
            Session::flash('error', 'Rapport introuvable.');
        }

        return $this->back($request);
    }

    /** Corrige la gravité d'un rapport à la main. */
    public function updateGravity(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard($request)) !== null) {
            return $guard;
        }

        $id = (int) $request->input('report_id');
        $gravity = Gravity::normalize((string) $request->input('gravity', ''));
        if ($gravity !== null && $this->modReportRepository->updateGravityMany([$id], $gravity) > 0) {
            Session::flash('success', 'Gravité mise à jour : ' . Gravity::label($gravity) . '.');
        } else {
            Session::flash('error', 'Rapport introuvable ou gravité inchangée.');
        }

        return $this->back($request, $id);
    }

    /**
     * Action groupée sur les rapports cochés (scope=selected) ou sur tout le filtre affiché (scope=filter).
     */
    public function bulk(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard($request)) !== null) {
            return $guard;
        }

        $action = (string) $request->input('bulk_action', '');
        // « gravity » : change la gravité des rapports vers celle choisie dans la liste gravity_to.
        $gravityTarget = $action === 'gravity' ? Gravity::normalize((string) $request->input('gravity_to', '')) : null;
        if ($gravityTarget === null && !array_key_exists($action, self::BULK_ACTIONS)) {
            Session::flash('error', 'Action inconnue.');

            return $this->back($request);
        }

        if ((string) $request->input('scope', 'selected') === 'filter') {
            [$sev, $st, $q, $grav] = $this->filters(
                (string) $request->input('return_severity', ''),
                (string) $request->input('return_status', ''),
                (string) $request->input('return_q', ''),
                (string) $request->input('return_gravity', '')
            );
            $ids = $this->modReportRepository->idsMatching($sev, $st, $q, $grav);
        } else {
            $raw = $request->input('ids', []);
            $ids = is_array($raw) ? array_map('intval', $raw) : [];
        }
        $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > 0));
        if ($ids === []) {
            Session::flash('error', 'Aucun rapport sélectionné.');

            return $this->back($request);
        }

        if ($gravityTarget !== null) {
            $n = $this->modReportRepository->updateGravityMany($ids, $gravityTarget);
            Session::flash('success', $n . ' rapport(s) passé(s) en gravité « ' . Gravity::label($gravityTarget) . ' ».');

            return $this->back($request);
        }

        $status = self::BULK_ACTIONS[$action];
        if ($status === null) {
            $n = $this->modReportRepository->deleteMany($ids);
            Session::flash('success', $n . ' rapport(s) supprimé(s).');

            return $this->back($request);
        }

        $notify = $status === AtakModReportRepository::STATUS_FIXED && (string) $request->input('notify', '') === '1';
        $reports = $notify ? $this->modReportRepository->findByIds($ids) : [];
        $n = $this->modReportRepository->updateWorkflowStatusMany($ids, $status);
        $msg = $n . ' rapport(s) passé(s) en « ' . AtakModReportRepository::statusLabel($status) . ' ».';
        if ($n < count($ids)) {
            $msg .= ' ' . (count($ids) - $n) . ' l’étaient déjà.';
        }
        if ($notify) {
            // Message vide : message « corrigé » type, dans la langue de chaque joueur et adapté à la source.
            $text = trim((string) $request->input('notify_message', ''));
            $stats = $this->replies()->notifyMany($reports, mb_substr($text, 0, 4000), $this->adminName());
            $msg .= ' ' . $this->notifySummary($stats);
        }
        Session::flash('success', $msg);

        return $this->back($request);
    }

    /** Répond au joueur : notification Athena, e-mail, et réponse gardée sur le rapport. */
    public function reply(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard($request)) !== null) {
            return $guard;
        }

        $id = (int) $request->input('report_id');
        $report = $this->modReportRepository->findById($id);
        if ($report === null) {
            Session::flash('error', 'Rapport introuvable.');

            return $this->back($request);
        }
        $message = trim((string) $request->input('reply_message', ''));
        if ($message === '') {
            Session::flash('error', 'Écrivez un message pour le joueur.');

            return $this->back($request, $id);
        }
        $message = mb_substr($message, 0, 4000);

        $steam = trim((string) ($report['steam_uid'] ?? ''));
        $account = $steam !== '' ? ($this->modReportRepository->linkedAccountsBySteam([$steam])[$steam] ?? null) : null;
        $result = $this->replies()->reply(
            $report,
            $message,
            $account,
            (string) $request->input('via_site', '') === '1',
            (string) $request->input('via_email', '') === '1',
            $this->adminName()
        );

        $after = (string) $request->input('then_status', '');
        if ($after !== '') {
            $this->modReportRepository->updateWorkflowStatus($id, $after);
        }

        $sent = [];
        if ($result['site']) {
            $sent[] = 'notification Athena';
        }
        if ($result['email']) {
            $sent[] = 'e-mail';
        }
        if ($sent !== []) {
            Session::flash('success', 'Réponse envoyée au joueur (' . implode(' et ', $sent) . ').');
        } elseif ($account === null) {
            Session::flash('success', 'Réponse enregistrée sur le rapport. Ce joueur n’a pas de compte Athena lié à son Steam : il n’a pas été prévenu.');
        } else {
            Session::flash('error', 'Réponse enregistrée sur le rapport, mais l’envoi au joueur a échoué ou aucun canal n’était coché.');
        }

        return $this->back($request, $id);
    }

    private function replies(): AtakModReportReplyService
    {
        return $this->replyService ??= new AtakModReportReplyService($this->modReportRepository);
    }

    /** @param array{players:int, site:int, email:int, unreachable:int, low_no_email?:int} $stats */
    private function notifySummary(array $stats): string
    {
        $parts = [];
        if ($stats['players'] > 0) {
            $parts[] = $stats['players'] . ' joueur(s) prévenu(s) (' . $stats['site'] . ' sur Athena, ' . $stats['email'] . ' par e-mail)';
        }
        if (($stats['low_no_email'] ?? 0) > 0) {
            $parts[] = $stats['low_no_email'] . ' joueur(s) sans e-mail car leurs rapports sont de gravité faible';
        }
        if ($stats['unreachable'] > 0) {
            $parts[] = $stats['unreachable'] . ' rapport(s) sans compte Athena lié, joueur non prévenu';
        }

        return $parts !== [] ? implode(' ; ', $parts) . '.' : '';
    }

    private function adminName(): string
    {
        $user = $this->authService->user() ?? [];
        $name = trim((string) ($user['callsign'] ?? '')) ?: trim((string) ($user['display_name'] ?? ''));

        return $name !== '' ? $name : trim((string) ($user['email'] ?? 'Administration'));
    }

    private function guard(Request $request): ?Response
    {
        if (!Csrf::validate($request->input('_csrf'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url(self::REDIRECT));
        }
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }

        return null;
    }

    /**
     * @return array{0:?string, 1:?string, 2:?string, 3:?string}
     */
    private function filters(string $severity, string $status, string $search, string $gravity = ''): array
    {
        $severity = trim($severity);
        $status = trim($status);
        $search = trim($search);

        return [
            $severity !== '' ? $severity : null,
            $status !== '' ? $this->modReportRepository->normalizeWorkflowStatus($status) : null,
            $search !== '' ? mb_substr($search, 0, 80) : null,
            Gravity::normalize($gravity),
        ];
    }

    /** Retour à la liste avec les filtres d'origine (et sur la carte du rapport traité). */
    private function back(Request $request, int $anchorId = 0): Response
    {
        $qs = [];
        $fields = ['severity' => 'return_severity', 'status' => 'return_status', 'q' => 'return_q', 'gravity' => 'return_gravity', 'sort' => 'return_sort'];
        foreach ($fields as $key => $field) {
            $v = trim((string) $request->input($field, ''));
            if ($v !== '') {
                $qs[$key] = $v;
            }
        }

        return Response::redirect(
            url(self::REDIRECT)
            . ($qs !== [] ? ('?' . http_build_query($qs)) : '')
            . ($anchorId > 0 ? '#rapport-' . $anchorId : '')
        );
    }
}
