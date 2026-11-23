<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseCaseRepository;
use App\Repositories\SseCrossDecisionRepository;
use App\Repositories\SseInterestCaseHistoryRepository;
use App\Repositories\SseInterestCaseLinkRepository;
use App\Repositories\SseInterestCaseRepository;
use App\Repositories\SsePortalSettingsRepository;
use App\Repositories\UserRepository;
use App\Services\Security\FileRateLimiter;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseCrossMatchService;
use App\Services\Sse\SseInterestActor;
use App\Services\Sse\SseInterestCaseException;
use App\Services\Sse\SseInterestCasePdfService;
use App\Services\Sse\SseInterestCaseService;
use App\Services\Sse\SseInterestLinkDirectory;
use App\Services\Sse\SseRedactionService;
use App\Services\Tactical\AtakActivityLogService;
use App\Support\SseInterestCaseCatalog as Catalog;

/**
 * Dossiers d'intérêt du portail SSE : file « ticketing », fiche à onglets, création, modification, changement d'état,
 * suppression (corbeille, définitive), éléments liés, export PDF. Les règles sont dans SseInterestCaseService ; ce contrôleur
 * vérifie le jeton CSRF, lit la requête, appelle le service et rend la vue ou redirige avec un message et les erreurs par champ.
 */
final class SseInterestCaseController
{
    private const TABS = ['synthese', 'analyse', 'liens', 'historique', 'pieces', 'discussion', 'acces'];

    private ?SseInterestActor $actor = null;

    public function __construct(
        private ?SseInterestCaseService $service = null,
        private ?SseAccessCodeService $access = null,
        private ?SseCaseRepository $cases = null,
        private ?SseCrossMatchService $cross = null,
        private ?SseCrossDecisionRepository $crossDecisions = null,
        private ?AtakActivityLogService $activityLog = null,
        private ?UserRepository $users = null,
        private ?SseInterestCasePdfService $pdf = null,
        private ?SsePortalSettingsRepository $settings = null,
    ) {
        $this->access ??= new SseAccessCodeService();
        $this->settings ??= new SsePortalSettingsRepository();
        $this->cases ??= new SseCaseRepository();
        $this->cross ??= new SseCrossMatchService();
        $this->crossDecisions ??= new SseCrossDecisionRepository();
        $this->activityLog ??= new AtakActivityLogService();
        $this->users ??= new UserRepository();
        $this->pdf ??= new SseInterestCasePdfService();
        $this->service ??= new SseInterestCaseService(
            new SseInterestCaseRepository(),
            new SseInterestCaseHistoryRepository(),
            new SseInterestCaseLinkRepository(),
            new SseInterestLinkDirectory(),
            types: Catalog::types($this->settings->get($this->tenantId(), Catalog::TYPES_SETTING, ''))
        );
    }

    // ------------------------------------------------------------------ Lecture

    public function index(Request $request, array $params = []): Response
    {
        $actor = $this->actor();
        try {
            if (($request->query('onglet') === Catalog::TAB_TRASH) && ($actor->canDelete || $actor->canManage)) {
                $this->service->sweepTrash($actor->tenantId);
            }
            $page = $this->service->page($actor, $request->queryParams());
        } catch (SseInterestCaseException $e) {
            Session::flash('error', $e->getMessage());

            return Response::redirect(url('atak/sse/accueil'));
        }

        $tabs = [];
        foreach (Catalog::TABS as $key => $meta) {
            $tabs[$key] = ['label' => $meta['label'], 'hint' => $meta['hint'], 'count' => (int) ($page['counts'][$key] ?? 0)];
        }
        if ($actor->canDelete || $actor->canManage) {
            $tabs[Catalog::TAB_TRASH] = ['label' => 'Corbeille', 'hint' => 'Supprimés depuis moins de ' . Catalog::TRASH_DAYS . ' jours, restaurables.', 'count' => (int) ($page['counts']['corbeille'] ?? 0)];
        }

        return $this->view('atak.sse.interest_cases', [
            'title' => 'Dossiers d’intérêt',
            'activeNav' => 'interet',
            'page' => $page,
            'tabs' => $tabs,
            'activeTab' => $page['query']['filters']['tab'] === '' ? 'tous' : $page['query']['filters']['tab'],
            'filters' => $page['query']['filters'],
            'rawQuery' => $request->queryParams(),
            'sorts' => Catalog::SORTS,
            'types' => $this->service->types(),
            'classifications' => $this->classificationOptions($actor),
            'members' => $this->members($actor->tenantId),
            'actor' => $actor,
            'form' => $this->formState(),
            'confidenceLevels' => SseInterestCaseRepository::CONFIDENCE,
            'canManage' => $actor->canWrite,
            'openCase' => (int) $request->query('ouvrir', 0),
        ]);
    }

