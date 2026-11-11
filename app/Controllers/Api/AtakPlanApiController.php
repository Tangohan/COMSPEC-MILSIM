<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\AtakPlanRepository;
use App\Services\AtakToc\PlanInterop;
use App\Services\AtakToc\PlanService;
use App\Services\AtakToc\PlanValidator;
use App\Services\AtakToc\TocAccess;

/**
 * Plans d'opération (/atak/toc/api/plans…) : liste, création, lecture synchronisée (since + ETag), éléments, phases,
 * réglages, versions, duplication, partage, commentaires, présence, envoi en jeu, import depuis le jeu et JSON,
 * import d'un fichier (GeoJSON, KML, export Athena) en aperçu, ORBAT de la communauté (/atak/toc/api/orbat) et
 * relief du terrain en binaire (/atak/toc/api/terrain/{mapId}/dem).
 * Toute lecture vérifie la communauté et le partage du plan ; toute écriture le jeton CSRF et la limite de débit.
 */
final class AtakPlanApiController extends AtakTocBaseController
{
    private PlanService $plans;

    /** @var callable(int, int): list<array<string, mixed>> */
    private $gamePlans;

    /** Unités renvoyées au plus par /atak/toc/api/orbat. */
    public const ORBAT_MAX_UNITS = 500;

    /** Relief : cellules relevées au minimum pour servir la grille. */
    public const DEM_MIN_CELLS = 9;

    /** Échelon de l'ORBAT du site (UnitMilitarySymbol::ECHELONS) => échelon d'un plan (PlanCatalog::ECHELONS). */
    private const ORBAT_ECHELONS = [
        'equipe' => 'team', 'groupe' => 'squad', 'section_otan' => 'section', 'section' => 'platoon', 'peloton' => 'platoon',
        'compagnie' => 'company', 'escadron' => 'company', 'batterie' => 'company', 'bataillon' => 'battalion',
        'regiment' => 'regiment', 'brigade' => 'brigade',
    ];

    /** Fonction de l'ORBAT du site (UnitMilitarySymbol::FUNCTIONS) => fonction d'unité d'un plan (PlanCatalog::UNIT_TYPES). */
    private const ORBAT_UNIT_TYPES = [
        'infantry' => 'infantry', 'infantry_light' => 'light', 'infantry_motorized' => 'motorized', 'infantry_mechanized' => 'mech',
        'infantry_airborne' => 'airborne', 'infantry_air_assault' => 'airassault', 'infantry_mountain' => 'mountain',
        'armor' => 'armor', 'recon' => 'recon', 'artillery' => 'artillery', 'mortar' => 'mortar', 'anti_armor' => 'antitank',
        'air_defense' => 'airdefense', 'engineer' => 'engineer', 'aviation' => 'aviation', 'aviation_rotary' => 'aviation',
        'aviation_fixed' => 'fixedwing', 'uav' => 'uav', 'signal' => 'signal', 'medical' => 'medical', 'supply' => 'supply',
        'sof' => 'sof', 'special_forces' => 'sof', 'ranger' => 'sof',
    ];

    /** @var callable(int, int): (array<string, mixed>|null) */
    private $orbat;

    /** @var callable(int, int): (array<string, mixed>|null) */
    private $terrain;

