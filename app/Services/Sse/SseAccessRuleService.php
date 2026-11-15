<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseAccessRuleRepository;
use App\Repositories\SseAuditLogRepository;
use App\Support\SseAccessCatalog as C;

/**
 * Écriture des règles de droits d'accès SSE : validation stricte, garde-fous, historique et journal d'audit.
 *
 * Garde-fous (toutes les écritures passent par ici) :
 *  - seul le droit de gestion SSE existant (SseAccessPolicy::canManageRights) écrit, jamais une règle ;
 *  - pas d'escalade : on n'accorde pas un droit qu'on n'a pas soi-même (et on ne lève pas un refus sur un niveau qu'on n'a pas) ;
 *  - le bénéficiaire (niveau d'accès, unité, membre) appartient à la communauté, le membre est visible dans l'annuaire ;
 *  - motif obligatoire pour une dérogation individuelle, un refus ou le niveau « gérer » ;
 *  - un refus ne peut pas retirer la lecture d'une section à tous les gestionnaires SSE (pas de verrouillage total) ;
 *  - plafond de règles actives ; une règle identique n'est jamais doublée.
 * Chaque changement est écrit dans sse_access_rule_history et dans le journal d'audit SSE (qui, quand, quoi, motif).
 */
class SseAccessRuleService
{
    private const NOTHING_TO_CREATE = 'Aucune règle à créer : tout est déjà en place ou hors de vos droits.';

    /** @var \Closure(array<string, mixed>): void|null */
    private ?\Closure $audit;

    /**
     * @param \Closure(array<string, mixed>): void|null $audit écrit une ligne du journal d'audit SSE (défaut : SseAuditLogRepository)
     */
    public function __construct(
        private ?SseAccessRuleRepository $rules = null,
        private ?SseAccessSubjects $subjects = null,
        private ?SseAccessPolicy $policy = null,
        private ?SseAccessDirectory $directory = null,
        ?\Closure $audit = null,
    ) {
        $this->audit = $audit;
    }

    private function rules(): SseAccessRuleRepository
    {
        return $this->rules ??= $this->policy()->rules();
    }

    private function subjects(): SseAccessSubjects
    {
        return $this->subjects ??= $this->policy()->subjects();
    }

    private function policy(): SseAccessPolicy
    {
        return $this->policy ??= SseAccessPolicy::forSession();
    }

    private function directory(): SseAccessDirectory
    {
        return $this->directory ??= new SseAccessDirectory();
    }

    // ------------------------------------------------------------------ Validation (sans écriture)

