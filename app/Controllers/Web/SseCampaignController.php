<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseCampaignRepository;
use App\Repositories\TenantRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseCampaignService;

/**
 * Bureau SSE › Campagnes : les campagnes du mod COMSPEC SSE (personnages récurrents,
 * organisations, connaissances, registre des scellés) gérées depuis Athena, et le
 * catalogue des campagnes que les communautés choisissent de partager.
 */
final class SseCampaignController
{
    public function __construct(
        private ?SseCampaignService $service = null,
        private ?SseAccessCodeService $access = null,
        private ?TenantRepository $tenants = null,
    ) {
        $this->service ??= new SseCampaignService();
        $this->access ??= new SseAccessCodeService();
        $this->tenants ??= new TenantRepository();
    }

    private function repo(): SseCampaignRepository
    {
        return $this->service->repository();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        $rows = $this->repo()->listForTenant($tenantId);
        $tenant = $this->tenants->findById($tenantId);
        foreach ($rows as &$row) {
            $row['global_id'] = SseCampaignService::campaignGlobalId($row, $tenant);
        }
        unset($row);

        return $this->view('atak.sse.campaigns', [
            'title' => 'Campagnes',
            'activeNav' => 'campagnes',
            'campaigns' => $rows,
            'sharedCount' => count($this->repo()->sharedCatalog($tenantId)),
        ]);
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        $row = $this->repo()->find($tenantId, (int) ($params['id'] ?? 0));
        if ($row === null) {
            Session::flash('error', 'Campagne introuvable.');

            return Response::redirect(url('atak/sse/campagnes'));
        }
        $tenant = $this->tenants->findById($tenantId);
        $source = null;
        if ((int) ($row['source_campaign_id'] ?? 0) > 0) {
            $source = $this->repo()->findShared((int) $row['source_campaign_id']);
        }

        return $this->view('atak.sse.campaign_show', [
            'title' => 'Campagne ' . ($row['name'] ?: $row['campaign_key']),
            'activeNav' => 'campagnes',
            'row' => $row,
            'globalId' => SseCampaignService::campaignGlobalId($row, $tenant),
            'detail' => $this->service->present($row),
            'history' => $this->repo()->logFor($tenantId, (int) $row['id']),
            'collisions' => $this->repo()->seedCollisions($row),
            'copies' => $this->repo()->copiesCount((int) $row['id']),
            'source' => $source,
            'sourceNewer' => $source !== null && ($source['payload_hash'] ?? '') !== ($row['source_hash'] ?? ''),
            'tab' => (string) $request->query('vue', 'organisations'),
        ]);
    }

    public function catalog(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        $mine = [];
        foreach ($this->repo()->listForTenant($tenantId) as $r) {
            if ((int) ($r['source_campaign_id'] ?? 0) > 0) {
                $mine[(int) $r['source_campaign_id']] = $r;
            }
        }

        return $this->view('atak.sse.campaign_catalog', [
            'title' => 'Campagnes partagées',
            'activeNav' => 'campagnes',
            'catalog' => $this->repo()->sharedCatalog($tenantId),
            'mine' => $mine,
        ]);
    }