    /**
     * @param callable(int, int): list<array<string, mixed>>|null $gamePlans plans du camp remontés par le jeu (tenant, carte)
     * @param callable(int, int): (array<string, mixed>|null)|null $orbat arbre de l'ORBAT vu par un membre (tenant, membre), null s'il n'y a pas accès
     * @param callable(int, int): (array<string, mixed>|null)|null $terrain grille du relief (tenant, carte), comme AtakTerrainRepository::getGrid
     */
    public function __construct(?TocAccess $access = null, ?PlanService $plans = null, ?callable $gamePlans = null, ?callable $orbat = null, ?callable $terrain = null)
    {
        parent::__construct($access);
        $this->plans = $plans ?? new PlanService(new AtakPlanRepository());
        $this->gamePlans = $gamePlans ?? static fn (int $tenantId, int $mapId): array => (new \App\Repositories\AtakBriefingPlanRepository())->recent($tenantId, $mapId);
        // Même source et mêmes règles que la page /orbat : droit de voir l'organigramme, unités masquées retirées
        // selon le lecteur, unités archivées écartées (OrbatRosterPayload).
        $this->orbat = $orbat ?? static function (int $tenantId, int $userId): ?array {
            $gate = \App\Core\Gate::getInstance();
            foreach (['organization.orbat.view', 'organization.orbat.manage', 'admin.organization', 'admin.access', 'site.support'] as $slug) {
                if ($gate->allows($slug)) {
                    return \App\Support\OrbatRosterPayload::buildForTenant(new \App\Repositories\UnitRepository(), $tenantId, $userId);
                }
            }

            return null;
        };
        $this->terrain = $terrain ?? static fn (int $tenantId, int $mapId): ?array => (new \App\Repositories\AtakTerrainRepository())->getGrid($tenantId, $mapId, true);
    }

    private function repo(): AtakPlanRepository
    {
        return $this->plans->repo();
    }

    /**
     * Plan lisible par l'appelant, ou réponse d'erreur (404 : un plan hors de portée n'existe pas pour lui).
     *
     * @return array{0: array<string, mixed>|null, 1: Response|null, 2: bool}
     */
    private function load(array $params, bool $write): array
    {
        $plan = $this->repo()->find($this->tenantId(), (int) ($params['id'] ?? 0));
        if ($plan === null) {
            return [null, Response::json(['ok' => false, 'error' => 'not_found'], 404), false];
        }
        $shares = $this->repo()->shares((int) $plan['id']);
        if (!$this->access->canView($plan, $this->tenantId(), $this->userId(), $shares)) {
            return [null, Response::json(['ok' => false, 'error' => 'not_found'], 404), false];
        }
        $canEdit = $this->access->canEdit($plan, $this->tenantId(), $this->userId(), $shares);
        if ($write && !$canEdit) {
            return [null, Response::json(['ok' => false, 'error' => 'read_only'], 403), false];
        }

        return [$plan, null, $canEdit];
    }

    /** @param array<string, mixed> $plan @return array<string, mixed> */
    private function summary(array $plan): array
    {
        return [
            'id' => $plan['id'], 'title' => $plan['title'], 'world' => $plan['world'], 'map_id' => $plan['map_id'],
            'sharing' => $plan['sharing'], 'unit_id' => $plan['unit_id'], 'is_template' => $plan['is_template'], 'locked' => $plan['locked'],
            'rev' => $plan['rev'], 'owner_user_id' => $plan['owner_user_id'], 'mine' => $plan['owner_user_id'] === $this->userId(),
            'updated_at' => $plan['updated_at'], 'published_at' => $plan['published_at'], 'published_side' => $plan['published_side'],
            'published_count' => $plan['published_count'], 'elements' => $this->repo()->countElements((int) $plan['id']),
        ];
    }

    public function index(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }
        try {
            $units = $this->access->unitsOf($this->tenantId(), $this->userId());
            $all = $this->access->canManage();
            $plans = $this->repo()->listVisible($this->tenantId(), $this->userId(), $units, $all);
            $templates = $this->repo()->listVisible($this->tenantId(), $this->userId(), $units, true, true);
        } catch (\Throwable) {
            return Response::json(['ok' => false, 'error' => 'schema_not_ready'], 503);
        }

