<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseAuditLogRepository;
use App\Repositories\SseTriageEventRepository;
use App\Repositories\SseTriageRuleRepository;
use App\Repositories\SseTriageStateRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseAccessPolicy;
use App\Services\Sse\SseClearanceService;
use App\Services\Sse\SseTriageActions;
use App\Services\Sse\SseTriageGrouping;
use App\Services\Sse\SseTriageInbox;
use App\Services\Sse\SseTriageRules;
use App\Support\SseAccessCatalog;
use App\Support\SsePortalNav;
use App\Support\SseTriageBadges;
use App\Support\SseTriagePresets;
use App\Support\SseTriageSchema;

/**
 * Portail SSE : boîte « À trier » (tout ce qui attend une décision humaine, toutes files confondues), compteurs légers pour les
 * pastilles de la barre latérale, et ses fonctions automatiques : regroupement, acquittement, veille, assignation, règles Cerbère-Tri.
 *
 * Droits (SseAccessPolicy, section « Analyse », aucun droit nouveau) : VOIR la page = ce qu'elle exige déjà (chaque file reste
 * filtrée par la lecture de sa propre section) ; TRAITER (acquitter, mettre en veille, rappeler, assigner) = écrire dans « Analyse » ;
 * GÉRER LES RÈGLES = gérer « Analyse ». Toute écriture : POST, jeton CSRF, communauté de la session, et chaque clé d'élément doit
 * figurer dans la liste que la session peut lire (anti-IDOR : on n'écrit jamais sur un élément qu'on ne voit pas).
 * Les décisions métier restent dans la page de chaque file.
 */
final class SseTriageController
{
    private const VIEWS = ['a-traiter' => 'À traiter', 'mes' => 'Mes éléments', 'veille' => 'En veille', 'acquittes' => 'Acquittés'];

    /**
     * @param (\Closure(string, string): bool)|null $allows (section, niveau) : droit de la session (tests) ; défaut SseAccessPolicy
     * @param (\Closure(): int)|null $user compte de la session (tests)
     * @param (\Closure(array<string, mixed>): void)|null $audit écrit une ligne du journal d'audit SSE
     */
    public function __construct(
        private ?SseAccessCodeService $access = null,
        private ?SseClearanceService $clearance = null,
        private ?SseTriageInbox $inbox = null,
        private ?SseTriageActions $actions = null,
        private ?SseTriageRuleRepository $rules = null,
        private ?SseTriageEventRepository $events = null,
        private ?\Closure $allows = null,
        private ?\Closure $user = null,
        private ?\Closure $audit = null,
    ) {
        $this->access ??= new SseAccessCodeService();
        $this->inbox ??= SseTriageInbox::forSession();
    }

    /** GET /atak/sse/a-trier : liste regroupée ; ?vue=, ?source=, ?groupe=. */
    public function index(Request $request, array $params = []): Response
    {
        return (new SseIdentityRegistryController($this->access, $this->clearance ?? new SseClearanceService($this->access)))->page('atak.sse.triage', $this->pageData($request));
    }

