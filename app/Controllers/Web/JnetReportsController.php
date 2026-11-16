<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Controllers\Admin\AdminAarReportsController;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AarAnnexRepository;
use App\Repositories\AarReportRepository;
use App\Repositories\AarReportTemplateRepository;
use App\Repositories\AtakDebriefRepository;
use App\Repositories\TheatreMissionCycleRepository;
use App\Repositories\UnitRepository;
use App\Services\Aar\AarReportAccess;
use App\Services\Aar\AarReportAdminService as Aar;
use App\Services\Atak\AtakMobileHistoryService;

/**
 * JNET — comptes rendus (/jnet/comptes-rendus) : les comptes rendus de la page du back-office /back-office/atak/comptes-rendus,
 * dans l'extranet. Mêmes dépôts, mêmes services et même règle d'accès (AarReportAccess) ; aucune logique n'est recopiée.
 *
 * - « Mes comptes rendus » (tout membre) : ce que le joueur a envoyé depuis son ATAK (AtakMobileHistoryService::sent, ses
 *   seuls envois) et les CR de fin d'opération qui lui ont été imposés (AtakDebriefRepository::forUser, son seul état).
 *   C'est ce que son téléphone lui montre déjà.
 * - « Comptes rendus », « Suivi des actions », « Remontées du terrain » : ceux qui lisent la page du back-office, rien de plus.
 * - Fiche /jnet/comptes-rendus/{id} : même lecture que le back-office ; 404 identique pour un compte rendu absent et pour
 *   un lecteur sans droit (on ne révèle pas qu'il existe).
 * - Actions (statut, actions décidées) : relayées aux méthodes du back-office (droit ATAK « gérer », jeton, journal du
 *   circuit, messages), puis retour sur la fiche JNET. 403 sans le droit de traiter.
 */
final class JnetReportsController
{
    public const PATH = 'jnet/comptes-rendus';

    /** Onglets : clé d'adresse => libellé. « mes » est ouvert à tous, les autres aux lecteurs du back-office. */
    public const TABS = [
        'mes' => 'Mes comptes rendus',
        'rapports' => 'Comptes rendus',
        'actions' => 'Suivi des actions',
        'terrain' => 'Remontées du terrain',
    ];

    public const PERIODS = [0 => 'Toute période', 30 => '30 derniers jours', 90 => '3 derniers mois', 365 => '12 derniers mois'];

    public function __construct(
        private ?JnetPortalController $portal = null,
        private ?AarReportRepository $reports = null,
        private ?AtakMobileHistoryService $history = null,
        private ?AtakDebriefRepository $debriefs = null,
        private ?UnitRepository $units = null,
        private ?AdminAarReportsController $backOffice = null,
    ) {
        $this->portal ??= new JnetPortalController();
        $this->reports ??= new AarReportRepository();
        $this->history ??= new AtakMobileHistoryService();
        $this->debriefs ??= new AtakDebriefRepository();
        $this->units ??= new UnitRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        $ctx = $this->portal->context();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId] = [$ctx['tenant_id'], $ctx['user_id']];
        $canRead = AarReportAccess::canRead();
        $canManage = $canRead && AarReportAccess::canManage();

        $tab = $request->queryString('vue', $canRead ? 'rapports' : 'mes');
        if (!isset(self::TABS[$tab]) || (!$canRead && $tab !== 'mes')) {
            $tab = 'mes';
        }

        $mine = $this->mine($tenantId, $userId);
        $data = [
            'jrTab' => $tab,
            'jrTabs' => $canRead ? self::TABS : ['mes' => self::TABS['mes']],
            'jrCanRead' => $canRead,
            'jrCanManage' => $canManage,
            'jrMine' => $mine,
            'jrMineFilter' => self::mineFilter($request),
            'csrfToken' => Csrf::token(),
        ];

        if ($canRead) {
            $all = $this->withAuthorUnits($tenantId, $this->reports->listForTenant($tenantId, []));
            $filters = self::filters($request);
            $data += [
                'jrAll' => $all,
                'jrReports' => Aar::sort(Aar::filter($all, $filters), $filters['sort']),
                'jrFilters' => $filters,
                'jrCounts' => Aar::statusCounts($all),
                'jrKpis' => Aar::kpis($all),
                'jrAuthors' => Aar::authors($all),
                'jrUnits' => self::unitOptions($all),
            ];
            if ($tab === 'actions') {
                $state = $request->queryString('etat', 'ouvertes');
                $state = in_array($state, ['ouvertes', 'retard', 'closes', 'toutes'], true) ? $state : 'ouvertes';
                $data['jrActionState'] = $state;
                $data['jrActions'] = array_values(array_filter(Aar::actions($all), static fn (array $a): bool => match ($state) {
                    'ouvertes' => $a['state'] === 'open',
                    'retard' => $a['overdue'],
                    'closes' => $a['state'] === 'closed',
                    default => true,
                }));
            }
            if ($tab === 'terrain') {
                // Même lecture que l'onglet « Remontées du terrain » du back-office ; le détail reste sur la page des remontées.
                try {
                    $data['jrField'] = (new \App\Services\Intel\IntelIntakeService())->listItems($tenantId, ['source' => 'cr', 'state' => 'all'], $userId, 300);
                } catch (\Throwable) {
                    $data['jrField'] = [];
                }
            }
        }

