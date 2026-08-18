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
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseFieldNoteService;
use App\Support\SseFieldNoteCatalog;

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
    ) {
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
        ];

        $tenantId = $this->tenantId();

        return $this->view('atak.sse.field_notes', [
            'title' => 'Fiches de renseignement',
            'activeNav' => 'fiches',
            'notes' => $this->notes->listForTenant($tenantId, array_merge($filters, ['limit' => 150])),
            'counters' => $this->notes->counters($tenantId),
            'filters' => $filters,
            'kindOptions' => SseFieldNoteCatalog::kindOptions(),
            'themeOptions' => SseFieldNoteCatalog::themeOptions(),
            'urgencyOptions' => SseFieldNoteCatalog::urgencyOptions(),
            'statusOptions' => SseFieldNoteCatalog::STATUSES,
        ]);
    }

    /** Rédacteur plein écran (même surface que l'ATAK). */
    public function composer(Request $request, array $params = []): Response
    {
        if (!$this->canWrite()) {
            Session::flash('error', 'Votre habilitation ne permet pas de rédiger une fiche.');

            return Response::redirect(url('atak/sse/fiches'));
        }

        $draft = Session::getFlash('sse_field_note_draft');
        $draft = is_array($draft) ? $draft : [];

        return Response::view('atak.sse.field_note_composer', [
            'title' => 'Nouvelle fiche de renseignement',
            'kinds' => SseFieldNoteCatalog::KINDS,
            'themes' => SseFieldNoteCatalog::THEMES,
            'urgencies' => SseFieldNoteCatalog::URGENCIES,
            'bodyMaxLength' => SseFieldNoteCatalog::BODY_MAX_LENGTH,
            'attachmentsMax' => SseFieldNoteCatalog::ATTACHMENTS_MAX,
            'themesMax' => SseFieldNoteCatalog::THEMES_MAX,
            'defaultKind' => SseFieldNoteCatalog::DEFAULT_KIND,
            'authorLabel' => $this->authorLabel(),
            'observedInputValue' => date('Y-m-d\TH:i'),
            'errors' => is_array(Session::getFlash('sse_field_note_errors'))
                ? Session::getFlash('sse_field_note_errors')
                : [],
            'draft' => $draft,
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
            'note_kind' => (string) $request->input('note_kind', SseFieldNoteCatalog::DEFAULT_KIND),
            'themes' => $request->input('themes', []),
            'observed_at' => (string) $request->input('observed_at', ''),
            'place_label' => (string) $request->input('place_label', ''),
            'grid_reference' => (string) $request->input('grid_reference', ''),
            'lat' => $request->input('lat', null),
            'lng' => $request->input('lng', null),
            'urgency' => (string) $request->input('urgency', SseFieldNoteCatalog::DEFAULT_URGENCY),
            'case_code' => (string) $request->input('case_code', ''),
            'classification' => sse_ui_classification(),
            'origin' => 'web',
            'author_label' => $this->authorLabel(),
            'author_user_id' => (int) Session::get('user_id') ?: null,
            'status' => SseFieldNoteCatalog::DEFAULT_STATUS,
        ];

        $errors = $this->noteService->validate($input);
        if ($errors !== []) {
            Session::flash('sse_field_note_errors', $errors);
            Session::flash('sse_field_note_draft', [
                'body' => $input['body'],
                'note_kind' => $input['note_kind'],
                'themes' => SseFieldNoteCatalog::normalizeThemes($input['themes']),
                'observed_at' => $input['observed_at'],
                'place_label' => $input['place_label'],
                'urgency' => $input['urgency'],
            ]);

            return Response::redirect(url('atak/sse/fiches/nouvelle'));
        }

        $tenantId = $this->tenantId();
        $created = $this->noteService->create($tenantId, $input);
        $noteId = (int) ($created['note']['id'] ?? 0);
        if ($noteId < 1) {
            Session::flash('error', 'La fiche n’a pas pu être enregistrée. Réessayez.');

            return Response::redirect(url('atak/sse/fiches/nouvelle'));
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
        if ($note === null) {
            Session::flash('error', 'Fiche introuvable.');

            return Response::redirect(url('atak/sse/fiches'));
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

        return $this->view('atak.sse.field_note_show', [
            'title' => (string) ($note['reference_code'] ?? 'Fiche de renseignement'),
            'activeNav' => 'fiches',
            'note' => $note,
            'linkedCase' => $case,
            'openCases' => $openCases,
            'statusOptions' => SseFieldNoteCatalog::STATUSES,
            'attachmentsMax' => SseFieldNoteCatalog::ATTACHMENTS_MAX,
        ]);
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
        if ($this->access->isGuest()) {
            return false;
        }

        return function_exists('can')
            && (can('atak.sse.access') || can('atak.sse.case.manage') || can('admin.access'));
    }

    private function canManage(): bool
    {
        if ($this->access->isGuest()) {
            return false;
        }

        return function_exists('can')
            && (can('atak.sse.case.manage') || can('atak.sse.grant') || can('admin.access'));
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