    /**
     * Données de la vue « À trier » : liste filtrée par vue et par file, groupes, droits de la session, règles et journal pour les
     * gestionnaires. Séparé de index() pour que la page se teste et se rende sans la coque du portail.
     *
     * @return array<string, mixed>
     */
    public function pageData(Request $request): array
    {
        [$sections, $snap] = $this->load();
        $tenant = $this->tenantId();
        $now = time();
        $vue = isset(self::VIEWS[$request->queryString('vue')]) ? $request->queryString('vue') : 'a-traiter';
        $tab = $request->queryString('source');
        if ($tab !== '' && !isset($snap['sources'][$tab])) {
            $tab = '';
        }
        $mode = SseTriageGrouping::mode($request->queryString('groupe'));
        $me = $this->userId();
        $items = array_values(array_filter($snap['items'], static function (array $i) use ($vue, $tab, $me): bool {
            if ($tab !== '' && $i['source'] !== $tab) {
                return false;
            }
            $st = $i['triage']['status'];

            return match ($vue) {
                'mes' => $st === SseTriageStateRepository::OPEN && $me > 0 && (int) $i['triage']['assigned_to'] === $me,
                'veille' => $st === SseTriageStateRepository::SNOOZED,
                'acquittes' => $st === SseTriageStateRepository::ACKNOWLEDGED,
                default => $st === SseTriageStateRepository::OPEN,
            };
        }));
        $canTreat = $this->canTreat($tenant) && !$snap['degraded'];
        $canRules = $this->canManageRules($tenant) && !$snap['degraded'];

        return [
            'title' => 'À trier',
            'triage' => $snap,
            'triageItems' => $items,
            'triageGroups' => SseTriageGrouping::group($items, $mode, $now),
            'triageTab' => $tab,
            'triageView' => $vue,
            'triageViews' => self::VIEWS,
            'triageMode' => $mode,
            'triageModes' => SseTriageGrouping::MODES,
            'canTreat' => $canTreat,
            'canRules' => $canRules,
            'rulesView' => $canRules ? $this->rulesView($tenant, $snap['items'], $now) : [],
            'journal' => $canRules ? $this->journal($snap['items']) : [],
            'assignees' => $canTreat ? $this->assignees($tenant) : [],
            'userId' => $me,
            'sourceTypes' => $this->sourceTypes($snap['items']),
            'activeNav' => 'a-trier',
            'lang' => SseWantedNoticeController::readerLang(),
        ];
    }

    /** GET /atak/sse/a-trier/compteurs : JSON léger, lecture seule (pas de jeton CSRF), pour l'actualisation des pastilles. */
    public function counters(Request $request, array $params = []): Response
    {
        [$sections, $snap] = $this->load();
        $lang = SseWantedNoticeController::readerLang();
        $data = SseTriageBadges::fromSnapshot($sections, $snap);
        $entries = [];
        foreach ($data['entries'] as $nav => $e) {
            $entries[$nav] = $e + ['label' => SseTriageBadges::label($e['count']), 'aria' => SseTriageBadges::aria($e['count'], $lang)];
        }
        $secs = [];
        foreach ($data['sections'] as $id => $e) {
            $secs[$id] = $e + ['label' => SseTriageBadges::label($e['count']), 'aria' => SseTriageBadges::aria($e['count'], $lang)];
        }

        return Response::json([
            'total' => $data['total'],
            'label' => SseTriageBadges::label($data['total']),
            'aria' => SseTriageBadges::aria($data['total'], $lang),
            'entries' => (object) $entries,
            'sections' => (object) $secs,
        ])->header('Cache-Control', 'private, no-store');
    }

    /**
     * POST /atak/sse/a-trier/traiter : action = acknowledge | snooze | recall | assign sur des clés d'éléments (keys[]).
     * Réponse : {ok, batch, changed} ; « batch » sert à annuler pendant 15 secondes.
     */
    public function act(Request $request, array $params = []): Response
    {
        if ($denied = $this->guardWrite($request)) {
            return $denied;
        }
        $tenant = $this->tenantId();
        if (!$this->canTreat($tenant)) {
            return $this->error('Vous n’avez pas le droit de traiter les éléments de « À trier ».', 403);
        }
        if (!SseTriageSchema::ready()) {
            return $this->error('Le traitement des éléments n’est pas encore disponible : la mise à jour de la base de données doit être lancée.', 503);
        }
        $action = $request->inputString('action');
        $keys = $this->keysOf($request);
        if (!in_array($action, SseTriageActions::ACTIONS, true) || $keys === []) {
            return $this->error('Demande incomplète.', 422);
        }
        $known = $this->knownKeys();
        foreach ($keys as $k) {
            if (!isset($known[$k])) {
                return $this->error('Un des éléments n’existe plus ou ne vous est pas accessible.', 404);
            }
        }
        $opts = ['note' => $request->inputString('note')];
        if ($action === 'snooze') {
            $opts['days'] = (int) $request->inputString('days');
        }
        if ($action === 'assign') {
            $assignee = $request->inputString('assignee');
            $opts['assignee'] = $assignee === 'me' ? $this->userId() : (int) $assignee;
            if ($opts['assignee'] > 0 && !$this->isAssignable($tenant, (int) $opts['assignee'])) {
                return $this->error('Cette personne ne peut pas traiter les éléments de « À trier ».', 422);
            }
        }
        $res = $this->actions()->apply($tenant, $action, $keys, $this->userId(), $this->actorLabel(), $opts);
        if (!$res['ok']) {
            return $this->error((string) ($res['error'] ?? 'Action impossible.'), 422);
        }
        SseTriageInbox::invalidate();

        return Response::json(['ok' => true, 'batch' => $res['batch'], 'changed' => $res['changed']])->header('Cache-Control', 'private, no-store');
    }