    /**
     * Règles qu'une saisie créerait, ou les erreurs qui l'en empêchent. N'écrit rien.
     *
     * Saisie : section, levels (liste), subject_type, subject_id, effect, starts_at, ends_at, reason, source.
     *
     * @param array<string, mixed> $input
     * @return array{rules: list<array<string, mixed>>, errors: list<string>, skipped: list<string>}
     */
    public function plan(int $tenantId, int $actorId, array $input): array
    {
        $errors = [];
        $skipped = [];
        $rules = [];
        $ctx = ['tenant_id' => $tenantId, 'guest' => false];
        if ($tenantId < 1 || $actorId < 1 || !$this->policy()->canManageRights($actorId, $ctx)) {
            return ['rules' => [], 'errors' => ['Seul le droit de gestion SSE permet de modifier les droits d’accès.'], 'skipped' => []];
        }
        if (!$this->rules()->ready()) {
            return ['rules' => [], 'errors' => ['Les droits d’accès SSE ne sont pas encore installés dans cette communauté (migration en attente).'], 'skipped' => []];
        }

        $section = (string) ($input['section'] ?? '');
        // Une section dont on ne voit pas l'existence n'existe pas pour soi : aucune règle ne s'y pose.
        if (!C::hasSection($section) || !$this->actorHolds($actorId, $tenantId, $section, C::LEVEL_EXISTS)) {
            $errors[] = 'Section inconnue.';
        }
        $type = (string) ($input['subject_type'] ?? '');
        $subjectId = (int) ($input['subject_id'] ?? 0);
        $label = null;
        if (!isset(C::SUBJECT_TYPES[$type]) || $subjectId < 1) {
            $errors[] = 'Bénéficiaire inconnu.';
        } else {
            $label = $this->subjects()->label($tenantId, $type, $subjectId);
            if ($label === null || ($type === C::SUBJECT_USER && $this->directory()->member($tenantId, $subjectId) === null)) {
                $errors[] = 'Bénéficiaire inconnu.';
            }
        }
        $effect = (string) ($input['effect'] ?? '');
        if (!isset(C::EFFECTS[$effect])) {
            $errors[] = 'Effet inconnu : choisissez Accorder ou Refuser.';
        }
        $levels = [];
        foreach ((array) ($input['levels'] ?? []) as $lv) {
            $lv = (string) $lv;
            if (!C::hasLevel($lv)) {
                $errors[] = 'Niveau de droit inconnu.';
                continue;
            }
            $levels[$lv] = true;
        }
        if ($levels === [] && !in_array('Niveau de droit inconnu.', $errors, true)) {
            $errors[] = 'Choisissez au moins un niveau de droit.';
        }
        [$start, $end, $dateError] = $this->dates((string) ($input['starts_at'] ?? ''), (string) ($input['ends_at'] ?? ''));
        if ($dateError !== null) {
            $errors[] = $dateError;
        }
        $reason = trim(preg_replace('/\s+/u', ' ', (string) ($input['reason'] ?? '')) ?? '');
        $reason = mb_substr($reason, 0, C::REASON_MAX);
        $needsReason = $type === C::SUBJECT_USER || $effect === C::EFFECT_DENY || isset($levels[C::LEVEL_MANAGE]);
        if ($needsReason && mb_strlen($reason) < C::REASON_MIN) {
            $errors[] = 'Un motif est obligatoire pour une dérogation individuelle, un refus ou le niveau « Gérer » (au moins 5 caractères).';
        }
        if ($errors !== []) {
            return ['rules' => [], 'errors' => array_values(array_unique($errors)), 'skipped' => []];
        }

        $live = $this->rules()->liveRules($tenantId);
        $room = C::MAX_ACTIVE_RULES - count($live);
        foreach (array_keys($levels) as $lv) {
            if (!C::applies($section, $lv)) {
                $skipped[] = C::sectionLabel($section) . ' : « ' . C::levelLabel($lv) . ' » est sans objet pour cette section.';
                continue;
            }
            if ($effect === C::EFFECT_ALLOW && !$this->actorHolds($actorId, $tenantId, $section, $lv)) {
                $skipped[] = C::sectionLabel($section) . ' : vous ne possédez pas « ' . C::levelLabel($lv) . ' », vous ne pouvez pas l’accorder.';
                continue;
            }
            $rule = [
                'id' => 0,
                'tenant_id' => $tenantId,
                'section' => $section,
                'level' => $lv,
                'subject_type' => $type,
                'subject_id' => $subjectId,
                'subject_label' => (string) $label,
                'effect' => $effect,
                'starts_at' => $start,
                'ends_at' => $end,
                'reason' => $reason,
                'source' => (string) ($input['source'] ?? ''),
            ];
            if ($this->duplicate($live, $rule)) {
                $skipped[] = C::sectionLabel($section) . ' : « ' . C::levelLabel($lv) . ' » est déjà réglé ainsi pour ce bénéficiaire.';
                continue;
            }
            $rules[] = $rule;
        }
        if (count($rules) > $room) {
            return ['rules' => [], 'errors' => ['Trop de règles actives dans cette communauté (' . C::MAX_ACTIVE_RULES . ' au plus) : retirez-en avant d’en ajouter.'], 'skipped' => $skipped];
        }
        $lockout = $this->lockout($tenantId, $actorId, $rules);
        if ($lockout !== null) {
            return ['rules' => [], 'errors' => [$lockout], 'skipped' => $skipped];
        }
        if ($rules === [] && $skipped === []) {
            $errors[] = 'Aucune règle à créer.';
        }

        return ['rules' => $rules, 'errors' => $errors, 'skipped' => $skipped];
    }

