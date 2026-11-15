<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseIdentityEventRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseWantedAttachmentRepository;
use App\Repositories\SseWantedDetectionRepository;
use App\Repositories\SseAuditLogRepository;
use App\Repositories\SseWantedExtrasRepository;
use App\Repositories\SseWantedMetaRepository;
use App\Repositories\SseWantedNoticeRepository;
use App\Repositories\SseWantedSettingsRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseClearanceService;
use App\Services\Sse\SseWantedAccess;
use App\Services\Sse\SseWantedAttachments;
use App\Services\Sse\SseWantedNoticePdfService;
use App\Services\Sse\SseWantedService;
use App\Support\SseWantedCatalog as C;
use App\Support\SseWantedCodes;
use App\Support\SseWantedPhysical;
use App\Support\SseWantedServiceAdmin;
use App\Support\SseWantedServices;

/**
 * Portail SSE : avis de recherche posés depuis la fiche d'une identité fictive (fait de jeu de rôle), notice PDF, liste des
 * avis, détections système et réglages de la notice.
 *
 * Droits (SseWantedAccess, qui réutilise les gardes existantes) : lire = accès au portail ; placer, lever, régler, traiter une
 * détection = gestion des fichiers SSE ; générer le PDF = cette gestion et les droits de l'atelier de papiers. Chaque écriture
 * exige le jeton CSRF ; l'identité et l'avis doivent appartenir à la communauté de la session.
 */
final class SseWantedNoticeController
{
    private const BASE = 'atak/sse/avis';

    private const PAPERS = 'atak/sse/papiers/identites';
    public const TOKEN_EXPIRED = 'Votre page est restée ouverte trop longtemps ou la session a changé : rechargez la page puis recommencez.';
    public const NEED_MANAGE = 'Droit manquant : écrire dans « Fichiers et sujets » (gestion des dossiers ou des fichiers SSE).';
    public const NEED_ADMIN = 'Droit manquant : les services demandeurs et la validation des avis relèvent du niveau « Gérer » de la section Fichiers et sujets.';
    public const NEED_PDF = 'Droit manquant : la notice PDF demande aussi d’écrire dans « Papiers et couvertures », d’exporter les papiers et l’habilitation Encadrement.';

    private ?SseWantedAccess $rights = null;

    public function __construct(
        private ?SseAccessCodeService $access = null,
        private ?SseClearanceService $clearance = null,
        private ?SseWantedNoticeRepository $notices = null,
        private ?SseIdentityRepository $identities = null,
        private ?SseWantedDetectionRepository $detections = null,
        private ?SseWantedSettingsRepository $settings = null,
        private ?SseWantedService $service = null,
        ?SseWantedAccess $rights = null,
        private ?SseWantedAttachments $attachments = null,
    ) {
        $this->access ??= new SseAccessCodeService();
        $this->clearance ??= new SseClearanceService($this->access);
        $this->notices ??= new SseWantedNoticeRepository();
        $this->identities ??= new SseIdentityRepository();
        $this->detections ??= new SseWantedDetectionRepository();
        $this->settings ??= new SseWantedSettingsRepository();
        $this->service ??= new SseWantedService($this->notices, $this->identities, null, $this->settings);
        $this->rights = $rights;
    }

    // ------------------------------------------------------------ Liste des avis

    public function index(Request $request, array $params = []): Response
    {
        $r = $this->r();
        if (!$r->canRead()) {
            return $this->denied(url('atak/sse/accueil'));
        }
        $tenantId = $r->tenantId();
        $ready = SseWantedNoticeRepository::ready();
        $filters = [
            'status' => $request->queryString('statut', 'active'),
            'type' => $request->queryString('type'),
            'threat' => $request->queryString('menace'),
            'q' => $request->queryString('q'),
            'code' => strtoupper($request->queryString('code')),
        ];
        if (!in_array($filters['status'], ['', 'active', 'lifted', 'expired', 'captured', 'a_valider'], true)) {
            $filters['status'] = 'active';
        }
        $notices = [];
        $counts = ['active' => 0, 'lifted' => 0, 'expired' => 0, 'captured' => 0];
        $newDetections = 0;
        $config = $this->settings->get($tenantId);
        $extras = [];
        $attachCounts = [];
        $noticeIds = [];
        $pendingIds = [];
        if ($ready) {
            $this->service->expireDue($tenantId);
            // Filtre par code, et recherche libre qui trouve aussi les codes dont le libellé correspond.
            $listFilters = $filters;
            if (SseWantedCodes::kindOf($filters['code']) !== null) {
                $listFilters['codes'] = [$filters['code']];
            }
            $listFilters['q_codes'] = SseWantedCodes::matching($filters['q'], $config);
            $pendingIds = $this->safe(fn (): array => (new SseWantedMetaRepository())->pendingIds($tenantId)) ?? [];
            if ($filters['status'] === 'a_valider') {
                // File « À valider » : avis proposés par un analyste, en attente d'un responsable.
                $listFilters['status'] = 'active';
                $listFilters['ids'] = $pendingIds === [] ? [0] : $pendingIds;
            }
            $notices = $this->safe(fn (): array => $this->notices->list($tenantId, $listFilters)) ?? [];
            $noticeIds = array_map(static fn (array $n): int => (int) $n['id'], $notices);
            $extras = $this->safe(fn (): array => (new SseWantedExtrasRepository())->forNotices($tenantId, $noticeIds)) ?? [];
            $attachCounts = $this->safe(fn (): array => (new SseWantedAttachmentRepository())->counts($tenantId, $noticeIds)) ?? [];
            $counts = $this->safe(fn (): array => $this->notices->counts($tenantId)) ?? $counts;
            $newDetections = (int) ($this->safe(fn (): array => $this->detections->counts($tenantId))['new'] ?? 0);
        }

        return $this->page('atak.sse.papers.wanted_list', [
            'title' => 'Avis de recherche',
            'notices' => $notices,
            'noticeExtras' => $extras,
            'attachCounts' => $attachCounts,
            'codeChoices' => ['reasons' => SseWantedCodes::active($config, SseWantedCodes::REASONS), 'measures' => SseWantedCodes::active($config, SseWantedCodes::MEASURES)],
            'filters' => $filters,
            'counts' => $counts,
            'newDetections' => $newDetections,
            'pendingCount' => count($pendingIds),
            'pendingIds' => $pendingIds,
            'noticeMeta' => $this->safe(fn (): array => (new SseWantedMetaRepository())->forNotices($tenantId, $noticeIds)) ?? [],
            'wantedReady' => $ready,
            'wantedConfig' => $config,
            'wantedTypes' => C::types($config),
            'authors' => $this->authors($tenantId, $notices),
            'rights' => $r,
            'lang' => self::readerLang(),
        ]);
    }

