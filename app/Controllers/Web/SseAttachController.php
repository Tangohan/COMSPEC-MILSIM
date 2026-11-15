<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseAttachmentRepository;
use App\Repositories\SsePortalSettingsRepository;
use App\Services\Security\FileRateLimiter;
use App\Services\Sse\Attach\SseAttachService;
use App\Services\Sse\Links\SseLinkSessionViewer;
use App\Services\Sse\Links\SseLinkViewer;
use App\Support\SseAttachCatalog as A;
use App\Support\SseLinkCatalog as C;

/**
 * Fenêtre « Rattacher » du bureau SSE : un seul point d'entrée pour rattacher une ou plusieurs pièces à un dossier, un dossier
 * d'intérêt, une piste, une investigation, une personne, une identité, un papier, un document, un site, un objet du registre,
 * une campagne, un avis, une fiche ou une transmission.
 *
 * La communauté vient de la session, jamais de la requête. Chaque écriture exige le jeton CSRF et passe par un débit limité.
 * Une pièce ou une cible que le demandeur ne peut pas lire (autre communauté, section, habilitation, identité d'un membre,
 * dossier d'intérêt fermé) répond exactement comme un élément inexistant.
 */
final class SseAttachController
{
    private const WRITES_PER_HOUR = 240;

    private const READS_PER_5_MIN = 900;

    public function __construct(private ?SseLinkViewer $viewer = null, private ?SseAttachService $service = null, private ?FileRateLimiter $limiter = null)
    {
    }

    private function viewer(): SseLinkViewer
    {
        return $this->viewer ??= new SseLinkSessionViewer();
    }

    private function service(): SseAttachService
    {
        return $this->service ??= new SseAttachService($this->viewer());
    }

    // ------------------------------------------------------------------ Lecture (JSON)

    /** Ouverture de la fenêtre : pièces, périmètres, natures, étiquettes, droits, cibles proposées et récentes. */
    public function context(Request $request, array $params = []): Response
    {
        if ($this->tooMany(false)) {
            return $this->busy();
        }
        $svc = $this->service();
        $ctx = $svc->context(self::refs($request->queryString('pieces')));
        if (!$ctx['found']) {
            return Response::json(['ok' => false, 'error' => SseAttachService::NOT_FOUND], 404);
        }
        $ctx['suggestions'] = $svc->directory()->suggestions($ctx['sources']);
        $ctx['recents'] = $svc->directory()->byKeys($ctx['sources'], self::refs($request->queryString('recents')));

        return Response::json(['ok' => true] + $ctx);
    }

    /** Liste d'un périmètre : recherche, tri, filtres, page, et compteurs de chaque onglet. */
    public function targets(Request $request, array $params = []): Response
    {
        if ($this->tooMany(false)) {
            return $this->busy();
        }
        $dir = $this->service()->directory();
        $sources = $dir->sources(self::refs($request->queryString('pieces')));
        if ($sources === []) {
            return Response::json(['ok' => false, 'error' => SseAttachService::NOT_FOUND], 404);
        }

        return Response::json(['ok' => true] + $dir->search($sources, [
            'scope' => $request->queryString('scope'), 'q' => $request->queryString('q'), 'sort' => $request->queryString('sort'),
            'status' => $request->queryString('status'), 'class' => $request->queryString('class'), 'tag' => (int) $request->queryString('tag'),
            'mine' => $request->queryString('mine') === '1', 'page' => (int) $request->queryString('page'),
        ]));
    }

    /** Détail d'une cible (description, responsable, dates, éléments déjà rattachés, qui peut la voir). */
    public function target(Request $request, array $params = []): Response
    {
        if ($this->tooMany(false)) {
            return $this->busy();
        }
        $dir = $this->service()->directory();
        $sources = $dir->sources(self::refs($request->queryString('pieces')));
        $p = C::parseRef($request->queryString('cible'));
        $detail = $sources !== [] && $p !== null ? $dir->detail($sources, $p['type'], $p['id']) : null;
        if ($detail === null) {
            return Response::json(['ok' => false, 'error' => SseAttachService::NOT_FOUND], 404);
        }

        return Response::json(['ok' => true, 'item' => $detail]);
    }

    // ------------------------------------------------------------------ Écriture