    /**
     * Planifie un modèle sur un bénéficiaire : une saisie par section du modèle (la validation est celle d'une saisie).
     *
     * @param array<string, mixed> $options subject_type, subject_id, starts_at, ends_at, reason, restrict_rest
     * @return array{rules: list<array<string, mixed>>, errors: list<string>, skipped: list<string>}
     */
    public function planTemplate(int $tenantId, int $actorId, string $template, array $options): array
    {
        $def = C::TEMPLATES[$template] ?? null;
        if ($def === null) {
            return ['rules' => [], 'errors' => ['Modèle inconnu.'], 'skipped' => []];
        }
        $sections = $def['sections'] ?? C::sectionKeys();
        $rules = [];
        $skipped = [];
        $errors = [];
        foreach ($sections as $section) {
            $base = [
                'section' => $section,
                'subject_type' => $options['subject_type'] ?? '',
                'subject_id' => $options['subject_id'] ?? 0,
                'starts_at' => $options['starts_at'] ?? '',
                'ends_at' => $options['ends_at'] ?? '',
                'reason' => $options['reason'] ?? '',
                'source' => $template,
            ];
            $steps = [$base + ['effect' => C::EFFECT_ALLOW, 'levels' => $def['levels']]];
            if (!empty($options['restrict_rest']) && $def['deny'] !== []) {
                $steps[] = $base + ['effect' => C::EFFECT_DENY, 'levels' => $def['deny']];
            }
            foreach ($steps as $step) {
                $plan = $this->plan($tenantId, $actorId, $step);
                $rules = array_merge($rules, $plan['rules']);
                $skipped = array_merge($skipped, $plan['skipped']);
                $errors = array_merge($errors, $plan['errors']);
            }
        }
        $errors = array_values(array_unique($errors));
        if ($errors !== []) {
            // Une erreur de saisie (bénéficiaire, dates, motif) ou de droit s'applique à toutes les sections : rien n'est créé.
            return ['rules' => [], 'errors' => $errors, 'skipped' => array_values(array_unique($skipped))];
        }

        return ['rules' => $rules, 'errors' => $rules === [] ? ['Aucune règle à créer : tout est déjà en place ou hors de vos droits.'] : [], 'skipped' => array_values(array_unique($skipped))];
    }

    /**
     * Accord ou refus « simple » : un bénéficiaire (position, fonction, qualification, identité, unité…), un accès (voir,
     * écrire, gérer) et une ou plusieurs sections. Chaque section passe par plan() : mêmes garde-fous (pas d'escalade,
     * bénéficiaire de la communauté, motif, dernier gestionnaire protégé, doublons, plafond).
     *
     * Saisie : sections (liste ; « * » pour toutes), access (voir|ecrire|gerer), subject_type, subject_id, effect,
     * starts_at, ends_at, reason, source.
     *
     * @param array<string, mixed> $input
     * @return array{rules: list<array<string, mixed>>, errors: list<string>, skipped: list<string>}
     */
    public function planSimple(int $tenantId, int $actorId, array $input): array
    {
        $access = (string) ($input['access'] ?? '');
        if (!isset(C::SIMPLE_ACCESS[$access])) {
            return ['rules' => [], 'errors' => ['Choisissez ce que la personne peut faire : voir, écrire ou gérer.'], 'skipped' => []];
        }
        $effect = (string) ($input['effect'] ?? C::EFFECT_ALLOW);
        $levels = $effect === C::EFFECT_DENY ? C::simpleDenyLevels($access) : C::SIMPLE_ACCESS[$access]['grant'];
        $wanted = array_values(array_filter(array_map('strval', (array) ($input['sections'] ?? [])), static fn (string $s): bool => $s !== ''));
        if ($wanted === []) {
            return ['rules' => [], 'errors' => ['Choisissez au moins une section.'], 'skipped' => []];
        }
        if (in_array('*', $wanted, true)) {
            $wanted = C::sectionKeys();
        }
        $rules = [];
        $skipped = [];
        $errors = [];
        foreach (array_values(array_unique($wanted)) as $section) {
            if (!C::hasSection($section)) {
                $errors[] = 'Section inconnue.';
                continue;
            }
            if (!$this->actorHolds($actorId, $tenantId, $section, C::LEVEL_EXISTS)) {
                $skipped[] = C::sectionLabel($section) . ' : vous n’avez pas accès à cette section, elle n’est pas modifiée.';
                continue;
            }
            $plan = $this->plan($tenantId, $actorId, [
                'section' => $section,
                'levels' => $levels,
                'subject_type' => $input['subject_type'] ?? '',
                'subject_id' => $input['subject_id'] ?? 0,
                'effect' => $effect,
                'starts_at' => $input['starts_at'] ?? '',
                'ends_at' => $input['ends_at'] ?? '',
                'reason' => $input['reason'] ?? '',
                'source' => (string) ($input['source'] ?? ''),
            ]);
            $rules = array_merge($rules, $plan['rules']);
            $skipped = array_merge($skipped, $plan['skipped']);
            $errors = array_merge($errors, array_values(array_diff($plan['errors'], [self::NOTHING_TO_CREATE])));
        }
        $errors = array_values(array_unique($errors));
        $skipped = array_values(array_unique($skipped));
        if ($errors !== []) {
            return ['rules' => [], 'errors' => $errors, 'skipped' => $skipped];
        }

        return ['rules' => $rules, 'errors' => $rules === [] ? ['Aucune règle à créer : tout est déjà en place ou hors de vos droits.'] : [], 'skipped' => $skipped];
    }