    /** Anciennes adresses : la création vit désormais dans une modale de la file. */
    public function createRedirect(Request $request, array $params = []): Response
    {
        return Response::redirect(url('atak/sse/interet') . '?creer=1');
    }

    public function show(Request $request, array $params = []): Response
    {
        $actor = $this->actor();
        $id = (int) ($params['id'] ?? 0);
        try {
            $detail = $this->service->detail($actor, $id, (string) $request->query('historique', '') === 'tout' ? 500 : 100);
        } catch (SseInterestCaseException $e) {
            Session::flash('error', $e->getMessage());

            return Response::redirect(url('atak/sse/interet'));
        }
        $case = $detail['case'];
        $tenantId = $actor->tenantId;
        $tab = (string) $request->query('onglet', 'synthese');
        $tab = in_array($tab, self::TABS, true) ? $tab : 'synthese';
        $acl = $this->service->repository()->listAcl($tenantId, $id);
        $constituted = $this->cases->findByInterestCase($tenantId, $id);

        return $this->view('atak.sse.interest_case_show', $detail + [
            'title' => (string) $case['reference_code'],
            'activeNav' => 'interet',
            'interestCase' => $case,
            'activeTab' => $tab,
            'tabsList' => self::TABS,
            'hypotheses' => $this->hypotheses($case),
            'proposals' => $this->crossProposals($tenantId, $id),
            'constitutedCase' => $constituted,
            'acl' => $acl,
            'tenantMembers' => $actor->canWrite ? $this->members($tenantId) : [],
            'statuses' => SseInterestCaseRepository::STATUSES,
            'cooldowns' => $this->service->repository()->allCooldownStates($tenantId, $id),
            'types' => $this->service->types(),
            'classifications' => $this->classificationOptions($actor),
            'confidenceLevels' => SseInterestCaseRepository::CONFIDENCE,
            'members' => $actor->canWrite ? $this->members($tenantId) : [],
            'jnetCount' => $this->service->jnetCitations($tenantId, $id),
            'actor' => $actor,
            'form' => $this->formState(),
            'canManage' => $actor->canWrite,
        ]);
    }

    /** Aperçu du tiroir (JSON) : l'essentiel du dossier, filtré pour le lecteur. */
    public function preview(Request $request, array $params = []): Response
    {
        try {
            return Response::json(['ok' => true, 'case' => $this->service->preview($this->actor(), (int) ($params['id'] ?? 0))]);
        } catch (SseInterestCaseException $e) {
            return Response::json(['ok' => false, 'message' => $e->getMessage()], $e->kind === SseInterestCaseException::NOT_FOUND ? 404 : 403);
        }
    }

    /** Recherche d'éléments à lier (JSON), limitée à ce que l'acteur peut lire ; débit borné. */
    public function linkSearch(Request $request, array $params = []): Response
    {
        $actor = $this->actor();
        if ($this->tooMany('sse_interest_link_search', $actor, 90, 60)) {
            return Response::json(['ok' => false, 'message' => 'Trop de recherches à la suite : patientez une minute.'], 429);
        }
        try {
            $rows = $this->service->searchLinks($actor, (string) $request->query('type', ''), (string) $request->query('q', ''));
        } catch (SseInterestCaseException $e) {
            return Response::json(['ok' => false, 'message' => $e->getMessage()], 403);
        }

        return Response::json(['ok' => true, 'results' => $rows]);
    }