    /** POST /atak/sse/a-trier/annuler : annule un lot (« batch ») ou une ligne du journal (« event », gestionnaires pour une règle). */
    public function undo(Request $request, array $params = []): Response
    {
        if ($denied = $this->guardWrite($request)) {
            return $denied;
        }
        $tenant = $this->tenantId();
        if (!$this->canTreat($tenant)) {
            return $this->error('Vous n’avez pas le droit de traiter les éléments de « À trier ».', 403);
        }
        if (!SseTriageSchema::ready()) {
            return $this->error('Le traitement des éléments n’est pas encore disponible : la mise à jour de la base de données doit être lancée.', 503);
        }
        $manager = $this->canManageRules($tenant);
        $event = (int) $request->inputString('event');
        $res = $event > 0
            ? $this->actions()->undoEvent($tenant, $event, $this->userId(), $this->actorLabel(), $manager)
            : $this->actions()->undoBatch($tenant, $request->inputString('batch'), $this->userId(), $this->actorLabel(), $manager);
        if (!$res['ok']) {
            return $this->error((string) ($res['error'] ?? 'Annulation impossible.'), 422);
        }
        SseTriageInbox::invalidate();

        return Response::json(['ok' => true, 'restored' => $res['restored'] ?? 1])->header('Cache-Control', 'private, no-store');
    }

    /**
     * POST /atak/sse/a-trier/regles : op = preset (activer ou couper un modèle fourni), toggle (activer ou couper une règle), custom
     * (créer ou modifier une règle). Réservé à ceux qui gèrent « Analyse ».
     */
    public function rulesSave(Request $request, array $params = []): Response
    {
        if ($denied = $this->guardWrite($request)) {
            return $denied;
        }
        $tenant = $this->tenantId();
        if (!$this->canManageRules($tenant)) {
            return $this->error('Vous n’avez pas le droit de gérer les règles de « À trier ».', 403);
        }
        if (!SseTriageSchema::ready()) {
            return $this->error('Les règles ne sont pas encore disponibles : la mise à jour de la base de données doit être lancée.', 503);
        }
        $repo = $this->rules();
        $op = $request->inputString('op');
        $enabled = $request->inputString('enabled') === '1';
        $before = null;
        if ($op === 'preset') {
            $presetKey = $request->inputString('preset');
            $preset = SseTriagePresets::all()[$presetKey] ?? null;
            if ($preset === null) {
                return $this->error('Modèle de règle inconnu.', 422);
            }
            $before = $repo->findPreset($tenant, $presetKey);
            $id = $repo->save($tenant, $before !== null ? (int) $before['id'] : null, ['name' => $preset['name'], 'preset_key' => $presetKey, 'enabled' => $enabled] + $preset['rule'], $this->userId());
        } elseif ($op === 'toggle') {
            $id = (int) $request->inputString('id');
            $before = $repo->find($tenant, $id);
            if ($before === null) {
                return $this->error('Règle introuvable.', 404);
            }
            $repo->setEnabled($tenant, $id, $enabled);
        } elseif ($op === 'custom') {
            $name = trim($request->inputString('name'));
            if ($name === '') {
                return $this->error('Donnez un nom à la règle.', 422);
            }
            $id = (int) $request->inputString('id');
            $before = $id > 0 ? $repo->find($tenant, $id) : null;
            if ($id > 0 && $before === null) {
                return $this->error('Règle introuvable.', 404);
            }
            $fields = [
                'name' => $name, 'source_key' => $request->inputString('source_key'), 'type_key' => $request->inputString('type_key'), 'keyword' => $request->inputString('keyword'),
                'min_age_days' => (int) $request->inputString('min_age_days'), 'min_severity' => $request->inputString('min_severity') ?: null, 'max_severity' => $request->inputString('max_severity') ?: null,
                'scope' => $request->inputString('scope', 'all'), 'action' => $request->inputString('rule_action'), 'action_value' => (int) $request->inputString('action_value'), 'enabled' => $enabled,
            ];
            if (!isset(SseTriagePresets::ACTIONS[$fields['action']])) {
                return $this->error('Choisissez l’action de la règle.', 422);
            }
            if ($fields['action'] === 'snooze' && $fields['action_value'] < 1) {
                return $this->error('Indiquez la durée de veille en jours.', 422);
            }
            if ($fields['action'] === 'assign' && !$this->isAssignable($tenant, (int) $fields['action_value'])) {
                return $this->error('Choisissez la personne à qui assigner.', 422);
            }
            $id = $repo->save($tenant, $id > 0 ? $id : null, $fields, $this->userId());
        } else {
            return $this->error('Demande incomplète.', 422);
        }
        $rule = $repo->find($tenant, $id);
        $this->writeAudit($tenant, 'sse.triage.rule.' . $op, $this->userId(), $this->actorLabel(), (string) ($rule['name'] ?? ''), ['rule' => $id, 'enabled' => $enabled, 'before' => $before === null ? null : ['enabled' => (int) ($before['enabled'] ?? 0)]]);
        SseTriageInbox::invalidate();
        [, $snap] = $this->load();

        return Response::json(['ok' => true, 'id' => $id, 'enabled' => (int) ($rule['enabled'] ?? 0) === 1, 'preview' => $rule !== null ? count(SseTriageRules::affected($rule, $snap['items'], time())) : 0])->header('Cache-Control', 'private, no-store');
    }

