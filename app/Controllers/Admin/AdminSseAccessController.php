<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseAccessCodeRepository;
use App\Repositories\SseAuditLogRepository;
use App\Repositories\SseCaseRepository;
use App\Services\Security\FileRateLimiter;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseAccessHub;
use App\Services\Sse\SseAccessMemberRights;
use App\Services\Sse\SseClearanceService;
use App\Services\Sse\SseMemberClearance;
use App\Services\Sse\SseRedactionService;
use App\Support\SseAccessCatalog as C;

/**
 * Back-office › Accès renseignement : centre de gestion des accès au renseignement (hors sas classifié). Vue d'ensemble,
 * personnels (accès au portail, habilitation, dérogations), documents (niveau de lecture par modèle), droits SSE et portail,
 * codes temporaires, niveaux de diffusion et journal.
 *
 * Aucune règle propre : les codes passent par SseAccessCodeService, l'habilitation et les dérogations d'un membre par
 * SseAccessMemberRights (mêmes garde-fous que le bloc « Accès SSE » de la fiche effectifs : droit de gestion SSE, pas
 * d'escalade, plafond, motif, journal), les lectures par SseAccessHub. La communauté est toujours celle de la session.
 */
final class AdminSseAccessController
{
    public const BASE = 'back-office/renseignement/acces';

    /** Onglets de la page, dans l'ordre : le premier s'ouvre par défaut (?onglet=… sans JavaScript). */
    public const TABS = ['ensemble', 'personnels', 'documents', 'sse', 'codes', 'delivrer', 'niveaux', 'journal'];

    /** Onglets réservés au droit de gestion SSE (SseAccessMemberRights::visibleTo) : absents sans lui. */
    public const TABS_MANAGE = ['personnels'];

    /** Membres traités au plus par une action groupée. */
    public const BULK_MAX = 50;

    /**
     * @param \Closure(int): list<array<string, mixed>>|null $auditLog journal d'audit SSE des habilitations (tests)
     */
    public function __construct(
        private ?SseAccessCodeService $access = null,
        private ?SseAccessCodeRepository $codes = null,
        private ?SseClearanceService $clearance = null,
        private ?SseAccessMemberRights $rights = null,
        private ?SseAccessHub $hub = null,
        private ?\Closure $auditLog = null,
    ) {
        $this->access ??= new SseAccessCodeService();
        $this->codes ??= new SseAccessCodeRepository();
        $this->clearance ??= new SseClearanceService($this->access);
    }

    private function rights(): SseAccessMemberRights
    {
        return $this->rights ??= new SseAccessMemberRights();
    }

    private function hub(): SseAccessHub
    {
        return $this->hub ??= new SseAccessHub();
    }