    public function pdf(Request $request, array $params = []): Response
    {
        $actor = $this->actor();
        $id = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/interet/' . $id);
        if (!$actor->canExport) {
            Session::flash('error', 'Votre habilitation ne permet pas d’exporter les dossiers d’intérêt.');

            return Response::redirect($back);
        }
        if ($this->tooMany('sse_interest_pdf', $actor, Catalog::EXPORTS_PER_HOUR, 3600)) {
            Session::flash('error', 'Vous avez atteint la limite de ' . Catalog::EXPORTS_PER_HOUR . ' exports par heure. Réessayez plus tard.');

            return Response::redirect($back);
        }
        try {
            $detail = $this->service->exportDetail($actor, $id);
            $detail = (new \App\Services\Sse\SseInterestCaseExportContext())->enrich(
                $detail, $actor, new \App\Services\Sse\Links\SseLinkSessionViewer($this->access)
            );
            $this->service->recordExport($actor, $id);
        } catch (SseInterestCaseException $e) {
            Session::flash('error', $e->getMessage());

            return Response::redirect(url('atak/sse/interet'));
        }

        return $this->pdf->export($detail, (string) (Session::get('tenant_name') ?? ''));
    }

    // ------------------------------------------------------------------ Création, modification

    public function store(Request $request, array $params = []): Response
    {
        $back = url('atak/sse/interet');
        if (!$this->csrf($request)) {
            return $this->csrfFailed($back);
        }
        $input = $request->all();
        try {
            $created = $this->service->create($this->actor(), $input);
        } catch (SseInterestCaseException $e) {
            return $this->failed($e, $back . '?creer=1', $input, 'create');
        }
        Session::flash('success', !empty($input['draft'])
            ? 'Brouillon enregistré sous la référence ' . $created['reference'] . '. Il reste dans l’onglet « En attente ».'
            : 'Dossier d’intérêt ' . $created['reference'] . ' ouvert et signé numériquement. Aucune identité n’a été déduite automatiquement.'
                . ($created['links_failed'] > 0 ? ' ' . $created['links_failed'] . ' élément(s) n’ont pas pu être liés : ajoutez-les depuis l’onglet « Éléments liés ».' : ''));

        return Response::redirect(url('atak/sse/interet/' . $created['id']));
    }

    public function update(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/interet/' . $id);
        if (!$this->csrf($request)) {
            return $this->csrfFailed($back);
        }
        $input = $request->all();
        try {
            $res = $this->service->update($this->actor(), $id, $input, $this->versionOf($request), (string) $request->input('updated_at', ''));
        } catch (SseInterestCaseException $e) {
            return $this->failed($e, $back . '?modifier=1', $input, 'edit');
        }
        Session::flash('success', $res['changed'] === [] ? 'Aucune modification : le dossier était déjà à jour.' : 'Dossier enregistré (' . count($res['changed']) . ' champ(s) modifié(s)). Le détail est dans l’historique.');

        return Response::redirect($back);
    }

    /** Ancienne adresse de l'enregistrement de la description : passe par la modification suivie. */
    public function description(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/interet/' . $id);
        if (!$this->csrf($request)) {
            return $this->csrfFailed($back);
        }
        try {
            $this->service->update($this->actor(), $id, ['description' => (string) $request->input('description', '')], $this->versionOf($request), (string) $request->input('updated_at', ''));
        } catch (SseInterestCaseException $e) {
            return $this->failed($e, $back, [], null);
        }
        Session::flash('success', 'Description du dossier enregistrée.');

        return Response::redirect($back . '#description');
    }

    public function status(Request $request, array $params = []): Response
    {
        $actor = $this->actor();
        $id = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/interet/' . $id);
        if (!$this->csrf($request)) {
            return $this->csrfFailed($back);
        }
        $to = (string) $request->input('status', '');
        $repo = $this->service->repository();
        $key = $to === 'en_validation' ? 'publish' : 'status';
        $state = $repo->cooldownState($actor->tenantId, $id, $key);
        if (!empty($state['blocked'])) {
            Session::flash('error', $repo->formatCooldownHuman($state));

            return Response::redirect($back);
        }
        try {
            $res = $this->service->transition($actor, $id, $to, (string) $request->input('reason', ''), $this->versionOf($request), (string) $request->input('updated_at', ''));
        } catch (SseInterestCaseException $e) {
            return $this->failed($e, $back . '?etat=1', $request->all(), 'status');
        }
        $repo->touchCooldown($actor->tenantId, $id, $key, $actor->userId);
        Session::flash('success', $to === 'en_validation' ? 'Dossier soumis à validation humaine.' : 'État passé à « ' . (SseInterestCaseRepository::STATUSES[$res['to']] ?? $res['to']) . ' ».');

        return Response::redirect($back);
    }