    /** GET /atak/sse/a-trier/regles/apercu : « cette règle concernerait N éléments », avant de l'activer. Mêmes champs que la règle. */
    public function rulesPreview(Request $request, array $params = []): Response
    {
        $tenant = $this->tenantId();
        if (!$this->canManageRules($tenant)) {
            return $this->error('Vous n’avez pas le droit de gérer les règles de « À trier ».', 403);
        }
        $rule = $this->ruleFromQuery($request);
        [, $snap] = $this->load();

        return Response::json(['ok' => true, 'count' => count(SseTriageRules::affected($rule, $snap['items'], time()))])->header('Cache-Control', 'private, no-store');
    }

    // ---------------------------------------------------------------------------------------------- aide

    /** @return array<string, mixed> */
    private function ruleFromQuery(Request $request): array
    {
        $preset = SseTriagePresets::all()[$request->queryString('preset')] ?? null;
        if ($preset !== null) {
            return $preset['rule'] + ['id' => 0];
        }

        return [
            'id' => 0, 'source_key' => $request->queryString('source_key'), 'type_key' => $request->queryString('type_key'), 'keyword' => $request->queryString('keyword'),
            'min_age_days' => (int) $request->queryString('min_age_days'), 'min_severity' => $request->queryString('min_severity') ?: null,
            'max_severity' => $request->queryString('max_severity') ?: null, 'scope' => $request->queryString('scope', 'all'), 'action' => 'acknowledge',
        ];
    }

