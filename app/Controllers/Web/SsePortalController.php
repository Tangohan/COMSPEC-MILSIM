<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseAccessCodeRepository;
use App\Repositories\SseCaseRepository;
use App\Repositories\SsePersonRepository;
use App\Repositories\SseSiteRepository;
use App\Repositories\SseWatchlistRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseCasePdfService;
use App\Services\Sse\SseCrossMatchService;

final class SsePortalController
{
    public function __construct(
        private ?SseAccessCodeService $access = null,
        private ?SseAccessCodeRepository $codes = null,
        private ?SseCaseRepository $cases = null,
        private ?SsePersonRepository $persons = null,
        private ?SseWatchlistRepository $watchlist = null,
        private ?SseSiteRepository $sites = null,
        private ?SseCrossMatchService $cross = null,
        private ?SseCasePdfService $pdf = null,
    ) {
        $this->access ??= new SseAccessCodeService();
        $this->codes ??= new SseAccessCodeRepository();
        $this->cases ??= new SseCaseRepository();
        $this->persons ??= new SsePersonRepository();
        $this->watchlist ??= new SseWatchlistRepository();
        $this->sites ??= new SseSiteRepository();
        $this->cross ??= new SseCrossMatchService();
        $this->pdf ??= new SseCasePdfService();
    }

    /** Sas d’entrée (public) */
    public function gate(Request $request, array $params = []): Response
    {
        if ($this->access->hasActiveClearance()) {
            return Response::redirect(url('atak/sse/dossiers'));
        }

        // Commandement : entrée directe sans code (pour délivrer les accès).
        if ($this->access->canEnterAsStaff()) {
            $this->access->establishStaffClearance((int) Session::get('tenant_id'));

            return Response::redirect(url('atak/sse/dossiers'));
        }

        return Response::view('atak.sse.gate', [
            'title' => 'Accès renseignement interpersonnel',
            'error' => Session::getFlash('error'),
            'success' => Session::getFlash('success'),
            'loggedIn' => (int) Session::get('user_id') > 0,
            'sseTheme' => sse_ui_theme(),
            'sseThemeOptions' => sse_ui_theme_options(),
        ]);
    }

