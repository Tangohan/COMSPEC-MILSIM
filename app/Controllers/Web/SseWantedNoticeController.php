<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseIdentityEventRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseWantedDetectionRepository;
use App\Repositories\SseWantedNoticeRepository;
use App\Repositories\SseWantedSettingsRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseClearanceService;
use App\Services\Sse\SseWantedAccess;
use App\Services\Sse\SseWantedNoticePdfService;
use App\Services\Sse\SseWantedService;
use App\Support\SseWantedCatalog as C;

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
        ];
        if (!in_array($filters['status'], ['', 'active', 'lifted', 'expired', 'captured'], true)) {
            $filters['status'] = 'active';
        }
        $notices = [];
        $counts = ['active' => 0, 'lifted' => 0, 'expired' => 0, 'captured' => 0];
        $newDetections = 0;
        if ($ready) {
            $this->service->expireDue($tenantId);
            $notices = $this->safe(fn (): array => $this->notices->list($tenantId, $filters)) ?? [];
            $counts = $this->safe(fn (): array => $this->notices->counts($tenantId)) ?? $counts;
            $newDetections = (int) ($this->safe(fn (): array => $this->detections->counts($tenantId))['new'] ?? 0);
        }
        $config = $this->settings->get($tenantId);

        return $this->page('atak.sse.papers.wanted_list', [
            'title' => 'Avis de recherche',
            'notices' => $notices,
            'filters' => $filters,
            'counts' => $counts,
            'newDetections' => $newDetections,
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
            return $this->denied(url(self::PAPERS . '/' . (int) $identity['id']));
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
        if (!self::csrfOk($request) || !$r->canManage()) {
            return $this->denied($back);
        }
        $input = $this->placeInput($request);
        $res = $this->service->place($tenantId, (int) $identity['id'], $input, $r);
        if ($res['ok'] !== true) {
            if ($res['error'] === 'forbidden') {
                return $this->denied($back);
            }

            return $this->renderPlaceForm($identity, $input, $res['errors'], 422);
        }
        Session::flash('success', 'Avis de recherche ' . $res['ref'] . ' placé.');

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
        if (!self::csrfOk($request) || !$r->canManage()) {
            return $this->denied($back);
        }
        $res = $this->service->lift($tenantId, (int) $notice['id'], $request->inputString('status', 'lifted'), $request->inputString('reason'), $r);
        if ($res['ok'] !== true) {
            return $this->flashBack('error', $res['errors'][0] ?? 'Action non autorisée.', $back);
        }

        return $this->flashBack('success', $request->inputString('status') === 'captured' ? 'Avis ' . $notice['ref'] . ' clos : personne capturée.' : 'Avis ' . $notice['ref'] . ' levé.', $back);
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
            return $this->denied($back);
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
        if (!self::csrfOk($request) || !$r->canManage()) {
            return $this->denied($back);
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

        return $this->page('atak.sse.papers.wanted_settings', [
            'title' => 'Réglages de la notice',
            'config' => $this->settings->get($tenantId),
            'errors' => [],
            'rights' => $r,
            'lang' => self::readerLang(),
        ]);
    }

    public function settingsSave(Request $request, array $params = []): Response
    {
        $r = $this->r();
        if (!self::csrfOk($request) || !$r->canManage()) {
            return $this->denied(url(self::BASE));
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
            'watermark' => $request->input('watermark'),
            'footer' => $request->input('footer'),
            'default_expiry_days' => $request->input('default_expiry_days'),
            'default_instructions_fr' => $request->inputString('default_instructions_fr'),
            'default_instructions_en' => $request->inputString('default_instructions_en'),
            'custom_types' => $request->inputString('custom_types'),
            'enabled_types' => is_array($request->input('enabled_types')) ? array_values(array_map('strval', $request->input('enabled_types'))) : [],
        ]);
        if ($checked['errors'] !== []) {
            return $this->page('atak.sse.papers.wanted_settings', [
                'title' => 'Réglages de la notice',
                'config' => $checked['settings'],
                'errors' => $checked['errors'],
                'rights' => $r,
                'lang' => self::readerLang(),
            ], 422);
        }
        try {
            $this->settings->save($tenantId, $checked['settings'], $r->userId());
        } catch (\Throwable $e) {
            error_log('Avis de recherche : réglages non enregistrés : ' . $e->getMessage());

            return $this->flashBack('error', 'Les réglages n’ont pas pu être enregistrés (étape de base de données en attente ?).', url(self::BASE . '/reglages'));
        }

        return $this->flashBack('success', 'Réglages de la notice enregistrés.', url(self::BASE . '/reglages'));
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

        return $this->page('atak.sse.papers.wanted_form', [
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
        foreach (['notice_type', 'threat_level', 'armed', 'reason', 'instructions', 'zone', 'last_seen_place', 'last_seen_at', 'contact_label', 'contact_detail', 'issued_at', 'expires_at'] as $k) {
            $out[$k] = $request->inputString($k);
        }

        return $out;
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

    private function r(): SseWantedAccess
    {
        return $this->rights ??= SseWantedAccess::fromSession($this->access, $this->clearance);
    }

    private function denied(string $back): Response
    {
        return $this->flashBack('error', 'Action non autorisée.', $back);
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