    public function unarchive(Request $request, array $params = []): Response
    {
        return $this->simple($request, $params, 'Dossier sorti des archives.', fn (SseInterestActor $a, int $id) => $this->service->unarchive($a, $id, (string) $request->input('reason', ''), $this->versionOf($request)));
    }

    // ------------------------------------------------------------------ Suppression

    public function trash(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/interet/' . $id);
        if (!$this->csrf($request)) {
            return $this->csrfFailed($back);
        }
        try {
            $this->service->trash($this->actor(), $id, (string) $request->input('reason', ''), $this->versionOf($request));
        } catch (SseInterestCaseException $e) {
            return $this->failed($e, $back . '?supprimer=1', $request->all(), 'trash');
        }
        Session::flash('success', 'Dossier mis à la corbeille : il reste restaurable pendant ' . Catalog::TRASH_DAYS . ' jours.');

        return Response::redirect(url('atak/sse/interet'));
    }

    public function restore(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/interet/' . $id);
        if (!$this->csrf($request)) {
            return $this->csrfFailed($back);
        }
        try {
            $this->service->restore($this->actor(), $id);
        } catch (SseInterestCaseException $e) {
            return $this->failed($e, $back, [], null);
        }
        Session::flash('success', 'Dossier restauré.');

        return Response::redirect($back);
    }

    public function purge(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/interet/' . $id);
        if (!$this->csrf($request)) {
            return $this->csrfFailed($back);
        }
        try {
            $this->service->purge($this->actor(), $id, (string) $request->input('reason', ''), (string) $request->input('confirm', ''));
        } catch (SseInterestCaseException $e) {
            return $this->failed($e, $back . '?supprimer=1', $request->all(), 'purge');
        }
        Session::flash('success', 'Dossier supprimé définitivement. La suppression est consignée au journal d’audit du bureau.');

        return Response::redirect(url('atak/sse/interet') . '?onglet=corbeille');
    }

    // ------------------------------------------------------------------ Liens, notes, abonnement

    public function linkAdd(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/interet/' . $id) . '?onglet=liens';
        if (!$this->csrf($request)) {
            return $this->csrfFailed($back);
        }
        try {
            $link = $this->service->addLink($this->actor(), $id, (string) $request->input('link_type', ''), (string) $request->input('target_id', ''), (string) $request->input('label', ''), (string) $request->input('note', ''));
        } catch (SseInterestCaseException $e) {
            return $this->failed($e, $back, $request->all(), 'link');
        }
        Session::flash('success', 'Élément lié : ' . $link['label'] . '.');

        return Response::redirect($back);
    }

    public function linkRemove(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/interet/' . $id) . '?onglet=liens';
        if (!$this->csrf($request)) {
            return $this->csrfFailed($back);
        }
        try {
            $this->service->removeLink($this->actor(), $id, (int) ($params['linkId'] ?? 0), (string) $request->input('reason', ''));
        } catch (SseInterestCaseException $e) {
            return $this->failed($e, $back, [], null);
        }
        Session::flash('success', 'Lien retiré. Le motif est consigné dans l’historique.');

        return Response::redirect($back);
    }

    public function note(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/interet/' . $id) . '?onglet=discussion';
        if (!$this->csrf($request)) {
            return $this->csrfFailed($back);
        }
        try {
            $this->service->addNote($this->actor(), $id, (string) $request->input('body', ''));
        } catch (SseInterestCaseException $e) {
            return $this->failed($e, $back, $request->all(), 'note');
        }
        Session::flash('success', 'Note ajoutée à la discussion.');

        return Response::redirect($back);
    }

    public function watch(Request $request, array $params = []): Response
    {
        $on = (string) $request->input('watch', '1') === '1';

        return $this->simple($request, $params, $on ? 'Vous suivez ce dossier : vous serez prévenu de ses changements.' : 'Vous ne suivez plus ce dossier.', fn (SseInterestActor $a, int $id) => $this->service->watch($a, $id, $on));
    }

    // ------------------------------------------------------------------ Diffusion, investigation, rapprochements, constitution (existant)