    public function import(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard($request)) !== null) {
            return $deny;
        }
        try {
            $r = $this->service->ingest($this->tenantId(), (string) $request->input('text', ''), trim((string) $request->input('key', '')), 'import', $this->actor());
            Session::flash('success', $r['created'] ? 'Campagne ' . $r['key'] . ' ajoutée.' : 'Campagne ' . $r['key'] . ' mise à jour.');

            return Response::redirect(url('atak/sse/campagnes/' . $r['id']));
        } catch (\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        }

        return Response::redirect(url('atak/sse/campagnes'));
    }

    public function action(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/campagnes/' . $id);
        if (($deny = $this->guard($request, $back)) !== null) {
            return $deny;
        }
        $tenantId = $this->tenantId();
        $row = $this->repo()->find($tenantId, $id);
        if ($row === null) {
            Session::flash('error', 'Campagne introuvable.');

            return Response::redirect(url('atak/sse/campagnes'));
        }
        $actor = $this->actor();
        try {
            switch ((string) $request->input('do', '')) {
                case 'edit':
                    $this->service->edit($tenantId, $row, trim((string) $request->input('name', '')), (string) $request->input('type', ''), $actor);
                    Session::flash('success', 'Campagne modifiée. Récupérez-la en jeu pour l’utiliser.');
                    break;
                case 'duplicate':
                    $r = $this->service->duplicate($tenantId, $row, (string) $request->input('new_key', ''), (string) $request->input('new_type', 'temporaire'), trim((string) $request->input('new_name', '')), $actor);
                    Session::flash('success', 'Copie ' . $r['key'] . ' créée : mêmes personnages, rien de découvert.');

                    return Response::redirect(url('atak/sse/campagnes/' . $r['id']));
                case 'reset':
                    $this->service->resetDiscoveries($tenantId, $row, $actor);
                    Session::flash('success', 'Campagne remise à zéro : plus rien n’est connu des joueurs.');
                    break;
                case 'archive':
                    $archived = ($row['status'] ?? 'active') === 'archived';
                    $this->repo()->update($tenantId, $id, ['status' => $archived ? 'active' : 'archived', 'shared' => $archived ? (int) $row['shared'] : 0]);
                    $this->repo()->log($tenantId, $id, $actor, $archived ? 'désarchivage' : 'archivage');
                    Session::flash('success', $archived ? 'Campagne réactivée.' : 'Campagne archivée (retirée du partage).');
                    break;
                case 'share':
                    $on = (string) $request->input('shared', '0') === '1';
                    $scope = (string) $request->input('share_scope', 'structure');
                    if (!isset(SseCampaignService::SHARE_SCOPES[$scope])) {
                        $scope = 'structure';
                    }
                    $note = mb_substr(trim((string) $request->input('share_note', '')), 0, 600);
                    $this->repo()->update($tenantId, $id, ['shared' => $on ? 1 : 0, 'share_scope' => $scope, 'share_note' => $note]);
                    $this->repo()->log($tenantId, $id, $actor, $on ? 'partage' : 'fin du partage', $on ? ($scope === 'complete' ? 'campagne complète' : 'structure seule') : '');
                    Session::flash('success', $on ? 'Campagne publiée dans le catalogue des autres communautés.' : 'Campagne retirée du catalogue.');
                    break;
                case 'pull_source':
                    $source = $this->repo()->findShared((int) ($row['source_campaign_id'] ?? 0));
                    if ($source === null) {
                        throw new \InvalidArgumentException('La campagne d’origine n’est plus partagée.');
                    }
                    $this->service->importShared($tenantId, $source, $actor, (string) $source['name']);
                    Session::flash('success', 'Copie mise à jour depuis la campagne d’origine.');
                    break;
                case 'delete':
                    if (trim((string) $request->input('confirm', '')) !== (string) $row['campaign_key']) {
                        throw new \InvalidArgumentException('Tapez l’identifiant ' . $row['campaign_key'] . ' pour confirmer la suppression.');
                    }
                    $this->repo()->delete($tenantId, $id);
                    Session::flash('success', 'Campagne ' . $row['campaign_key'] . ' supprimée d’Athena (la sauvegarde du serveur de jeu reste intacte).');

                    return Response::redirect(url('atak/sse/campagnes'));
                default:
                    throw new \InvalidArgumentException('Action inconnue.');
            }
        } catch (\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        }

        return Response::redirect($back);
    }

    public function export(Request $request, array $params = []): Response
    {
        $row = $this->repo()->find($this->tenantId(), (int) ($params['id'] ?? 0));
        if ($row === null) {
            return Response::redirect(url('atak/sse/campagnes'));
        }
        $file = 'campagne-sse-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $row['campaign_key']) . '.txt';

        return (new Response())
            ->header('Content-Type', 'text/plain; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="' . $file . '"')
            ->setBody((string) $row['payload']);
    }

    public function catalogImport(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard($request, url('atak/sse/campagnes/partagees'))) !== null) {
            return $deny;
        }
        $source = $this->repo()->findShared((int) ($params['id'] ?? 0));
        if ($source === null || (int) $source['tenant_id'] === $this->tenantId()) {
            Session::flash('error', 'Campagne partagée introuvable.');

            return Response::redirect(url('atak/sse/campagnes/partagees'));
        }
        $origin = $this->tenants->findById((int) $source['tenant_id']);
        $r = $this->service->importShared($this->tenantId(), $source, $this->actor(), (string) ($origin['name'] ?? ''));
        Session::flash('success', $r['updated'] ? 'Votre copie de ' . $r['key'] . ' est à jour.' : 'Campagne copiée sous ' . $r['key'] . '. Récupérez-la en jeu avec le module Zeus « Campagne : récupérer depuis Athena ».');

        return Response::redirect(url('atak/sse/campagnes/' . $r['id']));
    }

    private function guard(Request $request, ?string $back = null): ?Response
    {
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back ?? url('atak/sse/campagnes'));
        }
        if (!$this->canManage()) {
            Session::flash('error', 'Votre habilitation ne permet pas de gérer les campagnes.');

            return Response::redirect($back ?? url('atak/sse/campagnes'));
        }

        return null;
    }

    private function view(string $view, array $data): Response
    {
        $data['isGuest'] = $this->access->isGuest();
        $data['clearanceUntil'] = (int) Session::get(SseAccessCodeService::SESSION_UNTIL, 0);
        $data['guestLabel'] = (string) Session::get('sse_guest_label', '');
        $data['sseTheme'] = sse_ui_theme();
        $data['canManage'] = $this->canManage();
        $data['canGrant'] = function_exists('can') && !$this->access->isGuest() && (can('atak.sse.grant') || can('admin.access'));

        return Response::view($view, $data);
    }

    private function tenantId(): int
    {
        $tenantId = $this->access->tenantId();

        return $tenantId > 0 ? $tenantId : (int) Session::get('tenant_id');
    }

    private function canManage(): bool
    {
        if ($this->access->isGuest()) {
            return false;
        }

        return function_exists('can')
            && (can('atak.sse.case.manage') || can('atak.sse.grant') || can('admin.access'));
    }

    private function actor(): string
    {
        foreach (['callsign', 'display_name', 'sse_guest_label'] as $key) {
            $value = trim((string) (Session::get($key) ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return 'Bureau SSE';
    }
}