    /**
     * Règles de la communauté pour l'écran : modèles fournis (activés ou non) puis règles propres, avec l'aperçu en nombre d'éléments.
     *
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function rulesView(int $tenant, array $items, int $now): array
    {
        try {
            $rows = $this->rules()->all($tenant);
        } catch (\Throwable) {
            return [];
        }
        $byPreset = [];
        foreach ($rows as $r) {
            if ((string) ($r['preset_key'] ?? '') !== '') {
                $byPreset[(string) $r['preset_key']] = $r;
            }
        }
        $out = [];
        foreach (SseTriagePresets::all() as $key => $preset) {
            $row = $byPreset[$key] ?? null;
            $rule = ($row ?? $preset['rule']) + ['id' => (int) ($row['id'] ?? 0)];
            $out[] = ['id' => (int) ($row['id'] ?? 0), 'preset' => $key, 'name' => $preset['name'], 'hint' => $preset['hint'], 'engine' => $preset['engine'],
                'enabled' => $row !== null ? (int) $row['enabled'] === 1 : $preset['engine'],
                'rule' => $rule, 'last_fired_at' => (string) ($row['last_fired_at'] ?? ''), 'fired_count' => (int) ($row['fired_count'] ?? 0),
                'preview' => $preset['engine'] ? null : count(SseTriageRules::affected($rule, $items, $now)), 'custom' => false];
        }
        foreach ($rows as $r) {
            if ((string) ($r['preset_key'] ?? '') !== '') {
                continue;
            }
            $out[] = ['id' => (int) $r['id'], 'preset' => '', 'name' => (string) $r['name'], 'hint' => '', 'engine' => false, 'enabled' => (int) $r['enabled'] === 1,
                'rule' => $r, 'last_fired_at' => (string) ($r['last_fired_at'] ?? ''), 'fired_count' => (int) $r['fired_count'],
                'preview' => count(SseTriageRules::affected($r, $items, $now)), 'custom' => true];
        }

        return $out;
    }

    /**
     * Journal des déclenchements et des actions (50 derniers), avec le sujet quand l'élément est encore dans la liste.
     *
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function journal(array $items): array
    {
        try {
            $events = $this->events()->recent($this->tenantId(), 50);
            $rules = [];
            foreach ($this->rules()->all($this->tenantId()) as $r) {
                $rules[(int) $r['id']] = (string) $r['name'];
            }
        } catch (\Throwable) {
            return [];
        }
        $byKey = [];
        foreach ($items as $it) {
            $byKey[(string) $it['key']] = $it;
        }
        foreach ($events as &$e) {
            $it = $byKey[(string) $e['key']] ?? null;
            $e['subject'] = $it !== null ? (string) ($it['subject']['label'] ?? $it['title']) : (string) $e['key'];
            $e['type_label'] = $it !== null ? (string) $it['type_label'] : '';
            $e['rule_name'] = $rules[(int) ($e['rule_id'] ?? 0)] ?? '';
        }

        return $events;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return array<string, string> clé de type => libellé (formulaire d'une règle)
     */
    private function sourceTypes(array $items): array
    {
        $out = [];
        foreach ($items as $it) {
            $out[(string) $it['type_key']] = (string) $it['type_label'];
        }
        asort($out);

        return $out;
    }