    public function index(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessGrant();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = $this->tenantId();
        $actorId = $this->userId();
        $canPeople = $this->rights()->visibleTo($tenantId, $actorId);
        $canWrite = $canPeople && self::effectifsBlockAllowed();

        // Fiche d'un membre en tiroir : ?membre=ID (page complète, sans JavaScript) ou fragment seul (&fragment=fiche).
        $memberId = (int) $request->queryString('membre', '0');
        $member = $memberId > 0 && $canPeople ? $this->rights()->overview($tenantId, $actorId, $memberId) : null;
        if ($request->queryString('fragment') === 'fiche') {
            if ($member === null) {
                // Même réponse pour un membre inconnu, d'une autre communauté, masqué ou sans droit.
                return (new Response())->setStatusCode(404)->header('Content-Type', 'text/html; charset=utf-8')->setBody('');
            }

            return Response::view('admin.sse_access._member_drawer', ['member' => $member, 'canWrite' => $canWrite, 'selfId' => $actorId, 'drawerOpen' => true]);
        }

        $maxLevel = $this->clearance->maxLevel();
        $codes = $this->codes->listActiveForTenant($tenantId);
        $log = $this->codes->listLogForTenant($tenantId, 40);
        $log = is_array($log) ? $log : [];
        $cases = $this->cases($tenantId);

        $people = $canPeople ? $this->hub()->people($tenantId, $actorId, $maxLevel, $codes) : null;
        $filters = [
            'unite' => mb_substr(trim($request->queryString('unite')), 0, 120),
            'niveau' => $request->queryString('niveau'),
            'etat' => $request->queryString('etat'),
            'q' => mb_substr(trim($request->queryString('q')), 0, 80),
        ];

        $tabs = array_values(array_filter(self::TABS, static fn (string $t): bool => $canPeople || !in_array($t, self::TABS_MANAGE, true)));
        $tab = $request->queryString('onglet');
        if ($tab === '' && $member !== null) {
            $tab = 'personnels';
        }
        $lang = function_exists('locale') && locale() === 'en' ? 'en' : 'fr';
        $canFiles = AdminSseFilesController::allowed();

        return Response::view('layout.main', [
            'content' => 'admin.sse_access.index',
            'title' => 'Accès renseignement',
            // Le titre vit dans la bande d'en-tête de la vue : pas de second en-tête de shell.
            'boSkipPageHead' => true,
            'backOfficePageCss' => ['back-office-bands.css', 'back-office-sse-access.css'],
            'canManage' => true,
            'canPeople' => $canPeople,
            'canWrite' => $canWrite,
            'canFiles' => $canFiles,
            'selfId' => $actorId,
            'tabs' => $tabs,
            'activeTab' => in_array($tab, $tabs, true) ? $tab : $tabs[0],
            'maxLevel' => $maxLevel,
            'clearanceOrigin' => $this->clearance->origin(),
            'codes' => $codes,
            'cases' => $cases,
            'actionLog' => $log,
            'people' => $people,
            'peopleRows' => $people !== null ? SseAccessHub::filter($people['rows'], $filters) : [],
            'filters' => $filters,
            'overview' => SseAccessHub::overview($people['rows'] ?? null, $codes, $log, time()),
            'member' => $member,
            'grantable' => (new SseMemberClearance())->grantable($maxLevel),
            'sections' => $canPeople ? $this->hub()->sections($tenantId, $actorId) : [],
            'board' => $canPeople ? $this->hub()->board($tenantId, $actorId) : [],
            'clearanceRoles' => $this->hub()->clearanceRoles($tenantId),
            'documents' => SseAccessHub::documents($tenantId, null, $lang),
            'consultations' => $canFiles ? $this->consultations($tenantId) : null,
            'caseLock' => $this->setting(fn (): bool => $this->clearance->caseLockEnabled($tenantId)),
            'workingRedaction' => $this->setting(fn (): bool => $this->clearance->workingRedactionEnabled($tenantId)),
            'casesByLevel' => self::casesByLevel($cases),
            'lockedForMe' => $this->clearance->countLockedForMe($cases),
            'journal' => $this->journal($tenantId, $actorId, $codes, $log, $people['rows'] ?? null),
            'issuedPlain' => Session::getFlash('sse_issued_code'),
            'classificationLabels' => SseCaseRepository::CLASSIFICATION_LABELS,
            'levelLabels' => SseRedactionService::LEVELS,
        ]);
    }

    public function issue(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessGrant();
        if ($denied !== null) {
            return $denied;
        }
        if (!$request->isPost() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url(self::BASE));
        }
        $tenantId = $this->tenantId();
        // Plafond : un niveau de lecture au-dessus de sa propre habilitation ne se délivre pas.
        $level = (string) $request->input('clearance_level', SseCaseRepository::CLASS_INTERNAL);
        if (isset(SseRedactionService::LEVELS[$level]) && !$this->clearance->allows($level)) {
            Session::flash('error', 'Vous ne pouvez pas accorder une habilitation plus haute que la vôtre.');

            return Response::redirect(url(self::BASE));
        }
        $result = $this->access->issue(
            $tenantId,
            $this->userId(),
            trim((string) $request->input('label', 'Accès temporaire')),
            (string) $request->input('grant_type', 'member'),
            (int) $request->input('ttl_hours', 4),
            (int) $request->input('session_ttl_minutes', 240),
            (int) $request->input('max_uses', 1),
            ((int) $request->input('case_id', 0)) ?: null,
            $level
        );
        if ($result['ok']) {
            Session::flash('sse_issued_code', $result['plain']);
            Session::flash('success', 'Code créé. Communiquez-le par un canal sécurisé — il ne sera plus réaffiché.');
        } else {
            Session::flash('error', $result['message'] ?? 'Impossible de créer le code.');
        }

