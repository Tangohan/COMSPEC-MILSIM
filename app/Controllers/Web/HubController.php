<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Authorization\DashboardPinsAccess;
use App\Core\Container;
use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\CommunityEcosystemRepository;
use App\Repositories\TenantRepository;
use App\Services\Alerts\AlertPresentationService;
use App\Services\Ecosystem\CommunityStatsService;
use App\Support\AlertDisplayStyle;
use App\Support\CooperationAccess;
use App\Support\TrainingQuickAccess;

/**
 * Hub inter-communautés : découvrir et comparer les communautés Athena, les recommander,
 * évaluer les partenaires après une coopération et publier ou répondre à un besoin (bourse aux coopérations).
 */
class HubController
{
    private const SORTS = ['members', 'activity', 'units', 'ops_per_month', 'sse_records', 'cooperations', 'recommendations', 'rating', 'age_days', 'name'];

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = $this->currentTenantId();
        $userId = (int) Session::get('user_id');
        $stats = $this->stats();
        $rows = $stats->all();
        $mine = $tenantId > 0 ? $stats->forTenant($tenantId) : null;

        $sort = (string) $request->query('tri', 'members');
        if (!in_array($sort, self::SORTS, true)) {
            $sort = 'members';
        }
        $rows = $this->sortRows($rows, $sort);
        $ranks = [];
        if ($mine !== null) {
            foreach (array_keys(CommunityStatsService::METRICS) as $metric) {
                $ranks[$metric] = CommunityStatsService::rank($rows, $metric, $tenantId);
            }
        }

        $games = [];
        foreach ($rows as $row) {
            if ($row['game'] !== '') {
                $games[$row['game']] = $row['game'];
            }
        }
        natcasesort($games);

        $repo = $this->ecosystem();
        $openPosts = $repo->listPosts('open');

