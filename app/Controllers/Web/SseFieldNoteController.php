<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseCaseRepository;
use App\Repositories\SseFieldNoteRepository;
use App\Repositories\UserRepository;
use App\Services\Intel\IntelIntakeService;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseClearanceService;
use App\Services\Sse\SseFieldNoteService;
use App\Services\Sse\SseFieldNoteTagService;
use App\Support\SseFieldNoteTagCatalog;
use App\Support\SseFieldNoteCatalog;
use App\Services\Sse\SseAccessPolicy;
use App\Support\SseAccessCatalog;

/**
 * Fiches de renseignement simplifiées du bureau SSE.
 *
 * Le rédacteur est volontairement pauvre en champs : une date, un lieu, des
 * thèmes, un texte libre et des pièces jointes. Tout le reste (classement,
 * qualification, rapprochements) se fait ensuite côté analyste.
 */
final class SseFieldNoteController
{
    public function __construct(
        private ?SseFieldNoteService $noteService = null,
        private ?SseFieldNoteRepository $notes = null,
        private ?SseAccessCodeService $access = null,
        private ?SseCaseRepository $cases = null,
        private ?UserRepository $users = null,
        private ?SseFieldNoteTagService $tagService = null,
    ) {
        $this->tagService ??= new SseFieldNoteTagService();
        $this->noteService ??= new SseFieldNoteService();
        $this->notes ??= $this->noteService->repository();
        $this->access ??= new SseAccessCodeService();
        $this->cases ??= new SseCaseRepository();
        $this->users ??= new UserRepository();
    }

