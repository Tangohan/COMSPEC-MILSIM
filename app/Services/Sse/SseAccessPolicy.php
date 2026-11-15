<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Gate;
use App\Core\Session;
use App\Repositories\SseAccessRuleRepository;
use App\Services\Rbac\EffectivePermissions;
use App\Services\Rbac\EffectivePermissionsService;
use App\Support\SseAccessCatalog as C;

/**
 * Service de décision unique des droits du portail SSE : « cette personne peut-elle, dans cette section, faire ce
 * niveau de droit ? ». Toutes les gardes du portail, des API /api/sse, de la photothèque du site et de JNET le
 * consultent ; aucun contrôleur ne relit les droits à sa façon.
 *
 * Ordre de résolution (voir docs/sse-droits-acces.md) :
 *  1. section ou niveau inconnu, ou case sans objet : refus ;
 *  2. session invitée (code) ou sans compte, ou communauté sans règle, ou administrateur de la plateforme : la garde
 *     d'origine (profil du catalogue) décide, rien d'autre ;
 *  3. un refus explicite actif (membre, niveau d'accès, unité, unité et sous-unités) : refus, il l'emporte sur tout ;
 *  4. la garde d'origine ouvre : accès (une règle ne retire rien d'autre qu'un refus explicite) ;
 *  5. une règle d'accord active, et la porte du portail passée : accès. Sans la porte (droit d'entrer, engagement de
 *     confidentialité), une règle n'ouvre jamais rien ;
 *  6. sinon refus (refus par défaut).
 *
 * Un refus sur « existence » ou « lecture » emporte les niveaux qui en dépendent (Catalog::DEPENDENTS) ; un accord sur
 * un niveau (sauf « gérer ») donne aussi l'existence de la section. Les dates de début et de fin sont jugées à l'heure
 * de la requête.
 *
 * Contexte accepté par allows() : tenant_id (défaut : la session), guest (défaut : la session SSE), legacy (bool : la
 * garde d'origine déjà évaluée par l'appelant, quand elle diffère du profil du catalogue), door_mode (« session » :
 * session SSE active et confidentialité acceptée, défaut ; « entry » : droit d'entrer seulement, pour les API sans
 * session de portail), extra_rules et drop_rules (simulation : règles ajoutées ou retirées sans rien écrire).
 */
final class SseAccessPolicy
{
    public const DOOR_SESSION = 'session';
    public const DOOR_ENTRY = 'entry';

    private static ?self $session = null;

    /** @var array<int, array<string, list<array<string, mixed>>>> */
    private array $bySection = [];

    /** @var array<string, EffectivePermissions|null> */
    private array $effective = [];

    /**
     * @param \Closure(int, int, string): bool|null $permission droit d'origine d'un compte : (compte, communauté, droit)
     * @param \Closure(?int, int, string): bool|null $door porte du portail : (compte, communauté, mode)
     * @param \Closure(): string|null $clock « Y-m-d H:i:s »
     * @param \Closure(int, int): bool|null $platformAdmin administrateur de la plateforme : (compte, communauté)
     */
    public function __construct(
        private ?SseAccessRuleRepository $rules = null,
        private ?SseAccessSubjects $subjects = null,
        private ?\Closure $permission = null,
        private ?\Closure $door = null,
        private ?\Closure $clock = null,
        private ?\Closure $platformAdmin = null,
    ) {
    }

    /** Instance partagée de la requête (règles et appartenances lues une seule fois). */
    public static function forSession(): self
    {
        return self::$session ??= new self();
    }

    /**
     * Raccourci des gardes : le compte de la session (ou l'invité) dans sa communauté.
     *
     * @param array<string, mixed> $context voir allows()
     */
    public static function sessionAllows(string $section, string $level, array $context = []): bool
    {
        $uid = (int) Session::get('user_id');

        return self::forSession()->allows($uid > 0 ? $uid : null, $section, $level, $context);
    }

    /**
     * Garde d'origine d'un profil du catalogue pour la session (sans les règles) : pour les appelants dont une action garde
     * sa propre garde d'origine, qu'ils passent ensuite au contexte (« legacy ») de allows().
     *
     * @param array<string, mixed> $context guest, tenant_id
     */
    public static function sessionProfile(string $profile, array $context = []): bool
    {
        $policy = self::forSession();
        $uid = (int) Session::get('user_id');
        $guest = array_key_exists('guest', $context) ? (bool) $context['guest'] : $policy->sessionGuest();
        $tenant = $policy->tenantOf($context);

        return $policy->legacyAllows($uid > 0 ? $uid : 0, $tenant, $profile, $guest);
    }