    // ------------------------------------------------------------ Placer un avis

    public function placeForm(Request $request, array $params = []): Response
    {
        $r = $this->r();
        $identity = $this->identities->find((int) ($params['id'] ?? 0), $r->tenantId());
        if ($identity === null) {
            return $this->flashBack('error', 'Identité introuvable.', url(self::PAPERS));
        }
        if (!$r->canManage()) {
            return $this->denied(url(self::PAPERS . '/' . (int) $identity['id']), self::NEED_MANAGE);
        }

        return $this->renderPlaceForm($identity, [], []);
    }

    public function place(Request $request, array $params = []): Response
    {
        $r = $this->r();
        $tenantId = $r->tenantId();
        $identity = $this->identities->find((int) ($params['id'] ?? 0), $tenantId);
        if ($identity === null) {
            return $this->flashBack('error', 'Identité introuvable.', url(self::PAPERS));
        }
        $back = url(self::PAPERS . '/' . (int) $identity['id']);
        if (!self::csrfOk($request)) {
            return $this->denied($back, self::TOKEN_EXPIRED);
        }
        if (!$r->canManage()) {
            return $this->denied($back, self::NEED_MANAGE);
        }
        $input = $this->placeInput($request);
        // Les dates se saisissent à l'heure de la communauté ; le service les reçoit en UTC.
        $tz = (string) ($this->settings->get($tenantId)['process']['timezone'] ?? 'UTC');
        $utc = $input;
        foreach (['issued_at', 'expires_at', 'last_seen_at'] as $dateKey) {
            $utc[$dateKey] = self::localToUtc((string) ($input[$dateKey] ?? ''), $tz);
        }
        $res = $this->service->place($tenantId, (int) $identity['id'], $utc, $r, ['is_member' => $this->isMemberIdentity($tenantId, $identity)]);
        if ($res['ok'] !== true) {
            if ($res['error'] === 'forbidden') {
                return $this->denied($back);
            }

            return $this->renderPlaceForm($identity, $input, $res['errors'], 422);
        }
        $problems = $res['warnings'];
        $notice = $this->safe(fn (): ?array => $this->notices->find($tenantId, (int) $res['id']));
        if ($notice !== null) {
            $problems = array_merge($problems, $this->attachAfterPlace($request, $tenantId, $notice, $r));
        }
        Session::flash('success', $res['state'] === 'pending'
            ? 'Avis de recherche ' . $res['ref'] . ' proposé : un responsable doit le valider avant sa diffusion.'
            : 'Avis de recherche ' . $res['ref'] . ' placé.');
        if ($problems !== []) {
            Session::flash('error', implode(' ', array_slice($problems, 0, 4)));
        }

        return Response::redirect($back . '#avis');
    }

    // ------------------------------------------------------------ Lever un avis

    public function lift(Request $request, array $params = []): Response
    {
        $r = $this->r();
        $tenantId = $r->tenantId();
        $notice = $this->safe(fn (): ?array => $this->notices->find($tenantId, (int) ($params['id'] ?? 0)));
        if ($notice === null) {
            return $this->flashBack('error', 'Avis introuvable.', url(self::BASE));
        }
        $back = url(self::PAPERS . '/' . (int) $notice['identity_id']) . '#avis';
        if (!self::csrfOk($request)) {
            return $this->denied($back, self::TOKEN_EXPIRED);
        }
        if (!$r->canManage()) {
            return $this->denied($back, self::NEED_MANAGE);
        }
        $res = $this->service->lift($tenantId, (int) $notice['id'], $request->inputString('status', 'lifted'), $request->inputString('reason'), $r, $request->inputString('service_code') ?: null);
        if ($res['ok'] !== true) {
            return $this->flashBack('error', $res['errors'][0] ?? 'Action non autorisée.', $back);
        }

        return $this->flashBack('success', $request->inputString('status') === 'captured' ? 'Avis ' . $notice['ref'] . ' clos : personne capturée.' : 'Avis ' . $notice['ref'] . ' levé.', $back);
    }

    // ------------------------------------------------------------ Renouveler, prolonger, valider

    /** Renouvelle un avis : nouvel avis lié, mêmes motifs et même service, nouvelles dates. */
    public function renew(Request $request, array $params = []): Response
    {
        return $this->lifecycle($request, (int) ($params['id'] ?? 0), function (SseWantedAccess $r, array $notice) use ($request): array {
            $tz = (string) ($this->settings->get($r->tenantId())['process']['timezone'] ?? 'UTC');

            return $this->service->renew($r->tenantId(), (int) $notice['id'], [
                'issued_at' => self::localToUtc($request->inputString('issued_at'), $tz),
                'expires_at' => self::localToUtc($request->inputString('expires_at'), $tz),
                'no_expiry' => $request->inputString('no_expiry'),
            ], $r);
        }, static fn (array $res, array $notice): string => 'Avis ' . $notice['ref'] . ' renouvelé : nouvel avis ' . $res['ref'] . '.');
    }