        return Response::redirect(url(self::BASE));
    }

    public function revoke(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessGrant();
        if ($denied !== null) {
            return $denied;
        }
        $id = (int) ($params['id'] ?? 0);
        if (!$request->isPost() || !Csrf::validate((string) $request->input('_csrf_token', '')) || $id < 1) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url(self::BASE));
        }
        $tenantId = $this->tenantId();
        $this->codes->revoke($id, $tenantId);
        $this->codes->logEvent($tenantId, 'revoke', $id, null, $this->userId(), null, null);
        Session::flash('success', 'Code révoqué.');

        return Response::redirect(url(self::BASE));
    }

    /** POST …/personnels/habilitation : accorde, modifie ou retire l'habilitation d'un ou plusieurs membres (motif obligatoire). */
    public function peopleClearance(Request $request, array $params = []): Response
    {
        $back = url(self::BASE) . '?onglet=personnels';
        if (($denied = $this->guardWrite($request, $back)) !== null) {
            return $denied;
        }
        $level = $request->inputString('clearance_level');
        $reason = $request->inputString('reason');
        $done = 0;
        $errors = [];
        foreach ($this->memberIds($request) as $memberId) {
            if ($this->tooMany()) {
                $errors[] = 'Trop de changements de droits en peu de temps : réessayez plus tard.';
                break;
            }
            $res = $this->rights()->changeClearance($this->tenantId(), $this->userId(), $this->actorLabel(), $memberId, $level !== '' ? $level : null, $reason);
            if ($res['ok']) {
                $done++;
            } else {
                $errors[] = $res['message'];
            }
        }

        return $this->report($back, $done, $errors, $level === '' ? 'Habilitation retirée : %1 membre(s).' : 'Habilitation enregistrée : %1 membre(s).');
    }

    /** POST …/personnels/acces : donne ou restreint un accès par section à un ou plusieurs membres (dérogation datée, motif). */
    public function peopleAccess(Request $request, array $params = []): Response
    {
        $back = url(self::BASE) . '?onglet=personnels';
        if (($denied = $this->guardWrite($request, $back)) !== null) {
            return $denied;
        }
        $sections = $request->input('sections');
        $input = [
            'sections' => is_array($sections) ? array_values(array_map(static fn (mixed $v): string => is_scalar($v) ? (string) $v : '', $sections)) : [],
            'access' => $request->inputString('access'),
            'effect' => $request->inputString('effect') === C::EFFECT_DENY ? C::EFFECT_DENY : C::EFFECT_ALLOW,
            'ends_at' => $request->inputString('ends_at'),
            'reason' => $request->inputString('reason'),
        ];
        $done = 0;
        $errors = [];
        foreach ($this->memberIds($request) as $memberId) {
            if ($memberId === $this->userId()) {
                $errors[] = 'Vous ne pouvez pas modifier vos propres accès.';
                continue;
            }
            if ($this->tooMany()) {
                $errors[] = 'Trop de changements de droits en peu de temps : réessayez plus tard.';
                break;
            }
            $res = $this->rights()->save($this->tenantId(), $this->userId(), $this->actorLabel(), $memberId, $input);
            if ($res['ok']) {
                $done++;
            } else {
                $errors[] = $res['message'];
            }
        }

        return $this->report($back, $done, $errors, $input['effect'] === C::EFFECT_DENY ? 'Accès restreint : %1 membre(s).' : 'Accès accordé : %1 membre(s).');
    }

    /** POST …/personnels/{id}/regles/{ruleId}/retirer : retire une dérogation de ce membre (motif obligatoire). */
    public function peopleRuleRevoke(Request $request, array $params = []): Response
    {
        $memberId = (int) ($params['id'] ?? 0);
        $back = url(self::BASE) . '?onglet=personnels&membre=' . max(0, $memberId);
        if (($denied = $this->guardWrite($request, $back)) !== null) {
            return $denied;
        }
        $reason = trim($request->inputString('reason'));
        if ($memberId === $this->userId()) {
            Session::flash('error', 'Vous ne pouvez pas modifier vos propres accès.');

            return Response::redirect($back);
        }
        if (mb_strlen($reason) < C::REASON_MIN) {
            Session::flash('error', 'Un motif est obligatoire (5 caractères au moins).');

            return Response::redirect($back);
        }
        if ($this->tooMany()) {
            Session::flash('error', 'Trop de changements de droits en peu de temps : réessayez plus tard.');

            return Response::redirect($back);
        }
        $res = $this->rights()->revoke($this->tenantId(), $this->userId(), $this->actorLabel(), $memberId, (int) ($params['ruleId'] ?? 0), $reason);
        Session::flash($res['ok'] ? 'success' : 'error', $res['message']);

        return Response::redirect($back);
    }

    // ------------------------------------------------------------------ Gardes

    private function denyUnlessGrant(): ?Response
    {
        $tenantId = $this->tenantId();
        if ($tenantId < 1 || $this->userId() < 1) {
            return Response::redirect(url('login'));
        }
        $this->access->ensureGateHydrated();
        $ok = (function_exists('is_platform_admin') && is_platform_admin())
            || (function_exists('can') && (can('atak.sse.grant') || can('admin.access') || can('admin.organization')));
        if (!$ok) {
            Session::flash('error', 'Seul le commandement peut gérer les accès au renseignement.');

            return Response::redirect(url('back-office'));
        }

        return null;
    }

    /**
     * Écriture sur l'accès d'un membre : les droits du bloc « Accès SSE » de la fiche effectifs, exactement (droit de gestion
     * SSE, et l'accès aux effectifs que OrganizationAdminMiddleware exige sur ses routes), puis POST et jeton CSRF.
     */
    private function guardWrite(Request $request, string $back): ?Response
    {
        $denied = $this->denyUnlessGrant();
        if ($denied !== null) {
            return $denied;
        }
        if (!$this->rights()->visibleTo($this->tenantId(), $this->userId()) || !self::effectifsBlockAllowed()) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url(self::BASE));
        }
        if (!$request->isPost() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }

        return null;
    }

    /** Mêmes droits que OrganizationAdminMiddleware sur /back-office/ressources/effectifs (routes du bloc « Accès SSE »). */
    public static function effectifsBlockAllowed(): bool
    {
        if (function_exists('is_platform_admin') && is_platform_admin()) {
            return true;
        }
        if (!function_exists('can')) {
            return false;
        }
        foreach (['admin.organization', 'admin.access', 'site.support', 'organization.effectifs.hub.view', 'admin.roles.manage',
            'personnel.profile.update', 'personnel.assignments.manage', 'personnel.grades.manage', 'personnel.status.manage'] as $slug) {
            if (can($slug)) {
                return true;
            }
        }

        return false;
    }

    private function tooMany(): bool
    {
        // Même compteur que le bloc de la fiche effectifs et l'onglet « Droits d'accès » du bureau SSE.
        return (new FileRateLimiter())->tooManyAttempts('sse_access_rules|w|' . $this->tenantId() . '|' . $this->userId(), C::CHANGES_PER_HOUR, 3600);
    }

    /** @return list<int> */
    private function memberIds(Request $request): array
    {
        $raw = $request->input('member_ids');
        $ids = is_array($raw) ? $raw : [$request->input('member_id', 0)];
        $out = [];
        foreach ($ids as $v) {
            $id = is_scalar($v) ? (int) $v : 0;
            if ($id > 0) {
                $out[$id] = $id;
            }
        }

        return array_slice(array_values($out), 0, self::BULK_MAX);
    }

    /** @param list<string> $errors */
    private function report(string $back, int $done, array $errors, string $okTemplate): Response
    {
        $errors = array_values(array_unique($errors));
        if ($done === 0 && $errors === []) {
            Session::flash('error', 'Choisissez au moins un membre.');
        } elseif ($done > 0) {
            Session::flash('success', str_replace('%1', (string) $done, $okTemplate) . ($errors !== [] ? ' ' . implode(' ', $errors) : ''));
        } else {
            Session::flash('error', implode(' ', $errors));
        }

        return Response::redirect($back);
    }

    // ------------------------------------------------------------------ Lectures

    /** @return list<array<string, mixed>> */
    private function cases(int $tenantId): array
    {
        try {
            return (new SseCaseRepository())->listForTenant($tenantId, null);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param list<array<string, mixed>> $cases
     * @return array<string, int>
     */
    public static function casesByLevel(array $cases): array
    {
        $out = array_fill_keys(array_keys(SseRedactionService::LEVELS), 0);
        foreach ($cases as $c) {
            $k = SseCaseRepository::normalizeClassification((string) ($c['classification'] ?? ''));
            if (isset($out[$k])) {
                $out[$k]++;
            }
        }

        return $out;
    }

    /** @param \Closure(): bool $read */
    private function setting(\Closure $read): ?bool
    {
        try {
            return $read();
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return list<array<string, mixed>>|null dernières consultations des fichiers (sans les critères de recherche) */
    private function consultations(int $tenantId): ?array
    {
        try {
            $rows = (new \App\Repositories\SseFileRepository())->listQueries($tenantId, ['limit' => 8]);
        } catch (\Throwable) {
            return null;
        }
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'qrn' => (string) ($r['qrn'] ?? ''),
                'type' => (string) ($r['query_type_label'] ?? ''),
                'source' => (string) ($r['source_label'] ?? ''),
                'operator' => (string) ($r['operator_label'] ?? ''),
                'hit' => !empty($r['hit']),
                'created_at' => (string) ($r['created_at'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Journal unifié : codes (délivrés, utilisés, révoqués, engagements), habilitations des membres et dérogations
     * individuelles, du plus récent au plus ancien. Un membre que l'annuaire ne montre pas n'est jamais nommé.
     *
     * @param list<array<string, mixed>> $codes
     * @param list<array<string, mixed>> $log
     * @param list<array<string, mixed>>|null $people
     * @return list<array<string, mixed>>
     */
    private function journal(int $tenantId, int $actorId, array $codes, array $log, ?array $people): array
    {
        $codeLabels = [];
        foreach ($codes as $c) {
            $codeLabels[(int) $c['id']] = (string) $c['label'];
        }
        $rows = [];
        foreach ($log as $row) {
            $cid = (int) ($row['code_id'] ?? 0);
            $rows[] = [
                'ts' => (int) ($row['ts'] ?? 0),
                'action' => (string) ($row['event_type'] ?? ''),
                'subject' => $cid > 0 ? ($codeLabels[$cid] ?? '') : '',
                'actor' => (string) ($row['actor'] ?? ''),
                'detail' => (string) ($row['detail'] ?? ''),
            ];
        }
        if ($people !== null) {
            $names = [];
            foreach ($people as $p) {
                $names[(int) $p['user_id']] = (string) $p['label'];
            }
            $nameOf = static fn (int $id): string => $names[$id] ?? 'Membre protégé par l’annuaire';
            foreach ($this->clearanceAudit($tenantId) as $a) {
                $rows[] = [
                    'ts' => strtotime((string) ($a['created_at'] ?? '')) ?: 0,
                    'action' => (string) ($a['action'] ?? ''),
                    'subject' => $nameOf((int) ($a['object_id'] ?? 0)),
                    'actor' => (string) ($a['actor_label'] ?? ''),
                    'detail' => (string) ($a['reason'] ?? ''),
                ];
            }
            foreach ($this->hub()->memberRuleHistory($tenantId, $actorId) as $h) {
                $reason = (string) $h['reason'];
                $rows[] = [
                    'ts' => strtotime((string) $h['created_at']) ?: 0,
                    'action' => 'rule.' . ($h['action'] === 'revoke' ? 'revoke' : ($h['effect'] === C::EFFECT_DENY ? 'deny' : 'allow')),
                    'subject' => $nameOf((int) $h['subject_id']),
                    'actor' => (string) $h['actor_label'],
                    'detail' => C::sectionLabel((string) $h['section']) . ' · ' . C::levelLabel((string) $h['level']) . ($reason !== '' ? ' · ' . $reason : ''),
                ];
            }
        }
        usort($rows, static fn (array $a, array $b): int => $b['ts'] <=> $a['ts']);

        return array_slice($rows, 0, 150);
    }

    /** @return list<array<string, mixed>> */
    private function clearanceAudit(int $tenantId): array
    {
        try {
            if ($this->auditLog !== null) {
                return ($this->auditLog)($tenantId);
            }

            return (new SseAuditLogRepository())->listForTenant($tenantId, ['object_type' => SseMemberClearance::OBJECT, 'limit' => 60]);
        } catch (\Throwable) {
            return [];
        }
    }

    private function tenantId(): int
    {
        return (int) Session::get('tenant_id');
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
}