    /**
     * Modèle « en situation » (C::RECIPES) : une étape par bénéficiaire choisi (position, qualification…), chacune avec
     * son accès et ses sections. Le type de bénéficiaire de chaque étape doit être de ceux que le modèle prévoit.
     *
     * @param array<string, string> $refs référence « type:id » de chaque étape (index de l'étape)
     * @param array<string, mixed> $options starts_at, ends_at, reason
     * @return array{rules: list<array<string, mixed>>, errors: list<string>, skipped: list<string>}
     */
    public function planRecipe(int $tenantId, int $actorId, string $recipe, array $refs, array $options): array
    {
        $def = C::RECIPES[$recipe] ?? null;
        if ($def === null) {
            return ['rules' => [], 'errors' => ['Modèle inconnu.'], 'skipped' => []];
        }
        $rules = [];
        $skipped = [];
        $errors = [];
        foreach ($def['steps'] as $i => $step) {
            $ref = C::parseSubjectRef((string) ($refs[(string) $i] ?? ''));
            if ($ref === null || !in_array($ref[0], $step['kinds'], true)) {
                $errors[] = 'Choisissez un bénéficiaire pour « ' . $step['label'] . ' ».';
                continue;
            }
            $plan = $this->planSimple($tenantId, $actorId, [
                'sections' => $step['sections'] ?? ['*'],
                'access' => $step['access'],
                'subject_type' => $ref[0],
                'subject_id' => $ref[1],
                'effect' => C::EFFECT_ALLOW,
                'starts_at' => $options['starts_at'] ?? '',
                'ends_at' => $options['ends_at'] ?? '',
                'reason' => trim((string) ($options['reason'] ?? '')) !== '' ? $options['reason'] : 'Modèle ' . $def['label'],
                'source' => 'recipe:' . $recipe,
            ]);
            $rules = array_merge($rules, $plan['rules']);
            $skipped = array_merge($skipped, $plan['skipped']);
            // Une étape déjà en place ne bloque pas les autres : seul « rien du tout à créer » est signalé, à la fin.
            $errors = array_merge($errors, array_values(array_diff($plan['errors'], [self::NOTHING_TO_CREATE])));
        }
        $errors = array_values(array_unique($errors));
        if ($errors !== []) {
            return ['rules' => [], 'errors' => $errors, 'skipped' => array_values(array_unique($skipped))];
        }

        return ['rules' => $rules, 'errors' => $rules === [] ? ['Aucune règle à créer : tout est déjà en place ou hors de vos droits.'] : [], 'skipped' => array_values(array_unique($skipped))];
    }

    // ------------------------------------------------------------------ Écriture