    /** Récapitulatif avant validation (n'écrit rien, mais exige le jeton : il révèle les effets de confidentialité). */
    public function preview(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard($request, false)) !== null) {
            return $deny;
        }

        return $this->answer($request, $this->service()->preview(self::refs($request->inputString('pieces')), self::refs($request->inputString('cibles')), $this->settings($request)), '');
    }

    public function store(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard($request, true)) !== null) {
            return $deny;
        }
        $res = $this->service()->attach(self::refs($request->inputString('pieces')), self::refs($request->inputString('cibles')), $this->settings($request));

        return $this->answer($request, $res, $res['ok'] ? ($res['count'] > 1 ? $res['count'] . ' rattachements enregistrés.' : 'Rattachement enregistré.') : '');
    }

    public function undo(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard($request, true)) !== null) {
            return $deny;
        }
        $ids = array_map('intval', array_filter(explode(',', $request->inputString('ids'))));

        return $this->answer($request, $this->service()->undo($ids), 'Rattachement annulé.');
    }

    public function update(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard($request, true)) !== null) {
            return $deny;
        }
        $fields = [];
        foreach (['label', 'reason', 'note', 'nature', 'nature_label'] as $k) {
            if ($request->input($k) !== null) {
                $fields[$k] = $request->inputString($k);
            }
        }
        if ($request->input('tags') !== null) {
            $fields['tags'] = self::tags($request->inputString('tags'));
        }

        return $this->answer($request, $this->service()->update((int) ($params['id'] ?? 0), $fields), 'Rattachement modifié.');
    }

    public function detach(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard($request, true)) !== null) {
            return $deny;
        }

        return $this->answer($request, $this->service()->detach((int) ($params['id'] ?? 0), $request->inputString('reason')), 'Élément détaché. Le motif est consigné.');
    }

    public function move(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard($request, true)) !== null) {
            return $deny;
        }

        return $this->answer($request, $this->service()->move((int) ($params['id'] ?? 0), $request->inputString('cible'), $request->inputString('reason')), 'Élément déplacé.');
    }

    /** Création à la volée d'un dossier ou d'une piste, depuis la fenêtre. */
    public function create(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard($request, true)) !== null) {
            return $deny;
        }
        $res = $this->service()->createTarget($request->inputString('type'), [
            'title' => $request->inputString('title'), 'description' => $request->inputString('description'), 'classification' => $request->inputString('classification'),
        ]);

        return $this->answer($request, $res, 'Créé.');
    }

    /** Valide UNE proposition de Cerbère-Classification (cible reclassifiée : relever la pièce qui avait demandé l'alignement). */
    public function align(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard($request, true)) !== null) {
            return $deny;
        }

        return $this->answer($request, $this->service()->applyAlign((int) $request->inputString('id')), 'Classification de la pièce relevée. Le changement est au journal.');
    }

    /** Réglage de la communauté : toujours aligner à la hausse au rattachement (droit « gérer » des dossiers). */
    public function setting(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard($request, true)) !== null) {
            return $deny;
        }
        $svc = $this->service();
        if (!$svc->canManage('dossiers')) {
            return $this->answer($request, ['ok' => false, 'error' => 'Droit manquant : gérer « Dossiers d’enquête ».'], '');
        }
        $on = $request->inputString('always_align') === '1';
        $v = $this->viewer();
        $ok = (new SsePortalSettingsRepository())->set($v->tenantId(), A::SETTING_ALWAYS_ALIGN, $on ? '1' : '0', $v->userId());
        try {
            (new \App\Repositories\SseAuditLogRepository())->write([
                'tenant_id' => $v->tenantId(), 'actor_user_id' => (int) $v->userId(), 'actor_label' => 'Rattacher', 'action' => 'attach.setting',
                'object_type' => 'sse_portal_settings', 'object_id' => 0, 'before' => ['always_align' => !$on], 'after' => ['always_align' => $on],
            ]);
        } catch (\Throwable) {
        }

        return $this->answer($request, ['ok' => $ok, 'error' => $ok ? null : 'Le réglage n’a pas pu être enregistré.'], $on ? 'Alignement à la hausse activé pour tous les rattachements.' : 'Alignement à la hausse désactivé : il se coche rattachement par rattachement.');
    }

    // ------------------------------------------------------------------ Pistes

    public function leads(Request $request, array $params = []): Response
    {
        $v = $this->viewer();
        if (!$v->canRead('dossiers')) {
            Session::flash('error', 'Droit manquant : lire la section « Dossiers d’enquête ».');

            return Response::redirect(url('atak/sse'));
        }
        $svc = $this->service();
        $ready = SseAttachService::ready();
        $rows = [];
        if ($ready) {
            // Mêmes règles que la fenêtre : une piste au-dessus de l'habilitation n'est pas listée.
            $res = $svc->directory()->search([['key' => 'case:0', 'type' => 'case', 'id' => '0']], ['scope' => 'lead', 'q' => $request->queryString('q'), 'sort' => 'recent', 'status' => $request->queryString('etat'), 'page' => 1]);
            $rows = $res['items'];
        }

        return (new SseIdentityRegistryController())->page('atak.sse.leads', [
            'title' => 'Pistes', 'leads' => $rows, 'ready' => $ready, 'q' => $request->queryString('q'), 'etat' => $request->queryString('etat'),
            'canWrite' => $ready && $v->canWrite('dossiers'), 'canManage' => $svc->canManage('dossiers'), 'alwaysAlign' => $svc->alwaysAlign(),
            'levels' => $svc->context([])['levels'], 'activeNav' => 'pistes',
        ]);
    }

    public function leadShow(Request $request, array $params = []): Response
    {
        $id = (string) (int) ($params['id'] ?? 0);
        $v = $this->viewer();
        $svc = $this->service();
        $node = SseAttachService::ready() ? $svc->directory()->graph()->see('lead', $id) : null;
        $lead = $node !== null ? (new SseAttachmentRepository())->findLead($v->tenantId(), (int) $id) : null;
        if ($lead === null) {
            Session::flash('error', 'Piste introuvable.');

            return Response::redirect(url('atak/sse/pistes'));
        }
        $names = $svc->directory()->userNames([(int) ($lead['owner_user_id'] ?? 0)]);

        return (new SseIdentityRegistryController())->page('atak.sse.lead_show', [
            'title' => $lead['reference_code'] . ' — ' . $lead['title'], 'lead' => $lead, 'owner' => $names[(int) ($lead['owner_user_id'] ?? 0)] ?? '',
            'canWrite' => $v->canWrite('dossiers'), 'levels' => $svc->context([])['levels'], 'activeNav' => 'pistes',
        ]);
    }

    public function leadStore(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard($request, true)) !== null) {
            return $deny;
        }
        $res = $this->service()->createTarget('lead', ['title' => $request->inputString('title'), 'description' => $request->inputString('description'), 'classification' => $request->inputString('classification')]);
        if (!$res['ok']) {
            Session::flash('error', (string) $res['error']);

            return Response::redirect(url('atak/sse/pistes'));
        }
        Session::flash('success', 'Piste ouverte.');

        return Response::redirect(url('atak/sse/pistes/' . (int) substr((string) $res['key'], 5)));
    }

    /** État, titre et description d'une piste. La classification ne se change pas ici vers le bas sans motif : elle ne peut que monter, dans la limite de l'habilitation. */
    public function leadUpdate(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard($request, true)) !== null) {
            return $deny;
        }
        $id = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/pistes/' . $id);
        $v = $this->viewer();
        $svc = $this->service();
        $node = SseAttachService::ready() ? $svc->directory()->graph()->see('lead', (string) $id) : null;
        if ($node === null || !$v->canWrite('dossiers')) {
            Session::flash('error', $node === null ? 'Piste introuvable.' : 'Droit manquant : écrire dans « Dossiers d’enquête ».');

            return Response::redirect(url('atak/sse/pistes'));
        }
        $repo = new SseAttachmentRepository();
        $before = $repo->findLead($v->tenantId(), $id) ?? [];
        $data = [];
        $title = trim(strip_tags($request->inputString('title')));
        if (mb_strlen($title) >= 3) {
            $data['title'] = $title;
        }
        if ($request->input('description') !== null) {
            $data['description'] = trim(strip_tags($request->inputString('description')));
        }
        $status = $request->inputString('status');
        if (isset(A::LEAD_STATUSES[$status])) {
            $data['status'] = $status;
        }
        $class = $request->inputString('classification');
        $reason = trim(strip_tags($request->inputString('reason')));
        if (A::isLevel($class) && $class !== (string) ($before['classification'] ?? '')) {
            $lower = A::rank($class) < A::rank((string) ($before['classification'] ?? ''));
            if (!$v->clearanceAllows($class)) {
                Session::flash('error', 'Vous ne pouvez pas classer au-dessus de votre propre habilitation.');

                return Response::redirect($back);
            }
            if ($lower && (!$svc->canManage('dossiers') || mb_strlen($reason) < A::REASON_MIN)) {
                Session::flash('error', 'Abaisser une classification demande le droit de gérer les dossiers et un motif.');

                return Response::redirect($back);
            }
            $data['classification'] = $class;
        }
        if ($data !== [] && $repo->updateLead($v->tenantId(), $id, $data)) {
            try {
                (new \App\Repositories\SseAuditLogRepository())->write([
                    'tenant_id' => $v->tenantId(), 'actor_user_id' => (int) $v->userId(), 'actor_label' => 'Pistes', 'action' => isset($data['classification']) ? 'lead.classify' : 'lead.update',
                    'object_type' => 'sse_leads', 'object_id' => $id, 'reason' => $reason,
                    'before' => array_intersect_key($before, $data), 'after' => $data,
                ]);
            } catch (\Throwable) {
            }
            Session::flash('success', 'Piste mise à jour.');
        }

        return Response::redirect($back);
    }

    // ------------------------------------------------------------------ Outils

    /** @return list<string> */
    private static function refs(string $raw): array
    {
        $out = [];
        foreach (explode(',', mb_substr($raw, 0, 4000)) as $ref) {
            $p = C::parseRef($ref);
            if ($p !== null) {
                $out[] = C::ref($p['type'], $p['id']);
            }
        }

        return array_values(array_unique($out));
    }

    /** @return list<string> */
    private static function tags(string $raw): array
    {
        $list = json_decode($raw, true);
        if (!is_array($list)) {
            $list = explode(',', $raw);
        }

        return array_slice(array_values(array_filter(array_map(static fn (mixed $t): string => is_scalar($t) ? trim((string) $t) : '', $list), static fn (string $t): bool => $t !== '')), 0, A::TAGS_PER_LINK);
    }

    /** @return array<string, mixed> */
    private function settings(Request $request): array
    {
        $per = json_decode($request->inputString('per'), true);

        return [
            'nature' => $request->inputString('nature'), 'nature_label' => $request->inputString('nature_label'), 'label' => $request->inputString('label'),
            'reason' => $request->inputString('reason'), 'note' => $request->inputString('note'), 'tags' => self::tags($request->inputString('tags')),
            'align' => $request->inputString('align') === '1', 'per' => is_array($per) ? $per : [],
        ];
    }

    private function guard(Request $request, bool $write): ?Response
    {
        $token = $request->input('_csrf_token');
        if (!is_string($token) || $token === '' || !Csrf::validate($token)) {
            return $this->answer($request, ['ok' => false, 'error' => 'Votre page est restée ouverte trop longtemps ou la session a changé : rechargez la page puis recommencez.'], '', 419);
        }
        if ($this->tooMany($write)) {
            return $this->busy();
        }

        return null;
    }

    private function tooMany(bool $write): bool
    {
        $v = $this->viewer();
        try {
            return ($this->limiter ??= new FileRateLimiter())->tooManyAttempts(
                'sse_attach|' . ($write ? 'w' : 'r') . '|' . $v->tenantId() . '|' . (int) $v->userId() . '|' . ($v->isGuest() ? 'g' : 'u'),
                $write ? self::WRITES_PER_HOUR : self::READS_PER_5_MIN,
                $write ? 3600 : 300
            );
        } catch (\Throwable) {
            return false;
        }
    }

    private function busy(): Response
    {
        return Response::json(['ok' => false, 'error' => 'Trop de demandes en peu de temps : patientez une minute puis recommencez.'], 429);
    }

    /**
     * Réponse JSON pour la fenêtre, ou message et retour à la page pour un formulaire envoyé sans script.
     *
     * @param array<string, mixed> $res
     */
    private function answer(Request $request, array $res, string $success, int $failStatus = 422): Response
    {
        $json = str_contains((string) $request->header('Accept'), 'application/json') || $request->header('X-Requested-With') !== null;
        if ($json) {
            return Response::json($res + ['message' => !empty($res['ok']) ? $success : ''], !empty($res['ok']) ? 200 : $failStatus);
        }
        Session::flash(!empty($res['ok']) ? 'success' : 'error', !empty($res['ok']) ? $success : (string) ($res['error'] ?? 'Action impossible.'));

        return Response::redirect(SseLinksController::safeBack($request->inputString('back')));
    }
}