    /** Mémorise l’apparence SSE (registre / console) puis renvoie à la page d’origine. */
    public function setTheme(Request $request, array $params = []): Response
    {
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('atak/sse'));
        }

        sse_ui_theme_persist((string) $request->input('theme', 'archive'));
        $back = (string) $request->input('back', '');
        $prefix = (string) parse_url(url('atak/sse'), PHP_URL_PATH);
        $backPath = (string) (parse_url($back, PHP_URL_PATH) ?: $back);
        if ($back === '' || $prefix === '' || !str_starts_with($backPath, $prefix)) {
            $back = $this->access->hasActiveClearance()
                ? url('atak/sse/dossiers')
                : url('atak/sse');
        }

        return Response::redirect($back);
    }

    /** Entrée commandement depuis le back-office (toujours vers les codes). */
    public function staffEnter(Request $request, array $params = []): Response
    {
        if (!$this->access->canEnterAsStaff()) {
            Session::flash('error', 'Seul le commandement peut ouvrir cet accès.');

            return Response::redirect(url('atak/sse'));
        }
        $this->access->establishStaffClearance((int) Session::get('tenant_id'));
        Session::flash('success', 'Session commandement ouverte — vous pouvez délivrer des codes.');

        return Response::redirect(url('atak/sse/acces'));
    }

    public function redeem(Request $request, array $params = []): Response
    {
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('atak/sse'));
        }

        sse_ui_theme_persist((string) $request->input('ui_theme', sse_ui_theme()));

        $code = strtoupper(trim((string) $request->input('access_code', '')));
        if ($code === '') {
            Session::flash('error', 'Indiquez le code d’accès fourni par votre commandement.');

            return Response::redirect(url('atak/sse'));
        }

        $userId = (int) Session::get('user_id');
        $hasPerm = $userId > 0 && function_exists('can') && can('atak.sse.access');
        $label = $userId > 0
            ? (string) (Session::get('display_name') ?? Session::get('callsign') ?? 'Membre')
            : trim((string) $request->input('guest_name', 'Invité'));

        $result = $this->access->redeem($code, $userId > 0 ? $userId : null, $hasPerm, $label);
        if (!$result['ok']) {
            Session::flash('error', $result['message']);

            return Response::redirect(url('atak/sse'));
        }

        Session::flash('success', 'Accès accordé. Diffusion restreinte — traçabilité active.');

        return Response::redirect(url('atak/sse/dossiers'));
    }

    public function logout(Request $request, array $params = []): Response
    {
        $this->access->clearSession();
        Session::flash('success', 'Session de renseignement fermée.');

        return Response::redirect(url('atak/sse'));
    }

    public function casesIndex(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        $scope = $this->access->caseScope();
        $list = $this->cases->listForTenant($tenantId, $scope, [
            'status' => $request->query('status'),
            'classification' => $request->query('classification'),
        ]);

        $counts = $this->cases->countsForCases(
            array_map(static fn (array $c): int => (int) ($c['id'] ?? 0), $list),
            $tenantId
        );

        return $this->portalView('atak.sse.cases', [
            'title' => 'Dossiers — Renseignement interpersonnel',
            'cases' => $list,
            'caseCounts' => $counts,
            'canManage' => $this->canManage(),
            'canGrant' => $this->canGrant(),
            'canExport' => $this->canExport(),
            'filters' => [
                'status' => (string) $request->query('status', ''),
                'classification' => (string) $request->query('classification', ''),
            ],
            'classifications' => SseCaseRepository::CLASSIFICATION_LABELS,
            'statuses' => SseCaseRepository::STATUS_LABELS,
            'activeNav' => 'dossiers',
        ]);
    }

    public function caseCreateForm(Request $request, array $params = []): Response
    {
        if (!$this->canManage()) {
            Session::flash('error', 'Vous n’êtes pas habilité à ouvrir un dossier.');

            return Response::redirect(url('atak/sse/dossiers'));
        }

        return $this->portalView('atak.sse.case_form', [
            'title' => 'Ouvrir un dossier',
            'case' => null,
            'classifications' => SseCaseRepository::CLASSIFICATION_LABELS,
            'statuses' => SseCaseRepository::STATUS_LABELS,
            'activeNav' => 'dossiers',
            'canManage' => true,
            'canGrant' => $this->canGrant(),
            'canExport' => $this->canExport(),
        ]);
    }

    public function caseStore(Request $request, array $params = []): Response
    {
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/dossiers'));
        }

        $unlockPlain = trim((string) $request->input('unlock_code', ''));
        $id = $this->cases->create([
            'tenant_id' => $this->tenantId(),
            'title' => (string) $request->input('title', ''),
            'summary' => (string) $request->input('summary', ''),
            'classification' => (string) $request->input('classification', 'encadrement'),
            'status' => 'ouvert',
            'created_by' => (int) Session::get('user_id') ?: null,
            'unlock_code_hash' => $unlockPlain !== '' ? hash('sha256', strtoupper($unlockPlain)) : null,
        ]);
        Session::flash('success', 'Dossier ouvert.');

        return Response::redirect(url('atak/sse/dossiers/' . $id));
    }

    public function caseShow(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $case = $this->requireCase($id);
        if ($case === null) {
            Session::flash('error', 'Dossier introuvable ou hors de votre périmètre.');

            return Response::redirect(url('atak/sse/dossiers'));
        }

        $links = $this->cases->listLinkedPersonIds($id, $this->tenantId());
        $people = [];
        foreach ($links as $link) {
            $p = $this->persons->findById((int) $link['person_id'], $this->tenantId());
            if ($p) {
                $people[] = $p;
            }
        }
        $available = $this->persons->listForContext($this->tenantId(), 1, ['limit' => 100]);

        return $this->portalView('atak.sse.case_show', [
            'title' => $case['reference_code'] . ' — ' . $case['title'],
            'case' => $case,
            'people' => $people,
            'availablePeople' => $available,
            'notes' => $this->cases->listNotes($id, $this->tenantId()),
            'evidence' => $this->cases->listEvidence($id, $this->tenantId()),
            'classifications' => SseCaseRepository::CLASSIFICATION_LABELS,
            'statuses' => SseCaseRepository::STATUS_LABELS,
            'canManage' => $this->canManage(),
            'canGrant' => $this->canGrant(),
            'canExport' => $this->canExport(),
            'activeNav' => 'dossiers',
        ]);
    }

    public function caseUpdate(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/dossiers/' . $id));
        }
        if ($this->requireCase($id) === null) {
            return Response::redirect(url('atak/sse/dossiers'));
        }
        $this->cases->update($id, $this->tenantId(), [
            'title' => (string) $request->input('title', ''),
            'summary' => (string) $request->input('summary', ''),
            'classification' => (string) $request->input('classification', 'encadrement'),
            'status' => (string) $request->input('status', 'ouvert'),
        ]);
        Session::flash('success', 'Dossier mis à jour.');

        return Response::redirect(url('atak/sse/dossiers/' . $id));
    }

    public function caseLinkPerson(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/dossiers/' . $id));
        }
        if ($this->requireCase($id) === null) {
            return Response::redirect(url('atak/sse/dossiers'));
        }
        $personId = (int) $request->input('person_id', 0);
        if ($personId < 1 || !$this->persons->findById($personId, $this->tenantId())) {
            Session::flash('error', 'Personne introuvable.');

            return Response::redirect(url('atak/sse/dossiers/' . $id));
        }
        $this->cases->linkPerson($id, $personId, $this->tenantId(), (int) Session::get('user_id') ?: null);
        Session::flash('success', 'Personne rattachée au dossier.');

        return Response::redirect(url('atak/sse/dossiers/' . $id));
    }

    public function caseAddNote(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/dossiers/' . $id));
        }
        if ($this->requireCase($id) === null) {
            return Response::redirect(url('atak/sse/dossiers'));
        }
        $body = trim((string) $request->input('body', ''));
        if ($body === '') {
            Session::flash('error', 'La note ne peut pas être vide.');

            return Response::redirect(url('atak/sse/dossiers/' . $id));
        }
        $this->cases->addNote(
            $id,
            $this->tenantId(),
            $body,
            (string) $request->input('classification', 'encadrement'),
            (int) Session::get('user_id') ?: null,
            (string) (Session::get('sse_guest_label') ?? Session::get('display_name') ?? 'Opérateur')
        );
        Session::flash('success', 'Note ajoutée.');

        return Response::redirect(url('atak/sse/dossiers/' . $id));
    }

    public function caseAddEvidence(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/dossiers/' . $id));
        }
        if ($this->requireCase($id) === null) {
            return Response::redirect(url('atak/sse/dossiers'));
        }
        $label = trim((string) $request->input('label', 'Preuve'));
        $caption = trim((string) $request->input('caption', ''));
        $imagePath = null;
        if (!empty($_FILES['image']['tmp_name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
            $dir = base_path('public/uploads/sse/evidence');
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $ext = pathinfo((string) ($_FILES['image']['name'] ?? 'img.jpg'), PATHINFO_EXTENSION) ?: 'jpg';
            $ext = preg_replace('/[^a-z0-9]/i', '', $ext) ?: 'jpg';
            $name = 'ev_' . $id . '_' . time() . '.' . strtolower($ext);
            if (@move_uploaded_file($_FILES['image']['tmp_name'], $dir . DIRECTORY_SEPARATOR . $name)) {
                $imagePath = 'uploads/sse/evidence/' . $name;
            }
        }
        $this->cases->addEvidence($id, $this->tenantId(), [
            'label' => $label !== '' ? $label : 'Preuve',
            'caption' => $caption,
            'image_path' => $imagePath,
            'author_label' => (string) (Session::get('sse_guest_label') ?? Session::get('display_name') ?? 'Opérateur'),
        ]);
        Session::flash('success', 'Preuve enregistrée.');

        return Response::redirect(url('atak/sse/dossiers/' . $id));
    }

    public function casePdf(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$this->canExport()) {
            Session::flash('error', 'Export non autorisé.');

            return Response::redirect(url('atak/sse/dossiers/' . $id));
        }
        if ($this->requireCase($id) === null) {
            return Response::redirect(url('atak/sse/dossiers'));
        }

        return $this->pdf->export($this->tenantId(), $id);
    }

    public function personsIndex(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        $list = $this->persons->listForContext($tenantId, 1, [
            'status' => $request->query('status'),
            'limit' => 100,
        ]);

        // Chaîne de possession : une seule requête pour tout le registre.
        $custody = $this->persons->custodyEventsForPersons(
            array_map(static fn (array $p): int => (int) ($p['id'] ?? 0), $list),
            $tenantId
        );

        // Relevés biométriques simulés + croisement listes de surveillance.
        foreach ($list as $i => $p) {
            $pid = (int) ($p['id'] ?? 0);
            $list[$i]['biometric_samples'] = $this->persons->listBiometricSamples($pid, $tenantId);
            $list[$i]['watchlist'] = $this->cross->matchOne($p, $tenantId);
            $list[$i]['custody'] = $custody[$pid] ?? [];
        }

        return $this->portalView('atak.sse.persons', [
            'title' => 'Personnes identifiées',
            'persons' => $list,
            'canManage' => $this->canManage(),
            'canGrant' => $this->canGrant(),
            'canExport' => $this->canExport(),
            'activeNav' => 'personnes',
        ]);
    }

    public function sitesIndex(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        $list = $this->sites->listForContext($tenantId, 1, [
            'status' => $request->query('status'),
            'site_type' => $request->query('site_type'),
            'limit' => 100,
        ]);
        $counts = $this->sites->countsForSites(
            array_map(static fn (array $s): int => (int) ($s['id'] ?? 0), $list),
            $tenantId
        );

        return $this->portalView('atak.sse.sites', [
            'title' => 'Sites exploités',
            'sites' => $list,
            'siteCounts' => $counts,
            'statuses' => SseSiteRepository::STATUS_LABELS,
            'types' => SseSiteRepository::TYPE_LABELS,
            'filters' => [
                'status' => (string) $request->query('status', ''),
                'site_type' => (string) $request->query('site_type', ''),
            ],
            'canManage' => $this->canManage(),
            'canGrant' => $this->canGrant(),
            'canExport' => $this->canExport(),
            'activeNav' => 'sites',
        ]);
    }

    public function siteShow(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        $site = $this->sites->findById((int) ($params['id'] ?? 0), $tenantId);
        if ($site === null) {
            Session::flash('error', 'Site introuvable.');

            return Response::redirect(url('atak/sse/sites'));
        }

        return $this->portalView('atak.sse.site_show', [
            'title' => 'Site — ' . ($site['reference_code'] ?? ''),
            'site' => $site,
            'fiveLine' => $this->sites->buildFiveLineReport($site),
            'seizureCategories' => SseSiteRepository::SEIZURE_LABELS,
            'canManage' => $this->canManage(),
            'canGrant' => $this->canGrant(),
            'canExport' => $this->canExport(),
            'activeNav' => 'sites',
        ]);
    }

    public function siteRoomToggle(Request $request, array $params = []): Response
    {
        $siteId = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/sites/' . $siteId);

        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect($back);
        }

        $roomId = (int) ($params['roomId'] ?? 0);
        $checked = (string) $request->input('checked', '0') === '1';
        if (!$this->sites->setRoomChecked($roomId, $this->tenantId(), $checked, null)) {
            Session::flash('error', 'Pièce introuvable.');

            return Response::redirect($back);
        }
        Session::flash('success', $checked ? 'Pièce marquée fouillée.' : 'Pièce remise en attente.');

        return Response::redirect($back);
    }

    public function siteCloseAction(Request $request, array $params = []): Response
    {
        $siteId = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/sites/' . $siteId);

        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect($back);
        }

        $tenantId = $this->tenantId();
        $site = $this->sites->findById($siteId, $tenantId);
        if ($site === null) {
            Session::flash('error', 'Site introuvable.');

            return Response::redirect(url('atak/sse/sites'));
        }

        $summary = trim((string) $request->input('summary', ''));
        if ($summary === '') {
            $summary = $this->sites->buildFiveLineReport($site);
        }
        $actor = (string) (Session::get('callsign') ?? Session::get('display_name') ?? 'Commandement');
        $this->sites->close($siteId, $tenantId, $summary, $actor);
        Session::flash('success', 'Site clôturé — compte rendu enregistré.');

        return Response::redirect($back);
    }

    public function crossIndex(Request $request, array $params = []): Response
    {
        $matches = $this->cross->matchPersonsAgainstWatchlist($this->tenantId());
        $entries = $this->watchlist->listActive($this->tenantId());

        return $this->portalView('atak.sse.cross', [
            'title' => 'Croisements — listes de surveillance',
            'matches' => $matches,
            'entries' => $entries,
            'canManage' => $this->canManage(),
            'canGrant' => $this->canGrant(),
            'canExport' => $this->canExport(),
            'activeNav' => 'croisements',
        ]);
    }

    public function watchlistStore(Request $request, array $params = []): Response
    {
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/croisements'));
        }
        $this->watchlist->create([
            'tenant_id' => $this->tenantId(),
            'last_name' => (string) $request->input('last_name', ''),
            'first_name' => (string) $request->input('first_name', ''),
            'alias' => (string) $request->input('alias', ''),
            'threat_level' => (string) $request->input('threat_level', 'surveillance'),
            'notes' => (string) $request->input('notes', ''),
        ]);
        Session::flash('success', 'Entrée ajoutée à la liste de surveillance.');

        return Response::redirect(url('atak/sse/croisements'));
    }

    public function accessAdmin(Request $request, array $params = []): Response
    {
        if (!$this->canGrant()) {
            Session::flash('error', 'Seul le commandement peut délivrer des codes d’accès.');

            return Response::redirect(url('atak/sse/dossiers'));
        }
        $cases = $this->cases->listForTenant($this->tenantId(), null);

        return $this->portalView('atak.sse.access', [
            'title' => 'Codes d’accès temporaires',
            'codes' => $this->codes->listActiveForTenant($this->tenantId()),
            'cases' => $cases,
            'issuedPlain' => Session::getFlash('sse_issued_code'),
            'canManage' => $this->canManage(),
            'canGrant' => true,
            'canExport' => $this->canExport(),
            'activeNav' => 'acces',
        ]);
    }

    public function accessIssue(Request $request, array $params = []): Response
    {
        if (!$this->canGrant() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/acces'));
        }
        $result = $this->access->issue(
            $this->tenantId(),
            (int) Session::get('user_id'),
            trim((string) $request->input('label', 'Accès temporaire')),
            (string) $request->input('grant_type', 'member'),
            (int) $request->input('ttl_hours', 4),
            (int) $request->input('session_ttl_minutes', 240),
            (int) $request->input('max_uses', 1),
            ((int) $request->input('case_id', 0)) ?: null
        );
        if ($result['ok']) {
            Session::flash('sse_issued_code', $result['plain']);
            Session::flash('success', 'Code créé. Communiquez-le par un canal sécurisé — il ne sera plus réaffiché.');
        } else {
            Session::flash('error', $result['message'] ?? 'Impossible de créer le code.');
        }

        return Response::redirect(url('atak/sse/acces'));
    }

    public function accessRevoke(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$this->canGrant() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/acces'));
        }
        $this->codes->revoke($id, $this->tenantId());
        $this->codes->logEvent($this->tenantId(), 'revoke', $id, null, (int) Session::get('user_id'), null, null);
        Session::flash('success', 'Code révoqué.');

        return Response::redirect(url('atak/sse/acces'));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function portalView(string $view, array $data): Response
    {
        $data['isGuest'] = $this->access->isGuest();
        $data['clearanceUntil'] = (int) Session::get(SseAccessCodeService::SESSION_UNTIL, 0);
        $data['guestLabel'] = (string) Session::get('sse_guest_label', '');
        $data['sseTheme'] = sse_ui_theme();
        $data['sseThemeOptions'] = sse_ui_theme_options();

        return Response::view($view, $data);
    }

    private function tenantId(): int
    {
        $tid = $this->access->tenantId();
        if ($tid > 0) {
            return $tid;
        }

        return (int) Session::get('tenant_id');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function requireCase(int $id): ?array
    {
        $case = $this->cases->findById($id, $this->tenantId());
        if ($case === null) {
            return null;
        }
        $scope = $this->access->caseScope();
        if ($scope !== null && !in_array($id, $scope, true)) {
            return null;
        }

        return $case;
    }

    private function canManage(): bool
    {
        if ($this->access->isGuest()) {
            return false;
        }

        return function_exists('can') && (can('atak.sse.case.manage') || can('atak.sse.grant') || can('admin.access'));
    }

    private function canGrant(): bool
    {
        if ($this->access->isGuest()) {
            return false;
        }

        return function_exists('can') && (can('atak.sse.grant') || can('admin.access'));
    }

    private function canExport(): bool
    {
        if ($this->access->isGuest()) {
            // Invité : export interdit par défaut (périmètre lecture)
            return false;
        }

        return function_exists('can') && (can('atak.sse.export') || can('admin.access'));
    }
}
