<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\DataCampaignRepository;
use App\Services\DataCampaign\DataCampaignService;
use App\Support\DataCampaignBlocks;
use App\Support\DataCampaignCalendar;
use App\Support\DataCampaignScope;
use App\Support\PlaytimeSheet;

/**
 * Back-office « Fiabilisation des données » : créer, ouvrir et clôturer une campagne, suivre l'avancement (global et par
 * unité), relancer, traiter les signalements, exporter. Mêmes droits que « Qualité des données » et que la gestion des
 * dossiers RH : aucun droit nouveau. Le lecteur ne voit que les membres de son périmètre (DataCampaignScope).
 */
final class DataCampaignAdminController
{
    /** Droits qui ouvrent la gestion : ceux de la qualité des données et des dossiers RH existants (au moins un). */
    public const PERMISSIONS = [
        'admin.organization',
        'admin.access',
        'organization.orbat.manage',
        'personnel.profile.update',
        'personnel.assignments.manage',
    ];

    private const FILTERS = ['tous' => 'Tous', 'sans-reponse' => 'Sans réponse', 'signales' => 'Avec signalements', 'confirmes' => 'Confirmés'];

    public function __construct(
        private ?DataCampaignService $service = null,
        private ?DataCampaignRepository $repo = null,
    ) {
        $this->service ??= Container::get(DataCampaignService::class);
        $this->repo ??= Container::get(DataCampaignRepository::class);
    }

    public function index(Request $request, array $params = []): Response
    {
        $ctx = $this->context();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId] = $ctx;
        $ready = $this->repo->schemaReady();