    /**
     * Crée les règles d'un plan déjà validé par plan() (même appelant) et journalise.
     *
     * @param list<array<string, mixed>> $planned
     * @return list<int> identifiants créés
     */
    public function commit(int $tenantId, int $actorId, string $actorLabel, array $planned, string $action = 'create'): array
    {
        if ($tenantId < 1 || $actorId < 1 || $planned === [] || !$this->policy()->canManageRights($actorId, ['tenant_id' => $tenantId, 'guest' => false])) {
            return [];
        }
        $now = $this->policy()->now();
        $ids = [];
        foreach ($planned as $rule) {
            $id = $this->rules()->insert($tenantId, [
                'section' => (string) $rule['section'],
                'level' => (string) $rule['level'],
                'subject_type' => (string) $rule['subject_type'],
                'subject_id' => (int) $rule['subject_id'],
                'effect' => (string) $rule['effect'],
                'starts_at' => $rule['starts_at'] !== null && $rule['starts_at'] !== '' ? (string) $rule['starts_at'] : null,
                'ends_at' => $rule['ends_at'] !== null && $rule['ends_at'] !== '' ? (string) $rule['ends_at'] : null,
                'reason' => (string) $rule['reason'],
                'source' => (string) ($rule['source'] ?? ''),
            ], $actorId, $now);
            if ($id < 1) {
                continue;
            }
            $ids[] = $id;
            $this->rules()->addHistory($tenantId, $action, $actorId, $actorLabel, ['rule_id' => $id] + $rule, $now);
        }
        if ($ids !== []) {
            $this->policy()->reset();
            $first = $planned[0];
            $this->journal([
                'tenant_id' => $tenantId,
                'actor_user_id' => $actorId,
                'actor_label' => $actorLabel,
                'action' => $action === 'template' ? 'access_rule.template' : 'access_rule.create',
                'object_type' => 'access_rule',
                'object_id' => $ids[0],
                'reason' => (string) ($first['reason'] ?? ''),
                'after' => [
                    'rules' => count($ids),
                    'section' => $first['section'],
                    'effect' => $first['effect'],
                    'subject' => ($first['subject_type'] ?? '') . ':' . ($first['subject_id'] ?? 0),
                    'source' => $first['source'] ?? '',
                ],
            ]);
        }

        return $ids;
    }

    /**
     * Retire une règle de la communauté (elle reste en base et dans l'historique).
     *
     * @return array{ok: bool, message: string}
     */
    public function revoke(int $tenantId, int $actorId, string $actorLabel, int $ruleId, string $reason = ''): array
    {
        if ($tenantId < 1 || $actorId < 1 || !$this->policy()->canManageRights($actorId, ['tenant_id' => $tenantId, 'guest' => false])) {
            return ['ok' => false, 'message' => 'Seul le droit de gestion SSE permet de modifier les droits d’accès.'];
        }
        $rule = $this->rules()->find($tenantId, $ruleId);
        if ($rule === null || $rule['revoked_at'] !== null) {
            return ['ok' => false, 'message' => 'Règle introuvable ou déjà retirée.'];
        }
        // Lever un refus étend les droits : il faut posséder le niveau que le refus retirait.
        if ($rule['effect'] === C::EFFECT_DENY && !$this->actorHolds($actorId, $tenantId, (string) $rule['section'], (string) $rule['level'], $ruleId)) {
            return ['ok' => false, 'message' => 'Vous ne possédez pas ce droit : vous ne pouvez pas lever ce refus.'];
        }
        $reason = mb_substr(trim(preg_replace('/\s+/u', ' ', $reason) ?? ''), 0, C::REASON_MAX);
        $now = $this->policy()->now();
        if (!$this->rules()->revoke($tenantId, $ruleId, $actorId, $now)) {
            return ['ok' => false, 'message' => 'Règle introuvable ou déjà retirée.'];
        }
        $label = $this->subjects()->label($tenantId, (string) $rule['subject_type'], (int) $rule['subject_id']) ?? '';
        $this->rules()->addHistory($tenantId, 'revoke', $actorId, $actorLabel, [
            'rule_id' => $ruleId,
            'section' => $rule['section'],
            'level' => $rule['level'],
            'subject_type' => $rule['subject_type'],
            'subject_id' => $rule['subject_id'],
            'subject_label' => $label,
            'effect' => $rule['effect'],
            'starts_at' => $rule['starts_at'],
            'ends_at' => $rule['ends_at'],
            'reason' => $reason !== '' ? $reason : 'Retrait manuel',
        ], $now);
        $this->policy()->reset();
        $this->journal([
            'tenant_id' => $tenantId,
            'actor_user_id' => $actorId,
            'actor_label' => $actorLabel,
            'action' => 'access_rule.revoke',
            'object_type' => 'access_rule',
            'object_id' => $ruleId,
            'reason' => $reason !== '' ? $reason : 'Retrait manuel',
            'before' => ['section' => $rule['section'], 'level' => $rule['level'], 'effect' => $rule['effect'], 'subject' => $rule['subject_type'] . ':' . $rule['subject_id']],
        ]);

        return ['ok' => true, 'message' => 'Règle retirée.'];
    }

