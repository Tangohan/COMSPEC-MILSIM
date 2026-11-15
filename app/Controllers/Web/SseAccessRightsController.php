<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Security\FileRateLimiter;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseAccessDirectory;
use App\Services\Sse\SseAccessMatrix;
use App\Services\Sse\SseAccessPolicy;
use App\Services\Sse\SseAccessRuleService;
use App\Support\SseAccessCatalog as C;

/**
 * Onglet « Droits d'accès » du portail SSE : qui peut voir, lire, écrire, verser, supprimer, exporter et gérer, section
 * par section, paramétré par communauté (grille, règles d'une section, modèles, droits effectifs d'un membre, « qui a
 * accès », historique).
 *
 * Réservé au droit de gestion SSE existant (SseAccessPolicy::canManageRights) : toute autre personne est renvoyée à l'accueil
 * du bureau sans qu'on lui dise que la page existe. Toute écriture : CSRF, limite de changements par heure, validation par
 * SseAccessRuleService (pas d'escalade, bénéficiaire de la communauté, motif, dernier gestionnaire protégé), aperçu de l'effet
 * avant enregistrement. L'écran parle de droits, jamais de contenus : aucune identité SSE ni couverture n'y paraît.
 */
final class SseAccessRightsController
{
    public const BASE = 'atak/sse/droits';

    private ?SseAccessDirectory $directory = null;

    /** @var array<string, bool> */
    private array $visible = [];

    public function __construct(
        private ?SseAccessPolicy $policy = null,
        private ?SseAccessRuleService $service = null,
        private ?SseAccessMatrix $matrix = null,
    ) {
        $this->policy ??= SseAccessPolicy::forSession();
    }

    // ------------------------------------------------------------------ Pages

    public function index(Request $request, array $params = []): Response
    {
        $denied = $this->guard();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = $this->tenantId();

        return $this->view('atak.sse.rights.index', [
            'title' => 'Droits d’accès',
            'tab' => 'grille',
            'grid' => array_values(array_filter($this->matrix()->grid($tenantId), fn (array $row): bool => $this->mayView((string) $row['key']))),
            'rulesTotal' => $this->policy->rules()->countLive($tenantId),
            'ready' => $this->policy->rules()->ready(),
            'options' => $this->policy->subjects()->options($tenantId),
            'old' => $this->oldInput(),
        ]);
    }

    public function section(Request $request, array $params = []): Response
    {
        $denied = $this->guard();
        if ($denied !== null) {
            return $denied;
        }
        $key = (string) ($params['section'] ?? '');
        if (!C::hasSection($key) || !$this->mayView($key)) {
            return Response::redirect(url(self::BASE));
        }
        $tenantId = $this->tenantId();

        return $this->view('atak.sse.rights.section', [
            'title' => C::sectionLabel($key) . ' · droits d’accès',
            'tab' => 'grille',
            'sectionKey' => $key,
            'section' => C::SECTIONS[$key],
            'rules' => $this->matrix()->rulesOfSection($tenantId, $key),
            'options' => $this->policy->subjects()->options($tenantId),
            'ready' => $this->policy->rules()->ready(),
            'old' => $this->oldInput(),
            'focus' => C::hasLevel((string) $request->query('niveau', '')) ? (string) $request->query('niveau') : '',
        ]);
    }

