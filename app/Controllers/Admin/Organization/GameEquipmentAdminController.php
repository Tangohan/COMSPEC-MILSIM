<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\GameEquipmentRepository;
use App\Repositories\GameItemIconRepository;
use App\Repositories\GameRoleKitRepository;
use App\Services\GameEquipment\GameItemKinds;
use App\Services\GameEquipment\RoleKitLoadout;
use App\Services\GameEquipment\RoleKitService;

/**
 * Back-office : « Équipement des joueurs » (ce que le mod remonte) et « Kits par rôle ».
 */
final class GameEquipmentAdminController
{
    public const PERIODS = [7, 30, 90];

    /** Familles affichées en classement, dans l’ordre de la page. */
    public const TOP_KINDS = [
        'primary', 'handgun', 'secondary', 'vest', 'headgear', 'uniform', 'backpack',
        'optic', 'muzzle', 'pointer', 'nvg', 'radio', 'goggles', 'binocular', 'medical', 'magazine',
    ];

    public function __construct(
        private GameEquipmentRepository $equipment,
        private GameRoleKitRepository $kits,
        private RoleKitService $kitService,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $deny = $this->denyUnlessManage();
        if ($deny !== null) {
            return $deny;
        }
        $tenantId = (int) Session::get('tenant_id');
        $days = (int) $request->query('jours', '30');
        if (!in_array($days, self::PERIODS, true)) {
            $days = 30;
        }
        $ready = $this->equipment->tablesReady();
        $tops = $ready ? $this->equipment->topByKind($tenantId, $days, self::TOP_KINDS, 10) : [];
        $summary = $ready ? $this->equipment->summary($tenantId, $days) : ['players' => 0, 'items' => 0, 'observations' => 0];
        $mods = $ready ? $this->equipment->detectedMods($tenantId, $days) : [];
        $itemMods = $ready ? $this->equipment->itemModsUsage($tenantId, $days) : [];

        return Response::view('layout.main', [
            'title' => 'Équipement des joueurs',
            'content' => 'admin.organization.game_equipment',
            'geReady' => $ready,
            'geDays' => $days,
            'gePeriods' => self::PERIODS,
            'geSummary' => $summary,
            'geTops' => $tops,
            'geKinds' => self::TOP_KINDS,
            'geMods' => $mods,
            'geItemMods' => $itemMods,
            'geModPlayers' => $ready ? $this->equipment->playersWithMods($tenantId, $days) : 0,
            'geKitCount' => count($this->kits->listForTenant($tenantId)),
        ]);
    }