    /** Remplace (tests) ou efface l'instance partagée. */
    public static function useForSession(?self $policy): void
    {
        self::$session = $policy;
    }

    public function rules(): SseAccessRuleRepository
    {
        return $this->rules ??= new SseAccessRuleRepository();
    }

    public function subjects(): SseAccessSubjects
    {
        return $this->subjects ??= new SseAccessSubjects();
    }

    /** La communauté a-t-elle au moins une règle non retirée ? Sinon les gardes d'origine décident seules. */
    public function hasRules(int $tenantId): bool
    {
        return $tenantId > 0 && $this->rules()->countLive($tenantId) > 0;
    }

    /**
     * Un membre change de position, de fonction ou de qualification : on oublie ce qui a été lu de lui pendant la requête
     * (les règles et les droits ne sont gardés dans aucun cache partagé entre requêtes pour ces appartenances).
     */
    public static function forgetMember(int $tenantId, int $userId): void
    {
        self::$session?->subjects()->forget($tenantId, $userId);
        self::$session?->forgetEffective($tenantId, $userId);
    }

    /** @internal */
    public function forgetEffective(int $tenantId, int $userId): void
    {
        if ($tenantId > 0) {
            unset($this->effective[$tenantId . ':' . $userId]);

            return;
        }
        foreach (array_keys($this->effective) as $key) {
            if (str_ends_with($key, ':' . $userId)) {
                unset($this->effective[$key]);
            }
        }
    }

    /** Oublie ce qui a été lu (après un changement de règle). */
    public function reset(): void
    {
        $this->bySection = [];
        $this->effective = [];
        $this->subjects?->reset();
    }

    public function now(): string
    {
        return $this->clock !== null ? (string) ($this->clock)() : date('Y-m-d H:i:s');
    }

    // ------------------------------------------------------------------ Décision