    public function member(Request $request, array $params = []): Response
    {
        $denied = $this->guard();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = $this->tenantId();
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 60);
        $id = (int) ($params['id'] ?? 0);
        if ($id > 0) {
            $member = $this->directory()->member($tenantId, $id);
            if ($member === null) {
                Session::flash('error', 'Membre introuvable.');

                return Response::redirect(url(self::BASE . '/membre'));
            }
            $mine = [];
            foreach (C::sectionKeys() as $key) {
                if (!$this->mayView($key)) {
                    continue;
                }
                foreach ($this->matrix()->rulesOfSection($tenantId, $key) as $level => $rules) {
                    foreach ($rules as $r) {
                        if ($r['subject_type'] === C::SUBJECT_USER && (int) $r['subject_id'] === $id) {
                            $mine[] = $r + ['section_label' => C::sectionLabel($key), 'level_label' => C::levelLabel((string) $level)];
                        }
                    }
                }
            }

            $effective = $this->matrix()->effectiveForMember($tenantId, $id);
            $effective['sections'] = array_values(array_filter($effective['sections'], fn (array $row): bool => $this->mayView((string) $row['key'])));

            return $this->view('atak.sse.rights.member', [
                'title' => 'Droits effectifs · ' . $member['label'],
                'tab' => 'membre',
                'member' => $member,
                'effective' => $effective,
                'mine' => $mine,
                'old' => $this->oldInput(),
            ]);
        }