    /** @return array<int, string> comptes qui peuvent traiter les éléments : id => nom (200 au plus) */
    private function assignees(int $tenant): array
    {
        try {
            $out = [];
            foreach (Database::getInstance()->fetchAll('SELECT id, display_name FROM users WHERE tenant_id = :t ORDER BY display_name ASC LIMIT 200', ['t' => $tenant]) as $u) {
                if ($this->allowsUser((int) $u['id'], $tenant)) {
                    $out[(int) $u['id']] = (string) $u['display_name'];
                }
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    private function isAssignable(int $tenant, int $userId): bool
    {
        if ($userId < 1) {
            return false;
        }
        if ($userId === $this->userId()) {
            return $this->canTreat($tenant);
        }
        try {
            $row = Database::getInstance()->fetchOne('SELECT id FROM users WHERE id = :id AND tenant_id = :t LIMIT 1', ['id' => $userId, 't' => $tenant]);
        } catch (\Throwable) {
            return false;
        }

        return $row !== null && $this->allowsUser($userId, $tenant);
    }

    private function allowsUser(int $userId, int $tenant): bool
    {
        if ($this->allows !== null) {
            return (bool) ($this->allows)('analyse', SseAccessCatalog::LEVEL_WRITE, $userId);
        }
        try {
            $section = 'analyse'; // section d'accès SSE, pas une fonctionnalité de forfait
            return SseAccessPolicy::forSession()->allows($userId, $section, SseAccessCatalog::LEVEL_WRITE, ['tenant_id' => $tenant, 'guest' => false]);
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array<string, true> clés des éléments que la session peut lire */
    private function knownKeys(): array
    {
        [, $snap] = $this->load();

        return array_fill_keys(array_map(static fn (array $i): string => (string) $i['key'], $snap['items']), true);
    }

    /** @return list<string> */
    private function keysOf(Request $request): array
    {
        $raw = $request->input('keys', []);
        $raw = is_array($raw) ? $raw : [$raw];

        return array_slice(array_values(array_unique(array_filter(array_map(static fn (mixed $k): string => is_scalar($k) ? trim((string) $k) : '', $raw), static fn (string $k): bool => $k !== ''))), 0, SseTriageActions::MAX_KEYS);
    }

    /** Garde commune des écritures : méthode POST, jeton CSRF, compte identifié. */
    private function guardWrite(Request $request): ?Response
    {
        if (strtoupper($request->method()) !== 'POST') {
            return $this->error('Méthode non autorisée.', 405);
        }
        if (!Csrf::verify($request)) {
            return $this->error('Session expirée : rechargez la page.', 419);
        }
        if ($this->access->isGuest() || $this->userId() < 1 || $this->tenantId() < 1) {
            return $this->error('Un compte identifié est nécessaire.', 403);
        }

        return null;
    }

    private function error(string $message, int $status): Response
    {
        return Response::json(['ok' => false, 'error' => $message], $status)->header('Cache-Control', 'private, no-store');
    }

    private function actions(): SseTriageActions
    {
        return $this->actions ??= new SseTriageActions(new SseTriageStateRepository(), $this->events(), $this->rules(), $this->audit);
    }

    private function rules(): SseTriageRuleRepository
    {
        return $this->rules ??= new SseTriageRuleRepository();
    }

    private function events(): SseTriageEventRepository
    {
        return $this->events ??= new SseTriageEventRepository();
    }

    private function userId(): int
    {
        return $this->user !== null ? (int) ($this->user)() : (int) Session::get('user_id');
    }

    private function tenantId(): int
    {
        return $this->access->tenantId() ?: (int) Session::get('tenant_id');
    }

    private function actorLabel(): string
    {
        return (string) (Session::get('display_name') ?: 'Opérateur');
    }

    private function allowsSession(string $section, string $level, int $tenant): bool
    {
        if ($this->allows !== null) {
            return (bool) ($this->allows)($section, $level, $this->userId());
        }

        return SseAccessPolicy::sessionAllows($section, $level, ['tenant_id' => $tenant]);
    }

    private function canTreat(int $tenant): bool
    {
        return !$this->access->isGuest() && $this->userId() > 0 && $this->allowsSession('analyse', SseAccessCatalog::LEVEL_WRITE, $tenant);
    }

    private function canManageRules(int $tenant): bool
    {
        return !$this->access->isGuest() && $this->userId() > 0 && $this->allowsSession('analyse', SseAccessCatalog::LEVEL_MANAGE, $tenant);
    }

    /** @param array<string, mixed> $after */
    private function writeAudit(int $tenant, string $action, int $userId, string $label, string $reason, array $after): void
    {
        $row = ['tenant_id' => $tenant, 'actor_user_id' => $userId, 'actor_label' => $label, 'action' => $action, 'object_type' => 'triage_rule', 'reason' => $reason, 'after' => $after];
        try {
            $this->audit !== null ? ($this->audit)($row) : (new SseAuditLogRepository())->write($row);
        } catch (\Throwable $e) {
            error_log('À trier : journal d’audit indisponible : ' . $e->getMessage());
        }
    }

    /** @return array{0: list<array<string, mixed>>, 1: array{total: int, sources: array<string, array<string, mixed>>, items: list<array<string, mixed>>, counts: array<string, int>, degraded: bool}} */
    private function load(): array
    {
        $isGuest = $this->access->isGuest();
        $nav = SsePortalNav::forSession(false, false, $isGuest);
        $sections = $nav['sections'];
        $navIds = [];
        foreach ($sections as $s) {
            foreach ($s['items'] as $i) {
                $navIds[] = (string) $i['id'];
            }
        }
        $snap = $this->inbox->snapshot($this->tenantId(), $this->userId(), $navIds);

        return [$sections, $snap];
    }
}
