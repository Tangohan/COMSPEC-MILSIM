<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\DataCampaignRepository;
use App\Services\Auth\AuthService;
use App\Services\DataCampaign\DataCampaignService;
use App\Support\DataCampaignBlocks;
use App\Support\DataCampaignLoginPrompt;
use App\Support\DataCampaignScope;
use App\Support\JnetMemberShell;

/**
 * « Fiabilisation des données » dans Ma situation (/jnet/moi/fiabilisation) : le membre relit SA fiche, bloc par bloc,
 * et dit « c'est exact » ou « à corriger ». L'identifiant du membre vient toujours de la session, jamais de la requête :
 * personne ne peut lire ni confirmer la fiche d'un autre. Rien n'est modifié dans la fiche (voir DataCampaignService).
 */
final class MemberDataCampaignController
{
    /** Rend ses pages dans la coque JNET (JnetMemberShell). */
    public const JNET_MOI_READY = true;

    public function __construct(
        private ?AuthService $authService = null,
        private ?DataCampaignService $service = null,
        private ?DataCampaignRepository $repo = null,
    ) {
        $this->authService ??= Container::get(AuthService::class);
        $this->service ??= Container::get(DataCampaignService::class);
        $this->repo ??= Container::get(DataCampaignRepository::class);
    }

    /** GET /jnet/moi/fiabilisation (?etape=identity|…|recap) */
    public function index(Request $request, array $params = []): Response
    {
        $ctx = $this->context();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId] = $ctx;
        $ready = $this->repo->schemaReady();
        $active = $ready ? $this->service->activeFor($tenantId, $userId) : null;
        $view = null;
        $step = '';
        if ($active !== null) {
            $view = $this->service->memberView($tenantId, $userId, $active['campaign'], $active['member']);
            $keys = array_keys($view['blocks']);
            $asked = $request->queryString('etape', '');
            // Arrivé par la connexion (destination gardée en session) : vue d'ensemble d'abord.
            $default = DataCampaignLoginPrompt::continueUrl() !== null ? DataCampaignLoginPrompt::STEP : $this->firstOpenStep($view['blocks']);
            $step = in_array($asked, ['recap', DataCampaignLoginPrompt::STEP], true) || in_array($asked, $keys, true) ? $asked : $default;
        }