    public function acl(Request $request, array $params = []): Response
    {
        $actor = $this->actor();
        $id = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/interet/' . $id) . '?onglet=acces';
        if (!$this->csrf($request)) {
            return $this->csrfFailed($back);
        }
        try {
            if (!$actor->canManage) {
                throw SseInterestCaseException::forbidden('La diffusion d’un dossier est réservée aux gestionnaires des dossiers.');
            }
            $case = $this->service->find($actor, $id);
            $dest = $request->input('destinataires', []);
            $deny = $request->input('interdits', []);
            $ok = $this->service->repository()->replaceAcl(
                $actor->tenantId,
                (int) $case['id'],
                array_map('intval', is_array($dest) ? $dest : []),
                array_map('intval', is_array($deny) ? $deny : []),
                $actor->userId
            );
        } catch (SseInterestCaseException $e) {
            return $this->failed($e, $back, [], null);
        }
        if (!$ok) {
            Session::flash('error', 'La diffusion n’a pas pu être enregistrée. Réessayez.');

            return Response::redirect($back);
        }
        $this->service->recordAclChange($actor, $id);
        Session::flash('success', 'Destinataires et interdictions nominatives mis à jour.');

        return Response::redirect($back);
    }

    public function openInvestigation(Request $request, array $params = []): Response
    {
        $actor = $this->actor();
        $id = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/interet/' . $id);
        if (!$this->csrf($request)) {
            return $this->csrfFailed($back);
        }
        try {
            if (!$actor->canWrite) {
                throw SseInterestCaseException::forbidden('Votre habilitation ne permet pas d’ouvrir une investigation.');
            }
            $this->service->find($actor, $id);
        } catch (SseInterestCaseException $e) {
            return $this->failed($e, $back, [], null);
        }
        $repo = $this->service->repository();
        $state = $repo->cooldownState($actor->tenantId, $id, 'open_mesh');
        if (!empty($state['blocked'])) {
            Session::flash('error', $repo->formatCooldownHuman($state));

            return Response::redirect($back);
        }
        $repo->touchCooldown($actor->tenantId, $id, 'open_mesh', $actor->userId);

        return Response::redirect(url('atak/sse/toiles/nouveau'));
    }

    /**
     * Constitue un dossier à partir d'un dossier d'intérêt instruit : le travail d'analyse reste ici, le dossier ouvert en reprend
     * la substance et garde trace de son origine. Rien n'est déduit.
     */
    public function constitute(Request $request, array $params = []): Response
    {
        $actor = $this->actor();
        $id = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/interet/' . $id);
        if (!$this->csrf($request)) {
            return $this->csrfFailed($back);
        }
        try {
            if (!$actor->canWrite) {
                throw SseInterestCaseException::forbidden('Votre habilitation ne permet pas de constituer un dossier.');
            }
            $interest = $this->service->find($actor, $id);
            if (!empty($interest['deleted_at'])) {
                throw SseInterestCaseException::rule('Ce dossier est à la corbeille : restaurez-le avant de le constituer.');
            }
        } catch (SseInterestCaseException $e) {
            return $this->failed($e, $back, [], null);
        }
        $repo = $this->service->repository();
        $state = $repo->cooldownState($actor->tenantId, $id, 'constitute');
        if (!empty($state['blocked'])) {
            Session::flash('error', $repo->formatCooldownHuman($state));

            return Response::redirect($back);
        }
        $existing = $this->cases->findByInterestCase($actor->tenantId, $id);
        if ($existing !== null) {
            Session::flash('error', 'Un dossier a déjà été constitué à partir de ce dossier d’intérêt.');

            return Response::redirect(url('atak/sse/dossiers/' . (int) $existing['id']));
        }
        $summary = array_values(array_filter([
            trim((string) ($interest['opening_reason'] ?? '')),
            trim((string) ($interest['description'] ?? '')),
            trim((string) ($interest['observed_elements'] ?? '')),
            trim((string) ($interest['analysis_facts'] ?? '')),
        ], static fn (string $s): bool => $s !== ''));
        $origin = sprintf('Constitué à partir du dossier d’intérêt %s (%s).', (string) ($interest['reference_code'] ?? ''), (string) ($interest['temporary_designation'] ?? 'sujet non désigné'));
        $caseId = $this->cases->create([
            'tenant_id' => $actor->tenantId,
            'title' => (string) ($interest['temporary_designation'] ?? 'Dossier sans titre'),
            'summary' => implode("\n\n", array_merge($summary, [$origin])),
            'classification' => SseRedactionService::levelRank((string) $interest['classification']) > SseRedactionService::levelRank(SseCaseRepository::CLASS_CONFIDENTIAL) ? (string) $interest['classification'] : SseCaseRepository::CLASS_CONFIDENTIAL,
            'status' => 'ouvert',
            'interest_case_id' => $id,
            'created_by' => $actor->userId,
        ]);
        $repo->touchCooldown($actor->tenantId, $id, 'constitute', $actor->userId);
        $this->activityLog->record($actor->tenantId, 1, 'SSE_CASE', sprintf('Dossier constitué à partir du dossier d’intérêt %s.', (string) ($interest['reference_code'] ?? $id)), $actor->label);
        Session::flash('success', 'Dossier ouvert à partir du dossier d’intérêt. Rattachez-y au moins une identité pour le rendre exploitable.');

        return Response::redirect(url('atak/sse/dossiers/' . $caseId));
    }