        return $this->view('atak.sse.rights.member', [
            'title' => 'Droits effectifs d’un membre',
            'tab' => 'membre',
            'member' => null,
            'q' => $q,
            'results' => $q !== '' ? $this->directory()->search($tenantId, $q, 30) : [],
        ]);
    }

    public function who(Request $request, array $params = []): Response
    {
        $denied = $this->guard();
        if ($denied !== null) {
            return $denied;
        }
        $section = (string) $request->query('section', '');
        $level = (string) $request->query('niveau', C::LEVEL_READ);
        $valid = C::hasSection($section) && C::hasLevel($level) && C::applies($section, $level) && $this->mayView($section);
        $tenantId = $this->tenantId();

        return $this->view('atak.sse.rights.who', [
            'title' => 'Qui a accès',
            'tab' => 'qui',
            'section' => $valid ? $section : '',
            'level' => $valid ? $level : C::LEVEL_READ,
            'result' => $valid ? $this->matrix()->whoHas($tenantId, $section, $level) : null,
        ]);
    }

    public function history(Request $request, array $params = []): Response
    {
        $denied = $this->guard();
        if ($denied !== null) {
            return $denied;
        }

        return $this->view('atak.sse.rights.history', [
            'title' => 'Historique des droits d’accès',
            'tab' => 'historique',
            'rows' => array_values(array_filter($this->policy->rules()->history($this->tenantId(), 150), fn (array $r): bool => $this->mayView((string) $r['section']))),
            'audit' => $this->auditRows(),
        ]);
    }

    // ------------------------------------------------------------------ Écritures

    /** Ajouter des règles à une section (ou, depuis la fiche d'un membre, des dérogations individuelles). */
    public function ruleStore(Request $request, array $params = []): Response
    {
        $denied = $this->guardWrite($request, $request->inputString('do') === 'save');
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = $this->tenantId();
        $memberId = (int) ($params['id'] ?? 0);
        $input = [
            'section' => (string) ($params['section'] ?? $request->inputString('section')),
            'levels' => $this->stringList($request->input('levels')),
            'effect' => $request->inputString('effect'),
            'starts_at' => $request->inputString('starts_at'),
            'ends_at' => $request->inputString('ends_at'),
            'reason' => $request->inputString('reason'),
            'source' => '',
        ];
        if ($memberId > 0) {
            $input += ['subject_type' => C::SUBJECT_USER, 'subject_id' => $memberId];
            $back = self::BASE . '/membre/' . $memberId;
            $action = $back . '/derogation';
        } else {
            $ref = C::parseSubjectRef($request->inputString('subject_ref'));
            $input += ['subject_type' => $ref[0] ?? '', 'subject_id' => $ref[1] ?? 0];
            $back = self::BASE . '/section/' . rawurlencode($input['section']);
            $action = $back . '/regle';
        }

        return $this->planAndRespond($request, $tenantId, $input, null, $back, $action);
    }

    /** Poser un modèle sur un bénéficiaire. */
    public function template(Request $request, array $params = []): Response
    {
        $denied = $this->guardWrite($request, $request->inputString('do') === 'save');
        if ($denied !== null) {
            return $denied;
        }
        $ref = C::parseSubjectRef($request->inputString('subject_ref'));
        $input = [
            'template' => $request->inputString('template'),
            'subject_type' => $ref[0] ?? '',
            'subject_id' => $ref[1] ?? 0,
            'starts_at' => $request->inputString('starts_at'),
            'ends_at' => $request->inputString('ends_at'),
            'reason' => $request->inputString('reason'),
            'restrict_rest' => $request->inputString('restrict_rest') === '1' ? 1 : 0,
        ];

        return $this->planAndRespond($request, $this->tenantId(), $input, (string) $input['template'], self::BASE, self::BASE . '/modele');
    }

    public function ruleRevoke(Request $request, array $params = []): Response
    {
        $denied = $this->guardWrite($request, true);
        if ($denied !== null) {
            return $denied;
        }
        $res = $this->service()->revoke($this->tenantId(), $this->userId(), $this->actorLabel(), (int) ($params['id'] ?? 0), $request->inputString('reason'));
        Session::flash($res['ok'] ? 'success' : 'error', $res['message']);

        return Response::redirect(url($this->safeBack($request->inputString('back'), self::BASE)));
    }

    // ------------------------------------------------------------------ Outils

    /**
     * @param array<string, mixed> $input
     */
    private function planAndRespond(Request $request, int $tenantId, array $input, ?string $template, string $back, string $action): Response
    {
        $plan = $template !== null
            ? $this->service()->planTemplate($tenantId, $this->userId(), $template, $input)
            : $this->service()->plan($tenantId, $this->userId(), $input);
        $preview = $request->inputString('do') !== 'save';
        if ($plan['errors'] !== [] || $plan['rules'] === []) {
            $this->flashOld($input);
            Session::flash('error', $plan['errors'] !== [] ? implode(' ', $plan['errors']) : implode(' ', $plan['skipped']));

            return Response::redirect(url($this->safeBack($back, self::BASE)));
        }
        if ($preview) {
            return $this->view('atak.sse.rights.preview', [
                'title' => 'Aperçu avant enregistrement',
                'tab' => 'grille',
                'rules' => $plan['rules'],
                'skipped' => $plan['skipped'],
                'sim' => $this->matrix()->simulate($tenantId, $plan['rules']),
                'input' => $input,
                'template' => $template,
                'action' => $action,
                'back' => $back,
            ]);
        }
        $ids = $this->service()->commit($tenantId, $this->userId(), $this->actorLabel(), $plan['rules'], $template !== null ? 'template' : 'create');
        if ($ids === []) {
            Session::flash('error', 'Rien n’a été enregistré : réessayez.');
        } else {
            Session::flash('success', 'Règles enregistrées. Les gardes du bureau en tiennent compte tout de suite.');
        }

        return Response::redirect(url($this->safeBack($back, self::BASE)));
    }

    /** Page réservée au droit de gestion SSE ; sinon retour à l'accueil du bureau, sans explication. */
    private function guard(): ?Response
    {
        $tenantId = $this->tenantId();
        $uid = $this->userId();
        if ($tenantId < 1 || $uid < 1 || $this->isGuest() || !$this->policy->canManageRights($uid, ['tenant_id' => $tenantId, 'guest' => false])) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/accueil'));
        }

        return null;
    }

    private function guardWrite(Request $request, bool $save): ?Response
    {
        $denied = $this->guard();
        if ($denied !== null) {
            return $denied;
        }
        if (!$request->isPost() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url(self::BASE));
        }
        $limit = $save ? C::CHANGES_PER_HOUR : 200;
        if ((new FileRateLimiter())->tooManyAttempts('sse_access_rules|' . ($save ? 'w' : 'p') . '|' . $this->tenantId() . '|' . $this->userId(), $limit, 3600)) {
            Session::flash('error', 'Trop de changements de droits en peu de temps : réessayez plus tard.');

            return Response::redirect(url(self::BASE));
        }

        return null;
    }

    /** Communauté de la session SSE (les clés que SseAccessCodeService écrit à l'entrée), sinon celle du compte. */
    /**
     * Une section ne se montre qu'à une personne qui en voit au moins l'existence : un refus explicite sur la section la
     * retire de l'onglet (règles, qui a accès, historique) pour elle aussi.
     */
    private function mayView(string $section): bool
    {
        if (!isset($this->visible[$section])) {
            $this->visible[$section] = C::hasSection($section) && $this->policy->allows($this->userId(), $section, C::LEVEL_EXISTS, [
                'tenant_id' => $this->tenantId(),
                'guest' => false,
                'door_mode' => SseAccessPolicy::DOOR_ENTRY,
            ]);
        }

        return $this->visible[$section];
    }

    private function tenantId(): int
    {
        $tid = (int) Session::get(SseAccessCodeService::SESSION_TENANT, 0);

        return $tid > 0 ? $tid : (int) Session::get('tenant_id');
    }

    private function isGuest(): bool
    {
        return (bool) Session::get(SseAccessCodeService::SESSION_GUEST, false);
    }

    private function userId(): int
    {
        return (int) Session::get('user_id');
    }

    private function actorLabel(): string
    {
        foreach (['callsign', 'display_name'] as $k) {
            $v = trim((string) (Session::get($k) ?? ''));
            if ($v !== '') {
                return mb_substr($v, 0, 120);
            }
        }

        return 'Membre #' . $this->userId();
    }

    private function matrix(): SseAccessMatrix
    {
        return $this->matrix ??= new SseAccessMatrix($this->policy, $this->directory());
    }

    private function service(): SseAccessRuleService
    {
        return $this->service ??= new SseAccessRuleService($this->policy->rules(), $this->policy->subjects(), $this->policy, $this->directory());
    }

    private function directory(): SseAccessDirectory
    {
        return $this->directory ??= new SseAccessDirectory();
    }

    /** Retour sur une page de l'onglet seulement (jamais une adresse extérieure). */
    private function safeBack(string $back, string $default): string
    {
        $back = trim($back);
        if ($back !== '' && preg_match('#^atak/sse/droits(/[A-Za-z0-9/_%-]*)?(\?[A-Za-z0-9=&_%-]*)?$#', $back) === 1) {
            return $back;
        }

        return $default;
    }

    /** @return list<string> */
    private function stringList(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $v): string => is_scalar($v) ? (string) $v : '', $raw));
    }

    /** @param array<string, mixed> $input */
    private function flashOld(array $input): void
    {
        $keep = [];
        foreach (['levels', 'effect', 'starts_at', 'ends_at', 'reason', 'subject_type', 'subject_id', 'template', 'restrict_rest', 'section'] as $k) {
            if (isset($input[$k])) {
                $keep[$k] = $input[$k];
            }
        }
        Session::flash('sse_rights_old', (string) json_encode($keep, JSON_UNESCAPED_UNICODE));
    }

    /** @return array<string, mixed> */
    private function oldInput(): array
    {
        $raw = Session::getFlash('sse_rights_old');
        $data = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($data) ? $data : [];
    }

    /** Dernières lignes du journal d'audit SSE qui concernent les droits d'accès (sans contenu SSE). @return list<array<string, mixed>> */
    private function auditRows(): array
    {
        try {
            $rows = (new \App\Repositories\SseAuditLogRepository())->listForTenant($this->tenantId(), ['object_type' => 'access_rule', 'limit' => 60]);
        } catch (\Throwable) {
            return [];
        }

        return $rows;
    }

    /** @param array<string, mixed> $data */
    private function view(string $view, array $data): Response
    {
        $data['isGuest'] = false;
        $data['clearanceUntil'] = (int) Session::get(SseAccessCodeService::SESSION_UNTIL, 0);
        $data['guestLabel'] = '';
        $data['sseTheme'] = sse_ui_theme();
        $data['sseThemeOptions'] = sse_ui_theme_options();
        $data['canGrant'] = true;
        $data['canManage'] = true;
        $data['activeNav'] = 'droits';
        $data['csrfToken'] = Csrf::token();
        $data['base'] = self::BASE;

        return Response::view($view, $data);
    }
}
