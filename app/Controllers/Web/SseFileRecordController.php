<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseCaseRepository;
use App\Repositories\SseFileRecordRepository;
use App\Repositories\SseFileRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseLoreRepository;
use App\Services\Security\FileRateLimiter;
use App\Services\Sse\SseFileRecordService;
use App\Services\Sse\SseFileSearchService;
use App\Services\Sse\SseFileXrefService;
use App\Services\Sse\SseIdImageService;
use App\Services\Sse\SsePhoneExtractionService;
use App\Services\Sse\SseMemberImageGate;
use App\Services\Sse\SseSeekPhotoService;
use App\Support\SseFileSubjectCatalog as S;
use App\Support\SseFileVisibility;
use App\Support\SseMeasure;

/**
 * Portail SSE — Fichiers : fiche sujet (consultation journalisée, fiche imprimable), annotations, recoupements,
 * surveillances, photothèque et comparaison, envoi de photos, téléphones synchronisés et extractions, sources citées.
 *
 * Toutes les lectures et écritures portent la communauté de la session ; un identifiant d'une autre communauté répond
 * « introuvable ». Les écritures demandent un compte (jamais une session invitée) et le jeton CSRF.
 */
final class SseFileRecordController
{
    private const BASE = SseFilesContext::BASE;

    private SseFilesContext $ctx;

    public function __construct(
        ?SseFilesContext $ctx = null,
        private ?SseFileRecordRepository $records = null,
        private ?SseFileRecordService $service = null,
        private ?SseFileXrefService $xref = null,
        private ?SsePhoneExtractionService $phones = null,
    ) {
        $this->ctx = $ctx ?? new SseFilesContext();
    }

    // ------------------------------------------------------------- Fiche

    public function subject(Request $request, array $params = []): Response
    {
        $tenantId = $this->ctx->tenantId();
        $id = (int) ($params['id'] ?? 0);
        $this->catchUp($tenantId);
        $record = $this->recordFor($tenantId, $id);
        if ($record === null) {
            return $this->notFound();
        }
        // Ouvrir une fiche est une consultation : inscrite au journal, comme au terminal.
        $this->logOpen($tenantId, $record, $request->queryString('motif'));

        return $this->ctx->render('atak.sse.files.subject', [
            'title' => $record['identity']['display_name'] . ' — Fiche',
            'record' => $record,
            'watching' => $this->ctx->userId() !== null && $this->xref()->watchesSubject($tenantId, (int) $this->ctx->userId(), $id),
            'gm' => $this->ctx->canLore(),
        ]);
    }

    public function subjectPrint(Request $request, array $params = []): Response
    {
        $tenantId = $this->ctx->tenantId();
        $record = $this->recordFor($tenantId, (int) ($params['id'] ?? 0));
        if ($record === null) {
            return $this->notFound();
        }

        return Response::view('atak.sse.files.subject_print', ['record' => $record, 'printedBy' => $this->ctx->operatorLabel() ?? '—']);
    }

    /** @return array<string, mixed>|null */
    private function recordFor(int $tenantId, int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        try {
            return $this->service()->record($tenantId, $id, [
                'gm' => false,
                'can_see' => $this->ctx->canSee(),
                'terminal_country' => (string) $this->ctx->settings()['default_country'],
            ]);
        } catch (\Throwable $e) {
            error_log('Fichiers SSE : fiche illisible : ' . $e->getMessage());

            return null;
        }
    }

