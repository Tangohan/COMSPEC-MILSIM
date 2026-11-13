<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseFileRecordRepository;
use App\Repositories\SseFileRepository;
use App\Repositories\SseIdentityRepository;
use App\Services\Security\FileRateLimiter;
use App\Services\Sse\SseFileRecordService;
use App\Services\Sse\SseFileSearchService;
use App\Services\Sse\SseFileXrefService;
use App\Services\Sse\SseIdImageService;
use App\Services\Sse\SsePhoneExtractionService;
use App\Support\SseFileSubjectCatalog as S;
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
            'photo' => $this->ctx->canManage() ? $this->records()->attachPhoto($tenantId, (int) $p['id'], (int) $p['identity_id']) : null,
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
            'unattached' => $this->ctx->canWrite() ? $this->safe(fn (): array => $this->records()->unattachedPhotos($tenantId, 24)) : [],
            'photoUpload' => !empty($this->ctx->settings()['member_photo_upload']) && $this->ctx->canWrite(),
        ]);
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
        if (!$this->ctx->canWrite() || !$this->ctx->csrfOk($request) || empty($this->ctx->settings()['member_photo_upload'])) {
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
        $abs = base_path('public/' . ltrim($stored['path'], '/'));
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
        if ($photo === null || !$this->ctx->canWrite() || !$this->ctx->csrfOk($request)
            || ($request->inputString('detach') === '1' && !$this->ctx->canManage())) {
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
        $moderator = $this->ctx->canAdmin();
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
        $ext = $this->extractions()->find($tenantId, (int) ($params['id'] ?? 0), $this->ctx->canAdmin());
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
        $ext = $this->extractions()->find($tenantId, (int) ($params['id'] ?? 0), $this->ctx->canAdmin());
        if ($ext === null || !$this->ctx->canWrite()) {
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
        $ext = $this->extractions()->find($tenantId, (int) ($params['id'] ?? 0), $this->ctx->canAdmin());
        if ($ext === null || !$this->ctx->canManage() || !$this->ctx->csrfOk($request)) {
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