    /**
     * @param array<string, mixed> $context voir la description de la classe
     */
    public function allows(?int $userId, string $section, string $level, array $context = []): bool
    {
        return $this->decide($userId, $section, $level, $context)->allowed;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function decide(?int $userId, string $section, string $level, array $context = []): SseAccessDecision
    {
        if (!C::hasSection($section) || !C::hasLevel($level) || !C::applies($section, $level)) {
            return new SseAccessDecision(false, SseAccessDecision::INVALID, false);
        }
        $tenantId = $this->tenantOf($context);
        $uid = $userId !== null && $userId > 0 ? $userId : 0;
        $guest = array_key_exists('guest', $context) ? (bool) $context['guest'] : $this->sessionGuest();
        $mode = ($context['door_mode'] ?? self::DOOR_SESSION) === self::DOOR_ENTRY ? self::DOOR_ENTRY : self::DOOR_SESSION;
        $legacy = array_key_exists('legacy', $context) && is_bool($context['legacy'])
            ? $context['legacy']
            : $this->legacyAllows($uid, $tenantId, C::legacyProfile($section, $level), $guest, $mode);

        $extra = isset($context['extra_rules']) && is_array($context['extra_rules']) ? $context['extra_rules'] : [];
        $drop = isset($context['drop_rules']) && is_array($context['drop_rules']) ? array_map('intval', $context['drop_rules']) : [];
        $rules = $this->rulesFor($tenantId, $section, $extra, $drop);

        if ($uid < 1 || $guest || $tenantId < 1 || $rules === []) {
            return new SseAccessDecision($legacy, $legacy ? SseAccessDecision::LEGACY : SseAccessDecision::NONE, $legacy);
        }
        if ($this->isPlatformAdmin($uid, $tenantId)) {
            return new SseAccessDecision($legacy, SseAccessDecision::PLATFORM, $legacy);
        }

        $now = $this->now();
        $mine = $this->subjects()->forUser($tenantId, $uid);
        $deny = [];
        $allow = [];
        foreach ($rules as $rule) {
            if (!$this->isActive($rule, $now) || !$this->matches($rule, $tenantId, $uid, $mine)) {
                continue;
            }
            $ruleLevel = (string) $rule['level'];
            if ($rule['effect'] === C::EFFECT_DENY && in_array($level, C::deniedBy($ruleLevel), true)) {
                $deny[] = (int) $rule['id'];
            } elseif ($rule['effect'] === C::EFFECT_ALLOW && ($ruleLevel === $level || ($level === C::LEVEL_EXISTS && $ruleLevel !== C::LEVEL_MANAGE))) {
                $allow[] = (int) $rule['id'];
            }
        }
        if ($deny !== []) {
            return new SseAccessDecision(false, SseAccessDecision::DENY_RULE, $legacy, $deny);
        }
        if ($legacy) {
            return new SseAccessDecision(true, SseAccessDecision::LEGACY, true);
        }
        if ($allow !== []) {
            if ($this->doorOpen($uid, $tenantId, $mode)) {
                return new SseAccessDecision(true, SseAccessDecision::ALLOW_RULE, false, $allow);
            }

            return new SseAccessDecision(false, SseAccessDecision::DOOR_CLOSED, false, $allow);
        }

        return new SseAccessDecision(false, SseAccessDecision::NONE, false);
    }

    /**
     * Droit d'ouvrir et de modifier les droits d'accès SSE : le droit de gestion SSE existant (attribution des accès) ou
     * l'administration de l'organisation / de la plateforme. Aucune règle ne l'étend ni ne le retire : les gestionnaires ne peuvent donc jamais être verrouillés dehors.
     */
    public function canManageRights(?int $userId = null, array $context = []): bool
    {
        $uid = $userId ?? (int) Session::get('user_id');
        $tenantId = $this->tenantOf($context);
        $guest = array_key_exists('guest', $context) ? (bool) $context['guest'] : $this->sessionGuest();

        if ($this->legacyAllows($uid > 0 ? $uid : 0, $tenantId, 'grant', $guest, self::DOOR_ENTRY)) {
            return true;
        }

        // Les administrateurs de l'organisation et de la plateforme, qui entrent déjà au bureau sans code (hasEntryRight),
        // gèrent aussi les droits d'accès : sinon le propriétaire d'une communauté sans le droit « attribution des accès »
        // se voit refuser sa propre page de droits. Une session invitée (code) ne gère jamais.
        if ($guest || $uid < 1 || $tenantId < 1) {
            return false;
        }

        return $this->isPlatformAdmin($uid, $tenantId) || $this->permissionOf($uid, $tenantId, 'admin.organization');
    }

    /**
     * Le compte peut-il entrer au portail (droit d'entrer sans code) ? Les mêmes droits que SseAccessCodeService :
     * accès SSE, attribution des accès, administration. La confidentialité de session n'est pas jugée ici.
     */
    public function hasEntryRight(int $userId, int $tenantId): bool
    {
        if ($userId < 1 || $tenantId < 1) {
            return false;
        }
        if ($this->isPlatformAdmin($userId, $tenantId)) {
            return true;
        }
        foreach (['atak.sse.grant', 'admin.access', 'admin.organization', 'atak.sse.access'] as $slug) {
            if ($this->permissionOf($userId, $tenantId, $slug)) {
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------------ Gardes d'origine

    /** Garde d'origine d'un profil du catalogue, pour un compte. */
    public function legacyAllows(int $userId, int $tenantId, string $profile, bool $guest, string $mode = self::DOOR_SESSION): bool
    {
        if (!isset(C::PROFILES[$profile]) || $profile === 'none' || $profile === 'sealed') {
            // « sealed » : aucune garde d'origine n'ouvre (ni gestionnaire SSE, ni administrateur) ; seule une règle d'accord le fait.
            return false;
        }
        if ($profile === 'entrant') {
            return $this->doorOpen($userId > 0 ? $userId : null, $tenantId, $mode);
        }
        $def = C::PROFILES[$profile];
        if ($def['any'] === []) {
            // « Tout compte identifié » : un compte, jamais une session invitée par code.
            return !$guest && $userId > 0;
        }
        if ($def['account'] && $guest) {
            return false;
        }
        foreach ($def['any'] as $slug) {
            if ($this->permissionOf($userId, $tenantId, $slug)) {
                return true;
            }
        }

        return false;
    }

    private function permissionOf(int $userId, int $tenantId, string $slug): bool
    {
        if ($this->permission !== null) {
            return (bool) ($this->permission)($userId, $tenantId, $slug);
        }
        // Sans compte précis (appel de la requête en cours) ou pour le compte de la session : les droits chargés dans la porte.
        if ($userId < 1 || $this->isSessionUser($userId, $tenantId)) {
            return Gate::getInstance()->allows($slug);
        }

        return $this->effectiveFor($userId, $tenantId)?->allows($slug) ?? false;
    }

    private function isPlatformAdmin(int $userId, int $tenantId): bool
    {
        if ($this->platformAdmin !== null) {
            return (bool) ($this->platformAdmin)($userId, $tenantId);
        }
        if ($this->isSessionUser($userId, $tenantId)) {
            return Gate::getInstance()->isPlatformAdmin();
        }

        return $this->effectiveFor($userId, $tenantId)?->isPlatformAdmin() ?? false;
    }

    private function effectiveFor(int $userId, int $tenantId): ?EffectivePermissions
    {
        $key = $tenantId . ':' . $userId;
        if (!array_key_exists($key, $this->effective)) {
            try {
                $this->effective[$key] = (new EffectivePermissionsService())->forUser($userId, $tenantId);
            } catch (\Throwable) {
                $this->effective[$key] = null;
            }
        }

        return $this->effective[$key];
    }

    private function isSessionUser(int $userId, int $tenantId): bool
    {
        return $userId > 0 && $userId === (int) Session::get('user_id') && $tenantId === (int) Session::get('tenant_id');
    }

    private function sessionGuest(): bool
    {
        try {
            return (new SseAccessCodeService())->isGuest();
        } catch (\Throwable) {
            return false;
        }
    }

    /** @param array<string, mixed> $context */
    private function tenantOf(array $context): int
    {
        if (isset($context['tenant_id'])) {
            return (int) $context['tenant_id'];
        }
        $sse = (int) Session::get(SseAccessCodeService::SESSION_TENANT, 0);

        return $sse > 0 ? $sse : (int) Session::get('tenant_id');
    }

    /** Porte du portail : la même que les deux intergiciels (session active et confidentialité acceptée), ou le droit d'entrer seul. */
    private function doorOpen(?int $userId, int $tenantId, string $mode): bool
    {
        if ($this->door !== null) {
            return (bool) ($this->door)($userId, $tenantId, $mode);
        }
        if ($mode === self::DOOR_ENTRY) {
            return $userId !== null && $this->hasEntryRight($userId, $tenantId);
        }
        try {
            $sse = new SseAccessCodeService();

            return $sse->hasActiveClearance() && $sse->hasAcceptedConfidentiality() && $sse->tenantId() === $tenantId;
        } catch (\Throwable) {
            return false;
        }
    }

    // ------------------------------------------------------------------ Règles

    /**
     * Règles d'une section (non retirées, plus les règles simulées, moins celles écartées).
     *
     * @param list<array<string, mixed>> $extra
     * @param list<int> $drop
     * @return list<array<string, mixed>>
     */
    private function rulesFor(int $tenantId, string $section, array $extra, array $drop): array
    {
        if (!isset($this->bySection[$tenantId])) {
            $by = [];
            foreach ($this->rules()->liveRules($tenantId) as $r) {
                $by[(string) $r['section']][] = $r;
            }
            $this->bySection[$tenantId] = $by;
        }
        $rules = $this->bySection[$tenantId][$section] ?? [];
        if ($drop !== []) {
            $rules = array_values(array_filter($rules, static fn (array $r): bool => !in_array((int) $r['id'], $drop, true)));
        }
        foreach ($extra as $r) {
            if (is_array($r) && (string) ($r['section'] ?? '') === $section) {
                $rules[] = $r;
            }
        }

        return $rules;
    }

    /** @param array<string, mixed> $rule */
    private function isActive(array $rule, string $now): bool
    {
        $start = $rule['starts_at'] ?? null;
        $end = $rule['ends_at'] ?? null;
        if (is_string($start) && $start !== '' && $start > $now) {
            return false;
        }
        if (is_string($end) && $end !== '' && $end <= $now) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $rule
     * @param array{roles: list<int>, units: list<int>, tree: list<int>} $mine
     */
    private function matches(array $rule, int $tenantId, int $userId, array $mine): bool
    {
        $id = (int) $rule['subject_id'];

        return match ((string) $rule['subject_type']) {
            C::SUBJECT_USER => $id === $userId,
            C::SUBJECT_ROLE => in_array($id, $mine['roles'], true),
            C::SUBJECT_UNIT => in_array($id, $mine['units'], true),
            C::SUBJECT_UNIT_TREE => in_array($id, $mine['tree'], true),
            // Une position de service est un rôle d'affichage du membre ; la fonction et la qualification valide se lisent à part (une fois par membre).
            C::SUBJECT_POSITION => in_array($id, $mine['roles'], true),
            C::SUBJECT_JOB => in_array($id, $this->subjects()->facetsFor($tenantId, $userId)['jobs'], true),
            C::SUBJECT_QUALIF => in_array($id, $this->subjects()->facetsFor($tenantId, $userId)['qualifs'], true),
            default => false,
        };
    }
}