    /** Tranche un rapprochement proposé : confirmé, maintenu séparé, ou à approfondir. */
    public function crossDecide(Request $request, array $params = []): Response
    {
        $actor = $this->actor();
        $id = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/interet/' . $id) . '?onglet=analyse';
        if (!$this->csrf($request)) {
            return $this->csrfFailed($back);
        }
        try {
            if (!$actor->canWrite) {
                throw SseInterestCaseException::forbidden('Votre habilitation ne permet pas de trancher un rapprochement.');
            }
            $case = $this->service->find($actor, $id);
        } catch (SseInterestCaseException $e) {
            return $this->failed($e, $back, [], null);
        }
        $repo = $this->service->repository();
        $state = $repo->cooldownState($actor->tenantId, $id, 'cross_decide');
        if (!empty($state['blocked'])) {
            Session::flash('error', $repo->formatCooldownHuman($state));

            return Response::redirect($back);
        }
        $personId = (int) $request->input('person_id', 0);
        $entryId = (int) $request->input('entry_id', 0);
        $decision = (string) $request->input('decision', '');
        if ($personId < 1 || $entryId < 1) {
            Session::flash('error', 'Rapprochement introuvable : rechargez la page.');

            return Response::redirect($back);
        }
        if ($decision === 'reouvrir') {
            $this->crossDecisions->clear($actor->tenantId, $id, $personId, $entryId);
            $repo->touchCooldown($actor->tenantId, $id, 'cross_decide', $actor->userId);
            Session::flash('success', 'Décision retirée : le rapprochement redevient à traiter.');

            return Response::redirect($back);
        }
        if (!SseCrossDecisionRepository::isDecision($decision)) {
            Session::flash('error', 'Choisissez une décision valable pour ce rapprochement.');

            return Response::redirect($back);
        }
        $ok = $this->crossDecisions->record($actor->tenantId, $id, [
            'person_id' => $personId, 'entry_id' => $entryId, 'decision' => $decision,
            'score' => (int) $request->input('score', 0), 'reason' => (string) $request->input('reason', ''),
            'note' => trim((string) $request->input('note', '')) ?: null, 'author_label' => $actor->label, 'decided_by' => $actor->userId,
        ]);
        if (!$ok) {
            Session::flash('error', 'La décision n’a pas pu être enregistrée.');

            return Response::redirect($back);
        }
        $repo->touchCooldown($actor->tenantId, $id, 'cross_decide', $actor->userId);
        $this->activityLog->record($actor->tenantId, 1, 'SSE_CROSS', sprintf('%s sur le dossier %s.', SseCrossDecisionRepository::decisionLabel($decision), (string) ($case['reference_code'] ?? $id)), $actor->label);
        Session::flash('success', match ($decision) {
            SseCrossDecisionRepository::CONFIRMED => 'Rapprochement confirmé et consigné au dossier.',
            SseCrossDecisionRepository::SEPARATE => 'Rapprochement écarté : les deux identités restent distinctes.',
            default => 'Analyse complémentaire demandée sur ce rapprochement.',
        });

        return Response::redirect($back);
    }

    // ------------------------------------------------------------------ Outils