        return Response::view('layout.main', [
            'content' => 'hub.index',
            'title' => 'Hub des communautés',
            'hubRows' => $rows,
            'hubMine' => $mine,
            'hubMedians' => CommunityStatsService::medians($rows),
            'hubRanks' => $ranks,
            'hubSort' => $sort,
            'hubGames' => array_values($games),
            'hubMetrics' => CommunityStatsService::METRICS,
            'hubRecommended' => $repo->recommendedByUser($userId),
            'hubPendingReviews' => $tenantId > 0 && CooperationAccess::canManage() ? $repo->pendingReviews($tenantId) : [],
            'hubBoardPosts' => array_slice($openPosts, 0, 6),
            'hubBoardOpenCount' => count($openPosts),
            'hubCanCooperate' => $tenantId > 0 && CooperationAccess::canCreate(),
            'hubQuickLinks' => $this->quickLinks(),
            'hub_announce_items' => $this->announceItems(),
            'hub_announce_manage_url' => DashboardPinsAccess::canManage(Gate::getInstance()) ? url('back-office/alerts') : null,
            'hubTraining' => TrainingQuickAccess::summary(),
            'hubShell' => $this->shell('home', $mine, count($openPosts), $tenantId > 0 && CooperationAccess::canManage() ? count($repo->pendingReviews($tenantId)) : 0),
            'csrfToken' => Csrf::token(),
            'backOfficePageCss' => ['announce-tiles.css'],
        ]);
    }

    /** Fiche comparée d’une communauté : indicateurs, structure, avis reçus et actions. */
    public function community(Request $request, array $params = []): Response
    {
        $slug = (string) ($params['slug'] ?? '');
        $stats = $this->stats();
        $row = $stats->forSlug($slug);
        if ($row === null) {
            return Response::view('errors.404', ['title' => 'Communauté introuvable'])->setStatusCode(404);
        }
        $tenantId = $this->currentTenantId();
        $rows = $stats->all();
        $mine = $tenantId > 0 ? $stats->forTenant($tenantId) : null;
        $userId = (int) Session::get('user_id');
        $ranks = [];
        $peaks = [];
        foreach (array_keys(CommunityStatsService::METRICS) as $metric) {
            $ranks[$metric] = CommunityStatsService::rank($rows, $metric, (int) $row['id']);
            $peak = 0.0;
            foreach ($rows as $candidate) {
                if (isset($candidate[$metric]) && $candidate[$metric] !== null) {
                    $peak = max($peak, (float) $candidate[$metric]);
                }
            }
            $peaks[$metric] = $peak;
        }

        return Response::view('layout.main', [
            'content' => 'hub.community',
            'title' => $row['name'] . ' — hub des communautés',
            'hubRow' => $row,
            'hubMine' => $mine !== null && $mine['id'] !== $row['id'] ? $mine : null,
            'hubIsOwn' => $mine !== null && $mine['id'] === $row['id'],
            'hubMedians' => CommunityStatsService::medians($rows),
            'hubMetrics' => CommunityStatsService::METRICS,
            'hubRanks' => $ranks,
            'hubPeaks' => $peaks,
            'hubNetworkSize' => count($rows),
            'hubStructure' => $stats->structure((int) $row['id'], (bool) $row['orbat_public']),
            'hubReviews' => $this->ecosystem()->reviewsFor((int) $row['id']),
            'hubRecommended' => in_array((int) $row['id'], $this->ecosystem()->recommendedByUser($userId), true),
            'hubCanCooperate' => $tenantId > 0 && CooperationAccess::canCreate() && ($mine === null || $mine['id'] !== $row['id']),
            'hubShell' => $this->shell($mine !== null && $mine['id'] === $row['id'] ? 'fiche' : 'annuaire', $mine),
            'csrfToken' => Csrf::token(),
        ]);
    }

    /** Comparaison côte à côte de deux communautés (par défaut : la vôtre à gauche). */
    public function compare(Request $request, array $params = []): Response
    {
        $stats = $this->stats();
        $tenantId = $this->currentTenantId();
        $mine = $tenantId > 0 ? $stats->forTenant($tenantId) : null;
        $a = $stats->forSlug((string) $request->query('a', $mine['slug'] ?? '')) ?? $mine;
        $b = $stats->forSlug((string) $request->query('b', ''));
        $rows = $this->sortRows($stats->all(), 'name');

        return Response::view('layout.main', [
            'content' => 'hub.compare',
            'title' => 'Comparer des communautés',
            'hubA' => $a,
            'hubB' => $b,
            'hubStructureA' => $a !== null ? $stats->structure((int) $a['id'], (bool) $a['orbat_public'] || ($mine !== null && $a['id'] === $mine['id'])) : null,
            'hubStructureB' => $b !== null ? $stats->structure((int) $b['id'], (bool) $b['orbat_public'] || ($mine !== null && $b['id'] === $mine['id'])) : null,
            'hubRows' => $rows,
            'hubMetrics' => CommunityStatsService::METRICS,
            'hubMedians' => CommunityStatsService::medians($rows),
            'hubShell' => $this->shell('comparer', $mine),
        ]);
    }

    public function recommend(Request $request, array $params = []): Response
    {
        $back = $this->backUrl($request);
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide. Rechargez la page.');

            return Response::redirect($back);
        }
        $userId = (int) Session::get('user_id');
        $target = $this->stats()->forSlug((string) ($params['slug'] ?? ''));
        if ($userId < 1 || $target === null) {
            return Response::redirect($back);
        }
        $tenantId = $this->currentTenantId();
        if ($tenantId === (int) $target['id']) {
            Session::flash('error', 'Vous ne pouvez pas recommander votre propre communauté.');

            return Response::redirect($back);
        }
        $on = $this->ecosystem()->toggleRecommendation((int) $target['id'], $userId, $tenantId > 0 ? $tenantId : null);
        $this->stats()->forgetCache();
        Session::flash('success', $on ? 'Recommandation enregistrée pour ' . $target['name'] . '.' : 'Recommandation retirée.');

        return Response::redirect($back);
    }

    public function review(Request $request, array $params = []): Response
    {
        $back = url('hub') . '#avis';
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide. Rechargez la page.');

            return Response::redirect($back);
        }
        $tenantId = $this->currentTenantId();
        $missionId = (int) $request->input('mission_id', 0);
        $reviewed = (int) $request->input('tenant_id', 0);
        if ($tenantId < 1 || !CooperationAccess::canManage() || !$this->ecosystem()->canReview($missionId, $tenantId, $reviewed)) {
            Session::flash('error', 'Cette coopération ne peut pas être évaluée par votre communauté.');

            return Response::redirect($back);
        }
        $score = static function (mixed $v): ?int {
            $n = (int) $v;

            return $n >= 1 && $n <= 5 ? $n : null;
        };
        $rating = $score($request->input('rating'));
        if ($rating === null) {
            Session::flash('error', 'Choisissez une note globale entre 1 et 5.');

            return Response::redirect($back);
        }
        $this->ecosystem()->saveReview($missionId, $tenantId, $reviewed, (int) Session::get('user_id'), [
            'rating' => $rating,
            'reliability' => $score($request->input('reliability')),
            'organisation' => $score($request->input('organisation')),
            'ambiance' => $score($request->input('ambiance')),
            'comment' => mb_substr(trim((string) $request->input('comment', '')), 0, 2000),
        ]);
        $this->stats()->forgetCache();
        Session::flash('success', 'Merci : votre avis est publié sur la fiche de la communauté partenaire.');

        return Response::redirect($back);
    }

    public function board(Request $request, array $params = []): Response
    {
        $tenantId = $this->currentTenantId();
        $repo = $this->ecosystem();
        $scope = (string) $request->query('vue', 'ouverts') === 'tous' ? 'all' : 'open';

        return Response::view('layout.main', [
            'content' => 'hub.board',
            'title' => 'Bourse aux coopérations',
            'boardPosts' => $repo->listPosts($scope),
            'boardScope' => $scope,
            'boardMyPosts' => $tenantId > 0 ? $repo->postsOfTenant($tenantId) : [],
            'boardMyResponses' => $repo->responseStatusesOfTenant($tenantId),
            'boardTenantId' => $tenantId,
            'boardCanPublish' => $tenantId > 0 && CooperationAccess::canCreate(),
            'boardCanRespond' => $tenantId > 0 && CooperationAccess::canRespond(),
            'boardNeedTypes' => CommunityEcosystemRepository::NEED_TYPES,
            'boardReady' => $repo->tableExists('cooperation_board_posts'),
            'hubShell' => $this->shell('bourse', $tenantId > 0 ? $this->stats()->forTenant($tenantId) : null),
            'csrfToken' => Csrf::token(),
        ]);
    }

    public function boardStore(Request $request, array $params = []): Response
    {
        $back = url('hub/bourse');
        $tenantId = $this->currentTenantId();
        if (!Csrf::validate($request->input('_csrf_token')) || $tenantId < 1 || !CooperationAccess::canCreate()) {
            Session::flash('error', 'Publication refusée : il faut un rôle autorisé à créer des coopérations.');

            return Response::redirect($back);
        }
        $title = trim((string) $request->input('title', ''));
        if (mb_strlen($title) < 5 || mb_strlen($title) > 160) {
            Session::flash('error', 'Le titre doit faire entre 5 et 160 caractères.');

            return Response::redirect($back);
        }
        $type = (string) $request->input('need_type', 'renfort');
        if (!array_key_exists($type, CommunityEcosystemRepository::NEED_TYPES)) {
            $type = 'autre';
        }
        $startsAt = trim((string) $request->input('starts_at', ''));
        $startsTs = $startsAt !== '' ? strtotime($startsAt) : false;
        $slots = (int) $request->input('slots', 0);
        $id = $this->ecosystem()->createPost($tenantId, (int) Session::get('user_id'), [
            'title' => $title,
            'need_type' => $type,
            'game_label' => mb_substr(trim((string) $request->input('game_label', '')), 0, 80),
            'starts_at' => $startsTs !== false ? date('Y-m-d H:i:s', $startsTs) : null,
            'slots' => $slots > 0 ? min($slots, 999) : null,
            'details' => mb_substr(trim((string) $request->input('details', '')), 0, 4000),
        ]);
        Session::flash('success', 'Besoin publié : les autres communautés peuvent maintenant y répondre.');

        return Response::redirect($back . '#besoin-' . $id);
    }

    public function boardRespond(Request $request, array $params = []): Response
    {
        $back = url('hub/bourse');
        $tenantId = $this->currentTenantId();
        $post = $this->ecosystem()->findPost((int) ($params['id'] ?? 0));
        if (!Csrf::validate($request->input('_csrf_token')) || $tenantId < 1 || $post === null || !CooperationAccess::canRespond()) {
            Session::flash('error', 'Réponse impossible : il faut un rôle autorisé à répondre aux coopérations.');

            return Response::redirect($back);
        }
        if ((int) $post['tenant_id'] === $tenantId || (string) $post['status'] !== 'open') {
            Session::flash('error', 'Ce besoin n’accepte pas de réponse de votre part.');

            return Response::redirect($back);
        }
        if ((string) $request->input('action', '') === 'withdraw') {
            $this->ecosystem()->withdrawResponse((int) $post['id'], $tenantId);
            Session::flash('success', 'Votre proposition est retirée.');

            return Response::redirect($back . '#besoin-' . (int) $post['id']);
        }
        $slots = (int) $request->input('slots', 0);
        $this->ecosystem()->respond(
            (int) $post['id'],
            $tenantId,
            (int) Session::get('user_id'),
            mb_substr(trim((string) $request->input('message', '')), 0, 2000),
            $slots > 0 ? min($slots, 999) : null
        );
        Session::flash('success', 'Proposition envoyée à ' . $post['tenant_name'] . '.');

        return Response::redirect($back . '#besoin-' . (int) $post['id']);
    }

    public function boardDecide(Request $request, array $params = []): Response
    {
        $back = url('hub/bourse') . '#mes-besoins';
        $tenantId = $this->currentTenantId();
        $repo = $this->ecosystem();
        $response = $repo->findResponse((int) ($params['id'] ?? 0));
        $post = $response !== null ? $repo->findPost((int) $response['post_id']) : null;
        if (!Csrf::validate($request->input('_csrf_token')) || $post === null || (int) $post['tenant_id'] !== $tenantId || !CooperationAccess::canManage()) {
            Session::flash('error', 'Action refusée.');

            return Response::redirect($back);
        }
        $decision = (string) $request->input('decision', '');
        if ($decision === 'decline') {
            $repo->setResponseStatus((int) $response['id'], 'declined');
            Session::flash('success', 'Proposition déclinée.');

            return Response::redirect($back);
        }
        $repo->setResponseStatus((int) $response['id'], 'accepted');
        Session::flash('success', 'Proposition acceptée. Finalisez la coopération : l’unité partenaire est déjà cochée.');
        $query = http_build_query(['partenaire' => (int) $response['tenant_id'], 'intitule' => (string) $post['title']]);

        return Response::redirect(cooperation_mission_create_url() . '?' . $query);
    }

    public function boardClose(Request $request, array $params = []): Response
    {
        $back = url('hub/bourse') . '#mes-besoins';
        $tenantId = $this->currentTenantId();
        $post = $this->ecosystem()->findPost((int) ($params['id'] ?? 0));
        if (!Csrf::validate($request->input('_csrf_token')) || $post === null || (int) $post['tenant_id'] !== $tenantId || !CooperationAccess::canManage()) {
            Session::flash('error', 'Action refusée.');

            return Response::redirect($back);
        }
        $reopen = (string) $request->input('action', '') === 'reopen';
        $this->ecosystem()->setPostStatus((int) $post['id'], $reopen ? 'open' : 'closed');
        Session::flash('success', $reopen ? 'Besoin rouvert.' : 'Besoin clôturé : il n’apparaît plus dans la bourse.');

        return Response::redirect($back);
    }

    /** Communauté active de la session, ou 0 pour l’espace sans organisation. */
    private function currentTenantId(): int
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 1) {
            return 0;
        }
        try {
            $tenant = Container::get(TenantRepository::class)->findById($tenantId);
        } catch (\Throwable) {
            return 0;
        }
        if ($tenant === null || strtolower((string) ($tenant['slug'] ?? '')) === 'default') {
            return 0;
        }

        return $tenantId;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function sortRows(array $rows, string $sort): array
    {
        usort($rows, static function (array $a, array $b) use ($sort): int {
            if ($sort === 'name') {
                return strnatcasecmp((string) $a['name'], (string) $b['name']);
            }
            $va = $a[$sort] ?? null;
            $vb = $b[$sort] ?? null;
            if ($va === $vb) {
                return strnatcasecmp((string) $a['name'], (string) $b['name']);
            }
            if ($va === null) {
                return 1;
            }
            if ($vb === null) {
                return -1;
            }

            return $vb <=> $va;
        });

        return $rows;
    }

    /** @return list<array{label: string, url: string, description: string}> */
    /**
     * Navigation commune aux pages du hub (rail latéral, barre mobile).
     *
     * @param array<string, mixed>|null $mine
     * @return array{active: string, mine_slug: string, mine_name: string, training: bool, board_open: ?int, reviews: int}
     */
    private function shell(string $active, ?array $mine, ?int $boardOpen = null, int $reviews = 0): array
    {
        return [
            'active' => $active,
            'mine_slug' => (string) ($mine['slug'] ?? ''),
            'mine_name' => (string) ($mine['name'] ?? ''),
            'training' => TrainingQuickAccess::allowed(),
            'board_open' => $boardOpen,
            'reviews' => $reviews,
        ];
    }

    private function quickLinks(): array
    {
        return [
            ['label' => 'Tableau de bord', 'url' => url('dashboard'), 'description' => 'Synthèse du jour'],
            ['label' => 'Centre d’actions', 'url' => url('centre-actions'), 'description' => 'Ce qui demande attention'],
            ['label' => 'Boîte de réception', 'url' => url('boite-reception'), 'description' => 'Messages et notifications'],
            ['label' => 'Mes démarches', 'url' => url('back-office/ma-situation/mes-demarches'), 'description' => 'Absences, demandes, documents'],
            ['label' => 'Coopérations', 'url' => cooperation_mission_index_url(), 'description' => 'Missions avec d’autres unités'],
        ];
    }

    /** @return list<array<string, mixed>> annonces de la communauté (hors bandeau et fenêtres) */
    private function announceItems(): array
    {
        $items = [];
        try {
            foreach (Container::get(AlertPresentationService::class)->forCurrentRequest() as $alert) {
                $style = (string) ($alert['display_style'] ?? 'classic');
                if (AlertDisplayStyle::isNavbarStyle($style) || AlertDisplayStyle::isPopupStyle($style)) {
                    continue;
                }
                $items[] = [
                    'scope' => (string) ($alert['scope'] ?? 'tenant'),
                    'kind' => (string) ($alert['kind'] ?? 'info'),
                    'title' => (string) ($alert['title'] ?? ''),
                    'body' => (string) ($alert['body'] ?? ''),
                    'cta_label' => $alert['cta_label'] ?? null,
                    'cta_url' => $alert['cta_url'] ?? null,
                    'accent_color' => $alert['accent_color'] ?? null,
                    'image_url' => $alert['image_url'] ?? null,
                ];
            }
        } catch (\Throwable) {
            return [];
        }

        return $items;
    }

    private function backUrl(Request $request): string
    {
        // Chemin relatif au site, limité au hub (pas de redirection ouverte).
        $back = (string) $request->input('back', '');

        return preg_match('#^hub(/[a-z0-9/_-]*)?(\?[a-z0-9=&_-]*)?$#i', $back) === 1 ? url($back) : url('hub');
    }

    private function stats(): CommunityStatsService
    {
        return Container::get(CommunityStatsService::class);
    }

    private function ecosystem(): CommunityEcosystemRepository
    {
        return Container::get(CommunityEcosystemRepository::class);
    }
}