    /** File des fiches reçues. */
    public function index(Request $request, array $params = []): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => strtolower(trim((string) $request->query('status', ''))),
            'note_kind' => strtoupper(trim((string) $request->query('note_kind', ''))),
            'theme' => strtolower(trim((string) $request->query('theme', ''))),
            'urgency' => strtolower(trim((string) $request->query('urgency', ''))),
            'tag' => strtolower(trim((string) $request->query('tag', ''))),
            'tag_family' => strtolower(trim((string) $request->query('tag_family', ''))),
        ];

        $tenantId = $this->tenantId();
        $lang = self::lang();
        $query = array_merge($filters, ['limit' => 150], $this->tagService->noteFilters($tenantId, $filters));
        $notes = $this->present($tenantId, $this->notes->listForTenant($tenantId, $query));
        $noteTags = $this->tagService->tagsForNotes($tenantId, array_map(static fn (array $n): int => (int) ($n['id'] ?? 0), $notes), $lang);
        foreach ($notes as $i => $note) {
            $notes[$i] = $this->tagService->decorate($tenantId, $note, $lang, $noteTags[(int) ($note['id'] ?? 0)] ?? []);
        }
        $kindOptions = [];
        foreach ($this->tagService->kinds($tenantId) as $code => $kind) {
            $kindOptions[$code] = $kind['label'];
        }
        $themeOptions = [];
        foreach ($this->tagService->activeThemes($tenantId, $lang) as $code => $theme) {
            $themeOptions[$code] = $code . ' — ' . $theme['label'];
        }

        return $this->view('atak.sse.field_notes', [
            'title' => 'Fiches de renseignement',
            'activeNav' => 'fiches',
            'notes' => $notes,
            'counters' => $this->notes->counters($tenantId),
            'filters' => $filters,
            'kindOptions' => $kindOptions,
            'themeOptions' => $themeOptions,
            'urgencyOptions' => SseFieldNoteCatalog::urgencyOptions(),
            'statusOptions' => SseFieldNoteCatalog::STATUSES,
            'tagFamilies' => $this->tagService->pickerFamilies($tenantId, $lang, false),
            'tagsReady' => $this->tagService->ready(),
        ]);
    }

    private static function lang(): string
    {
        return function_exists('locale') && locale() === 'en' ? 'en' : 'fr';
    }

    /** Auteur des changements, pour le journal SSE. @return array{user_id: ?int, label: string} */
    private function actor(): array
    {
        return ['user_id' => (int) Session::get('user_id') ?: null, 'label' => $this->authorLabel()];
    }

    /** Rédacteur plein écran (même surface que l'ATAK). */
    public function composer(Request $request, array $params = []): Response
    {
        if (!$this->canWrite()) {
            Session::flash('error', 'Votre habilitation ne permet pas de rédiger une fiche.');

            return Response::redirect(url('atak/sse/fiches'));
        }

        // getFlash consomme la valeur : la relire renverrait toujours vide.
        $draft = Session::getFlash('sse_field_note_draft');
        $errors = Session::getFlash('sse_field_note_errors');

        $tenantId = $this->tenantId();
        $lang = self::lang();
        $kindSettings = $this->tagService->kinds($tenantId);
        $activeKinds = $this->tagService->activeKinds($tenantId);

        return Response::view('atak.sse.field_note_composer', [
            'title' => 'Nouvelle fiche de renseignement',
            'kinds' => $activeKinds,
            'themes' => $this->tagService->activeThemes($tenantId, $lang),
            'tagFamilies' => $this->tagService->pickerFamilies($tenantId, $lang),
            'tagsReady' => $this->tagService->ready(),
            'tagsMax' => SseFieldNoteTagCatalog::TAGS_PER_NOTE,
            'tagSuggestions' => array_map(static fn (array $k): array => $k['suggested'], $kindSettings),
            'kindTemplates' => array_filter(array_map(static fn (array $k): string => $k['template'], $kindSettings), static fn (string $t): bool => $t !== ''),
            'relToDefault' => $this->tagService->rules($tenantId)['rel_to'],
            'urgencies' => SseFieldNoteCatalog::URGENCIES,
            'sources' => SseFieldNoteCatalog::SOURCES,
            'bodyMaxLength' => SseFieldNoteCatalog::BODY_MAX_LENGTH,
            'attachmentsMax' => SseFieldNoteCatalog::ATTACHMENTS_MAX,
            'themesMax' => SseFieldNoteCatalog::THEMES_MAX,
            'defaultKind' => isset($activeKinds[SseFieldNoteCatalog::DEFAULT_KIND]) ? SseFieldNoteCatalog::DEFAULT_KIND : (string) (array_key_first($activeKinds) ?? SseFieldNoteCatalog::DEFAULT_KIND),
            'authorLabel' => $this->authorLabel(),
            'observedInputValue' => date('Y-m-d\TH:i'),
            'errors' => is_array($errors) ? $errors : [],
            'draft' => is_array($draft) ? $draft : [],
            'cancelUrl' => url('atak/sse/fiches'),
            'submitUrl' => url('atak/sse/fiches'),
        ]);
    }

    /** Enregistrement d'une fiche rédigée dans le portail. */
    public function store(Request $request, array $params = []): Response
    {
        if (!$this->canWrite() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée ou session expirée. Reprenez la rédaction.');

            return Response::redirect(url('atak/sse/fiches/nouvelle'));
        }

        $input = [
            'body' => (string) $request->input('body', ''),
            'title' => (string) $request->input('title', ''),
            'note_kind' => (string) $request->input('note_kind', SseFieldNoteCatalog::DEFAULT_KIND),
            'themes' => $request->input('themes', []),
            'observed_at' => (string) $request->input('observed_at', ''),
            'place_label' => (string) $request->input('place_label', ''),
            'grid_reference' => (string) $request->input('grid_reference', ''),
            'lat' => $request->input('lat', null),
            'lng' => $request->input('lng', null),
            'urgency' => (string) $request->input('urgency', SseFieldNoteCatalog::DEFAULT_URGENCY),
            'intel_source' => (string) $request->input('intel_source', ''),
            'case_code' => (string) $request->input('case_code', ''),
            'classification' => sse_ui_classification(),
            'origin' => 'web',
            'author_label' => $this->authorLabel(),
            'author_user_id' => (int) Session::get('user_id') ?: null,
            'status' => SseFieldNoteCatalog::DEFAULT_STATUS,
        ];

        // Étiquettes de la communauté : sélection nettoyée, règles du type de fiche (champs et étiquettes obligatoires).
        $tenantId = $this->tenantId();
        $picked = $this->tagService->normalizeSelection($tenantId, $request->input('tags', []), $request->input('tag_qualifier', []));
        $selection = $picked['selection'];
        $hasCategoryTag = false;
        foreach ($selection as $link) {
            $hasCategoryTag = $hasCategoryTag || ($this->tagService->find($tenantId, $link['slug'])['family'] ?? '') === 'category';
        }

        $errors = $this->noteService->validate($input, $hasCategoryTag);
        $errors = array_merge($errors, $picked['errors'], $this->tagService->validateForKind($tenantId, $input['note_kind'], $input, $selection));
        if ($errors !== []) {
            Session::flash('sse_field_note_errors', $errors);
            Session::flash('sse_field_note_draft', [
                'tags' => $selection,
                'body' => $input['body'],
                'title' => $input['title'],
                'note_kind' => $input['note_kind'],
                'themes' => SseFieldNoteCatalog::normalizeThemes($input['themes']),
                'observed_at' => $input['observed_at'],
                'place_label' => $input['place_label'],
                'urgency' => $input['urgency'],
                'intel_source' => $input['intel_source'],
            ]);

            return Response::redirect(url('atak/sse/fiches/nouvelle'));
        }

        $created = $this->noteService->create($tenantId, $input);
        $noteId = (int) ($created['note']['id'] ?? 0);
        if ($noteId < 1) {
            Session::flash('error', 'La fiche n’a pas pu être enregistrée. Réessayez.');

            return Response::redirect(url('atak/sse/fiches/nouvelle'));
        }
        $selection = $this->tagService->withDefaults($tenantId, $selection);
        if ($selection !== []) {
            $this->tagService->setNoteTags($tenantId, $noteId, $selection, $this->actor());
        }

        $message = 'Fiche ' . (string) ($created['note']['reference_code'] ?? '') . ' transmise au bureau SSE.';
        if (!empty($_FILES['pieces'])) {
            $batch = $this->noteService->attachUploadedBatch($tenantId, $noteId, $_FILES['pieces'], [
                'author_label' => $input['author_label'],
                'grid_reference' => $input['grid_reference'],
            ]);
            if ($batch['stored'] > 0) {
                $message .= sprintf(
                    ' %d pièce%s jointe%s.',
                    $batch['stored'],
                    $batch['stored'] > 1 ? 's' : '',
                    $batch['stored'] > 1 ? 's' : ''
                );
            }
            if ($batch['errors'] !== []) {
                Session::flash('error', implode(' ', $batch['errors']));
            }
        }

        Session::flash('success', $message);

        return Response::redirect(url('atak/sse/fiches/' . $noteId));
    }

    /** Lecture d'une fiche et de ses pièces jointes. */
    public function show(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        $note = $this->noteService->find($tenantId, (int) ($params['id'] ?? 0));
        // Caviardages et suppressions décidés dans le back-office Remontées.
        $note = $note !== null ? ($this->present($tenantId, [$note])[0] ?? null) : null;
        if ($note === null) {
            Session::flash('error', 'Fiche introuvable.');

            return Response::redirect(url('atak/sse/fiches'));
        }
        if (!$this->access->isGuest()) {
            $this->intake()->recordRead($tenantId, 'fiche', (int) $note['id'], (int) Session::get('user_id'), 'web');
        }

        $case = null;
        if (!empty($note['case_id'])) {
            try {
                $case = $this->cases->findById((int) $note['case_id'], $tenantId);
            } catch (\Throwable) {
                $case = null;
            }
        }

        $openCases = [];
        try {
            foreach ($this->cases->listForTenant($tenantId, $this->access->caseScope()) as $row) {
                if (!empty($row['is_folder'])) {
                    continue;
                }
                $openCases[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'label' => trim(sprintf(
                        '%s — %s',
                        (string) ($row['reference_code'] ?? ''),
                        (string) ($row['title'] ?? '')
                    ), ' —'),
                ];
            }
        } catch (\Throwable) {
            $openCases = [];
        }

        // Historique du suivi (back-office Remontées) : réservé à qui gère les fiches ; les commentaires internes ne sortent
        // que pour qui a accès au back-office.
        $history = [];
        if ($this->canManage()) {
            try {
                $history = \App\Support\SseFieldNoteTimeline::fromEvents(
                    $this->intake()->events($tenantId, 'fiche', (int) $note['id']),
                    \App\Controllers\Admin\AdminIntelIntakeController::allowed()
                );
            } catch (\Throwable) {
                $history = [];
            }
        }

        $lang = self::lang();
        $note = $this->tagService->decorate($tenantId, $note, $lang);

        return $this->view('atak.sse.field_note_show', [
            'title' => (string) ($note['reference_code'] ?? 'Fiche de renseignement'),
            'activeNav' => 'fiches',
            'note' => $note,
            'tagFamilies' => $this->canWrite() ? $this->tagService->pickerFamilies($tenantId, $lang, true, array_column($note['tags'], 'key')) : [],
            'tagsReady' => $this->tagService->ready(),
            'tagsMax' => SseFieldNoteTagCatalog::TAGS_PER_NOTE,
            'tagSuggestions' => [(string) $note['note_kind'] => $this->tagService->kinds($tenantId)[(string) $note['note_kind']]['suggested'] ?? []],
            'relToDefault' => $this->tagService->rules($tenantId)['rel_to'],
            'linkedCase' => $case,
            'openCases' => $openCases,
            'statusOptions' => SseFieldNoteCatalog::STATUSES,
            'attachmentsMax' => SseFieldNoteCatalog::ATTACHMENTS_MAX,
            'history' => $history,
        ]);
    }

    /** Étiquettes d'une fiche (mentions : aucun droit d'accès ne change). */
    public function tags(Request $request, array $params = []): Response
    {
        $noteId = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/fiches/' . $noteId) . '#etiquettes';
        if (!$this->canWrite() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée ou session expirée.');

            return Response::redirect($back);
        }
        $tenantId = $this->tenantId();
        $note = $this->notes->findForTenant($tenantId, $noteId);
        // Une fiche que la session ne peut pas lire (autre communauté, supprimée, au-dessus de son habilitation) ne s'étiquette pas.
        if ($note === null || $this->present($tenantId, [$note]) === []) {
            Session::flash('error', 'Fiche introuvable.');

            return Response::redirect(url('atak/sse/fiches'));
        }
        if (!$this->tagService->ready()) {
            Session::flash('error', SseFieldNoteTagService::notReadyMessage());

            return Response::redirect($back);
        }
        $uid = (int) Session::get('user_id');
        if ((new \App\Services\Security\FileRateLimiter())->tooManyAttempts('sse_fiche_tags|' . $tenantId . '|' . ($uid > 0 ? $uid : 'g' . sha1((string) session_id())), 120, 3600)) {
            Session::flash('error', 'Trop de changements en peu de temps : réessayez dans quelques minutes.');

            return Response::redirect($back);
        }
        $current = [];
        foreach ($this->tagService->tagsForNotes($tenantId, [$noteId])[$noteId] ?? [] as $tag) {
            $current[(string) $tag['key']] = (string) $tag['qualifier'];
        }
        $picked = $this->tagService->normalizeSelection($tenantId, $request->input('tags', []), $request->input('tag_qualifier', []), $current);
        $this->tagService->setNoteTags($tenantId, $noteId, $picked['selection'], $this->actor());
        if ($picked['errors'] !== []) {
            Session::flash('error', implode(' ', $picked['errors']));
        }
        Session::flash('success', count($picked['selection']) > 0 ? 'Étiquettes de la fiche enregistrées.' : 'La fiche ne porte plus aucune étiquette.');

        return Response::redirect($back);
    }

    /** Ajout d'une pièce jointe après coup. */
    public function attachmentStore(Request $request, array $params = []): Response
    {
        $noteId = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/fiches/' . $noteId);
        if (!$this->canWrite() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée ou session expirée.');

            return Response::redirect($back);
        }

        $tenantId = $this->tenantId();
        if ($this->notes->findForTenant($tenantId, $noteId) === null) {
            Session::flash('error', 'Fiche introuvable.');

            return Response::redirect(url('atak/sse/fiches'));
        }

        if (empty($_FILES['pieces']) && empty($_FILES['piece'])) {
            Session::flash('error', 'Choisissez une photo ou un document avant de valider.');

            return Response::redirect($back);
        }

        $entry = $_FILES['pieces'] ?? $_FILES['piece'];
        $batch = $this->noteService->attachUploadedBatch($tenantId, $noteId, $entry, [
            'author_label' => $this->authorLabel(),
            'caption' => (string) $request->input('caption', ''),
            'kind' => (string) $request->input('kind', ''),
        ]);

        if ($batch['stored'] > 0) {
            Session::flash('success', $batch['stored'] > 1
                ? $batch['stored'] . ' pièces jointes ajoutées à la fiche.'
                : 'Pièce jointe ajoutée à la fiche.');
        }
        if ($batch['errors'] !== []) {
            Session::flash('error', implode(' ', $batch['errors']));
        }

        return Response::redirect($back);
    }

    /** Retrait d'une pièce jointe. */
    public function attachmentDelete(Request $request, array $params = []): Response
    {
        $noteId = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/fiches/' . $noteId);
        if (!$this->canWrite() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée ou session expirée.');

            return Response::redirect($back);
        }

        $deleted = $this->noteService->deleteAttachment(
            $this->tenantId(),
            $noteId,
            (int) ($params['attachmentId'] ?? 0)
        );
        Session::flash(
            $deleted ? 'success' : 'error',
            $deleted ? 'Pièce jointe retirée de la fiche.' : 'Pièce jointe introuvable.'
        );

        return Response::redirect($back);
    }

    /** Suivi analyste : prise en compte, exploitation, classement sans suite. */
    public function triage(Request $request, array $params = []): Response
    {
        $noteId = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/fiches/' . $noteId);
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée ou session expirée.');

            return Response::redirect($back);
        }
        // Validation depuis le tableau de bord identité : on y revient, sans perdre le périmètre ni la liste.
        $back = self::triageBack((string) $request->input('return', ''), $back);

        $tenantId = $this->tenantId();
        $status = SseFieldNoteCatalog::normalizeStatus((string) $request->input('status', ''));
        $this->notes->updateTriage(
            $tenantId,
            $noteId,
            $status,
            (string) $request->input('triage_note', ''),
            (int) Session::get('user_id') ?: null
        );

        Session::flash('success', 'Suivi enregistré : ' . SseFieldNoteCatalog::statusLabel($status) . '.');

        return Response::redirect($back);
    }

    /** Retour autorisé après un suivi : uniquement le tableau de bord identité (chemin relatif, jamais un lien externe). */
    public static function triageBack(string $return, string $default): string
    {
        $return = trim($return);
        if (preg_match('~^atak/sse/papiers/tableau(\?[A-Za-z0-9%=&_.\-]{0,200})?(#frm)?$~', $return) !== 1) {
            return $default;
        }

        return url($return);
    }

    /** Rattachement de la fiche à un dossier validé. */
    public function attachCase(Request $request, array $params = []): Response
    {
        $noteId = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/fiches/' . $noteId);
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée ou session expirée.');

            return Response::redirect($back);
        }

        $caseId = (int) $request->input('case_id', 0);
        $this->notes->attachToCase($this->tenantId(), $noteId, $caseId > 0 ? $caseId : null, null);
        Session::flash(
            'success',
            $caseId > 0 ? 'Fiche rattachée au dossier.' : 'Rattachement de la fiche retiré.'
        );

        return Response::redirect($back);
    }

    private ?IntelIntakeService $intakeService = null;

    private function intake(): IntelIntakeService
    {
        return $this->intakeService ??= new IntelIntakeService();
    }

    /**
     * Fiches telles que la session a le droit de les lire (caviardages, fiches supprimées, pièces floutées).
     *
     * @param list<array<string, mixed>> $notes
     * @return list<array<string, mixed>>
     */
    private function present(int $tenantId, array $notes): array
    {
        try {
            $level = (new SseClearanceService($this->access))->maxLevel();
        } catch (\Throwable) {
            $level = SseCaseRepository::CLASS_INTERNAL;
        }
        $viewer = $this->access->isGuest() ? 0 : (int) Session::get('user_id');

        return $this->intake()->presentNotes($tenantId, $notes, $level, $viewer);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function view(string $view, array $data): Response
    {
        $data['isGuest'] = $this->access->isGuest();
        $data['clearanceUntil'] = (int) Session::get(SseAccessCodeService::SESSION_UNTIL, 0);
        $data['guestLabel'] = (string) Session::get('sse_guest_label', '');
        $data['sseTheme'] = sse_ui_theme();
        $data['canManage'] = $data['canManage'] ?? $this->canManage();
        $data['canGrant'] = $data['canGrant'] ?? $this->canGrant();
        $data['canWrite'] = $data['canWrite'] ?? $this->canWrite();

        return Response::view($view, $data);
    }

    private function tenantId(): int
    {
        $tenantId = $this->access->tenantId();

        return $tenantId > 0 ? $tenantId : (int) Session::get('tenant_id');
    }

    /**
     * Rédiger une fiche est le geste le plus courant : il suffit d'être
     * habilité au bureau, pas de gérer les dossiers.
     */
    private function canWrite(): bool
    {
        return SseAccessPolicy::sessionAllows('terrain', SseAccessCatalog::LEVEL_WRITE, ['guest' => $this->access->isGuest(), 'tenant_id' => $this->tenantId()]);
    }

    private function canManage(): bool
    {
        return SseAccessPolicy::sessionAllows('terrain', SseAccessCatalog::LEVEL_MANAGE, ['guest' => $this->access->isGuest(), 'tenant_id' => $this->tenantId()]);
    }

    private function canGrant(): bool
    {
        if ($this->access->isGuest()) {
            return false;
        }

        return function_exists('can') && (can('atak.sse.grant') || can('admin.access'));
    }

    private function authorLabel(): string
    {
        foreach (['sse_guest_label', 'callsign', 'display_name'] as $key) {
            $value = trim((string) (Session::get($key) ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return 'Analyste';
    }
}
