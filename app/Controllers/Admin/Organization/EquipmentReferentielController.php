<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\EquipmentItemDefinitionRepository;
use App\Repositories\PersonnelCareerEventRepository;
use App\Repositories\PersonnelEquipmentAssignmentRepository;
use App\Repositories\UserRepository;
use App\Services\GameEquipment\DotationOverviewService;
use App\Services\GameEquipment\GameItemKinds;
use App\Services\GameEquipment\GameItemUsageExtractor;
use App\Support\AdvancementCodes;

final class EquipmentReferentielController
{
    public function __construct(
        private EquipmentItemDefinitionRepository $definitions,
        private PersonnelEquipmentAssignmentRepository $assignments,
        private UserRepository $users,
        private PersonnelCareerEventRepository $careerEvents,
    ) {
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenant();
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $days = DotationOverviewService::period($request->query('jours', '30'));
        $kind = (string) $request->query('famille', '');
        $kind = isset(GameItemKinds::KINDS[$kind]) ? $kind : '';
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 80);
        $definitions = $this->definitions->listForTenant($tenantId, true);
        $service = $this->overviewService();
        try {
            $issued = $this->assignments->issuedPersonnelByDefinition($tenantId);
        } catch (\Throwable) {
            $issued = [];
        }
        $overview = $service->overview($tenantId, $days, $definitions, $issued);
        $linked = [];
        foreach ($definitions as $d) {
            $c = strtolower(trim((string) ($d['game_class_name'] ?? '')));
            if ($c !== '' && empty($d['archived_at'])) {
                $linked[$c] = $d;
            }
        }
        $kindLabels = [];
        foreach (GameItemKinds::KINDS as $k => $meta) {
            $kindLabels[$k] = $meta['label'];
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.advancement.equipment_index',
            'title' => 'Dotation',
            'isBackOfficeShell' => true,
            'boPageTitle' => 'Dotation',
            'boPageKicker' => 'RÉFÉRENTIELS · DOTATION',
            'boPageSubtitle' => 'Dotation type par fonction comparée à ce que les joueurs portent réellement en jeu, catalogue des objets vus et suivi nominatif du matériel.',
            'definitions' => $definitions,
            'members' => $this->users->listForTenant($tenantId, null, 'active', null, 200, 0, true),
            'overview' => $overview,
            'catalogue' => $service->catalogue($tenantId, $days, $kind, $search),
            'catalogueOptions' => ($kind === '' && $search === '') ? null : $service->catalogue($tenantId, $days, '', '', 600),
            'linkedByClass' => $linked,
            'gameClassReady' => $this->definitions->gameClassReady(),
            'days' => $days,
            'periods' => DotationOverviewService::PERIODS,
            'kind' => $kind,
            'search' => $search,
            'kindLabels' => $kindLabels,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
        ]);
    }

    /**
     * Lie (ou délie) un article du référentiel à un objet vu en jeu.
     */
    public function linkGameClass(Request $request, array $params = []): Response
    {
        $tenantId = $this->post($request);
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $back = url('back-office/referentiels/dotation') . '#articles';
        $id = (int) ($params['id'] ?? 0);
        if ($this->definitions->find($tenantId, $id) === null) {
            Session::flash('error', 'Cet article n’existe plus.');

            return Response::redirect($back);
        }
        $raw = trim((string) $request->input('game_class_name', ''));
        $class = GameItemUsageExtractor::cleanClass($raw);
        if (($raw !== '' && $class === '') || !$this->definitions->setGameClass($tenantId, $id, $class)) {
            Session::flash('error', $this->definitions->gameClassReady()
                ? 'Objet du jeu non reconnu : choisissez-le dans la liste proposée.'
                : 'Le lien avec les objets du jeu sera disponible après la prochaine mise à jour de la base.');

            return Response::redirect($back);
        }
        Session::flash('success', $class !== '' ? 'Article lié à l’objet vu en jeu.' : 'Lien avec l’objet du jeu retiré.');

        return Response::redirect($back);
    }

    private function overviewService(): DotationOverviewService
    {
        return Container::get(DotationOverviewService::class);
    }

    /** Code d’article (40 caractères au plus), dérivé de la classe du jeu et unique dans la communauté. */
    private function uniqueCode(int $tenantId, string $seed): string
    {
        $base = strtoupper(trim(preg_replace('/[^A-Za-z0-9]+/', '_', $seed) ?? '', '_'));
        $base = substr($base !== '' ? $base : 'ARTICLE', 0, 34);
        $code = $base;
        for ($i = 2; $i < 50 && $this->definitions->findByCode($tenantId, $code) !== null; $i++) {
            $code = $base . '_' . $i;
        }

        return $code;
    }

    public function store(Request $request, array $params = []): Response
    {
        $tenantId = $this->post($request);
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $back = url('back-office/referentiels/dotation') . '#articles';
        $gameClass = GameItemUsageExtractor::cleanClass($request->input('game_class_name', ''));
        $code = strtoupper(trim((string) $request->input('code', '')));
        $name = trim((string) $request->input('name', ''));
        if ($gameClass !== '') {
            $existing = $this->definitions->findByGameClass($tenantId, $gameClass);
            if ($existing !== null) {
                Session::flash('error', 'Cet objet est déjà au référentiel : « ' . (string) $existing['name'] . ' ».');

                return Response::redirect($back);
            }
            if ($code === '') {
                $code = $this->uniqueCode($tenantId, $gameClass);
            }
            if ($name === '') {
                $name = $gameClass;
            }
        }
        if ($code === '' || $name === '') {
            Session::flash('error', 'Code et nom obligatoires.');

            return Response::redirect($back);
        }
        if (mb_strlen($code) > 40 || mb_strlen($name) > 180) {
            Session::flash('error', 'Code (40 caractères) ou nom (180 caractères) trop long.');

            return Response::redirect($back);
        }
        if ($this->definitions->findByCode($tenantId, $code) !== null) {
            Session::flash('error', 'Ce code est déjà utilisé par un autre article.');

            return Response::redirect($back);
        }
        $this->definitions->create($tenantId, [
            'code' => $code,
            'name' => $name,
            'category' => $request->input('category'),
            'description' => $request->input('description'),
            'game_class_name' => $gameClass,
        ], (int) Session::get('user_id'));
        Session::flash('success', 'Article de dotation créé.');

        return Response::redirect($back);
    }

    public function archive(Request $request, array $params = []): Response
    {
        $tenantId = $this->post($request);
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $this->definitions->archive($tenantId, (int) ($params['id'] ?? 0));
        Session::flash('success', 'Article archivé.');

        return Response::redirect(url('back-office/referentiels/dotation'));
    }

    public function assign(Request $request, array $params = []): Response
    {
        $tenantId = $this->post($request);
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $personnelId = (int) $request->input('personnel_id', 0);
        $definitionId = (int) $request->input('definition_id', 0);
        if ($personnelId < 1 || $definitionId < 1) {
            Session::flash('error', 'Personnel et article obligatoires.');

            return Response::redirect(url('back-office/referentiels/dotation'));
        }
        $this->assignments->create($tenantId, [
            'personnel_id' => $personnelId,
            'definition_id' => $definitionId,
            'serial_number' => $request->input('serial_number'),
            'status' => AdvancementCodes::EQUIP_ISSUED,
            'assigned_at' => $request->input('assigned_at', date('Y-m-d')),
            'notes' => $request->input('notes'),
        ], (int) Session::get('user_id'));
        $this->careerEvents->record($tenantId, $personnelId, 'equipment_assigned', (int) Session::get('user_id'), [
            'definition_id' => $definitionId,
        ]);
        Session::flash('success', 'Dotation enregistrée.');

        return Response::redirect(url('back-office/referentiels/dotation'));
    }

    public function changeStatus(Request $request, array $params = []): Response
    {
        $tenantId = $this->post($request);
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $status = (string) $request->input('status', '');
        $allowed = [
            AdvancementCodes::EQUIP_ISSUED,
            AdvancementCodes::EQUIP_REPAIR,
            AdvancementCodes::EQUIP_LOST,
            AdvancementCodes::EQUIP_RETURNED,
        ];
        if (!in_array($status, $allowed, true)) {
            Session::flash('error', 'Statut inconnu.');

            return Response::redirect(url('back-office/referentiels/dotation'));
        }
        $this->assignments->changeStatus(
            $tenantId,
            (int) ($params['id'] ?? 0),
            $status,
            trim((string) $request->input('notes', '')) ?: null,
            (int) Session::get('user_id')
        );
        Session::flash('success', 'Statut de dotation mis à jour.');

        return Response::redirect(url('back-office/referentiels/dotation'));
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenant();
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $id = (int) ($params['id'] ?? 0);
        $def = $this->definitions->find($tenantId, $id);
        if ($def === null) {
            return Response::redirect(url('back-office/referentiels/dotation'));
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.advancement.equipment_show',
            'title' => (string) $def['name'],
            'isBackOfficeShell' => true,
            'boPageTitle' => (string) $def['name'],
            'boPageKicker' => 'ORGANISATION · DOTATION',
            'definition' => $def,
            'assignments' => $this->assignments->listForDefinition($tenantId, $id),
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
        ]);
    }

    private function tenant(): int|Response
    {
        $id = (int) Session::get('tenant_id');

        return $id > 0 ? $id : Response::redirect(url('login'));
    }

    private function post(Request $request): int|Response
    {
        $tenantId = $this->tenant();
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/referentiels/dotation'));
        }

        return $tenantId;
    }
}