    /**
     * Icône PNG d’un objet (convertie depuis le .paa envoyé par COMSPEC Link). Nom = empreinte SHA-256, servie
     * seulement si elle appartient à la communauté ; contenu immuable, donc mis en cache longtemps.
     */
    public function icon(Request $request, array $params = []): Response
    {
        $deny = $this->denyUnlessManage();
        if ($deny !== null) {
            return $deny;
        }
        $file = (string) ($params['file'] ?? '');
        $notFound = (new Response())->setStatusCode(404)->header('Content-Type', 'text/plain; charset=UTF-8')->setBody('Not found');
        if (preg_match('/^([a-f0-9]{64})\.png$/', $file, $m) !== 1) {
            return $notFound;
        }
        $repo = new GameItemIconRepository();
        if (!$repo->tenantHasHash((int) Session::get('tenant_id'), $m[1])) {
            return $notFound;
        }
        $path = $repo->pathForHash($m[1]);
        $body = is_file($path) ? @file_get_contents($path) : false;
        if (!is_string($body) || $body === '') {
            return $notFound;
        }

        return (new Response())
            ->setStatusCode(200)
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'private, max-age=31536000, immutable')
            ->header('X-Content-Type-Options', 'nosniff')
            ->setBody($body);
    }

    public function kits(Request $request, array $params = []): Response
    {
        $deny = $this->denyUnlessManage();
        if ($deny !== null) {
            return $deny;
        }
        $tenantId = (int) Session::get('tenant_id');

        return Response::view('layout.main', [
            'title' => 'Kits par rôle',
            'content' => 'admin.organization.game_role_kits',
            'kitsReady' => $this->kitService->ready(),
            'kits' => $this->kitService->listForTenant($tenantId),
            'roleOptions' => $this->kitService->roleOptions($tenantId),
            'catalogueSize' => $this->equipment->catalogueCount($tenantId),
            'csrf' => Csrf::token(),
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        return $this->formView(null);
    }

    public function edit(Request $request, array $params = []): Response
    {
        $deny = $this->denyUnlessManage();
        if ($deny !== null) {
            return $deny;
        }
        $kit = $this->kits->find((int) Session::get('tenant_id'), (int) ($params['id'] ?? 0));
        if ($kit === null) {
            Session::flash('error', 'Ce kit n’existe plus.');

            return Response::redirect(url('back-office/kits-roles'));
        }

        return $this->formView($kit);
    }

    public function store(Request $request, array $params = []): Response
    {
        return $this->save($request, null);
    }

    public function update(Request $request, array $params = []): Response
    {
        return $this->save($request, (int) ($params['id'] ?? 0));
    }

    public function delete(Request $request, array $params = []): Response
    {
        $deny = $this->denyUnlessManage();
        if ($deny !== null) {
            return $deny;
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Rechargez la page et recommencez.');

            return Response::redirect(url('back-office/kits-roles'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if ($this->kitService->delete($tenantId, (int) ($params['id'] ?? 0))) {
            Session::flash('success', 'Kit supprimé, et retiré de l’arsenal en jeu.');
        } else {
            Session::flash('error', 'Ce kit n’existe plus.');
        }

        return Response::redirect(url('back-office/kits-roles'));
    }

    /**
     * Republie la tenue d’arsenal d’un kit (après suppression manuelle de la tenue, par exemple).
     */
    public function publish(Request $request, array $params = []): Response
    {
        $deny = $this->denyUnlessManage();
        if ($deny !== null) {
            return $deny;
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Rechargez la page et recommencez.');

            return Response::redirect(url('back-office/kits-roles'));
        }
        $ok = $this->kitService->publish((int) Session::get('tenant_id'), (int) Session::get('user_id'), (int) ($params['id'] ?? 0));
        Session::flash($ok ? 'success' : 'error', $ok ? 'Tenue d’arsenal republiée.' : 'Publication impossible : vérifiez que les tenues d’arsenal sont activées.');

        return Response::redirect(url('back-office/kits-roles'));
    }

    private function save(Request $request, ?int $kitId): Response
    {
        $deny = $this->denyUnlessManage();
        if ($deny !== null) {
            return $deny;
        }
        $back = $kitId !== null ? url('back-office/kits-roles/' . $kitId) : url('back-office/kits-roles/nouveau');
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Rechargez la page et recommencez.');

            return Response::redirect($back);
        }
        if (!$this->kitService->ready()) {
            Session::flash('error', 'Les kits ne sont pas encore disponibles : appliquez les migrations (run-migrations.php).');

            return Response::redirect(url('back-office/kits-roles'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        $slotIn = $request->input('slot', []);
        $slotIn = is_array($slotIn) ? $slotIn : [];
        $cargoIn = $request->input('cargo', []);
        $cargoIn = is_array($cargoIn) ? $cargoIn : [];
        $result = $this->kitService->save($tenantId, $userId, $kitId, [
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'job_role_id' => $request->input('job_role_id'),
            'slot' => $slotIn,
            'cargo' => $cargoIn,
        ]);
        if (!$result['ok']) {
            Session::flash('error', (string) ($result['error'] ?? 'Enregistrement impossible.'));
            Session::flash('kit_form_old', [
                'name' => (string) $request->input('name'),
                'description' => (string) $request->input('description'),
                'job_role_id' => (int) $request->input('job_role_id'),
                'slots' => RoleKitLoadout::normalize($slotIn, $cargoIn),
            ]);

            return Response::redirect($back);
        }
        $warnings = $result['warnings'] ?? [];
        Session::flash('success', $kitId !== null ? 'Kit mis à jour, et sa tenue d’arsenal aussi.' : 'Kit créé et proposé dans l’arsenal en jeu.');
        if ($warnings !== []) {
            Session::flash('warning', implode(' ', $warnings));
        }

        return Response::redirect(url('back-office/kits-roles/' . (int) $result['id']));
    }

    /**
     * @param array<string, mixed>|null $kit
     */
    private function formView(?array $kit): Response
    {
        $deny = $this->denyUnlessManage();
        if ($deny !== null) {
            return $deny;
        }
        $tenantId = (int) Session::get('tenant_id');
        $old = Session::getFlash('kit_form_old');
        $values = [
            'name' => (string) ($kit['name'] ?? ''),
            'description' => (string) ($kit['description'] ?? ''),
            'job_role_id' => (int) ($kit['job_role_id'] ?? 0),
            'slots' => RoleKitLoadout::normalize(is_array($kit['slots'] ?? null) ? $kit['slots'] : []),
        ];
        if (is_array($old)) {
            $values = array_merge($values, $old);
        }
        $catalogue = $this->equipment->catalogue($tenantId, 6000);
        $kinds = [];
        foreach (GameItemKinds::KINDS as $k => $meta) {
            $kinds[$k] = $meta['label'];
        }
        $id = (int) ($kit['id'] ?? 0);

        return Response::view('layout.main', [
            'title' => $kit !== null ? 'Modifier le kit' : 'Nouveau kit',
            'content' => 'admin.organization.game_role_kit_form',
            'kit' => $kit,
            'values' => $values,
            'roleOptions' => $this->kitService->roleOptions($tenantId),
            'catalogue' => $catalogue,
            'kindLabels' => $kinds,
            'kitsReady' => $this->kitService->ready(),
            'formAction' => $id > 0 ? url('back-office/kits-roles/' . $id) : url('back-office/kits-roles'),
            'csrf' => Csrf::token(),
        ]);
    }

    private function denyUnlessManage(): ?Response
    {
        if (!Session::get('user_id')) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization') && !$gate->allows('admin.access')) {
            Session::flash('error', 'Cette page est réservée aux responsables de l’organisation.');

            return Response::redirect(url('back-office'));
        }

        return null;
    }
}