        return $this->portal->page('reports', 'Comptes rendus', $data, 'reports', ['jnet_reports.css'], ['jnet_reports.js']);
    }

    public function show(Request $request, array $params = []): Response
    {
        $ctx = $this->portal->context();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $tenantId = $ctx['tenant_id'];
        $id = (int) ($params['id'] ?? 0);
        // Sans le droit de lire, la même réponse qu'un compte rendu absent.
        $report = AarReportAccess::canRead() ? $this->reports->findForTenant($tenantId, $id) : null;
        if ($report === null) {
            return self::notFound();
        }

        $mission = null;
        $missionId = (int) ($report['mission_cycle_id'] ?? 0);
        if ($missionId > 0) {
            try {
                $cycles = new TheatreMissionCycleRepository();
                $row = $cycles->findForTenant($tenantId, $missionId);
                $mission = is_array($row) ? $cycles->present($row) : null;
            } catch (\Throwable) {
                $mission = null;
            }
        }
        $template = null;
        if ((int) ($report['template_id'] ?? 0) > 0) {
            try {
                $template = (new AarReportTemplateRepository())->findForTenant($tenantId, (int) $report['template_id']);
            } catch (\Throwable) {
                $template = null;
            }
        }
        $op = trim((string) ($report['operation_label'] ?? '')) !== '' ? (string) $report['operation_label'] : (string) ($report['title'] ?? 'Compte rendu');
        $author = (int) ($report['author_user_id'] ?? 0);

        return $this->portal->page('report_show', $op, [
            'jrReport' => $this->withAuthorUnits($tenantId, [$report])[0],
            'jrMission' => $mission,
            'jrMissionPdfUrl' => AdminAarReportsController::missionAarPdfUrl($mission),
            'jrTemplate' => $template,
            'jrAnnexes' => (new AarAnnexRepository())->listForReport($tenantId, $id),
            'jrCanManage' => AarReportAccess::canManage(),
            'jrReplyUrl' => url('jnet/courrier/nouveau') . '?' . http_build_query(array_filter(['membre' => $author > 0 && $author !== $ctx['user_id'] ? $author : null, 'cr' => $id])),
            'jrBackOfficeUrl' => url('back-office/atak/comptes-rendus/' . $id),
            'csrfToken' => Csrf::token(),
        ], 'report_show', ['jnet_reports.css'], ['jnet_reports.js']);
    }

    /** POST /jnet/comptes-rendus/{id}/statut : la transition du back-office (mêmes contrôles, même circuit), retour JNET. */
    public function status(Request $request, array $params = []): Response
    {
        return $this->relay($request, $params, 'status', '');
    }

    /** POST /jnet/comptes-rendus/{id}/actions/basculer : clore ou rouvrir une action décidée. */
    public function toggleAction(Request $request, array $params = []): Response
    {
        $back = $request->inputString('retour', '') === 'suivi' ? 'suivi' : 'fiche';

        return $this->relay($request, $params, 'toggleAction', $back);
    }

    /**
     * Pastille : à relire pour un lecteur, CR de fin d'opération à remettre pour un membre (et toujours pour « Mes comptes
     * rendus », $mineOnly). Jamais d'erreur.
     */
    public static function badge(int $tenantId, int $userId, bool $mineOnly = false): int
    {
        try {
            if (!$mineOnly && AarReportAccess::canRead()) {
                return (new AarReportRepository())->countToReview($tenantId);
            }
            $n = 0;
            foreach ((new AtakDebriefRepository())->forUser($tenantId, $userId, null, 60) as $d) {
                $n += !$d['closed'] && in_array($d['state'], ['sent', 'delivered', 'opened'], true) ? 1 : 0;
            }

            return $n;
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Objet et corps d'un message JNET au sujet d'un compte rendu (rédaction ?cr=), pour qui peut le lire ; null sinon.
     *
     * @return array{id: int, subject: string, body: string}|null
     */
    public static function mailReference(int $tenantId, int $reportId): ?array
    {
        if ($reportId < 1 || !AarReportAccess::canRead()) {
            return null;
        }
        try {
            $r = (new AarReportRepository())->findForTenant($tenantId, $reportId);
        } catch (\Throwable) {
            return null;
        }
        if ($r === null) {
            return null;
        }
        $op = trim((string) ($r['operation_label'] ?? '')) !== '' ? (string) $r['operation_label'] : (string) ($r['title'] ?? '');
        $ref = (string) ($r['reference_code'] ?? '');

        return [
            'id' => $reportId,
            'subject' => mb_substr('Compte rendu ' . $ref . ($op !== '' ? ' — ' . $op : ''), 0, 190),
            'body' => 'Au sujet du compte rendu ' . $ref . ' : ' . url(self::PATH . '/' . $reportId) . "\n\n",
        ];
    }

    private function relay(Request $request, array $params, string $method, string $back): Response
    {
        $ctx = $this->portal->context();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $id = (int) ($params['id'] ?? 0);
        // Droit d'abord, sans lire le compte rendu : le refus est le même qu'il existe ou non.
        if (!AarReportAccess::canManage()) {
            return Response::view('errors.403', ['title' => 'Accès refusé'])->setStatusCode(403);
        }
        if ($this->reports->findForTenant($ctx['tenant_id'], $id) === null) {
            return self::notFound();
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Votre session a expiré, rien n’a été modifié.');

            return Response::redirect(url(self::PATH . '/' . $id));
        }
        $controller = $this->backOffice ?? new AdminAarReportsController($this->reports);
        $controller->{$method}($request, ['id' => (string) $id]);

        return Response::redirect($back === 'suivi'
            ? url(self::PATH) . '?vue=actions'
            : url(self::PATH . '/' . $id) . ($method === 'toggleAction' ? '#actions' : ''));
    }

    /**
     * Ce que le joueur a envoyé et reçu à remplir, lui seul.
     *
     * @return array{sent: list<array<string, mixed>>, debriefs: list<array<string, mixed>>}
     */
    private function mine(int $tenantId, int $userId): array
    {
        $steam = '';
        try {
            $user = (new \App\Repositories\UserRepository())->findById($userId, $tenantId);
            $steam = trim((string) ($user['steam_id'] ?? ''));
        } catch (\Throwable) {
        }
        $sent = [];
        try {
            $sent = $this->history->sent(['tenant_id' => $tenantId, 'map_id' => 0, 'user_id' => $userId, 'steam_uid' => $steam], 100);
        } catch (\Throwable) {
        }
        $debriefs = [];
        try {
            $debriefs = $this->debriefs->forUser($tenantId, $userId, $steam !== '' ? $steam : null);
        } catch (\Throwable) {
        }

        return ['sent' => $sent, 'debriefs' => $debriefs];
    }

    /**
     * Unité principale de chaque auteur (filtre « Unité ») : première unité de ses affectations dans la communauté.
     *
     * @param list<array<string, mixed>> $reports
     * @return list<array<string, mixed>>
     */
    private function withAuthorUnits(int $tenantId, array $reports): array
    {
        $labels = [];
        foreach ($reports as $r) {
            $author = (int) ($r['author_user_id'] ?? 0);
            if ($author < 1 || isset($labels[$author])) {
                continue;
            }
            $labels[$author] = '';
            try {
                foreach ($this->units->unitIdsForUser($tenantId, $author) as $unitId) {
                    $unit = $this->units->findById((int) $unitId, $tenantId);
                    $name = trim((string) ($unit['name'] ?? ''));
                    if ($name !== '') {
                        $labels[$author] = $name;
                        break;
                    }
                }
            } catch (\Throwable) {
            }
        }
        foreach ($reports as &$r) {
            $r['author_unit'] = $labels[(int) ($r['author_user_id'] ?? 0)] ?? '';
        }
        unset($r);

        return $reports;
    }

    /**
     * @param list<array<string, mixed>> $reports
     * @return list<string>
     */
    private static function unitOptions(array $reports): array
    {
        $units = array_values(array_unique(array_filter(array_map(static fn (array $r): string => (string) ($r['author_unit'] ?? ''), $reports))));
        sort($units, SORT_NATURAL | SORT_FLAG_CASE);

        return $units;
    }

    /** @return array{q: string, status: string, author: string, unit: string, type: string, period: int, open_actions: bool, sort: string} */
    private static function filters(Request $request): array
    {
        $status = trim($request->queryString('statut', ''));
        $type = trim($request->queryString('type', ''));
        $period = (int) $request->queryString('periode', '0');
        $sort = $request->queryString('tri', 'recent');

        return [
            'q' => mb_substr(trim($request->queryString('q', '')), 0, 120),
            'status' => isset(Aar::STATUSES[$status]) ? $status : '',
            'author' => mb_substr(trim($request->queryString('auteur', '')), 0, 120),
            'unit' => mb_substr(trim($request->queryString('unite', '')), 0, 120),
            'type' => isset(Aar::TYPES[$type]) ? $type : '',
            'period' => isset(self::PERIODS[$period]) ? $period : 0,
            'open_actions' => $request->queryString('actions_ouvertes', '') === '1',
            'sort' => isset(Aar::SORTS[$sort]) ? $sort : 'recent',
        ];
    }

    /** @return array{q: string, kind: string} */
    private static function mineFilter(Request $request): array
    {
        $kind = $request->queryString('envoi', '');

        return [
            'q' => mb_substr(trim($request->queryString('q', '')), 0, 120),
            'kind' => in_array($kind, ['envoyes', 'fin-op'], true) ? $kind : '',
        ];
    }

    private static function notFound(): Response
    {
        return Response::view('errors.404', ['title' => 'Compte rendu introuvable'])->setStatusCode(404);
    }
}