        return Response::view('layout.main', [
            'content' => 'admin.organization.data_campaigns',
            'title' => 'Fiabilisation des données',
            'schemaReady' => $ready,
            'campaigns' => $ready ? $this->repo->listCampaigns($tenantId) : [],
            'settings' => $this->repo->settings($tenantId),
            'openNowPlan' => $ready ? $this->openNowPlan($tenantId) : null,
            'units' => $ready ? $this->repo->units($tenantId) : [],
            'blockCatalog' => DataCampaignBlocks::CATALOG,
            'defaultYear' => (int) date('Y'),
            'defaultDue' => DataCampaignCalendar::window((int) date('Y'), DataCampaignCalendar::DEFAULT_OPEN_MD, DataCampaignCalendar::DEFAULT_CLOSE_MD)['due_on'],
            'csrfToken' => Csrf::token(),
            'backOfficePageCss' => ['data-campaign.css'],
        ]);
    }

    public function show(Request $request, array $params = []): Response
    {
        $ctx = $this->context();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId] = $ctx;
        $campaign = $this->repo->schemaReady() ? $this->repo->findCampaign((int) ($params['id'] ?? 0), $tenantId) : null;
        if ($campaign === null) {
            Session::flash('error', 'Campagne introuvable.');

            return Response::redirect(url('back-office/organisation/fiabilisation'));
        }
        $overview = $this->service->overview($tenantId, $campaign, $this->viewerUnits($tenantId));
        $filter = $request->queryString('filtre', 'tous');
        $filter = isset(self::FILTERS[$filter]) ? $filter : 'tous';
        $unit = max(0, (int) $request->queryString('unite', '0'));
        $members = array_values(array_filter($overview['members'], static function (array $m) use ($filter, $unit): bool {
            if ($unit > 0 && (int) $m['unit_id'] !== $unit) {
                return false;
            }

            return match ($filter) {
                'sans-reponse' => $m['bucket'] === 'none',
                'signales' => $m['bucket'] === 'flagged',
                'confirmes' => $m['bucket'] === 'confirmed',
                default => true,
            };
        }));

        return Response::view('layout.main', [
            'content' => 'admin.organization.data_campaign_show',
            'title' => 'Fiabilisation des données',
            'schemaReady' => true,
            'campaign' => $campaign,
            'overview' => $overview,
            'members' => $members,
            'filter' => $filter,
            'filters' => self::FILTERS,
            'unitFilter' => $unit,
            'blockCatalog' => DataCampaignBlocks::CATALOG,
            'cooldownHours' => DataCampaignService::REMINDER_COOLDOWN_HOURS,
            'csrfToken' => Csrf::token(),
            'backOfficePageCss' => ['data-campaign.css'],
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        $ctx = $this->writeContext($request, url('back-office/organisation/fiabilisation'));
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $actorId] = $ctx;
        $result = $this->service->createCampaign($tenantId, $actorId, [
            'year' => $request->input('year'),
            'title' => $request->input('title'),
            'blocks' => $request->input('blocks'),
            'unit_ids' => $request->input('unit_ids'),
            'opens_on' => $request->input('opens_on'),
            'due_on' => $request->input('due_on'),
            'reminder_days' => $request->input('reminder_days'),
        ]);
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return Response::redirect(url('back-office/organisation/fiabilisation' . (isset($result['id']) ? '/' . $result['id'] : '')));
    }

    /**
     * « Ouvrir maintenant » : même passage que la tâche planifiée et que la commande data-campaign.php (--open-now), limité à
     * cette communauté. Le nombre de membres concernés est annoncé avant l'envoi (confirmation en ligne du bouton).
     */
    public function openNow(Request $request, array $params = []): Response
    {
        $ctx = $this->writeContext($request, url('back-office/organisation/fiabilisation'));
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $actorId] = $ctx;
        $result = $this->service->runScheduled([
            'force' => true,
            'tenant' => $tenantId,
            'actor' => $actorId,
            'deadline' => $request->input('deadline') !== null ? (string) $request->input('deadline') : null,
        ]);
        $plan = $result['plan'][0] ?? null;
        if ($result['opened'] > 0) {
            Session::flash('success', 'Campagne ouverte par Athena : ' . (int) ($plan['members'] ?? 0) . ' membre(s) concerné(s), les notifications partent par lots.');
        } else {
            Session::flash('error', 'Aucune campagne ouverte : ' . (string) ($plan['reason'] ?? 'rien à faire.'));
        }

        return Response::redirect(url('back-office/organisation/fiabilisation'));
    }

    public function update(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $back = url('back-office/organisation/fiabilisation/' . $id);
        $ctx = $this->writeContext($request, $back);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $actorId] = $ctx;
        $result = $this->service->updateCampaign($tenantId, $actorId, $id, (string) $request->input('title', ''), (string) $request->input('due_on', ''));
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return Response::redirect($back);
    }

    public function saveSettings(Request $request, array $params = []): Response
    {
        $ctx = $this->writeContext($request, url('back-office/organisation/fiabilisation'));
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $actorId] = $ctx;
        $result = $this->service->saveSettings($tenantId, $actorId, [
            'auto_enabled' => $request->input('auto_enabled', '1'),
            'emails_enabled' => $request->input('emails_enabled', '1'),
            'open_md' => self::md($request->input('open_day'), $request->input('open_month')),
            'close_md' => self::md($request->input('close_day'), $request->input('close_month')),
            'blocks' => $request->input('blocks'),
            'unit_ids' => $request->input('unit_ids'),
            'reminder_days' => $request->input('reminder_days'),
        ]);
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return Response::redirect(url('back-office/organisation/fiabilisation') . '#reglage');
    }

    public function open(Request $request, array $params = []): Response
    {
        return $this->transition($request, $params, 'open');
    }

    public function close(Request $request, array $params = []): Response
    {
        return $this->transition($request, $params, 'close');
    }

    public function remind(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $back = url('back-office/organisation/fiabilisation/' . $id);
        $ctx = $this->writeContext($request, $back);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $actorId] = $ctx;
        $userId = (int) $request->input('user_id', 0);
        $result = $this->service->remind($tenantId, $actorId, $id, $userId > 0 ? [$userId] : null, $this->viewerUnits($tenantId));
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return Response::redirect($back);
    }

    public function handleFlag(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $back = url('back-office/organisation/fiabilisation/' . $id) . '#signalements';
        $ctx = $this->writeContext($request, $back);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $actorId] = $ctx;
        $result = $this->service->handleFlag($tenantId, $actorId, (int) ($params['flagId'] ?? 0), (string) $request->input('answer', ''), $this->viewerUnits($tenantId));
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return Response::redirect($back);
    }

    /** GET : export CSV du suivi (colonnes documentées dans la page), limité au périmètre du lecteur. */
    public function export(Request $request, array $params = []): Response
    {
        $ctx = $this->context();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $actorId] = $ctx;
        $campaign = $this->repo->findCampaign((int) ($params['id'] ?? 0), $tenantId);
        if ($campaign === null) {
            Session::flash('error', 'Campagne introuvable.');

            return Response::redirect(url('back-office/organisation/fiabilisation'));
        }
        $fh = fopen('php://temp', 'r+');
        if ($fh === false) {
            return Response::redirect(url('back-office/organisation/fiabilisation/' . $campaign['id']));
        }
        foreach ($this->service->exportRows($tenantId, $campaign, $this->viewerUnits($tenantId)) as $row) {
            fputcsv($fh, array_map(static fn (string $c): string => PlaytimeSheet::csvCell($c), $row), ';', '"', '');
        }
        rewind($fh);
        $csv = "\xEF\xBB\xBF" . (stream_get_contents($fh) ?: '');
        fclose($fh);
        try {
            Container::get(\App\Services\Audit\AuditService::class)->log('data_campaign.exported', $tenantId, $actorId, 'data_campaign', (int) $campaign['id'], null, null);
        } catch (\Throwable) {
        }

        return (new Response())
            ->setStatusCode(200)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="fiabilisation-' . (int) $campaign['campaign_year'] . '-' . (int) $campaign['id'] . '.csv"')
            ->setBody($csv);
    }

    /**
     * Ce que ferait « Ouvrir maintenant » (sans rien écrire) : nombre de membres concernés ou raison du refus.
     *
     * @return array<string, mixed>|null
     */
    private function openNowPlan(int $tenantId): ?array
    {
        try {
            return $this->service->runScheduled(['force' => true, 'dry_run' => true, 'tenant' => $tenantId])['plan'][0] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param array<string, string> $params */
    private function transition(Request $request, array $params, string $action): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $back = url('back-office/organisation/fiabilisation/' . $id);
        $ctx = $this->writeContext($request, $back);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $actorId] = $ctx;
        $result = $action === 'open'
            ? $this->service->openCampaign($tenantId, $actorId, $id)
            : $this->service->closeCampaign($tenantId, $actorId, $id);
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return Response::redirect($back);
    }

    /**
     * Jour et mois saisis (deux champs) → « MM-JJ » ; vide si l'un manque (le défaut s'applique).
     */
    private static function md(mixed $day, mixed $month): string
    {
        if (!is_numeric($day) || !is_numeric($month)) {
            return '';
        }

        return sprintf('%02d-%02d', (int) $month, (int) $day);
    }

    /** @return list<int>|null null : toute la communauté (droit de gestion) */
    private function viewerUnits(int $tenantId): ?array
    {
        // L'accès à cet écran suppose un droit de gestion de la communauté : le périmètre est donc la communauté entière.
        // Un chef d'unité sans ce droit suit son unité depuis « Ma situation » (même service, périmètre restreint).
        return DataCampaignScope::viewerUnits(true, [], $this->repo->units($tenantId));
    }

    /** @return array{0: int, 1: int}|Response */
    private function context(): array|Response
    {
        $userId = (int) Session::get('user_id');
        $tenantId = (int) Session::get('tenant_id');
        if ($userId < 1 || $tenantId < 1) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        foreach (self::PERMISSIONS as $slug) {
            if ($gate->allows($slug)) {
                return [$tenantId, $userId];
            }
        }
        Session::flash('error', 'Droits insuffisants pour la fiabilisation des données.');

        return Response::redirect(url('dashboard'));
    }

    /** @return array{0: int, 1: int}|Response */
    private function writeContext(Request $request, string $back): array|Response
    {
        $ctx = $this->context();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        if (!$request->isPost() || !Csrf::validate(is_string($t = $request->input('_csrf_token')) ? $t : null)) {
            Session::flash('error', 'Session expirée. Rechargez la page et réessayez.');

            return Response::redirect($back);
        }

        return $ctx;
    }
}