    /** Prolonge un avis actif : nouvelle date d'expiration et motif. */
    public function extend(Request $request, array $params = []): Response
    {
        return $this->lifecycle($request, (int) ($params['id'] ?? 0), function (SseWantedAccess $r, array $notice) use ($request): array {
            $tz = (string) ($this->settings->get($r->tenantId())['process']['timezone'] ?? 'UTC');

            return $this->service->extend($r->tenantId(), (int) $notice['id'], self::localToUtc($request->inputString('expires_at'), $tz), $request->inputString('reason'), $r);
        }, static fn (array $res, array $notice): string => 'Avis ' . $notice['ref'] . ' prolongé.');
    }

    /** Décision d'un responsable sur un avis proposé : « valider » ou « refuser » (motif obligatoire). */
    public function decide(Request $request, array $params = []): Response
    {
        $approve = $request->inputString('decision') === 'approve';

        return $this->lifecycle(
            $request,
            (int) ($params['id'] ?? 0),
            fn (SseWantedAccess $r, array $notice): array => $this->service->decide($r->tenantId(), (int) $notice['id'], $approve, $request->inputString('reason'), $r),
            static fn (array $res, array $notice): string => $approve ? 'Avis ' . $notice['ref'] . ' validé et diffusé.' : 'Avis ' . $notice['ref'] . ' refusé.',
            true
        );
    }

    /**
     * Gabarit des actions sur un avis existant : avis de la communauté de la session, jeton CSRF, droit d'écriture, puis action.
     *
     * @param \Closure(SseWantedAccess, array<string, mixed>): array<string, mixed> $act
     * @param \Closure(array<string, mixed>, array<string, mixed>): string $okMessage
     */
    private function lifecycle(Request $request, int $noticeId, \Closure $act, \Closure $okMessage, bool $adminOnly = false): Response
    {
        $r = $this->r();
        $notice = $this->safe(fn (): ?array => $this->notices->find($r->tenantId(), $noticeId));
        if ($notice === null) {
            return $this->flashBack('error', 'Avis introuvable.', url(self::BASE));
        }
        $back = url(self::PAPERS . '/' . (int) $notice['identity_id']) . '#avis';
        if (!self::csrfOk($request)) {
            return $this->denied($back, self::TOKEN_EXPIRED);
        }
        if (!$r->canManage() || ($adminOnly && !$r->canAdmin())) {
            return $this->denied($back, $adminOnly ? 'Droit manquant : la validation est réservée aux gestionnaires de la section.' : self::NEED_MANAGE);
        }
        $res = $act($r, $notice);
        if (($res['ok'] ?? false) !== true) {
            return $this->flashBack('error', (string) (($res['errors'] ?? [])[0] ?? 'Action non autorisée.'), $back);
        }
        if (is_array($res['warnings'] ?? null) && $res['warnings'] !== []) {
            Session::flash('error', implode(' ', array_slice($res['warnings'], 0, 3)));
        }

        return $this->flashBack('success', $okMessage($res, $notice), $back);
    }

    /** « 2026-11-20T14:30 » dans le fuseau de la communauté, converti en UTC « Y-m-d H:i:s » ; vide ou illisible : renvoyé tel quel. */
    public static function localToUtc(string $value, string $timezone): string
    {
        $value = trim(str_replace('T', ' ', $value));
        if ($value === '') {
            return '';
        }
        try {
            $tz = new \DateTimeZone($timezone !== '' ? $timezone : 'UTC');
        } catch (\Throwable) {
            $tz = new \DateTimeZone('UTC');
        }
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'] as $format) {
            $d = \DateTimeImmutable::createFromFormat('!' . $format, $value, $tz);
            if ($d !== false && $d->format($format) === $value) {
                return $d->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            }
        }