    /** @param array<string, mixed> $record */
    private function logOpen(int $tenantId, array $record, string $purpose): void
    {
        try {
            $repo = new SseFileRepository();
            $logged = $repo->logQuery($tenantId, [
                'query_type' => 'QW',
                'criteria' => ['ref' => (string) $record['identity']['ref'], 'last' => (string) $record['identity']['last_name'], 'first' => (string) $record['identity']['first_name']],
                'source' => 'web',
                'operator_user_id' => $this->ctx->userId(),
                'operator_label' => $this->ctx->operatorLabel(),
                'hit' => true,
                'verdict' => $record['response']['code'] === 'hit' ? 'recherche' : 'ras',
                'codes' => array_slice(array_map(static fn (array $f): string => (string) $f['code'], $record['flags']), 0, 16),
            ]);
            $repo->db()->execute(
                'UPDATE sse_file_queries SET identity_id = :i, purpose = :p WHERE id = :id AND tenant_id = :t',
                ['i' => (int) $record['identity']['id'], 'p' => isset(S::PURPOSES[$purpose]) ? $purpose : null, 'id' => $logged['id'], 't' => $tenantId]
            );
        } catch (\Throwable $e) {
            error_log('Fichiers SSE : ouverture non journalisée : ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------ Modifications

    public function profileUpdate(Request $request, array $params = []): Response
    {
        [$tenantId, $id] = [$this->ctx->tenantId(), (int) ($params['id'] ?? 0)];
        if (!$this->ctx->canManage() || !$this->ctx->csrfOk($request) || !$this->identityExists($tenantId, $id)) {
            return $this->ctx->denied($request, self::BASE . '/sujet/' . $id);
        }
        $height = trim($request->inputString('height'));
        $weight = trim($request->inputString('weight'));
        $this->records()->saveProfile($tenantId, $id, [
            'country' => $request->inputString('country'),
            'height_cm' => $height !== '' ? SseMeasure::parseHeight($height) : null,
            'weight_kg' => $weight !== '' ? SseMeasure::parseWeight($weight) : null,
            'build' => $request->inputString('build'),
            'hair' => $request->inputString('hair'),
            'eyes' => $request->inputString('eyes'),
            'complexion' => $request->inputString('complexion'),
            'marks' => $request->inputString('marks'),
            'caution_codes' => is_array($request->input('cautions')) ? $request->input('cautions') : [],
        ], $this->ctx->userId());
        Session::flash('success', 'Signalement enregistré.');

        return Response::redirect(url(self::BASE . '/sujet/' . $id) . '#signalement');
    }

    public function aliasStore(Request $request, array $params = []): Response
    {
        [$tenantId, $id] = [$this->ctx->tenantId(), (int) ($params['id'] ?? 0)];
        if (!$this->ctx->canManage() || !$this->ctx->csrfOk($request) || !$this->identityExists($tenantId, $id)) {
            return $this->ctx->denied($request, self::BASE . '/sujet/' . $id);
        }
        $ok = $this->records()->addAlias($tenantId, $id, mb_substr($request->inputString('alias'), 0, 160), $request->inputString('kind', 'aka'), 'known', 0, $this->ctx->userId());
        Session::flash($ok !== null ? 'success' : 'error', $ok !== null ? 'Alias ajouté.' : 'Alias vide : rien n’a été ajouté.');

        return Response::redirect(url(self::BASE . '/sujet/' . $id) . '#identite');
    }

    public function aliasDelete(Request $request, array $params = []): Response
    {
        $tenantId = $this->ctx->tenantId();
        $aliasId = (int) ($params['id'] ?? 0);
        $row = $this->records()->db()->fetchOne('SELECT * FROM sse_file_aliases WHERE id = :id AND tenant_id = :t', ['id' => $aliasId, 't' => $tenantId]);
        if ($row === null || !$this->ctx->canManage() || !$this->ctx->csrfOk($request)) {
            return $this->ctx->denied($request);
        }
        $this->records()->deleteAlias($tenantId, $aliasId);
        $back = self::BASE . '/sujet/' . (int) $row['identity_id'] . '#identite';
        SseFilesContext::offerUndo('Alias « ' . $row['alias'] . ' » retiré.', 'alias', ['identity_id' => (int) $row['identity_id'], 'alias' => (string) $row['alias'], 'kind' => (string) $row['kind']], $back);

        return Response::redirect(SseFilesContext::safeBack($back));
    }

    public function linkStore(Request $request, array $params = []): Response
    {
        [$tenantId, $id] = [$this->ctx->tenantId(), (int) ($params['id'] ?? 0)];
        if (!$this->ctx->canManage() || !$this->ctx->csrfOk($request) || !$this->identityExists($tenantId, $id)) {
            return $this->ctx->denied($request, self::BASE . '/sujet/' . $id);
        }
        $kind = $request->inputString('kind');
        $linked = 0;
        if ($kind === 'associate') {
            $ref = strtoupper(trim($request->inputString('value')));
            if (preg_match('/^IDT-0*(\d{1,10})$/', $ref, $m) === 1 && $this->identityExists($tenantId, (int) $m[1])) {
                $linked = (int) $m[1];
            }
        }
        $ok = $this->records()->addLink($tenantId, $id, [
            'kind' => $kind,
            'value' => $request->inputString('value'),
            'detail' => [
                'make' => $request->inputString('make'), 'model' => $request->inputString('model'), 'color' => $request->inputString('color'),
                'country' => $request->inputString('link_country'), 'note' => $request->inputString('note'),
            ],
            'linked_identity_id' => $linked > 0 ? $linked : null,
            'relation' => $request->inputString('relation'),
            'flag' => $request->inputString('flag'),
        ], $this->ctx->userId());
        Session::flash($ok !== null ? 'success' : 'error', $ok !== null ? 'Ajouté à la fiche.' : 'Valeur manquante : rien n’a été ajouté.');

        return Response::redirect(url(self::BASE . '/sujet/' . $id) . '#liens');
    }

    public function linkDelete(Request $request, array $params = []): Response
    {
        $tenantId = $this->ctx->tenantId();
        $linkId = (int) ($params['id'] ?? 0);
        $row = $this->records()->db()->fetchOne('SELECT * FROM sse_file_links WHERE id = :id AND tenant_id = :t', ['id' => $linkId, 't' => $tenantId]);
        if ($row === null || !$this->ctx->canManage() || !$this->ctx->csrfOk($request)) {
            return $this->ctx->denied($request);
        }
        $link = SseFileRecordRepository::hydrateLink($row);
        $this->records()->deleteLink($tenantId, $linkId);
        $back = self::BASE . '/sujet/' . $link['identity_id'] . '#liens';
        SseFilesContext::offerUndo($link['kind_label'] . ' « ' . $link['value'] . ' » retiré.', 'link', $link, $back);

        return Response::redirect(SseFilesContext::safeBack($back));
    }

    /** Annuler la dernière suppression (alias, lien, annotation, photo retirée d'une fiche). */
    public function undo(Request $request, array $params = []): Response
    {
        $tenantId = $this->ctx->tenantId();
        $undo = Session::get('sse_files_undo');
        if (!is_array($undo) || !$this->ctx->csrfOk($request) || !hash_equals((string) ($undo['token'] ?? ''), $request->inputString('token'))
            || time() - (int) ($undo['at'] ?? 0) > 900 || !$this->ctx->canWrite()) {
            Session::flash('error', 'Plus rien à annuler.');

            return $this->ctx->back($request);
        }
        Session::forget('sse_files_undo');
        $p = is_array($undo['payload'] ?? null) ? $undo['payload'] : [];
        $uid = $this->ctx->userId();
        match ((string) $undo['kind']) {
            'alias' => $this->ctx->canManage() ? $this->records()->addAlias($tenantId, (int) $p['identity_id'], (string) $p['alias'], (string) $p['kind'], 'known', 0, $uid) : null,
            'link' => $this->ctx->canManage() ? $this->records()->addLink($tenantId, (int) $p['identity_id'], [
                'kind' => (string) $p['kind'], 'value' => (string) $p['value'], 'detail' => (array) ($p['detail'] ?? []),
                'linked_identity_id' => $p['linked_identity_id'] ?? null, 'relation' => $p['relation'] ?? null, 'flag' => $p['flag'] ?? null,
                'visibility' => (string) ($p['visibility'] ?? 'known'), 'reveal_phase' => (int) ($p['reveal_phase'] ?? 0),
            ], $uid) : null,
            'note' => $this->records()->restoreNote($tenantId, (int) $p['id'], $uid, $this->ctx->canManage()),
            'photo' => $this->ctx->allows('photos', 'delete') ? $this->records()->attachPhoto($tenantId, (int) $p['id'], (int) $p['identity_id']) : null,
            'watch' => $uid !== null ? $this->xref()->watch($tenantId, $uid, (string) $p['kind'], (array) $p['values'], ($p['identity_id'] ?? null) !== null ? (int) $p['identity_id'] : null) : null,
            default => null,
        };
        Session::flash('success', 'Suppression annulée.');

        return Response::redirect(SseFilesContext::safeBack((string) ($undo['back'] ?? self::BASE)));
    }

    // ------------------------------------------------------- Annotations

    public function noteStore(Request $request, array $params = []): Response
    {
        [$tenantId, $id] = [$this->ctx->tenantId(), (int) ($params['id'] ?? 0)];
        if (!$this->ctx->canWrite() || !$this->ctx->csrfOk($request) || !$this->identityExists($tenantId, $id)) {
            return $this->ctx->denied($request, self::BASE . '/sujet/' . $id);
        }
        $noteId = $this->records()->addNote($tenantId, $id, $request->inputString('body'), $this->ctx->userId(), $this->ctx->operatorLabel());
        if ($noteId !== null) {
            $this->xref()->reindex($tenantId, 'note', $noteId);
        }
        Session::flash($noteId !== null ? 'success' : 'error', $noteId !== null ? 'Annotation ajoutée au dossier.' : 'Annotation vide.');

        return Response::redirect(url(self::BASE . '/sujet/' . $id) . '#annotations');
    }

    public function noteDelete(Request $request, array $params = []): Response
    {
        $tenantId = $this->ctx->tenantId();
        $noteId = (int) ($params['id'] ?? 0);
        $row = $this->records()->db()->fetchOne('SELECT identity_id FROM sse_file_notes WHERE id = :id AND tenant_id = :t', ['id' => $noteId, 't' => $tenantId]);
        if ($row === null || !$this->ctx->canWrite() || !$this->ctx->csrfOk($request)
            || !$this->records()->deleteNote($tenantId, $noteId, $this->ctx->userId(), $this->ctx->canManage())) {
            return $this->ctx->denied($request);
        }
        $this->xref()->reindex($tenantId, 'note', $noteId);
        $back = self::BASE . '/sujet/' . (int) $row['identity_id'] . '#annotations';
        SseFilesContext::offerUndo('Annotation supprimée.', 'note', ['id' => $noteId], $back);

        return Response::redirect(SseFilesContext::safeBack($back));
    }

    // ------------------------------------------------- Recoupements, veille

    public function xrefDecide(Request $request, array $params = []): Response
    {
        [$tenantId, $id] = [$this->ctx->tenantId(), (int) ($params['id'] ?? 0)];
        if (!$this->ctx->canWrite() || !$this->ctx->csrfOk($request) || !$this->identityExists($tenantId, $id)) {
            return $this->ctx->denied($request, self::BASE . '/sujet/' . $id);
        }
        $decision = $request->inputString('decision');
        $ok = $this->xref()->decide($tenantId, $id, $request->inputString('source_type'), (int) $request->input('source_id', 0), $decision, $this->ctx->userId());
        Session::flash($ok ? 'success' : 'error', match (true) {
            !$ok => 'Recoupement introuvable.',
            $decision === 'attach' => 'Source rattachée à la fiche.',
            $decision === 'reject' => 'Noté : ce recoupement ne sera plus proposé pour cette fiche.',
            default => 'Décision annulée.',
        });

        return Response::redirect(url(self::BASE . '/sujet/' . $id) . '#recoupements');
    }

    public function watchSubject(Request $request, array $params = []): Response
    {
        [$tenantId, $id, $uid] = [$this->ctx->tenantId(), (int) ($params['id'] ?? 0), $this->ctx->userId()];
        if ($uid === null || !$this->ctx->canWrite() || !$this->ctx->csrfOk($request)) {
            return $this->ctx->denied($request, self::BASE . '/sujet/' . $id);
        }
        $record = $this->recordFor($tenantId, $id);
        if ($record === null) {
            return $this->notFound();
        }
        if ($this->xref()->watchesSubject($tenantId, $uid, $id)) {
            $this->xref()->unwatchSubject($tenantId, $uid, $id);
            Session::flash('success', 'Surveillance arrêtée.');
        } else {
            $values = array_values(array_filter(array_merge(
                [(string) $record['identity']['last_name'] !== 'INCONNU' ? (string) $record['identity']['last_name'] : ''],
                array_map(static fn (array $l): string => (string) $l['value'], $record['links']['phone']),
                array_map(static fn (array $l): string => (string) $l['value'], $record['links']['vehicle']),
                array_map(static fn (array $d): string => (string) $d['doc_number'], $record['documents'])
            )));
            $n = $this->xref()->watch($tenantId, $uid, 'subject', $values, $id);
            Session::flash($n > 0 ? 'success' : 'error', $n > 0 ? 'Fiche surveillée : vous serez prévenu quand un nouveau document la cite.' : 'Rien à surveiller sur cette fiche (ni nom, ni numéro).');
        }

        return Response::redirect(url(self::BASE . '/sujet/' . $id));
    }

    public function watchStore(Request $request, array $params = []): Response
    {
        [$tenantId, $uid] = [$this->ctx->tenantId(), $this->ctx->userId()];
        if ($uid === null || !$this->ctx->canWrite() || !$this->ctx->csrfOk($request)) {
            return $this->ctx->denied($request);
        }
        $value = mb_substr(trim($request->inputString('value')), 0, 120);
        $n = $this->xref()->watch($tenantId, $uid, preg_match('/\d/', $value) === 1 ? 'number' : 'name', [$value]);
        Session::flash($n > 0 ? 'success' : 'error', $n > 0 ? 'Surveillance enregistrée : « ' . $value . ' ».' : 'Rien à surveiller : saisissez un nom, un prénom ou un numéro (3 caractères au moins).');

        return $this->ctx->back($request, self::BASE . '#surveillances');
    }

    public function watchDelete(Request $request, array $params = []): Response
    {
        [$tenantId, $uid] = [$this->ctx->tenantId(), $this->ctx->userId()];
        $row = $uid !== null ? $this->records()->db()->fetchOne('SELECT * FROM sse_file_watches WHERE id = :id AND tenant_id = :t AND user_id = :u', ['id' => (int) ($params['id'] ?? 0), 't' => $tenantId, 'u' => $uid]) : null;
        if ($row === null || !$this->ctx->csrfOk($request)) {
            return $this->ctx->denied($request);
        }
        $this->xref()->unwatch($tenantId, (int) $uid, (int) $row['id']);
        SseFilesContext::offerUndo('Surveillance « ' . $row['value'] . ' » arrêtée.', 'watch', ['kind' => (string) $row['kind'], 'values' => [(string) $row['value']], 'identity_id' => $row['identity_id']], self::BASE . '#surveillances');

        return $this->ctx->back($request, self::BASE . '#surveillances');
    }

    /** Texte d'une source citée (compte rendu, fiche, contrôle, extraction), avec le mot recherché surligné. */
    public function source(Request $request, array $params = []): Response
    {
        $tenantId = $this->ctx->tenantId();
        $src = $this->xref()->source($tenantId, (string) ($params['type'] ?? ''), (int) ($params['id'] ?? 0), $this->ctx->canSee());
        if ($src === null) {
            return $this->notFound();
        }
        $subjectId = (int) $request->queryString('sujet');

        return $this->ctx->render('atak.sse.files.source', [
            'title' => $src['title'],
            'source' => $src,
            'subjectId' => $subjectId > 0 && $this->identityExists($tenantId, $subjectId) ? $subjectId : 0,
            'needle' => mb_substr(trim($request->queryString('mot')), 0, 64),
        ]);
    }

    // --------------------------------------------------------- Photos

    /** Photothèque : planche de photos signalétiques filtrée par signalement (« qui ressemble à ça ? »). */
    public function photobook(Request $request, array $params = []): Response
    {
        $tenantId = $this->ctx->tenantId();
        $p = SseFileSearchService::cleanParams($_GET + ['photo' => $request->queryString('toutes') === '1' ? '' : '1']);
        $result = ['hits' => [], 'total' => 0, 'page' => 1, 'pages' => 0];
        try {
            $result = (new SseFileSearchService())->search($tenantId, $p, max(1, (int) $request->queryString('page', '1')), 24);
        } catch (\Throwable $e) {
            error_log('Fichiers SSE : photothèque illisible : ' . $e->getMessage());
        }

        return $this->ctx->render('atak.sse.files.photobook', [
            'title' => 'Photothèque',
            'params' => $p,
            'result' => $result,
            // Clichés non rattachés : ceux d'un membre (fiche SEEK II rattachée) ne sont ni listés ni adressables sans autorisation.
            'unattached' => $this->ctx->allows('photos', 'add')
                ? SseMemberImageGate::forSession()->filterPhotos($tenantId, $this->safe(fn (): array => $this->records()->unattachedPhotos($tenantId, 24)))['photos']
                : [],
            'photoUpload' => !empty($this->ctx->settings()['member_photo_upload']) && $this->ctx->allows('photos', 'add'),
        ]);
    }

    /**
     * Image d'une photo de la photothèque rangée à l'abri (cliché du terminal SEEK II) : GET fichiers/photos/{id}/image.
     * Mêmes droits que la photothèque (accès au portail SSE, porte d'entrée comprise), communauté de la session, jamais
     * la photo d'une identité couverture ou fausse sans l'habilitation Secret (S) ; un identifiant d'une autre
     * communauté, d'une photo masquée ou d'un chemin étranger répond 404, sans rien révéler.
     */
    public function photoImage(Request $request, array $params = []): Response
    {
        $notFound = static fn (): Response => (new Response())
            ->setStatusCode(404)
            ->header('Content-Type', 'text/plain; charset=utf-8')
            ->header('Cache-Control', 'no-store')
            ->setBody('Not found');
        $tenantId = $this->ctx->tenantId();
        $photo = null;
        try {
            $photo = $tenantId > 0 ? $this->records()->photo($tenantId, (int) ($params['id'] ?? 0)) : null;
        } catch (\Throwable $e) {
            error_log('Fichiers SSE : image illisible : ' . $e->getMessage());
        }
        if ($photo === null || ($photo['visibility'] === 'hidden' && !$this->ctx->canManage())) {
            return $notFound();
        }
        if (($photo['identity_id'] ?? null) !== null) {
            $identity = (new SseIdentityRepository())->find((int) $photo['identity_id'], $tenantId);
            if ($identity === null || (in_array($identity['kind'], ['cover', 'forged'], true) && !($this->ctx->canSee())(SseCaseRepository::CLASS_CONFIDENTIAL))) {
                return $notFound();
            }
            // Lore de session : une photo que la fiche ne montre pas encore à l'opérateur ne se lit pas non plus par son adresse.
            if (!$this->ctx->canLore()) {
                try {
                    $profile = $this->records()->profile($tenantId, (int) $photo['identity_id']);
                    $phase = SseFileVisibility::phaseOf($profile, (new SseLoreRepository())->phases($tenantId));
                    if (!SseFileVisibility::inFiles($profile) || !SseFileVisibility::field($profile, 'photo', $phase)) {
                        return $notFound();
                    }
                } catch (\Throwable $e) {
                    error_log('Fichiers SSE : lore de la photo illisible : ' . $e->getMessage());
                }
            }
        }
        // Image d'un membre : TRÈS SECRET (TS), titulaire, hiérarchie, habilitation ou dérogation seulement ; jamais un octet sinon.
        $gate = SseMemberImageGate::forSession();
        $access = $gate->forPhoto($tenantId, $photo);
        if (!$access['allowed']) {
            $gate->recordDenied($tenantId, $gate->viewerId(), 'photo', (int) $photo['id'], $access['owner']);

            return $notFound();
        }
        $seekSvc = new SseSeekPhotoService($this->records());
        $abs = $seekSvc->absolutePath($photo, $tenantId)
            ?? ($access['confidential'] || \App\Support\SseImageStorage::isStoredPath((string) $photo['path']) ? SseMemberImageGate::publicAbsolutePath($photo) : null);
        if ($abs === null) {
            return $notFound();
        }
        if ($access['confidential']) {
            try {
                $gate->recordConsultation($tenantId, (int) $gate->viewerId(), 'photo', (int) $photo['id'], $access['owner'], $access['reason']);
            } catch (\Throwable $e) {
                error_log('Fichiers SSE : consultation d’une image confidentielle non journalisée, image refusée : ' . $e->getMessage());

                return $notFound();
            }
        }
        // Type annoncé = type réel du contenu (jamais la colonne mime ni l'extension) ; pas une image : pas un octet.
        $mime = \App\Support\SseImageStorage::verifiedMime($abs);
        if ($mime === null) {
            return $notFound();
        }
        $ext = ['image/png' => 'png', 'image/webp' => 'webp', 'image/jpeg' => 'jpg'][$mime];

        return (new Response())
            ->header('Content-Type', $mime)
            ->header('Content-Length', (string) filesize($abs))
            ->header('Content-Disposition', 'inline; filename="seek-' . (int) $photo['id'] . '.' . $ext . '"')
            ->header('Cache-Control', $access['confidential'] ? 'private, no-store' : 'private, max-age=3600')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Referrer-Policy', 'no-referrer')
            ->setBodyStream(static function () use ($abs): void {
                $h = fopen($abs, 'rb');
                if ($h !== false) {
                    fpassthru($h);
                    fclose($h);
                }
            });
    }

    /**
     * Image d'une identité du registre (photo d'identité ou signature) : GET fichiers/identites/{id}/photo ou /signature.
     * Seule adresse des portraits et signatures des MEMBRES dans le portail (jamais le dossier public) : TRÈS SECRET (TS), lue par le
     * titulaire, sa hiérarchie, une habilitation « Très secret (TS) » avec le droit « Identités de service » ou une dérogation, et journalisée.
     * Identité d'une autre communauté, image absente ou refus : 404 identique, sans rien révéler.
     */
    public function identityImage(Request $request, array $params = []): Response
    {
        $notFound = static fn (): Response => (new Response())
            ->setStatusCode(404)
            ->header('Content-Type', 'text/plain; charset=utf-8')
            ->header('Cache-Control', 'no-store')
            ->setBody('Not found');
        if (!in_array($params['kind'] ?? '', ['photo', 'signature'], true)) {
            return $notFound();
        }
        $kind = (string) $params['kind'];
        $tenantId = $this->ctx->tenantId();
        $identity = null;
        try {
            $identity = $tenantId > 0 ? (new SseIdentityRepository())->find((int) ($params['id'] ?? 0), $tenantId) : null;
        } catch (\Throwable $e) {
            error_log('Fichiers SSE : identité illisible : ' . $e->getMessage());
        }
        $path = $identity !== null ? (string) ($identity[$kind . '_path'] ?? '') : '';
        $folder = $kind === 'signature' ? 'sse-id-signatures' : 'sse-id-photos';
        // Espace privé (sse-private/…) ou emplacement hérité (uploads/…) le temps de la migration des fichiers.
        if ($identity === null || preg_match('#^(?:uploads|sse-private)/' . $folder . '/' . $tenantId . '_[a-f0-9]{16}\.(jpg|png|webp)$#', $path) !== 1) {
            return $notFound();
        }
        $gate = SseMemberImageGate::forSession();
        $access = $gate->forIdentity($tenantId, $identity);
        if (!$access['allowed']) {
            $gate->recordDenied($tenantId, $gate->viewerId(), 'identity_' . $kind, (int) $identity['id'], $access['owner']);

            return $notFound();
        }
        if (!$access['confidential'] && in_array($identity['kind'], ['cover', 'forged'], true) && !($this->ctx->canSee())(SseCaseRepository::CLASS_CONFIDENTIAL)) {
            return $notFound();
        }
        $abs = SseMemberImageGate::publicAbsolutePath(['path' => $path]);
        if ($abs === null) {
            return $notFound();
        }
        if ($access['confidential']) {
            try {
                $gate->recordConsultation($tenantId, (int) $gate->viewerId(), 'identity_' . $kind, (int) $identity['id'], $access['owner'], $access['reason']);
            } catch (\Throwable $e) {
                error_log('Fichiers SSE : consultation d’une image confidentielle non journalisée, image refusée : ' . $e->getMessage());

                return $notFound();
            }
        }
        // Type annoncé = type réel du contenu (jamais l'extension) ; un fichier qui n'est pas une image n'est pas servi.
        $mime = \App\Support\SseImageStorage::verifiedMime($abs);
        if ($mime === null) {
            return $notFound();
        }
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));

        return (new Response())
            ->header('Content-Type', $mime)
            ->header('Content-Length', (string) filesize($abs))
            ->header('Content-Disposition', 'inline; filename="identite-' . (int) $identity['id'] . '-' . $kind . '.' . $ext . '"')
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Referrer-Policy', 'no-referrer')
            ->setBodyStream(static function () use ($abs): void {
                $h = fopen($abs, 'rb');
                if ($h !== false) {
                    fpassthru($h);
                    fclose($h);
                }
            });
    }

    /**
     * Image de fond d'un papier à gabarit personnalisé : GET fichiers/documents/{id}/fond. Rangée dans l'espace privé SSE, jamais
     * servie par le dossier public. Mêmes règles que le portrait de l'identité du papier (TRÈS SECRET (TS) pour un membre,
     * habilitation Secret (S) pour une couverture ou une fausse identité) ; autre communauté, absente ou refus : 404 identique.
     */
    public function documentBackground(Request $request, array $params = []): Response
    {
        $notFound = static fn (): Response => (new Response())
            ->setStatusCode(404)
            ->header('Content-Type', 'text/plain; charset=utf-8')
            ->header('Cache-Control', 'no-store')
            ->setBody('Not found');
        $tenantId = $this->ctx->tenantId();
        $doc = null;
        $identity = null;
        try {
            $doc = $tenantId > 0 ? (new \App\Repositories\SseIdDocumentRepository())->find((int) ($params['id'] ?? 0), $tenantId) : null;
            $identity = $doc !== null && (int) ($doc['identity_id'] ?? 0) > 0 ? (new SseIdentityRepository())->find((int) $doc['identity_id'], $tenantId) : null;
        } catch (\Throwable $e) {
            error_log('Fichiers SSE : papier illisible : ' . $e->getMessage());
        }
        $path = $doc !== null ? (string) ($doc['background_path'] ?? '') : '';
        if ($doc === null || $identity === null || preg_match('#^(?:uploads|sse-private)/sse-id-backgrounds/' . $tenantId . '_[a-f0-9]{16}\.(jpg|png|webp)$#', $path) !== 1) {
            return $notFound();
        }
        $gate = SseMemberImageGate::forSession();
        $access = $gate->forIdentity($tenantId, $identity);
        if (!$access['allowed']) {
            $gate->recordDenied($tenantId, $gate->viewerId(), 'document_background', (int) $doc['id'], $access['owner']);

            return $notFound();
        }
        if (!$access['confidential'] && in_array($identity['kind'], ['cover', 'forged'], true) && !($this->ctx->canSee())(SseCaseRepository::CLASS_CONFIDENTIAL)) {
            return $notFound();
        }
        $abs = SseMemberImageGate::publicAbsolutePath(['path' => $path]);
        $mime = $abs !== null ? \App\Support\SseImageStorage::verifiedMime($abs) : null;
        if ($abs === null || $mime === null) {
            return $notFound();
        }
        if ($access['confidential']) {
            try {
                $gate->recordConsultation($tenantId, (int) $gate->viewerId(), 'document_background', (int) $doc['id'], $access['owner'], $access['reason']);
            } catch (\Throwable $e) {
                error_log('Fichiers SSE : consultation d’une image confidentielle non journalisée, image refusée : ' . $e->getMessage());

                return $notFound();
            }
        }

        return (new Response())
            ->header('Content-Type', $mime)
            ->header('Content-Length', (string) filesize($abs))
            ->header('Content-Disposition', 'inline; filename="papier-' . (int) $doc['id'] . '-fond.' . strtolower(pathinfo($abs, PATHINFO_EXTENSION)) . '"')
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Referrer-Policy', 'no-referrer')
            ->setBodyStream(static function () use ($abs): void {
                $h = fopen($abs, 'rb');
                if ($h !== false) {
                    fpassthru($h);
                    fclose($h);
                }
            });
    }

    /** Demandes d'accès aux images des membres, à décider par la hiérarchie : GET fichiers/acces-images. */
    public function accessRequests(Request $request, array $params = []): Response
    {
        $tenantId = $this->ctx->tenantId();
        $viewer = SseMemberImageGate::forSession()->viewerId();
        $rows = [];
        if ($viewer !== null && $tenantId > 0) {
            try {
                $rows = SseMemberImageGate::forSession()->decidableRequests($tenantId, $viewer);
            } catch (\Throwable $e) {
                error_log('Fichiers SSE : demandes d’accès illisibles : ' . $e->getMessage());
            }
        }
        $names = [];
        foreach ($rows as $r) {
            foreach (['owner_user_id', 'grantee_user_id'] as $k) {
                $uid = (int) $r[$k];
                if (!isset($names[$uid])) {
                    $u = (new \App\Repositories\UserRepository())->findById($uid, $tenantId);
                    $names[$uid] = trim((string) ($u['callsign'] ?? '')) !== '' ? (string) $u['callsign'] : trim((string) ($u['display_name'] ?? ('Membre ' . $uid)));
                }
            }
        }

        return $this->ctx->render('atak.sse.files.access_requests', [
            'title' => 'Accès aux images confidentielles',
            'requests' => $rows,
            'names' => $names,
            'maxEnd' => gmdate('Y-m-d', time() + 30 * 86400),
            'minEnd' => gmdate('Y-m-d', time() + 86400),
        ]);
    }

    /** « Demander l'accès » depuis un cadre TRÈS SECRET (TS) : POST fichiers/acces-images/demande. */
    public function accessRequestStore(Request $request, array $params = []): Response
    {
        $gate = SseMemberImageGate::forSession();
        $viewer = $gate->viewerId();
        if ($viewer === null || !$this->ctx->csrfOk($request)) {
            return $this->ctx->denied($request, self::BASE . '/photos');
        }
        try {
            $r = $gate->requestAccess($this->ctx->tenantId(), $viewer, (int) $request->inputString('owner'), $request->inputString('reason'));
        } catch (\Throwable $e) {
            error_log('Fichiers SSE : demande d’accès impossible : ' . $e->getMessage());
            $r = ['ok' => false, 'message' => 'Demande impossible pour le moment.'];
        }
        Session::flash($r['ok'] ? 'success' : 'error', $r['message']);

        return $this->ctx->back($request, self::BASE . '/photos');
    }

    /** Décision d'un supérieur : POST fichiers/acces-images/{id}. */
    public function accessDecide(Request $request, array $params = []): Response
    {
        $gate = SseMemberImageGate::forSession();
        $viewer = $gate->viewerId();
        if ($viewer === null || !$this->ctx->csrfOk($request)) {
            return $this->ctx->denied($request, self::BASE . '/acces-images');
        }
        try {
            $r = $gate->decideRequest(
                $this->ctx->tenantId(),
                $viewer,
                (int) ($params['id'] ?? 0),
                $request->inputString('decision') === 'grant',
                $request->inputString('reason'),
                $request->inputString('ends_at') !== '' ? $request->inputString('ends_at') . ' 23:59:59' : null
            );
        } catch (\Throwable $e) {
            error_log('Fichiers SSE : décision d’accès impossible : ' . $e->getMessage());
            $r = ['ok' => false, 'message' => 'Décision impossible pour le moment.'];
        }
        Session::flash($r['ok'] ? 'success' : 'error', $r['message']);

        return Response::redirect(url(self::BASE . '/acces-images'));
    }

    /** Deux fiches côte à côte (photos et signalement). */
    public function compare(Request $request, array $params = []): Response
    {
        $tenantId = $this->ctx->tenantId();
        $a = $this->recordFor($tenantId, (int) $request->queryString('a'));
        $b = $this->recordFor($tenantId, (int) $request->queryString('b'));

        return $this->ctx->render('atak.sse.files.compare', ['title' => 'Comparer deux fiches', 'a' => $a, 'b' => $b]);
    }

    /** Envoi d'une photo par un membre : vérifiée, ré-encodée, simplement rangée (aucune reconnaissance faciale). */
    public function photoUpload(Request $request, array $params = []): Response
    {
        $tenantId = $this->ctx->tenantId();
        if (!$this->ctx->allows('photos', 'add') || !$this->ctx->csrfOk($request) || empty($this->ctx->settings()['member_photo_upload'])) {
            return $this->ctx->denied($request, self::BASE . '/photos');
        }
        if ((new FileRateLimiter())->tooManyAttempts('sse_files_photo|' . $tenantId . '|' . (int) $this->ctx->userId(), 30, 3600)) {
            Session::flash('error', 'Trop de photos envoyées en une heure : réessayez plus tard.');

            return $this->ctx->back($request, self::BASE . '/photos');
        }
        $identityId = (int) $request->input('identity_id', 0);
        if ($identityId > 0 && !$this->identityExists($tenantId, $identityId)) {
            $identityId = 0;
        }
        $stored = (new SseIdImageService())->storeUpload($_FILES['photo'] ?? null, 'photo', $tenantId);
        if (!$stored['ok'] || !isset($stored['path'])) {
            Session::flash('error', !empty($stored['none']) ? 'Choisissez une image.' : (string) ($stored['error'] ?? 'Image refusée.'));

            return $this->ctx->back($request, self::BASE . '/photos');
        }
        $abs = (string) \App\Support\SseImageStorage::readable((string) $stored['path']);
        $size = is_file($abs) ? @getimagesize($abs) : false;
        $kind = in_array($request->inputString('kind'), ['front', 'profile', 'field', 'document'], true) ? $request->inputString('kind') : 'field';
        $this->records()->addPhoto($tenantId, [
            'identity_id' => $identityId > 0 ? $identityId : null,
            'path' => $stored['path'],
            'mime' => is_array($size) ? (string) ($size['mime'] ?? 'image/jpeg') : 'image/jpeg',
            'width' => is_array($size) ? (int) $size[0] : null,
            'height' => is_array($size) ? (int) $size[1] : null,
            'bytes' => is_file($abs) ? (int) filesize($abs) : null,
            'sha1' => is_file($abs) ? (string) sha1_file($abs) : null,
            'kind' => $kind,
            'source' => 'upload',
            'caption' => $request->inputString('caption'),
        ], $this->ctx->userId());
        Session::flash('success', $identityId > 0 ? 'Photo ajoutée à la fiche.' : 'Photo rangée dans la photothèque : rattachez-la à une fiche quand vous savez qui c’est.');

        return $this->ctx->back($request, self::BASE . '/photos');
    }

    public function photoAttach(Request $request, array $params = []): Response
    {
        $tenantId = $this->ctx->tenantId();
        $photo = $this->records()->photo($tenantId, (int) ($params['id'] ?? 0));
        if ($photo === null || !$this->ctx->allows('photos', 'write') || !$this->ctx->csrfOk($request)
            || ($request->inputString('detach') === '1' && !$this->ctx->allows('photos', 'delete'))) {
            return $this->ctx->denied($request, self::BASE . '/photos');
        }
        $ref = strtoupper(trim($request->inputString('target')));
        $target = preg_match('/^(?:IDT-)?0*(\d{1,10})$/', $ref, $m) === 1 ? (int) $m[1] : 0;
        if ($request->inputString('detach') === '1') {
            $this->records()->attachPhoto($tenantId, (int) $photo['id'], null);
            SseFilesContext::offerUndo('Photo retirée de la fiche.', 'photo', ['id' => (int) $photo['id'], 'identity_id' => (int) $photo['identity_id']], self::BASE . '/sujet/' . (int) $photo['identity_id']);

            return Response::redirect(url(self::BASE . '/sujet/' . (int) $photo['identity_id']));
        }
        if ($target < 1 || !$this->identityExists($tenantId, $target)) {
            Session::flash('error', 'Fiche introuvable : indiquez sa référence (IDT-…).');

            return $this->ctx->back($request, self::BASE . '/photos');
        }
        $this->records()->attachPhoto($tenantId, (int) $photo['id'], $target);
        Session::flash('success', 'Photo rattachée à la fiche IDT-' . $target . '.');

        return Response::redirect(url(self::BASE . '/sujet/' . $target) . '#photos');
    }

    // ------------------------------------------------ Recherches enregistrées

    public function searchSave(Request $request, array $params = []): Response
    {
        [$tenantId, $uid] = [$this->ctx->tenantId(), $this->ctx->userId()];
        if ($uid === null || !$this->ctx->csrfOk($request)) {
            return $this->ctx->denied($request);
        }
        $p = SseFileSearchService::cleanParams(is_array($request->input('p')) ? $request->input('p') : []);
        $this->records()->rememberSearch($tenantId, $uid, $p, true, mb_substr(trim($request->inputString('label')), 0, 80) ?: null);
        Session::flash('success', 'Recherche enregistrée.');

        return $this->ctx->back($request);
    }

    public function searchDelete(Request $request, array $params = []): Response
    {
        [$tenantId, $uid] = [$this->ctx->tenantId(), $this->ctx->userId()];
        if ($uid === null || !$this->ctx->csrfOk($request)) {
            return $this->ctx->denied($request);
        }
        $this->records()->deleteSearch($tenantId, $uid, (int) ($params['id'] ?? 0));

        return $this->ctx->back($request);
    }

    // ------------------------------------------------- Téléphones du jeu

    public function phones(Request $request, array $params = []): Response
    {
        $tenantId = $this->ctx->tenantId();
        $uid = $this->ctx->userId();
        $moderator = $this->ctx->allows('extractions', 'manage');
        $mine = $request->queryString('miens') === '1' && $uid !== null;
        $list = ['rows' => [], 'total' => 0];
        $devices = [];
        try {
            $list = $this->extractions()->list($tenantId, ['mine' => $mine ? (int) $uid : 0, 'q' => mb_substr($request->queryString('q'), 0, 60), 'page' => (int) $request->queryString('page', '1')], $moderator);
            $devices = $uid !== null ? $this->extractions()->devices($tenantId, $uid) : [];
        } catch (\Throwable $e) {
            error_log('Fichiers SSE : extractions illisibles : ' . $e->getMessage());
        }

        return $this->ctx->render('atak.sse.files.phones', [
            'title' => 'Téléphones du jeu',
            'list' => $list,
            'devices' => $devices,
            'mine' => $mine,
            'q' => $request->queryString('q'),
            'enabled' => !empty($this->ctx->settings()['extractions_enabled']),
        ]);
    }

    public function phoneShow(Request $request, array $params = []): Response
    {
        $tenantId = $this->ctx->tenantId();
        $ext = $this->extractions()->find($tenantId, (int) ($params['id'] ?? 0), $this->ctx->allows('extractions', 'manage'));
        if ($ext === null) {
            return $this->notFound();
        }
        $tab = $request->queryString('onglet', 'contacts');
        $tab = isset(SsePhoneExtractionService::CATEGORIES[$tab]) || $tab === 'reseau' ? $tab : 'contacts';
        $q = mb_substr(trim($request->queryString('q')), 0, 80);
        $subject = $ext['identity_id'] !== null ? (new SseIdentityRepository())->find((int) $ext['identity_id'], $tenantId) : null;

        return $this->ctx->render('atak.sse.files.phone_show', [
            'title' => $ext['ref'] . ' — Extraction',
            'ext' => $ext,
            'tab' => $tab,
            'q' => $q,
            'counts' => $this->extractions()->counts($ext),
            'items' => $tab === 'reseau' ? [] : $this->extractions()->listItems($tenantId, (int) $ext['id'], $q !== '' ? '' : $tab, $q),
            'subject' => $subject,
        ]);
    }

    public function phoneExport(Request $request, array $params = []): Response
    {
        $tenantId = $this->ctx->tenantId();
        $ext = $this->extractions()->find($tenantId, (int) ($params['id'] ?? 0), $this->ctx->allows('extractions', 'manage'));
        if ($ext === null || !$this->ctx->allows('extractions', 'export')) {
            return $this->notFound();
        }
        $items = $this->extractions()->listItems($tenantId, (int) $ext['id'], '');
        if ($request->queryString('format') === 'csv') {
            $fh = fopen('php://temp', 'w+');
            if ($fh === false) {
                return $this->notFound();
            }
            fwrite($fh, "\xEF\xBB\xBF");
            fputcsv($fh, ['categorie', 'heure', 'titre', 'texte', 'numero', 'grille', 'sens'], ';', '"', '');
            foreach ($items as $it) {
                fputcsv($fh, [$it['category'], $it['at'], $it['title'], $it['body'], $it['number'], $it['grid'], $it['direction']], ';', '"', '');
            }
            rewind($fh);

            return (new Response())->setStatusCode(200)->header('Content-Type', 'text/csv; charset=utf-8')
                ->header('Content-Disposition', 'attachment; filename="' . $ext['ref'] . '.csv"')->setBody((string) stream_get_contents($fh));
        }

        return (new Response())->setStatusCode(200)->header('Content-Type', 'application/json; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="' . $ext['ref'] . '.json"')
            ->setBody((string) json_encode(['format' => 'athena-sse-extraction', 'simulation' => true, 'extraction' => $ext, 'items' => $items], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function phoneAttach(Request $request, array $params = []): Response
    {
        $tenantId = $this->ctx->tenantId();
        $ext = $this->extractions()->find($tenantId, (int) ($params['id'] ?? 0), $this->ctx->allows('extractions', 'manage'));
        if ($ext === null || !$this->ctx->allows('extractions', 'write', $this->ctx->profile('files_manage')) || !$this->ctx->csrfOk($request)) {
            return $this->ctx->denied($request, self::BASE . '/telephones');
        }
        $ref = strtoupper(trim($request->inputString('target')));
        $target = preg_match('/^(?:IDT-)?0*(\d{1,10})$/', $ref, $m) === 1 ? (int) $m[1] : 0;
        if ($ref !== '' && ($target < 1 || !$this->identityExists($tenantId, $target))) {
            Session::flash('error', 'Fiche introuvable : indiquez sa référence (IDT-…).');
        } else {
            $this->extractions()->attach($tenantId, (int) $ext['id'], $target > 0 ? $target : null);
            Session::flash('success', $target > 0 ? 'Extraction rattachée à la fiche IDT-' . $target . '.' : 'Extraction détachée.');
        }

        return Response::redirect(url(self::BASE . '/telephones/' . (int) $ext['id']));
    }

    public function deviceConsent(Request $request, array $params = []): Response
    {
        [$tenantId, $uid] = [$this->ctx->tenantId(), $this->ctx->userId()];
        if ($uid === null || !$this->ctx->csrfOk($request)) {
            return $this->ctx->denied($request, self::BASE . '/telephones');
        }
        $allowed = $request->inputString('allowed') === '1';
        $ok = $this->extractions()->setConsent($tenantId, $uid, (int) ($params['id'] ?? 0), $allowed);
        Session::flash($ok ? 'success' : 'error', !$ok ? 'Téléphone introuvable.' : ($allowed ? 'Synchronisation de nouveau acceptée pour ce téléphone.' : 'Ce téléphone ne pourra plus envoyer de données au site.'));

        return Response::redirect(url(self::BASE . '/telephones'));
    }

    // ------------------------------------------------------------ Outils

    private function identityExists(int $tenantId, int $id): bool
    {
        if ($id < 1) {
            return false;
        }
        $i = (new SseIdentityRepository())->find($id, $tenantId);

        return $i !== null && $i['kind'] !== 'forged';
    }

    private function catchUp(int $tenantId): void
    {
        try {
            if (!empty($this->ctx->settings()['xref_enabled'])) {
                $this->xref()->catchUp($tenantId, 200);
            }
        } catch (\Throwable $e) {
            error_log('Fichiers SSE : indexation différée : ' . $e->getMessage());
        }
    }

    private function notFound(): Response
    {
        Session::flash('error', 'Fiche introuvable dans les fichiers de votre communauté.');

        return Response::redirect(url(self::BASE));
    }

    /** @return list<array<string, mixed>> */
    private function safe(callable $fn): array
    {
        try {
            $r = $fn();

            return is_array($r) ? array_values($r) : [];
        } catch (\Throwable $e) {
            error_log('Fichiers SSE : ' . $e->getMessage());

            return [];
        }
    }

    private function records(): SseFileRecordRepository
    {
        return $this->records ??= new SseFileRecordRepository();
    }

    private function service(): SseFileRecordService
    {
        return $this->service ??= new SseFileRecordService($this->records());
    }

    private function xref(): SseFileXrefService
    {
        return $this->xref ??= new SseFileXrefService($this->records());
    }

    private function extractions(): SsePhoneExtractionService
    {
        return $this->phones ??= new SsePhoneExtractionService($this->records(), $this->xref());
    }
}