        return JnetMemberShell::view($request, [
            'title' => 'Fiabilisation des données',
            'content' => 'admin.member_situation.data_campaign',
            'boPageTitle' => 'Fiabilisation des données',
            'boPageSubtitle' => 'Relisez votre fiche, partie par partie : dites si c’est exact ou ce qui est à corriger. Rien n’est modifié sans validation de l’encadrement.',
            'backOfficePageCss' => ['back-office-member-situation.css', 'back-office-member-dossier.css', 'data-campaign.css'],
            'schemaReady' => $ready,
            'campaign' => $active['campaign'] ?? null,
            'member' => $active['member'] ?? null,
            'campaignView' => $view,
            'step' => $step,
            'history' => $ready ? $this->repo->historyForUser($tenantId, $userId) : [],
            'unitFollow' => $ready && $active !== null ? $this->unitFollow($tenantId, $userId, $active['campaign']) : null,
            'csrfToken' => Csrf::token(),
            'comingFromLogin' => DataCampaignLoginPrompt::continueUrl() !== null,
            'today' => date('Y-m-d'),
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
        ]);
    }

    /** POST /jnet/moi/fiabilisation/repondre */
    public function answer(Request $request, array $params = []): Response
    {
        $ctx = $this->context();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId] = $ctx;
        $back = Response::redirect(jnet_moi_url('fiabilisation'));
        if (!$request->isPost() || !Csrf::validate(is_string($t = $request->input('_csrf_token')) ? $t : null)) {
            Session::flash('error', 'Session expirée. Rechargez la page et réessayez.');

            return $back;
        }
        $block = (string) $request->input('block', '');
        $raw = $request->input('f', []);
        $fields = [];
        foreach (is_array($raw) ? $raw : [] as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $fields[$key] = (string) $value;
            }
        }
        $result = $this->service->answer(
            $tenantId,
            $userId,
            (int) $request->input('campaign', 0),
            $block,
            (string) $request->input('verdict', ''),
            (string) $request->input('message', ''),
            $fields
        );
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);
        $keys = DataCampaignBlocks::keys();
        $next = 'recap';
        if (!$result['ok']) {
            $next = DataCampaignBlocks::isBlock($block) ? $block : 'recap';
        } else {
            $pos = array_search($block, $keys, true);
            $active = $this->service->activeFor($tenantId, $userId);
            $campaignBlocks = $active !== null ? $active['campaign']['blocks'] : $keys;
            $after = array_values(array_filter($campaignBlocks, static fn (string $k): bool => $pos === false || array_search($k, $keys, true) > $pos));
            $next = $after[0] ?? 'recap';
        }

        return Response::redirect(jnet_moi_url('fiabilisation', ['etape' => $next]));
    }

    /** POST /jnet/moi/fiabilisation/confirmer */
    public function confirm(Request $request, array $params = []): Response
    {
        $ctx = $this->context();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId] = $ctx;
        if (!$request->isPost() || !Csrf::validate(is_string($t = $request->input('_csrf_token')) ? $t : null)) {
            Session::flash('error', 'Session expirée. Rechargez la page et réessayez.');

            return Response::redirect(jnet_moi_url('fiabilisation'));
        }
        $result = $this->service->confirm($tenantId, $userId, (int) $request->input('campaign', 0));
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return Response::redirect(jnet_moi_url('fiabilisation', ['etape' => 'recap']));
    }

    /** POST /jnet/moi/fiabilisation/tout-confirmer : toutes les parties lues et complètes sont confirmées, puis la fiche. */
    public function confirmAll(Request $request, array $params = []): Response
    {
        $ctx = $this->context();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId] = $ctx;
        if (!$request->isPost() || !Csrf::validate(is_string($t = $request->input('_csrf_token')) ? $t : null)) {
            Session::flash('error', 'Session expirée. Rechargez la page et réessayez.');

            return Response::redirect(jnet_moi_url('fiabilisation', ['etape' => DataCampaignLoginPrompt::STEP]));
        }
        $result = $this->service->confirmAll($tenantId, $userId, (int) $request->input('campaign', 0));
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);
        if (!$result['ok']) {
            return Response::redirect(jnet_moi_url('fiabilisation', ['etape' => DataCampaignLoginPrompt::STEP]));
        }
        // Fiche confirmée : on reprend la destination d'origine de la connexion, s'il y en a une.
        if (DataCampaignLoginPrompt::continueUrl() !== null) {
            return Response::redirect(DataCampaignLoginPrompt::consumeContinue());
        }

        return Response::redirect(jnet_moi_url('fiabilisation', ['etape' => 'recap']));
    }

    /** GET /jnet/moi/fiabilisation/plus-tard : retour à la destination demandée ; la page reviendra à la prochaine connexion. */
    public function later(Request $request, array $params = []): Response
    {
        $ctx = $this->context();
        if ($ctx instanceof Response) {
            return $ctx;
        }

        return Response::redirect(DataCampaignLoginPrompt::consumeContinue());
    }

    /**
     * @return array{0: int, 1: int}|Response communauté active et membre connecté (tous deux tirés de la session)
     */
    private function context(): array|Response
    {
        $user = $this->authService->user();
        if (!$user) {
            Session::flash('error', 'Connectez-vous pour continuer.');

            return Response::redirect(url('login'));
        }
        $userId = (int) ($user['id'] ?? 0);
        $tenantId = (int) (Session::get('tenant_id') ?? $user['tenant_id'] ?? 0);
        if ($userId < 1 || $tenantId < 1) {
            return Response::redirect(url('login'));
        }

        return [$tenantId, $userId];
    }

    /**
     * Première partie sans réponse (ou récapitulatif quand tout est répondu).
     *
     * @param array<string, array<string, mixed>> $blocks
     */
    private function firstOpenStep(array $blocks): string
    {
        foreach ($blocks as $key => $b) {
            if ((string) ($b['state'] ?? '') === '') {
                return (string) $key;
            }
        }

        return 'recap';
    }

    /**
     * Suivi de l'unité pour qui la commande : statuts seulement (jamais le contenu des signalements), limités aux unités
     * commandées et à leurs sous-unités.
     *
     * @param array<string, mixed> $campaign
     * @return array{members: list<array<string, mixed>>, totals: array<string, int>}|null
     */
    private function unitFollow(int $tenantId, int $userId, array $campaign): ?array
    {
        try {
            $commanded = $this->repo->commandedUnitIds($tenantId, $userId);
            if ($commanded === []) {
                return null;
            }
            $viewerUnits = DataCampaignScope::viewerUnits(false, $commanded, $this->repo->units($tenantId));
            $overview = $this->service->overview($tenantId, $campaign, $viewerUnits);

            return ['members' => $overview['members'], 'totals' => $overview['totals']];
        } catch (\Throwable) {
            return null;
        }
    }
}