        return $value;
    }

    private function isMemberIdentity(int $tenantId, array $identity): bool
    {
        return \App\Services\Sse\SseMemberImageGate::forSession()->identityState($tenantId, $identity)['class'] !== \App\Services\Sse\SseMemberImageGate::CLASS_PUBLIC;
    }

    // ----------------------------------------------------------------- PDF

    /** Notice PDF : téléchargement (défaut) ou affichage pour l'impression (?mode=impression). */
    public function pdf(Request $request, array $params = []): Response
    {
        $r = $this->r();
        $tenantId = $r->tenantId();
        $notice = $this->safe(fn (): ?array => $this->notices->find($tenantId, (int) ($params['id'] ?? 0)));
        $back = $notice !== null ? url(self::PAPERS . '/' . (int) $notice['identity_id']) . '#avis' : url(self::BASE);
        if (!$r->canGeneratePdf()) {
            return $this->denied($back, $r->canManage() ? self::NEED_PDF : self::NEED_MANAGE);
        }
        if ($notice === null) {
            return $this->flashBack('error', 'Avis introuvable.', url(self::BASE));
        }
        $config = $this->settings->get($tenantId);
        $lang = SseWantedNoticePdfService::resolveLang($request->queryString('langue'), (string) $config['pdf_lang'] === 'auto' ? '' : (string) $config['pdf_lang'], self::readerLang());
        $faces = $request->queryString('faces', 'auto');
        $built = (new SseWantedNoticePdfService(null, null, null, $this->settings))->build($tenantId, $notice, $lang, in_array($faces, ['1', '2'], true) ? $faces : 'auto');
        if ($built === null) {
            return $this->flashBack('error', 'La notice PDF n’a pas pu être générée : le moteur PDF ou ses polices sont indisponibles.', $back);
        }
        $this->service->tracePdf($tenantId, (int) $notice['id'], $lang, $r);
        $inline = $request->queryString('mode') === 'impression';

        return (new Response())
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', ($inline ? 'inline' : 'attachment') . '; filename="' . $built['filename'] . '"')
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Content-Type-Options', 'nosniff')
            ->setBody($built['bytes']);
    }

    // ------------------------------------------------------------ Pièces jointes

    /** Ajoute des images à un avis existant : fichiers envoyés et photos de la photothèque de l'identité. */
    public function attachmentAdd(Request $request, array $params = []): Response
    {
        $r = $this->r();
        $tenantId = $r->tenantId();
        $notice = $this->safe(fn (): ?array => $this->notices->find($tenantId, (int) ($params['id'] ?? 0)));
        if ($notice === null) {
            return $this->flashBack('error', 'Avis introuvable.', url(self::BASE));
        }
        $back = url(self::PAPERS . '/' . (int) $notice['identity_id']) . '#avis';
        if (!self::csrfOk($request)) {
            return $this->denied($back, self::TOKEN_EXPIRED);
        }
        if (!$r->canManage()) {
            return $this->denied($back, self::NEED_MANAGE);
        }
        $problems = $this->attachAfterPlace($request, $tenantId, $notice, $r);
        if ($problems !== []) {
            return $this->flashBack('error', implode(' ', array_slice($problems, 0, 4)), $back);
        }

        return $this->flashBack('success', 'Images jointes à l’avis ' . $notice['ref'] . '.', $back);
    }

    public function attachmentRemove(Request $request, array $params = []): Response
    {
        return $this->attachmentAction($request, (int) ($params['id'] ?? 0), false);
    }

    public function attachmentMain(Request $request, array $params = []): Response
    {
        return $this->attachmentAction($request, (int) ($params['id'] ?? 0), true);
    }

    private function attachmentAction(Request $request, int $attachmentId, bool $main): Response
    {
        $r = $this->r();
        $tenantId = $r->tenantId();
        $repo = new SseWantedAttachmentRepository();
        $att = $this->safe(fn (): ?array => $repo->find($tenantId, $attachmentId));
        $notice = $att !== null ? $this->safe(fn (): ?array => $this->notices->find($tenantId, (int) $att['notice_id'])) : null;
        if ($att === null || $notice === null) {
            return $this->flashBack('error', 'Pièce introuvable.', url(self::BASE));
        }
        $back = url(self::PAPERS . '/' . (int) $notice['identity_id']) . '#avis';
        if (!self::csrfOk($request)) {
            return $this->denied($back, self::TOKEN_EXPIRED);
        }
        if (!$r->canManage()) {
            return $this->denied($back, self::NEED_MANAGE);
        }
        $service = $this->attachments();
        $res = $main ? $service->makeMain($tenantId, $notice, $attachmentId, $r) : $service->remove($tenantId, $notice, $attachmentId, $r);
        if ($res['ok'] !== true) {
            return $this->flashBack('error', $res['error'], $back);
        }

        return $this->flashBack('success', $main ? 'Image principale changée.' : 'Image retirée de l’avis.', $back);
    }

    /**
     * Image d'une pièce jointe : GET avis/pieces/{id}/image (?v=mini : miniature). Seule adresse des images d'un avis : lecture du
     * portail, communauté de la session, et pour l'identité d'un MEMBRE l'autorisation hiérarchique (SseMemberImageGate), journalisée.
     * Introuvable, autre communauté ou refus : 404 identique.
     */
    public function attachmentImage(Request $request, array $params = []): Response
    {
        $notFound = static fn (): Response => (new Response())
            ->setStatusCode(404)
            ->header('Content-Type', 'text/plain; charset=utf-8')
            ->header('Cache-Control', 'no-store')
            ->setBody('Not found');
        $r = $this->r();
        if (!$r->canRead() || $r->tenantId() < 1) {
            return $notFound();
        }
        $service = $this->attachments();
        $res = $this->safe(fn (): ?array => $service->resolve($r->tenantId(), (int) ($params['id'] ?? 0), $request->queryString('v') === 'mini'));
        if ($res === null) {
            return $notFound();
        }
        if ($res['confidential']) {
            try {
                $gate = \App\Services\Sse\SseMemberImageGate::forSession();
                $gate->recordConsultation($r->tenantId(), (int) $gate->viewerId(), 'wanted_attachment', (int) $res['attachment']['id'], $res['owner'], $res['reason']);
            } catch (\Throwable $e) {
                error_log('Avis de recherche : consultation d’une image confidentielle non journalisée, image refusée : ' . $e->getMessage());

                return $notFound();
            }
        }
        $abs = $res['file'];
        $ext = ['image/png' => 'png', 'image/webp' => 'webp', 'image/jpeg' => 'jpg'][$res['mime']] ?? 'bin';

        return (new Response())
            ->header('Content-Type', $res['mime'])
            ->header('Content-Length', (string) filesize($abs))
            ->header('Content-Disposition', 'inline; filename="avis-' . (int) $res['attachment']['notice_id'] . '-' . (int) $res['attachment']['id'] . '.' . $ext . '"')
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
     * Traite les images du formulaire (fichiers de « att_file » et photos cochées « att_photo ») pour un avis existant.
     *
     * @param array<string, mixed> $notice
     * @return list<string> problèmes à montrer (une image refusée n'empêche pas les autres)
     */
    private function attachAfterPlace(Request $request, int $tenantId, array $notice, SseWantedAccess $access): array
    {
        $service = $this->attachments();
        $problems = [];
        $kinds = $request->input('att_kind');
        $caps = $request->input('att_caption');
        $kinds = is_array($kinds) ? array_values($kinds) : [];
        $caps = is_array($caps) ? array_values($caps) : [];
        $main = $request->inputString('att_main');
        foreach (self::uploadedFiles('att_file') as $i => $file) {
            if ($file['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $label = $file['name'] !== '' ? '« ' . mb_substr($file['name'], 0, 40) . ' » : ' : '';
            if ($file['error'] !== UPLOAD_ERR_OK || !self::isUpload($file['tmp_name'])) {
                $problems[] = $label . 'l’image dépasse 5 Mo ou n’a pas pu être reçue.';
                continue;
            }
            $res = $service->addFile($tenantId, $notice, $file['tmp_name'], ['kind' => $kinds[$i] ?? 'other', 'caption' => $caps[$i] ?? '', 'main' => $main === 'up:' . $i], $access);
            if ($res['ok'] !== true) {
                $problems[] = $label . $res['error'];
            }
        }
        $photoIds = $request->input('att_photo');
        $photoKinds = $request->input('att_photo_kind');
        foreach (is_array($photoIds) ? array_slice($photoIds, 0, 6) : [] as $pid) {
            $pid = (int) $pid;
            if ($pid < 1) {
                continue;
            }
            $res = $service->addFromPhoto($tenantId, $notice, $pid, [
                'kind' => is_array($photoKinds) ? ($photoKinds[$pid] ?? 'portrait') : 'portrait', 'main' => $main === 'ph:' . $pid,
            ], $access);
            if ($res['ok'] !== true) {
                $problems[] = $res['error'];
            }
        }

        return $problems;
    }

    /**
     * Fichiers de $_FILES[$key] mis à plat.
     *
     * @return list<array{name: string, tmp_name: string, error: int}>
     */
    private static function uploadedFiles(string $key): array
    {
        $f = $_FILES[$key] ?? null;
        if (!is_array($f) || !is_array($f['name'] ?? null)) {
            return [];
        }
        $out = [];
        foreach (array_keys($f['name']) as $i) {
            $out[] = ['name' => (string) $f['name'][$i], 'tmp_name' => (string) ($f['tmp_name'][$i] ?? ''), 'error' => (int) ($f['error'][$i] ?? UPLOAD_ERR_NO_FILE)];
        }

        return array_slice($out, 0, 12);
    }

    /** Fichier réellement reçu par PHP en HTTP (jamais un chemin choisi par le client). */
    private static function isUpload(string $tmp): bool
    {
        return $tmp !== '' && is_uploaded_file($tmp);
    }

    // ------------------------------------------------------------ Détections

    public function detections(Request $request, array $params = []): Response
    {
        $r = $this->r();
        if (!$r->canRead()) {
            return $this->denied(url('atak/sse/accueil'));
        }
        $tenantId = $r->tenantId();
        $status = $request->queryString('statut', 'new');
        if (!in_array($status, ['', 'new', 'acknowledged', 'dismissed'], true)) {
            $status = 'new';
        }
        $ready = SseWantedNoticeRepository::ready();
        $rows = $ready ? ($this->safe(fn (): array => $this->detections->list($tenantId, ['status' => $status])) ?? []) : [];
        $counts = $ready ? ($this->safe(fn (): array => $this->detections->counts($tenantId)) ?? []) : [];

        return $this->page('atak.sse.papers.wanted_detections', [
            'title' => 'Détections',
            'detections' => $rows,
            'counts' => $counts + ['new' => 0, 'acknowledged' => 0, 'dismissed' => 0],
            'status' => $status,
            'wantedReady' => $ready,
            'rights' => $r,
            'lang' => self::readerLang(),
        ]);
    }

    public function detectionStatus(Request $request, array $params = []): Response
    {
        $r = $this->r();
        $tenantId = $r->tenantId();
        $back = url(self::BASE . '/detections');
        if (!self::csrfOk($request)) {
            return $this->denied($back, self::TOKEN_EXPIRED);
        }
        if (!$r->canManage()) {
            return $this->denied($back, self::NEED_MANAGE);
        }
        $row = $this->safe(fn (): ?array => $this->detections->find($tenantId, (int) ($params['id'] ?? 0)));
        $status = $request->inputString('status');
        if ($row === null || !in_array($status, ['acknowledged', 'dismissed'], true)) {
            return $this->flashBack('error', 'Détection introuvable.', $back);
        }
        $comment = C::line($request->inputString('comment'), 500);
        $this->detections->setStatus($tenantId, (int) $row['id'], $status, $comment, $r->userId());
        (new SseIdentityEventRepository())->tryAdd($tenantId, (int) $row['identity_id'], 'detection.handled', 'Détection traitée.', [
            'ref' => $row['notice_ref'], 'status_label_fr' => C::label(C::DETECTION_STATUSES, $status, 'fr'), 'status_label_en' => C::label(C::DETECTION_STATUSES, $status, 'en'),
        ]);

        return $this->flashBack('success', 'Détection ' . mb_strtolower(C::label(C::DETECTION_STATUSES, $status, 'fr')) . '.', $back);
    }

    // ------------------------------------------------------------ Réglages

    public function settings(Request $request, array $params = []): Response
    {
        $r = $this->r();
        if (!$r->canManage()) {
            return $this->denied(url(self::BASE));
        }
        $tenantId = $r->tenantId();

        return $this->settingsPage($this->settings->get($tenantId), [], $r);
    }

    /**
     * Page des réglages : notice, codification et services (catalogue livré, services de la communauté, usage par les avis).
     *
     * @param array<string, mixed> $config
     * @param list<string> $errors
     * @param array<string, mixed> $services état du tiroir de services en cas d'erreur : op, code, old, errors, report
     */
    private function settingsPage(array $config, array $errors, SseWantedAccess $r, array $services = [], int $status = 200): Response
    {
        $tenantId = $r->tenantId();

        return $this->page('atak.sse.papers.wanted_settings', [
            'title' => 'Réglages de la notice',
            'config' => $config,
            'errors' => $errors,
            'rights' => $r,
            'lang' => self::readerLang(),
            'serviceList' => SseWantedServices::all($config),
            'serviceFamilies' => SseWantedServices::families(),
            'servicePalette' => SseWantedServices::palette(),
            'serviceEmblems' => SseWantedServices::emblems(),
            'serviceUse' => $this->safe(fn (): array => (new SseWantedMetaRepository())->countsByService($tenantId)) ?? [],
            'serviceStoreReady' => SseWantedMetaRepository::ready(),
            'serviceState' => $services,
            'serviceEmblemReady' => \App\Services\Sse\SseServiceEmblems::ready(),
            'serviceEmblemList' => $this->safe(fn (): array => (new \App\Services\Sse\SseServiceEmblems(null, null, $this->settings))->listFor($tenantId)) ?? [],
            'timezones' => \DateTimeZone::listIdentifiers(),
        ], $status);
    }

    public function settingsSave(Request $request, array $params = []): Response
    {
        $r = $this->r();
        if (!self::csrfOk($request)) {
            return $this->denied(url(self::BASE), self::TOKEN_EXPIRED);
        }
        if (!$r->canManage()) {
            return $this->denied(url(self::BASE), self::NEED_MANAGE);
        }
        $tenantId = $r->tenantId();
        $checked = SseWantedSettingsRepository::validate([
            'authority_fr' => $request->inputString('authority_fr'),
            'authority_en' => $request->inputString('authority_en'),
            'contact_label' => $request->inputString('contact_label'),
            'contact_detail' => $request->inputString('contact_detail'),
            'ref_format' => $request->inputString('ref_format'),
            'banner' => $request->inputString('banner'),
            'emblem' => $request->inputString('emblem'),
            'pdf_lang' => $request->inputString('pdf_lang'),
            'default_classification' => $request->inputString('default_classification'),
            'default_expiry_days' => $request->input('default_expiry_days'),
            'default_instructions_fr' => $request->inputString('default_instructions_fr'),
            'default_instructions_en' => $request->inputString('default_instructions_en'),
            'custom_types' => $request->inputString('custom_types'),
            'enabled_types' => is_array($request->input('enabled_types')) ? array_values(array_map('strval', $request->input('enabled_types'))) : [],
        ]);
        // Onglet « Codification » : absent du formulaire (ancienne page), on garde les réglages en place.
        if ($request->inputString('codes_present') === '1') {
            $codes = SseWantedCodes::fromForm($this->codesInput($request));
            $checked['settings']['codes'] = $codes['settings'];
            $checked['errors'] = array_merge($checked['errors'], $codes['errors']);
        } else {
            $checked['settings']['codes'] = $this->settings->get($tenantId)['codes'];
        }
        // Services : gérés par leur propre formulaire (tiroir), jamais écrasés par celui de la notice.
        $current = $this->settings->get($tenantId);
        $checked['settings']['services'] = $current['services'];
        $checked['settings']['process'] = $request->inputString('process_present') === '1'
            ? SseWantedSettingsRepository::normalizeProcess([
                'validation' => $request->inputString('process_validation') === '1',
                'timezone' => $request->inputString('process_timezone'),
                'notify' => $request->inputString('process_notify') === '1',
            ])
            : $current['process'];
        if ($checked['errors'] !== []) {
            return $this->settingsPage($checked['settings'], $checked['errors'], $r, [], 422);
        }
        try {
            $this->settings->save($tenantId, $checked['settings'], $r->userId());
        } catch (\Throwable $e) {
            error_log('Avis de recherche : réglages non enregistrés : ' . $e->getMessage());

            return $this->flashBack('error', 'Les réglages n’ont pas pu être enregistrés (étape de base de données en attente ?).', url(self::BASE . '/reglages'));
        }

        return $this->flashBack('success', 'Réglages de la notice enregistrés.', url(self::BASE . '/reglages'));
    }

    // ------------------------------------------------------- Services demandeurs de la communauté

    /**
     * Gestion des services demandeurs : une seule adresse, l'opération dans « op » (create, update, duplicate, toggle, delete,
     * default, move, import). Réservé aux gestionnaires de la section ; jeton CSRF ; la communauté est celle de la session ;
     * chaque changement est écrit au journal d'audit SSE.
     */
    public function servicesSave(Request $request, array $params = []): Response
    {
        $r = $this->r();
        $back = url(self::BASE . '/reglages') . '#services';
        if (!self::csrfOk($request)) {
            return $this->denied($back, self::TOKEN_EXPIRED);
        }
        if (!$r->canAdmin()) {
            return $this->denied($back, self::NEED_ADMIN);
        }
        $tenantId = $r->tenantId();
        $config = $this->settings->get($tenantId);
        $op = $request->inputString('op');
        $code = strtoupper(trim($request->inputString('code')));
        $form = $this->serviceInput($request);
        $import = null;
        switch ($op) {
            case 'create':
                $res = SseWantedServiceAdmin::create($config, $form);
                break;
            case 'update':
                $res = SseWantedServiceAdmin::update($config, $code, $form);
                break;
            case 'duplicate':
                $res = SseWantedServiceAdmin::duplicate($config, $code);
                break;
            case 'toggle':
                $res = SseWantedServiceAdmin::setEnabled($config, $code, $request->inputString('enabled') === '1');
                break;
            case 'delete':
                $used = $this->safe(fn (): int => (new SseWantedMetaRepository())->countUsing($tenantId, $code)) ?? 0;
                // Sans l'étape de base de données, impossible de savoir si des avis portent ce service : on désactive seulement.
                $res = SseWantedMetaRepository::ready() ? SseWantedServiceAdmin::delete($config, $code, $used) : SseWantedServiceAdmin::setEnabled($config, $code, false);
                // Service réellement supprimé : son emblème téléversé part avec lui.
                if (($res['ok'] ?? false) === true && SseWantedServices::find($code, $res['config']) === null) {
                    $this->safe(fn () => (new \App\Services\Sse\SseServiceEmblems(null, null, $this->settings))->purge($tenantId, $code));
                }
                break;
            case 'default':
                $res = SseWantedServiceAdmin::setDefault($config, $code);
                break;
            case 'move':
                $res = SseWantedServiceAdmin::move($config, $code, $request->inputString('direction') === 'up' ? 'up' : 'down');
                break;
            case 'import':
                $file = $_FILES['import_file'] ?? null;
                if (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && self::isUpload((string) ($file['tmp_name'] ?? ''))) {
                    $json = (string) file_get_contents((string) $file['tmp_name'], false, null, 0, SseWantedServiceAdmin::IMPORT_MAX_BYTES + 1);
                } else {
                    $json = $request->inputString('import_json');
                }
                $res = SseWantedServiceAdmin::import($config, $json);
                $import = $res;
                break;
            default:
                return $this->flashBack('error', 'Opération inconnue.', $back);
        }
        if (($res['ok'] ?? false) !== true || ($import !== null && $import['rejected'] !== [])) {
            $state = ['op' => $op, 'code' => $code, 'old' => $form, 'errors' => $res['errors'] ?? [], 'report' => $import];
            if ($import !== null && $import['added'] > 0) {
                $this->settings->save($tenantId, $import['config'], $r->userId());
                $this->auditServices($tenantId, $r, 'import', ['added' => $import['added'], 'rejected' => count($import['rejected'])]);
                $state['saved'] = $import['message'];
            }

            return $this->settingsPage($import !== null && $import['added'] > 0 ? $import['config'] : $config, [], $r, $state, 422);
        }
        try {
            $this->settings->save($tenantId, $res['config'], $r->userId());
        } catch (\Throwable $e) {
            error_log('Avis de recherche : services non enregistrés : ' . $e->getMessage());

            return $this->flashBack('error', 'Les services n’ont pas pu être enregistrés (étape de base de données en attente ?).', $back);
        }
        $this->auditServices($tenantId, $r, $op, ['code' => $res['code'] ?? $code, 'message' => $res['message']]);

        return $this->flashBack('success', (string) $res['message'], $back);
    }

    /** Export JSON des services créés par la communauté (à partager avec une autre communauté). */
    public function servicesExport(Request $request, array $params = []): Response
    {
        $r = $this->r();
        if (!$r->canAdmin()) {
            return $this->denied(url(self::BASE . '/reglages'), self::NEED_ADMIN);
        }
        $json = SseWantedServiceAdmin::export($this->settings->get($r->tenantId()));
        $this->auditServices($r->tenantId(), $r, 'export', []);

        return (new Response())
            ->header('Content-Type', 'application/json; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="services-demandeurs-' . gmdate('Y-m-d') . '.json"')
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Content-Type-Options', 'nosniff')
            ->setBody($json);
    }

    /** @return array<string, mixed> */
    private function serviceInput(Request $request): array
    {
        // Même saisie que la fiche complète d'un service (description, adresse, cases « Afficher sur les PDF »…).
        return SseWantedServiceController::input($request);
    }

    /** @param array<string, mixed> $details */
    private function auditServices(int $tenantId, SseWantedAccess $r, string $action, array $details): void
    {
        try {
            (new SseAuditLogRepository())->write([
                'tenant_id' => $tenantId,
                'actor_user_id' => $r->userId(),
                'actor_label' => $r->actorLabel() !== '' ? $r->actorLabel() : 'Avis de recherche',
                'action' => 'wanted.services.' . $action,
                'object_type' => 'sse_wanted_services',
                'object_id' => 0,
                'reason' => (string) ($details['message'] ?? ''),
                'after' => $details,
            ]);
        } catch (\Throwable $e) {
            error_log('Avis de recherche : audit des services indisponible : ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------- Outils

    /**
     * @param array<string, mixed> $identity
     * @param array<string, mixed> $old
     * @param list<string> $errors
     */
    private function renderPlaceForm(array $identity, array $old, array $errors, int $status = 200): Response
    {
        $r = $this->r();
        $tenantId = $r->tenantId();
        $config = $this->settings->get($tenantId);
        $active = SseWantedNoticeRepository::ready() ? $this->safe(fn (): ?array => $this->notices->activeForIdentity($tenantId, (int) $identity['id'])) : null;
        $lang = self::readerLang();
        $gate = \App\Services\Sse\SseMemberImageGate::forSession();
        $isMember = $gate->identityState($tenantId, $identity)['class'] !== \App\Services\Sse\SseMemberImageGate::CLASS_PUBLIC;
        $attachLock = $this->attachments()->identityVisible($tenantId, (int) $identity['id']);
        $profile = $isMember ? null : $this->safe(fn (): ?array => (new \App\Repositories\SseFileRecordRepository())->profile($tenantId, (int) $identity['id']));
        $libraryPhotos = [];
        if (!$isMember && $attachLock['allowed']) {
            $libraryPhotos = $this->safe(fn (): array => array_values(array_filter(
                (new \App\Repositories\SseFileRecordRepository())->photosFor($tenantId, [(int) $identity['id']])[(int) $identity['id']] ?? [],
                static fn (array $p): bool => ($p['visibility'] ?? 'known') !== 'hidden'
            ))) ?? [];
            $libraryPhotos = array_slice($libraryPhotos, 0, 24);
        }

        $tzName = (string) ($config['process']['timezone'] ?? 'UTC');
        $last = SseWantedMetaRepository::ready() ? $this->safe(fn (): ?string => (new SseWantedMetaRepository())->lastServiceCode($tenantId, (int) $r->userId())) : null;

        return $this->page('atak.sse.papers.wanted_form', [
            'serviceData' => SseWantedServices::clientData($config, $lang) + ['last' => $last !== null && isset(SseWantedServices::available($config)[$last]) ? $last : ''],
            'serviceStoreReady' => SseWantedMetaRepository::ready(),
            'nowLocal' => (new \DateTimeImmutable('now', new \DateTimeZone($tzName)))->format('Y-m-d\TH:i'),
            'timezoneName' => $tzName,
            'hasActiveNotice' => $active !== null,
            'codesData' => SseWantedCodes::clientData($config, $lang),
            'physicalDefaults' => SseWantedPhysical::fromProfile($profile, $isMember),
            'isMemberIdentity' => $isMember,
            'attachLock' => $attachLock,
            'libraryPhotos' => $libraryPhotos,
            'extrasReady' => SseWantedExtrasRepository::ready(),
            'attachmentsReady' => SseWantedAttachmentRepository::ready(),
            'title' => 'Placer un avis de recherche',
            'identity' => $identity,
            'old' => $old,
            'errors' => $errors,
            'wantedConfig' => $config,
            'wantedTypes' => C::types($config),
            'wantedReady' => SseWantedNoticeRepository::ready(),
            'active' => $active,
            'rights' => $r,
            'lang' => self::readerLang(),
        ], $status);
    }

    /** @return array<string, mixed> */
    private function placeInput(Request $request): array
    {
        $out = [];
        foreach (['notice_type', 'threat_level', 'armed', 'reason', 'instructions', 'zone', 'last_seen_place', 'last_seen_at', 'contact_label', 'contact_detail', 'issued_at', 'expires_at', 'codes_template', 'service_code', 'classification', 'no_expiry'] as $k) {
            $out[$k] = $request->inputString($k);
        }
        foreach (['codes_reasons', 'codes_measures'] as $k) {
            $v = $request->input($k);
            $out[$k] = is_array($v) ? array_slice(array_values(array_filter(array_map('strval', $v), static fn (string $c): bool => $c !== '')), 0, 40) : [];
        }
        // Signalement physique : valeurs de la liste ou saisie libre, rangées sous « physical ».
        $physical = [];
        foreach (array_merge(['height_cm', 'weight_kg'], array_keys(SseWantedPhysical::lists()), array_keys(SseWantedPhysical::FREE)) as $k) {
            $physical[$k] = $request->inputString('phys_' . $k);
        }
        $equipment = $request->input('phys_equipment');
        $physical['equipment'] = is_array($equipment) ? array_values(array_map('strval', $equipment)) : [];
        $out['physical'] = $physical;
        $out['lang'] = self::readerLang();

        return $out;
    }

    /**
     * Saisie de l'onglet « Codification » des réglages.
     *
     * @return array<string, mixed>
     */
    private function codesInput(Request $request): array
    {
        $arr = static fn (mixed $v): array => is_array($v) ? $v : [];
        $enabled = $request->input('codes_enabled');

        return [
            'present' => '1',
            'rows' => is_array($request->input('codes_row')) ? array_values(array_map('strval', $request->input('codes_row'))) : [],
            'enabled' => is_array($enabled) ? array_values(array_map('strval', $enabled)) : [],
            'fr' => $arr($request->input('codes_fr')),
            'en' => $arr($request->input('codes_en')),
            'rank' => $arr($request->input('codes_rank')),
            'custom_codes' => $request->inputString('codes_custom'),
            'defaults_reasons' => $arr($request->input('codes_default_reasons')),
            'defaults_measures' => $arr($request->input('codes_default_measures')),
            'templates_enabled' => is_array($request->input('codes_templates')) ? array_values(array_map('strval', $request->input('codes_templates'))) : [],
            'custom_templates' => $request->inputString('codes_custom_templates'),
        ];
    }

    /**
     * Auteur de chaque avis : libellé noté dans la chronologie au moment de la pose.
     *
     * @param list<array<string, mixed>> $notices
     * @return array<int, string>
     */
    private function authors(int $tenantId, array $notices): array
    {
        $ids = array_values(array_filter(array_map(static fn (array $n): int => (int) $n['id'], $notices)));
        if ($ids === []) {
            return [];
        }
        $out = [];
        try {
            $db = \App\Core\Database::getInstance();
            $params = ['t' => $tenantId];
            $marks = [];
            foreach (array_slice($ids, 0, 300) as $k => $id) {
                $marks[] = ':n' . $k;
                $params['n' . $k] = $id;
            }
            foreach ($db->fetchAll("SELECT notice_id, actor_label FROM sse_identity_events WHERE tenant_id = :t AND event_type = 'notice.placed' AND notice_id IN (" . implode(', ', $marks) . ')', $params) as $row) {
                $out[(int) $row['notice_id']] = (string) ($row['actor_label'] ?? '');
            }
        } catch (\Throwable) {
            return [];
        }

        return $out;
    }

    /** @param array<string, mixed> $data */
    private function page(string $view, array $data, int $status = 200): Response
    {
        $data += ['csrfToken' => Csrf::token()];
        $response = (new SseIdentityRegistryController($this->access, $this->clearance))->page($view, $data);

        return $status === 200 ? $response : $response->setStatusCode($status);
    }

    /** Jeton CSRF présent et valable (un jeton vide n'est jamais accepté, même sans jeton en session). */
    private static function csrfOk(Request $request): bool
    {
        $token = $request->input('_csrf_token');

        return is_string($token) && $token !== '' && Csrf::validate($token);
    }

    private function attachments(): SseWantedAttachments
    {
        return $this->attachments ??= new SseWantedAttachments();
    }

    private function r(): SseWantedAccess
    {
        return $this->rights ??= SseWantedAccess::fromSession($this->access, $this->clearance);
    }

    private function denied(string $back, string $why = 'Action non autorisée.'): Response
    {
        return $this->flashBack('error', $why, $back);
    }

    private function flashBack(string $type, string $message, string $to): Response
    {
        Session::flash($type, $message);

        return Response::redirect($to);
    }

    /** Langue du lecteur : français, sinon anglais. */
    public static function readerLang(): string
    {
        return !function_exists('site_lang') || site_lang() === 'fr' ? 'fr' : 'en';
    }

    /**
     * @template T
     * @param \Closure(): T $fn
     * @return T|null
     */
    private function safe(\Closure $fn): mixed
    {
        try {
            return $fn();
        } catch (\Throwable) {
            return null;
        }
    }
}