    // ------------------------------------------------------------------ Garde-fous

    /** Le compte possède-t-il lui-même ce niveau (sans compter la règle $ignoreRule) ? */
    private function actorHolds(int $actorId, int $tenantId, string $section, string $level, int $ignoreRule = 0): bool
    {
        $ctx = ['tenant_id' => $tenantId, 'guest' => false, 'door_mode' => SseAccessPolicy::DOOR_ENTRY];
        if ($ignoreRule > 0) {
            $ctx['drop_rules'] = [$ignoreRule];
        }

        return $this->policy()->allows($actorId, $section, $level, $ctx);
    }

    /**
     * Un refus ne doit pas retirer la lecture d'une section à tous les gestionnaires SSE.
     *
     * @param list<array<string, mixed>> $rules
     */
    private function lockout(int $tenantId, int $actorId, array $rules): ?string
    {
        $sections = [];
        foreach ($rules as $r) {
            if ($r['effect'] === C::EFFECT_DENY && in_array($r['level'], [C::LEVEL_EXISTS, C::LEVEL_READ], true)) {
                $sections[(string) $r['section']] = true;
            }
        }
        if ($sections === []) {
            return null;
        }
        $managers = $this->subjects()->holdersOfAnyPermission($tenantId, ['atak.sse.grant', 'admin.access']);
        $managers[] = $actorId;
        $managers = array_values(array_unique($managers));
        foreach (array_keys($sections) as $section) {
            $kept = false;
            foreach ($managers as $m) {
                if ($this->policy()->allows($m, $section, C::LEVEL_READ, ['tenant_id' => $tenantId, 'guest' => false, 'door_mode' => SseAccessPolicy::DOOR_ENTRY, 'extra_rules' => $rules])) {
                    $kept = true;
                    break;
                }
            }
            if (!$kept) {
                return 'Cette règle retirerait la lecture de « ' . C::sectionLabel($section) . ' » à tous les gestionnaires SSE : refusée pour ne pas verrouiller la section.';
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $live
     * @param array<string, mixed> $rule
     */
    private function duplicate(array $live, array $rule): bool
    {
        foreach ($live as $l) {
            if ($l['section'] === $rule['section'] && $l['level'] === $rule['level'] && $l['subject_type'] === $rule['subject_type']
                && (int) $l['subject_id'] === (int) $rule['subject_id'] && $l['effect'] === $rule['effect']
                && ($l['starts_at'] ?? null) === ($rule['starts_at'] ?? null) && ($l['ends_at'] ?? null) === ($rule['ends_at'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: ?string} début, fin (« Y-m-d H:i:s ») et message d'erreur
     */
    private function dates(string $startRaw, string $endRaw): array
    {
        $start = $this->parseDate($startRaw, false);
        $end = $this->parseDate($endRaw, true);
        if (trim($startRaw) !== '' && $start === null) {
            return [null, null, 'La date de début n’est pas valide.'];
        }
        if (trim($endRaw) !== '' && $end === null) {
            return [null, null, 'La date de fin n’est pas valide.'];
        }
        if ($start !== null && $end !== null && $end <= $start) {
            return [null, null, 'La date de fin doit suivre la date de début.'];
        }
        if ($end !== null && $end <= $this->policy()->now()) {
            return [null, null, 'La date de fin est déjà passée.'];
        }

        return [$start, $end, null];
    }

    private function parseDate(string $raw, bool $endOfDay): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m) === 1 && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return $raw . ($endOfDay ? ' 23:59:59' : ' 00:00:00');
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})$/', $raw, $m) === 1 && checkdate((int) $m[2], (int) $m[3], (int) $m[1])
            && (int) $m[4] < 24 && (int) $m[5] < 60) {
            return $m[1] . '-' . $m[2] . '-' . $m[3] . ' ' . $m[4] . ':' . $m[5] . ':00';
        }

        return null;
    }

    /** @param array<string, mixed> $row */
    private function journal(array $row): void
    {
        try {
            if ($this->audit !== null) {
                ($this->audit)($row);

                return;
            }
            (new SseAuditLogRepository())->write($row);
        } catch (\Throwable) {
            // Le journal d'audit ne doit pas défaire un changement déjà inscrit dans l'historique des règles.
        }
    }
}