    private function actor(): SseInterestActor
    {
        return $this->actor ??= SseInterestActor::fromSession($this->access);
    }

    private function tenantId(): int
    {
        $tid = $this->access->tenantId();

        return $tid > 0 ? $tid : (int) Session::get('tenant_id');
    }

    private function csrf(Request $request): bool
    {
        return Csrf::validate((string) $request->input('_csrf_token', ''));
    }

    private function csrfFailed(string $back): Response
    {
        Session::flash('error', 'La session a expiré ou la demande n’est pas valable. Rechargez la page puis recommencez : rien n’a été modifié.');

        return Response::redirect($back);
    }

    private function versionOf(Request $request): ?int
    {
        $v = (string) $request->input('version', '');

        return ctype_digit($v) ? (int) $v : null;
    }

    /**
     * Redirige avec le message, les erreurs par champ et la saisie, pour rouvrir la bonne modale.
     *
     * @param array<string, mixed> $input
     */
    private function failed(SseInterestCaseException $e, string $back, array $input, ?string $modal): Response
    {
        Session::flash('error', $e->getMessage());
        if ($e->kind === SseInterestCaseException::VALIDATION && $modal !== null) {
            unset($input['_csrf_token']);
            Session::flash('sse_interest_form', ['modal' => $modal, 'errors' => $e->fields, 'old' => array_map(static fn (mixed $v): mixed => is_array($v) ? $v : (string) $v, $input)]);
        } elseif ($e->kind === SseInterestCaseException::CONFLICT || $e->kind === SseInterestCaseException::NOT_FOUND) {
            $back = $e->kind === SseInterestCaseException::NOT_FOUND ? url('atak/sse/interet') : preg_replace('/\?.*$/', '', $back);
        } else {
            $back = preg_replace('/\?(modifier|etat|supprimer|creer)=1.*$/', '', $back);
        }

        return Response::redirect($back);
    }

    /** @return array{modal: string, errors: array<string, string>, old: array<string, mixed>} */
    private function formState(): array
    {
        $raw = Session::getFlash('sse_interest_form');

        return is_array($raw)
            ? ['modal' => (string) ($raw['modal'] ?? ''), 'errors' => (array) ($raw['errors'] ?? []), 'old' => (array) ($raw['old'] ?? [])]
            : ['modal' => '', 'errors' => [], 'old' => []];
    }

    /** Action simple (CSRF, service, message, retour à la fiche). */
    private function simple(Request $request, array $params, string $success, callable $do): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/interet/' . $id);
        if (!$this->csrf($request)) {
            return $this->csrfFailed($back);
        }
        try {
            $do($this->actor(), $id);
        } catch (SseInterestCaseException $e) {
            return $this->failed($e, $back, [], null);
        }
        Session::flash('success', $success);