        return Response::json([
            'ok' => true,
            'plans' => array_map([$this, 'summary'], $plans),
            'templates' => array_map([$this, 'summary'], $templates),
            'can_manage' => $this->access->canManage(),
        ]);
    }

    /** Création : vide, depuis un modèle (template_id) ou depuis un fichier JSON (import). */
    public function store(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }
        if (($deny = $this->guardWrite($request, 'plan-create', 20)) !== null) {
            return $deny;
        }
        $b = $this->body();
        $tenantId = $this->tenantId();
        $userId = $this->userId();
        if (isset($b['import']) && is_string($b['import'])) {
            [$doc, $why] = PlanValidator::importDocument($b['import']);
            if ($doc === null) {
                return Response::json(['ok' => false, 'error' => $why], 422);
            }
            $mapId = $this->mapIdForWorld($doc['world'], (int) ($b['mapId'] ?? 0));
            $id = $this->repo()->create($tenantId, $userId, $doc['title'], $mapId, $doc['world'], 'private', null, $doc['meta']);
            $plan = $this->repo()->find($tenantId, $id);
            if ($plan !== null) {
                $this->plans->applyDocument($plan, $doc, $userId, false);
            }
            $this->audit('atak_toc.plan.import', $id, ['title' => $doc['title'], 'elements' => count($doc['elements']), 'rejected' => $doc['rejected']]);

            return Response::json(['ok' => true, 'id' => $id, 'rejected' => $doc['rejected']], 201);
        }
        if (!empty($b['template_id'])) {
            $tpl = $this->repo()->find($tenantId, (int) $b['template_id']);
            if ($tpl === null || !$tpl['is_template']) {
                return Response::json(['ok' => false, 'error' => 'not_found'], 404);
            }
            $id = $this->plans->duplicate($tpl, $userId, PlanValidator::text($b['title'] ?? '', 120));
            $this->audit('atak_toc.plan.from_template', $id, ['template_id' => $tpl['id']]);

            return Response::json(['ok' => true, 'id' => $id], 201);
        }
        $title = PlanValidator::text($b['title'] ?? '', 120);
        if ($title === '') {
            return Response::json(['ok' => false, 'error' => 'title_required'], 422);
        }
        $template = !empty($b['is_template']);
        if ($template && !$this->access->canManage()) {
            return Response::json(['ok' => false, 'error' => 'forbidden'], 403);
        }
        $world = PlanValidator::world($b['world'] ?? '');
        $mapId = $this->mapIdForWorld($world, (int) ($b['mapId'] ?? 0));
        $meta = PlanValidator::meta(['side' => $b['side'] ?? 'WEST', 'layers' => [['uid' => 'base', 'name' => 'Plan', 'visible' => true]]]);
        $id = $this->repo()->create($tenantId, $userId, $title, $mapId, $world, $template ? 'community' : 'private', null, $meta, $template);
        $this->audit('atak_toc.plan.create', $id, ['title' => $title, 'world' => $world, 'template' => $template]);

        return Response::json(['ok' => true, 'id' => $id], 201);
    }

    private function mapIdForWorld(string $world, int $mapId): int
    {
        try {
            $repo = new \App\Repositories\AtakMapRepository();
            if ($mapId > 0 && $repo->getById($mapId) !== null) {
                return $mapId;
            }
            if ($world !== '') {
                foreach ($repo->getAll() as $m) {
                    if (strtolower((string) ($m['slug'] ?? '')) === $world || strtolower((string) ($m['world_name'] ?? '')) === $world) {
                        return (int) $m['id'];
                    }
                }
            }
        } catch (\Throwable) {
        }

        return max(1, $mapId);
    }

    /** Lecture : tout le plan, ou seulement ce qui a changé depuis ?since= (ms, horloge du serveur). */
    public function show(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }
        [$plan, $err, $canEdit] = $this->load($params, false);
        if ($plan === null) {
            return $err ?? Response::json(['ok' => false], 404);
        }
        $planId = (int) $plan['id'];
        $since = max(0, (int) $request->query('since', 0));
        $tool = preg_replace('/[^a-z_]/', '', (string) $request->query('tool', '')) ?? '';
        $presence = $this->repo()->touchPresence($planId, $this->userId(), $this->actorLabel(), substr($tool, 0, 24));
        $last = $this->repo()->lastChangeMs($planId);
        $version = $planId . '-' . $plan['rev'] . '-' . $last . '-' . $since . '-' . md5(json_encode($presence) ?: '');

        return $this->jsonWithEtag([
            'ok' => true,
            'plan' => $this->summary($plan) + ['meta' => $plan['meta'], 'share_token' => $this->access->canAdminister($plan, $this->tenantId(), $this->userId()) ? $plan['share_token'] : null],
            'can_edit' => $canEdit,
            'can_admin' => $this->access->canAdminister($plan, $this->tenantId(), $this->userId()),
            'elements' => $this->repo()->elements($planId, $since, $since > 0),
            'phases' => $this->repo()->phases($planId),
            'presence' => $presence,
            'shares' => $this->repo()->shares($planId),
            'server_ms' => max($last, $since),
            'full' => $since === 0,
        ], substr(sha1($version), 0, 20));
    }

    public function update(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }
        [$plan, $err] = $this->load($params, true);
        if ($plan === null) {
            return $err ?? Response::json(['ok' => false], 404);
        }
        if (($deny = $this->guardWrite($request, 'plan-meta', 60)) !== null) {
            return $deny;
        }
        $b = $this->body();
        $admin = $this->access->canAdminister($plan, $this->tenantId(), $this->userId());
        $fields = [];
        if (isset($b['title'])) {
            $t = PlanValidator::text($b['title'], 120);
            if ($t !== '') {
                $fields['title'] = $t;
            }
        }
        if (isset($b['meta'])) {
            $fields['meta'] = PlanValidator::meta($b['meta']);
        }
        if ($admin && isset($b['sharing']) && in_array($b['sharing'], PlanValidator::SHARING, true)) {
            $fields['sharing'] = $b['sharing'];
            $fields['unit_id'] = null;
            if ($b['sharing'] === 'unit') {
                // Unité du partage : celle demandée si le membre en fait partie, sinon sa première unité.
                $mine = $this->access->unitsOf($this->tenantId(), $this->userId());
                $unitId = (int) ($b['unit_id'] ?? 0);
                $fields['unit_id'] = in_array($unitId, $mine, true) ? $unitId : ($mine[0] ?? null);
                if ($fields['unit_id'] === null) {
                    return Response::json(['ok' => false, 'error' => 'no_unit'], 422);
                }
            }
        }
        if ($admin && array_key_exists('locked', $b)) {
            $fields['locked'] = !empty($b['locked']);
        }
        if ($admin && isset($b['shares']) && is_array($b['shares'])) {
            $shares = [];
            foreach (array_slice($b['shares'], 0, 50) as $s) {
                if (is_array($s) && in_array($s['scope'] ?? '', ['unit', 'user'], true) && (int) ($s['ref_id'] ?? 0) > 0) {
                    $shares[] = ['scope' => (string) $s['scope'], 'ref_id' => (int) $s['ref_id'], 'can_edit' => !empty($s['can_edit'])];
                }
            }
            $this->repo()->replaceShares($this->tenantId(), (int) $plan['id'], $shares);
        }
        $this->repo()->updatePlan($this->tenantId(), (int) $plan['id'], $fields);
        $rev = $this->repo()->bumpRev($this->tenantId(), (int) $plan['id']);
        if (isset($fields['sharing']) || isset($fields['locked'])) {
            $this->audit('atak_toc.plan.settings', (int) $plan['id'], array_intersect_key($fields, ['sharing' => 1, 'unit_id' => 1, 'locked' => 1]));
        }

        return Response::json(['ok' => true, 'rev' => $rev]);
    }

    public function elements(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }
        [$plan, $err] = $this->load($params, true);
        if ($plan === null) {
            return $err ?? Response::json(['ok' => false], 404);
        }
        if (($deny = $this->guardWrite($request, 'plan-elements', 240)) !== null) {
            return $deny;
        }
        $b = $this->body();
        $base = [];
        foreach (is_array($b['base'] ?? null) ? $b['base'] : [] as $uid => $ms) {
            if (PlanValidator::uid($uid) !== null && is_numeric($ms)) {
                $base[(string) $uid] = (int) $ms;
            }
        }
        $res = $this->plans->saveElements(
            $plan,
            is_array($b['upserts'] ?? null) ? array_values($b['upserts']) : [],
            is_array($b['deletes'] ?? null) ? array_values($b['deletes']) : [],
            $this->userId(),
            $base,
            $this->access->canAdminister($plan, $this->tenantId(), $this->userId())
        );

        return Response::json($res);
    }

    public function phases(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }
        [$plan, $err] = $this->load($params, true);
        if ($plan === null) {
            return $err ?? Response::json(['ok' => false], 404);
        }
        if (($deny = $this->guardWrite($request, 'plan-phases', 60)) !== null) {
            return $deny;
        }
        $phases = $this->body()['phases'] ?? null;
        if (!is_array($phases)) {
            return Response::json(['ok' => false, 'error' => 'phases_invalid'], 422);
        }
        $rev = $this->plans->savePhases($plan, array_values($phases));

        return Response::json(['ok' => true, 'rev' => $rev, 'phases' => $this->repo()->phases((int) $plan['id'])]);
    }

    public function versions(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }
        [$plan, $err] = $this->load($params, false);
        if ($plan === null) {
            return $err ?? Response::json(['ok' => false], 404);
        }

        return Response::json(['ok' => true, 'versions' => $this->repo()->versions((int) $plan['id'])]);
    }

    public function saveVersion(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }
        [$plan, $err] = $this->load($params, true);
        if ($plan === null) {
            return $err ?? Response::json(['ok' => false], 404);
        }
        if (($deny = $this->guardWrite($request, 'plan-version', 20)) !== null) {
            return $deny;
        }
        $label = PlanValidator::text($this->body()['label'] ?? '', 120);
        $id = $this->plans->saveVersion($plan, $label !== '' ? $label : 'Version enregistrée', $this->userId());

        return Response::json(['ok' => true, 'id' => $id], 201);
    }

    public function restore(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }
        [$plan, $err] = $this->load($params, true);
        if ($plan === null) {
            return $err ?? Response::json(['ok' => false], 404);
        }
        if (($deny = $this->guardWrite($request, 'plan-version', 20)) !== null) {
            return $deny;
        }
        $vid = (int) ($params['vid'] ?? 0);
        if (!$this->plans->restore($plan, $vid, $this->userId())) {
            return Response::json(['ok' => false, 'error' => 'not_found'], 404);
        }
        $this->audit('atak_toc.plan.restore', (int) $plan['id'], ['version_id' => $vid]);

        return Response::json(['ok' => true]);
    }

    public function duplicate(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }
        [$plan, $err] = $this->load($params, false);
        if ($plan === null) {
            return $err ?? Response::json(['ok' => false], 404);
        }
        if (($deny = $this->guardWrite($request, 'plan-create', 20)) !== null) {
            return $deny;
        }
        $template = !empty($this->body()['as_template']);
        if ($template && !$this->access->canManage()) {
            return Response::json(['ok' => false, 'error' => 'forbidden'], 403);
        }
        $id = $this->plans->duplicate($plan, $this->userId(), PlanValidator::text($this->body()['title'] ?? '', 120), $template);
        $this->audit('atak_toc.plan.duplicate', $id, ['from' => $plan['id'], 'template' => $template]);

        return Response::json(['ok' => true, 'id' => $id], 201);
    }

    public function destroy(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }
        [$plan, $err] = $this->load($params, false);
        if ($plan === null) {
            return $err ?? Response::json(['ok' => false], 404);
        }
        if (!$this->access->canAdminister($plan, $this->tenantId(), $this->userId())) {
            return Response::json(['ok' => false, 'error' => 'forbidden'], 403);
        }
        if (($deny = $this->guardWrite($request, 'plan-create', 20)) !== null) {
            return $deny;
        }
        $this->plans->saveVersion($plan, 'Avant suppression', $this->userId());
        $this->repo()->softDelete($this->tenantId(), (int) $plan['id']);
        $this->audit('atak_toc.plan.delete', (int) $plan['id'], ['title' => $plan['title']]);

        return Response::json(['ok' => true]);
    }

    /** Lien de lecture pour les membres de la communauté : créer (create=1) ou retirer. */
    public function shareLink(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }
        [$plan, $err] = $this->load($params, false);
        if ($plan === null) {
            return $err ?? Response::json(['ok' => false], 404);
        }
        if (!$this->access->canAdminister($plan, $this->tenantId(), $this->userId())) {
            return Response::json(['ok' => false, 'error' => 'forbidden'], 403);
        }
        if (($deny = $this->guardWrite($request, 'plan-meta', 30)) !== null) {
            return $deny;
        }
        $token = !empty($this->body()['create']) ? bin2hex(random_bytes(16)) : null;
        $this->repo()->setShareToken($this->tenantId(), (int) $plan['id'], $token);
        $this->audit('atak_toc.plan.share_link', (int) $plan['id'], ['created' => $token !== null]);

        return Response::json(['ok' => true, 'token' => $token, 'url' => $token !== null ? url('atak/toc/plans/lien/' . $token) : null]);
    }

    public function comments(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }
        [$plan, $err] = $this->load($params, false);
        if ($plan === null) {
            return $err ?? Response::json(['ok' => false], 404);
        }
        if ($request->isPost()) {
            if (($deny = $this->guardWrite($request, 'plan-comment', 30)) !== null) {
                return $deny;
            }
            $b = $this->body();
            if (!empty($b['resolve'])) {
                $this->repo()->resolveComment((int) $plan['id'], (int) $b['resolve'], !empty($b['resolved']));
            } else {
                $body = PlanValidator::text($b['body'] ?? '', 2000);
                if ($body === '') {
                    return Response::json(['ok' => false, 'error' => 'text_required'], 422);
                }
                $this->repo()->addComment($this->tenantId(), (int) $plan['id'], $this->userId(), $this->actorLabel(), $body, PlanValidator::uid($b['element_uid'] ?? null));
            }
        }

        return Response::json(['ok' => true, 'comments' => $this->repo()->comments((int) $plan['id'])]);
    }

    /** « Envoyer en jeu » : tout le plan ou une phase, dans le plan partagé d'un camp. */
    public function publish(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }
        [$plan, $err] = $this->load($params, false);
        if ($plan === null) {
            return $err ?? Response::json(['ok' => false], 404);
        }
        if (($deny = $this->guardWrite($request, 'plan-publish', 6)) !== null) {
            return $deny;
        }
        $b = $this->body();
        $side = strtoupper((string) ($b['side'] ?? ($plan['meta']['side'] ?? 'WEST')));
        $phase = PlanValidator::uid($b['phase'] ?? null);
        $res = $this->plans->publish($plan, $side, $phase, $this->userId(), $this->actorLabel());
        $this->audit('atak_toc.plan.publish', (int) $plan['id'], ['side' => $side, 'phase' => $phase, 'sent' => $res['sent'], 'skipped' => $res['skipped']]);

        return Response::json($res, $res['ok'] ? 200 : 422);
    }

    /** « Importer depuis le jeu » : plan du camp dessiné en jeu, prêt à ajouter (aperçu, rien n'est écrit). */
    public function gameImport(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }
        [$plan, $err] = $this->load($params, false);
        if ($plan === null) {
            return $err ?? Response::json(['ok' => false], 404);
        }
        $side = $this->side($request);
        try {
            $gamePlans = ($this->gamePlans)($this->tenantId(), (int) $plan['map_id']);
        } catch (\Throwable) {
            $gamePlans = [];
        }
        $updated = null;
        foreach ($gamePlans as $gp) {
            if (($gp['side'] ?? '') === $side && empty($gp['personal'])) {
                $updated = $gp['updated_at'] ?? null;
            }
        }

        return Response::json([
            'ok' => true,
            'side' => $side,
            'updated_at' => $updated,
            'elements' => PlanService::elementsFromGamePlans($gamePlans, $side, (int) $plan['id']),
        ]);
    }

    /**
     * Import d'un fichier dans un plan : GeoJSON, KML ou export JSON d'Athena. Aperçu seulement : les éléments
     * validés (nouveaux identifiants) sont rendus à l'éditeur, qui les enregistre lui-même (l'import s'annule donc
     * comme toute autre action). Rien n'est écrit ici.
     */
    public function importFile(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }
        [$plan, $err] = $this->load($params, true);
        if ($plan === null) {
            return $err ?? Response::json(['ok' => false], 404);
        }
        if (($deny = $this->guardWrite($request, 'plan-elements', 240)) !== null) {
            return $deny;
        }
        $b = $this->body();
        $format = is_string($b['format'] ?? null) ? strtolower($b['format']) : '';
        $content = $b['content'] ?? null;
        if (!in_array($format, ['geojson', 'kml', 'athena'], true) || !is_string($content) || trim($content) === '') {
            return Response::json(['ok' => false, 'error' => 'import_format'], 422);
        }
        $phaseUids = array_values(array_map('strval', array_column($this->repo()->phases((int) $plan['id']), 'uid')));
        $fit = is_array($b['fit'] ?? null) ? $b['fit'] : $this->worldBox((int) $plan['map_id']);
        [$elements, $rejected, $why] = match ($format) {
            'geojson' => PlanInterop::fromGeoJson($content, $fit, $phaseUids),
            'kml' => PlanInterop::fromKml($content, $fit, $phaseUids),
            default => PlanInterop::fromAthena($content, $phaseUids),
        };
        if ($why !== '') {
            return Response::json(['ok' => false, 'error' => $why], 422);
        }
        $this->audit('atak_toc.plan.import_file', (int) $plan['id'], ['format' => $format, 'elements' => count($elements), 'rejected' => $rejected]);

        return Response::json(['ok' => true, 'elements' => $elements, 'rejected' => $rejected]);
    }

    /**
     * Cadre du terrain entier [0, 0, taille, taille] d'une carte, pour y ramener un fichier en degrés ; null si la
     * taille n'est pas connue (PlanInterop prend alors son cadre par défaut).
     *
     * @return array{0: float, 1: float, 2: float, 3: float}|null
     */
    private function worldBox(int $mapId): ?array
    {
        try {
            $map = $mapId > 0 ? (new \App\Repositories\AtakMapRepository())->getById($mapId) : null;
            $size = is_array($map) ? (float) (\App\Support\AtakOverwatchMapConfig::fromMap($map)['worldSize'] ?? 0) : 0.0;
        } catch (\Throwable) {
            $size = 0.0;
        }

        return $size >= 1000.0 ? [0.0, 0.0, min($size, PlanValidator::COORD_MAX), min($size, PlanValidator::COORD_MAX)] : null;
    }

    /**
     * ORBAT de la communauté, à plat, pour poser ses unités dans un plan : seulement ce que le membre voit déjà sur
     * la page /orbat (unités masquées et archivées retirées), et rien de personnel (ni membres, ni chefs).
     */
    public function orbat(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }
        try {
            $root = ($this->orbat)($this->tenantId(), $this->userId());
        } catch (\Throwable) {
            $root = null;
        }
        $units = [];
        if (is_array($root)) {
            $this->flattenOrbat($root, null, $units);
        }

        return Response::json(['ok' => true, 'available' => is_array($root), 'units' => $units]);
    }

    /**
     * @param array<string, mixed> $node nœud de OrbatRosterPayload
     * @param list<array<string, mixed>> $units
     */
    private function flattenOrbat(array $node, ?int $parentId, array &$units): void
    {
        $id = (int) ($node['unitId'] ?? 0);
        // Racine synthétique et branches masquées (unitId 0) : pas une unité, leurs enfants remontent d'un cran.
        if ($id > 0 && empty($node['isOrbatPlaceholder'])) {
            if (count($units) >= self::ORBAT_MAX_UNITS) {
                return;
            }
            $unit = [
                'id' => $id,
                'parent_id' => $parentId,
                'name' => PlanValidator::text($node['label'] ?? '', 120),
                'short' => PlanValidator::text($node['unitCode'] ?? '', 40),
                'type' => PlanValidator::text($node['structType'] ?? '', 40),
            ];
            $sidc = strtoupper((string) ($node['symbolSidc'] ?? ''));
            if (preg_match('/^[A-Z0-9*-]{10,30}$/', $sidc) === 1) {
                $unit['sidc'] = $sidc;
            }
            $echelon = self::ORBAT_ECHELONS[(string) ($node['echelon'] ?? '')] ?? null;
            if ($echelon !== null) {
                $unit['echelon'] = $echelon;
            }
            $unitType = self::ORBAT_UNIT_TYPES[(string) ($node['symbolFunction'] ?? '')] ?? null;
            if ($unitType !== null) {
                $unit['unitType'] = $unitType;
            }
            $units[] = $unit;
            $parentId = $id;
        }
        foreach (is_array($node['children'] ?? null) ? $node['children'] : [] as $child) {
            if (is_array($child)) {
                $this->flattenOrbat($child, $parentId, $units);
            }
        }
    }

    /**
     * Relief d'un terrain en binaire (contrat plans3d §4) : altitudes int16 little-endian, cols × rows, rangée 0 au
     * sud (index = r * cols + c, comme la grille enregistrée et AtakTerrainMath::heightAt), -32768 = pas de relevé.
     */
    public function dem(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }
        $raw = (string) ($params['mapId'] ?? '');
        if (preg_match('/^[1-9]\d{0,8}$/', $raw) !== 1) {
            return Response::json(['ok' => false, 'error' => 'map_invalid'], 404);
        }
        try {
            $grid = ($this->terrain)($this->tenantId(), (int) $raw);
        } catch (\Throwable) {
            $grid = null;
        }
        $cols = (int) ($grid['cols'] ?? 0);
        $rows = (int) ($grid['rows'] ?? $grid['grid_rows'] ?? 0);
        $blob = is_array($grid) && is_string($grid['heights'] ?? null) ? $grid['heights'] : '';
        $filled = (int) ($grid['filled_cells'] ?? 0);
        if ($cols < 2 || $rows < 2 || $filled < self::DEM_MIN_CELLS || strlen($blob) < 2) {
            return Response::json(['ok' => false, 'error' => 'relief_unavailable'], 404);
        }
        $bytes = $cols * $rows * 2;
        if (strlen($blob) !== $bytes) {
            // Grille incomplète en base : complétée par « pas de relevé », jamais plus longue que cols × rows.
            $blob = substr($blob, 0, $bytes) . \App\Services\Tactical\AtakTerrainMath::emptyBlob((int) max(0, ($bytes - strlen($blob)) / 2));
        }
        $cell = (float) ($grid['cell_m'] ?? 50);
        $etag = '"dem-' . substr(sha1(implode('|', [(int) ($grid['id'] ?? 0), $raw, $cols, $rows, $filled, (string) ($grid['updated_at'] ?? ''), crc32($blob)])), 0, 20) . '"';
        $r = new Response();
        $r->header('ETag', $etag)->header('Cache-Control', 'private, max-age=300');
        if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
            return $r->setStatusCode(304);
        }
        $num = static fn (float $v): string => rtrim(rtrim(sprintf('%.2F', $v), '0'), '.');
        $world = (float) ($grid['world_size'] ?? 0);

        return $r->setStatusCode(200)
            ->header('Content-Type', 'application/octet-stream')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('X-Dem-Cols', (string) $cols)
            ->header('X-Dem-Rows', (string) $rows)
            ->header('X-Dem-Cell', $num($cell))
            ->header('X-Dem-Origin', $num((float) ($grid['origin_x'] ?? 0)) . ',' . $num((float) ($grid['origin_y'] ?? 0)))
            ->header('X-Dem-Min', (string) (int) ($grid['min_z'] ?? 0))
            ->header('X-Dem-Max', (string) (int) ($grid['max_z'] ?? 0))
            ->header('X-Dem-World', $num($world > 0 ? $world : ($cols - 1) * $cell))
            ->header('X-Dem-Coverage', (string) max(0, min(100, (int) round(100 * $filled / ($cols * $rows)))))
            ->setBody($blob);
    }
}