        return Response::redirect($back);
    }

    private function tooMany(string $bucket, SseInterestActor $actor, int $max, int $window): bool
    {
        try {
            return (new FileRateLimiter())->tooManyAttempts($bucket . ':' . $actor->tenantId . ':' . ($actor->userId ?? 'guest-' . sha1((string) ($_SERVER['REMOTE_ADDR'] ?? ''))), $max, $window);
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array<string, string> niveaux que l'acteur peut poser : jamais au-dessus de son habilitation */
    private function classificationOptions(SseInterestActor $actor): array
    {
        return array_filter(
            SseCaseRepository::CLASSIFICATION_LABELS,
            static fn (string $label, string $key): bool => SseRedactionService::levelRank($key) <= $actor->maxRank(),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /** @return list<array{id: int, label: string}> */
    private function members(int $tenantId): array
    {
        try {
            $rows = $this->users->listForTenant($tenantId, null, 'active', null, 200, 0);
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $m) {
            $uid = (int) ($m['id'] ?? 0);
            if ($uid < 1) {
                continue;
            }
            $name = trim((string) ($m['display_name'] ?? ''));
            $cs = trim((string) ($m['callsign'] ?? ''));
            $out[] = ['id' => $uid, 'label' => $name === '' ? ($cs !== '' ? $cs : 'Membre') : ($cs !== '' ? $name . ' (' . $cs . ')' : $name)];
        }

        return $out;
    }

    /** @param array<string, mixed> $case @return list<array<string, mixed>> */
    private function hypotheses(array $case): array
    {
        $hypotheses = [];
        $raw = trim((string) ($case['analysis_assumptions'] ?? ''));
        if ($raw !== '') {
            foreach (preg_split('/\R+/', $raw) ?: [] as $i => $line) {
                $line = trim((string) $line);
                if ($line === '') {
                    continue;
                }
                $hypotheses[] = [
                    'code' => 'H' . ($i + 1), 'text' => $line, 'confidence' => max(40, min(88, 55 + ($i * 7))),
                    'pros' => array_values(array_filter([
                        trim((string) ($case['analysis_facts'] ?? '')) !== '' ? 'Éléments factuels consignés' : null,
                        trim((string) ($case['observed_elements'] ?? '')) !== '' ? 'Observations terrain présentes' : null,
                    ])),
                    'cons' => array_values(array_filter([
                        trim((string) ($case['analysis_contradictions'] ?? '')) !== '' ? 'Contradictions signalées' : null,
                        trim((string) ($case['analysis_questions'] ?? '')) !== '' ? 'Questions ouvertes' : null,
                    ])),
                ];
            }
        }
        if ($hypotheses === []) {
            $hypotheses[] = [
                'code' => 'H1', 'text' => 'La cible « ' . (string) ($case['temporary_designation'] ?? 'FALCON') . ' » correspond à une identité à consolider.',
                'confidence' => 52, 'pros' => ['Désignation temporaire ouverte', 'Motif d’ouverture documenté'], 'cons' => ['Identité non confirmée', 'Biométrie non consolidée'],
            ];
        }

        return $hypotheses;
    }

    /**
     * Rapprochements proposés, enrichis de la décision déjà prise. Les propositions sont recalculées à chaque ouverture ; seule la
     * décision humaine est conservée.
     *
     * @return list<array<string, mixed>>
     */
    private function crossProposals(int $tenantId, int $interestCaseId): array
    {
        $decisions = $this->crossDecisions->mapForCase($tenantId, $interestCaseId);
        $proposals = [];
        try {
            $matches = $this->cross->matchPersonsAgainstWatchlist($tenantId);
            foreach (array_slice(is_array($matches) ? $matches : [], 0, 5) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $person = is_array($row['person'] ?? null) ? $row['person'] : [];
                $top = is_array($row['matches'][0] ?? null) ? $row['matches'][0] : [];
                $entry = is_array($top['entry'] ?? null) ? $top['entry'] : [];
                $personId = (int) ($person['id'] ?? 0);
                $entryId = (int) ($entry['id'] ?? 0);
                if ($personId < 1 || $entryId < 1) {
                    continue;
                }
                $entryName = trim((string) ($entry['last_name'] ?? '') . ' ' . (string) ($entry['first_name'] ?? ''));
                if ($entryName === '') {
                    $entryName = (string) ($entry['alias'] ?? 'entrée surveillée');
                }
                $proposals[] = [
                    'person_id' => $personId, 'entry_id' => $entryId, 'person_name' => (string) ($person['display_name'] ?? 'Identité'),
                    'entry_name' => $entryName, 'reason' => (string) ($top['reason'] ?? 'Rapprochement à confirmer'),
                    'score' => (int) ($top['score'] ?? 0), 'decision' => $decisions[$personId . ':' . $entryId] ?? null,
                ];
            }
        } catch (\Throwable) {
            $proposals = [];
        }
        usort($proposals, static function (array $a, array $b): int {
            $pending = (int) ($a['decision'] === null) <=> (int) ($b['decision'] === null);

            return $pending !== 0 ? -$pending : (int) $b['score'] <=> (int) $a['score'];
        });

        return $proposals;
    }

    /** @param array<string, mixed> $data */
    private function view(string $view, array $data): Response
    {
        $data['isGuest'] = $this->access->isGuest();
        $data['clearanceUntil'] = (int) Session::get(SseAccessCodeService::SESSION_UNTIL, 0);
        $data['guestLabel'] = (string) Session::get('sse_guest_label', '');
        $data['sseTheme'] = sse_ui_theme();
        $data['canManage'] = $data['canManage'] ?? $this->actor()->canWrite;
        $data['canGrant'] = !$this->access->isGuest() && function_exists('can') && (can('atak.sse.grant') || can('admin.access'));

        return Response::view($view, $data);
    }
}
