<?php

declare(strict_types=1);

namespace App\Services\Academy;

use App\Core\Database;
use App\Repositories\QualificationAwardRuleRepository;
use App\Repositories\QualificationDefinitionRepository;
use App\Repositories\QualificationIssuerRepository;
use App\Repositories\QualificationReferentielRepository;
use App\Services\Personnel\QualificationAwardRuleService;
use App\Services\Personnel\QualificationBadgeStorageService;
use App\Services\Personnel\QualificationCriteriaEngine;
use App\Services\Personnel\QualificationReferenceWriter;
use App\Support\QualificationCatalog\QualificationCatalog;
use App\Support\QualificationCriteriaCatalog as Cat;
use App\Support\QualificationDefinitionPayload;
use PDO;
use RuntimeException;

/**
 * Qualifications et brevets créés depuis le Studio de l’Academy. Aucune donnée propre : ce sont les fiches du
 * référentiel des qualifications de la communauté (QualificationDefinitionRepository, même enregistrement que le
 * back-office), leurs conditions d’obtention (QualificationAwardRuleService) et leur émetteur habituel.
 *
 * Un brevet est une qualification dont le type est « Brevet » (AcademyOffer::isBrevet) : il porte en plus un
 * gabarit de PDF, un format de numéro et un insigne. Un cours « délivre » une qualification quand la règle
 * d’obtention de celle-ci demande de réussir ce cours (critère {type: academy, course_key}) : le lien se règle des
 * deux côtés (fiche de la qualification, ou réglages du cours) et il suit les nouvelles versions du cours.
 */
final class AcademyStudioCredentials
{
    public const KINDS = ['qualification', 'brevet'];
    public const BREVET_TYPE_CODE = 'BREVET';
    public const BREVET_TYPE_NAME = 'Brevet';
    /** Lignes de conditions mesurées proposées par le formulaire (existantes + vides). */
    public const METRIC_SLOTS = 3;

    /** @var array<string, true>|null */
    private static ?array $catalogCodes = null;

    public function __construct(
        private QualificationDefinitionRepository $definitions,
        private QualificationReferentielRepository $referentiel,
        private QualificationAwardRuleService $rules,
        private QualificationAwardRuleRepository $ruleRows,
        private QualificationIssuerRepository $issuers,
        private QualificationBadgeStorageService $badges,
        private ?PDO $pdo = null,
    ) {
    }

    public function ready(): bool
    {
        return $this->definitions->schemaReady();
    }

    public function rulesReady(): bool
    {
        return $this->rules->schemaReady();
    }

    /* ——————————————————— Lecture ——————————————————— */

    /**
     * Fiches du référentiel de la communauté (archivées comprises), avec leur nature, leur origine et les cours qui y
     * mènent.
     *
     * @return list<array<string, mixed>>
     */
    public function list(int $tenantId): array
    {
        if (!$this->ready()) {
            return [];
        }
        $settings = $this->ruleRows->listForTenant($tenantId);
        $out = [];
        foreach ($this->definitions->listForTenant($tenantId, true) as $d) {
            $rule = QualificationCriteriaEngine::normalize($settings[(int) $d['id']]['rule_json'] ?? null);
            $out[] = $this->summary($d, $rule);
        }

        return $out;
    }

    /** @return array<string, mixed>|null fiche + réglages d’obtention + formulaire de conditions */
    public function find(int $tenantId, int $id): ?array
    {
        if ($id < 1 || !$this->ready()) {
            return null;
        }
        // La liste porte aussi le nombre de titulaires et les libellés de catégorie et de type.
        $d = null;
        foreach ($this->definitions->listForTenant($tenantId, true) as $row) {
            if ((int) $row['id'] === $id) {
                $d = $row;
                break;
            }
        }
        if ($d === null) {
            return null;
        }
        $settings = $this->rulesReady() ? $this->rules->settings($tenantId, $id) : QualificationAwardRuleService::defaults() + ['rule' => []];

        return $this->summary($d, $settings['rule']) + [
            'definition' => $d,
            'settings' => $settings,
            'conditions' => self::formFromRule($settings['rule']),
            'advanced' => self::isAdvanced($settings['rule']),
            'issuer_id' => (int) ($d['default_issuer_id'] ?? 0),
        ];
    }

    /**
     * Qualifications et brevets délivrés par un cours (ou par une version qu’il remplace).
     *
     * @param list<string> $olderKeys versions précédentes du cours
     * @return list<array<string, mixed>>
     */
    public function forCourse(int $tenantId, string $courseKey, array $olderKeys = []): array
    {
        $keys = array_merge([$courseKey], $olderKeys);

        return array_values(array_filter($this->list($tenantId), static fn (array $c): bool => empty($c['archived']) && array_intersect($keys, $c['courses']) !== []));
    }

    /** @param array<string, mixed> $d @param array<string, mixed> $rule @return array<string, mixed> */
    private function summary(array $d, array $rule): array
    {
        $path = trim((string) ($d['badge_media_path'] ?? ''));

        return [
            'id' => (int) $d['id'],
            'code' => (string) ($d['code'] ?? ''),
            'name' => (string) ($d['name'] ?? ''),
            'short' => (string) ($d['short_name'] ?? ''),
            'description' => trim((string) ($d['description'] ?? '')),
            'category' => trim((string) ($d['category_name'] ?? '')),
            'kind' => AcademyOffer::isBrevet($d) ? 'brevet' : 'qualification',
            'origin' => self::origin((string) ($d['code'] ?? '')),
            'badge' => $path !== '' ? $this->badges->publicUrl($path) : '',
            'holders' => (int) ($d['holders_count'] ?? 0),
            'archived' => !empty($d['archived_at']) || (isset($d['is_active']) && (int) $d['is_active'] === 0),
            'permanent' => !empty($d['is_permanent']) || ((int) ($d['default_validity_months'] ?? 0) === 0 && (int) ($d['validity_days'] ?? 0) === 0),
            'months' => (int) ($d['default_validity_months'] ?? 0),
            'courses' => self::coursesInRule($rule),
            'updated_at' => (string) ($d['updated_at'] ?? ''),
        ];
    }

    /**
     * D’où vient la fiche : créée par la communauté, importée du catalogue de référence (packs pays / OTAN) ou
     * délivrée par un cours intégré de l’Academy (code ACAD-…).
     */
    public static function origin(string $code): string
    {
        $code = strtoupper(trim($code));
        if (str_starts_with($code, 'ACAD-')) {
            return 'academy';
        }
        if (self::$catalogCodes === null) {
            self::$catalogCodes = [];
            try {
                foreach (QualificationCatalog::rawEntries() as $entry) {
                    $c = strtoupper(trim((string) ($entry['code'] ?? '')));
                    if ($c !== '') {
                        self::$catalogCodes[$c] = true;
                    }
                }
            } catch (\Throwable) {
            }
        }

        return isset(self::$catalogCodes[$code]) ? 'catalog' : 'community';
    }

    /** Tests : oublie les codes du catalogue lus. */
    public static function resetCatalogCache(): void
    {
        self::$catalogCodes = null;
    }

    /* ——————————————————— Règle d’obtention ↔ formulaire ——————————————————— */

    /** @param array<string, mixed> $rule @return list<string> cours (clé) demandés par la règle */
    public static function coursesInRule(array $rule): array
    {
        $keys = [];
        foreach (QualificationCriteriaEngine::criteria($rule) as $c) {
            if (($c['type'] ?? '') === 'academy' && isset($c['course_key'])) {
                $keys[] = (string) $c['course_key'];
            }
        }

        return array_values(array_unique($keys));
    }

    /** Règle à plusieurs blocs : le Studio ne la représente pas, elle se modifie dans le référentiel. */
    public static function isAdvanced(array $rule): bool
    {
        return count($rule['groups'] ?? []) > 1;
    }

    /**
     * Conditions du formulaire du Studio : un seul bloc (toutes / au moins une) de cours réussis, qualifications
     * détenues, seuils mesurés (heures de jeu, heures de vol par appareil…) et conditions attestées par un instructeur.
     *
     * @param array<string, mixed> $in op, courses[], qualifications[], metrics[{metric, scope, min, window_days}], attestations[{keep, key, label, min}]
     * @return array<string, mixed> règle normalisée (vide : aucune condition)
     */
    public static function ruleFromForm(array $in): array
    {
        $items = [];
        foreach (is_array($in['courses'] ?? null) ? $in['courses'] : [] as $key) {
            if (is_string($key) && preg_match('/\A[a-z0-9-]{1,64}\z/', $key) === 1) {
                $items[] = ['type' => 'academy', 'course_key' => $key];
            }
        }
        foreach (is_array($in['qualifications'] ?? null) ? $in['qualifications'] : [] as $id) {
            if (is_scalar($id) && (int) $id > 0) {
                $items[] = ['type' => 'qualification', 'definition_id' => (int) $id];
            }
        }
        foreach (is_array($in['metrics'] ?? null) ? $in['metrics'] : [] as $row) {
            if (is_array($row) && trim((string) ($row['min'] ?? '')) !== '' && Cat::isMetric((string) ($row['metric'] ?? ''))) {
                $items[] = ['type' => 'metric', 'metric' => (string) $row['metric'], 'scope' => (string) ($row['scope'] ?? ''),
                    'min' => (string) $row['min'], 'window_days' => (int) ($row['window_days'] ?? 0)];
            }
        }
        // Conditions attestées par un instructeur (ajoutées depuis « Prérequis détectés » ou le référentiel) : gardées
        // si leur case reste cochée.
        foreach (is_array($in['attestations'] ?? null) ? $in['attestations'] : [] as $row) {
            if (is_array($row) && !empty($row['keep']) && Cat::isAttestation((string) ($row['key'] ?? ''))) {
                $items[] = ['type' => 'attestation', 'key' => (string) $row['key'], 'label' => (string) ($row['label'] ?? ''), 'min' => (string) ($row['min'] ?? 0)];
            }
        }
        // Doublons (même cours coché deux fois…) : une seule fois chacun.
        $items = array_values(array_unique(array_map(static fn (array $i): string => (string) json_encode($i), $items)));
        $items = array_map(static fn (string $j): array => (array) json_decode($j, true), $items);

        return QualificationCriteriaEngine::normalize(['op' => 'all', 'groups' => [['op' => ($in['op'] ?? 'all') === 'any' ? 'any' : 'all', 'items' => $items]]]);
    }

    /**
     * @param array<string, mixed> $rule
     * @return array{op: string, courses: list<string>, qualifications: list<int>, metrics: list<array<string, mixed>>, attestations: list<array<string, mixed>>}
     */
    public static function formFromRule(array $rule): array
    {
        $out = ['op' => (string) ($rule['groups'][0]['op'] ?? 'all'), 'courses' => [], 'qualifications' => [], 'metrics' => [], 'attestations' => []];
        foreach (QualificationCriteriaEngine::criteria($rule) as $c) {
            $type = (string) ($c['type'] ?? 'metric');
            if ($type === 'academy' && isset($c['course_key'])) {
                $out['courses'][] = (string) $c['course_key'];
            } elseif ($type === 'qualification') {
                $out['qualifications'][] = (int) $c['definition_id'];
            } elseif ($type === 'metric') {
                $out['metrics'][] = $c;
            } elseif ($type === 'attestation') {
                $out['attestations'][] = $c;
            }
        }

        return $out;
    }

    /**
     * Règle complétée par « réussir ce cours » (dans son premier bloc) ; inchangée si le cours y figure déjà.
     *
     * @param array<string, mixed> $rule
     * @return array<string, mixed>
     */
    public static function withCourse(array $rule, string $courseKey): array
    {
        if (in_array($courseKey, self::coursesInRule($rule), true)) {
            return $rule;
        }
        if ($rule === []) {
            return QualificationCriteriaEngine::normalize(AcademyBuiltInQualifications::rule($courseKey));
        }
        $item = ['type' => 'academy', 'course_key' => $courseKey];
        if (count($rule['groups'][0]['items']) < Cat::MAX_ITEMS) {
            $rule['groups'][0]['items'][] = $item;
        } else {
            $rule['groups'][] = ['op' => 'all', 'items' => [$item]];
        }

        return QualificationCriteriaEngine::normalize($rule);
    }

    /**
     * Règle sans le critère « réussir ce cours » (ses versions précédentes comprises).
     *
     * @param array<string, mixed> $rule
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    public static function withoutCourses(array $rule, array $keys): array
    {
        foreach ($rule['groups'] ?? [] as $gi => $group) {
            $rule['groups'][$gi]['items'] = array_values(array_filter($group['items'], static fn (array $c): bool => !(($c['type'] ?? '') === 'academy' && in_array((string) ($c['course_key'] ?? ''), $keys, true))));
        }
        $rule['groups'] = array_values(array_filter($rule['groups'] ?? [], static fn (array $g): bool => $g['items'] !== []));

        return QualificationCriteriaEngine::normalize($rule);
    }

    /* ——————————————————— Écriture ——————————————————— */

    /**
     * Crée ou modifie une qualification / un brevet. Les champs du référentiel absents du formulaire du Studio
     * gardent leur valeur ; les conditions remplacent la règle sauf si elle est avancée (plusieurs blocs).
     *
     * @param array<string, mixed> $in champs du formulaire (définition + issuer_id, category_new, conditions, auto_mode, auto_enabled)
     * @throws RuntimeException message = clé de traduction de l’Academy
     */
    public function save(int $tenantId, ?int $id, string $kind, array $in, ?int $actorId): int
    {
        if (!$this->ready()) {
            throw new RuntimeException('cred_unavailable');
        }
        $kind = in_array($kind, self::KINDS, true) ? $kind : 'qualification';
        $existing = $id !== null ? $this->definitions->find($tenantId, $id) : null;
        if ($id !== null && ($existing === null || !empty($existing['archived_at']))) {
            throw new RuntimeException('not_found');
        }
        $name = trim((string) ($in['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 150) {
            throw new RuntimeException('cred_name_required');
        }
        $sent = array_intersect_key($in, array_flip(QualificationDefinitionPayload::FIELDS));
        if ($existing === null) {
            $code = strtoupper(trim((string) ($in['code'] ?? '')));
            if ($code === '') {
                $code = self::codeFrom($name, $kind);
            }
            if (preg_match('/\A[A-Z0-9][A-Z0-9._-]{0,63}\z/', $code) !== 1) {
                throw new RuntimeException('cred_code_invalid');
            }
            if ($this->definitions->findByCode($tenantId, $code) !== null) {
                throw new RuntimeException('cred_code_taken');
            }
            $sent['code'] = $code;
        }
        // Cases à cocher du formulaire : décochées = absentes de la requête.
        foreach (['is_permanent', 'requires_exam', 'renewal_required', 'uses_levels'] as $box) {
            $sent[$box] = !empty($in[$box]) ? 1 : 0;
        }
        $categoryNew = trim((string) ($in['category_new'] ?? ''));
        if ($categoryNew !== '') {
            $sent['category_id'] = $this->writer()->ensureCategory($tenantId, mb_substr($categoryNew, 0, 150));
        }
        $brevetType = $kind === 'brevet' ? $this->brevetTypeId($tenantId) : 0;
        if ($brevetType > 0) {
            $sent['type_id'] = $brevetType;
        } elseif ($kind !== 'brevet' && $existing !== null && (int) ($existing['type_id'] ?? 0) > 0 && (int) $existing['type_id'] === $this->brevetTypeId($tenantId, false)) {
            // Un brevet repassé en simple qualification perd son type « Brevet ».
            $sent['type_id'] = null;
        }
        $payload = QualificationDefinitionPayload::merge($existing, $sent);
        if ($existing === null) {
            $id = $this->definitions->create($tenantId, $payload, $actorId);
        } else {
            $this->definitions->update($tenantId, (int) $id, $payload, $actorId);
        }
        $id = (int) $id;
        if (array_key_exists('issuer_id', $in)) {
            $issuer = (int) $in['issuer_id'];
            $known = array_map(static fn (array $i): int => (int) $i['id'], $this->issuers->listForTenant($tenantId));
            $this->issuers->setDefaultIssuer($tenantId, $id, in_array($issuer, $known, true) ? $issuer : null);
        }
        if (is_array($in['conditions'] ?? null) && $this->rulesReady()) {
            $current = $this->rules->settings($tenantId, $id);
            if (!self::isAdvanced($current['rule'])) {
                $this->saveRule($tenantId, $id, self::ruleFromForm($in['conditions']), $actorId, [
                    'auto_enabled' => !empty($in['auto_enabled']),
                    'auto_mode' => ($in['auto_mode'] ?? '') === Cat::AWARD_PROPOSE ? Cat::AWARD_PROPOSE : Cat::AWARD_DIRECT,
                ]);
            }
        }

        return $id;
    }

    /** Insigne du brevet (ou de la qualification) : même stockage que le back-office. @param array<string, mixed> $file */
    public function saveBadge(int $tenantId, int $id, array $file): void
    {
        $path = $this->badges->storeUpload($tenantId, $id, $file);
        $this->definitions->setBadgePath($tenantId, $id, $path);
    }

    public function archive(int $tenantId, int $id, ?int $actorId): bool
    {
        return $this->definitions->find($tenantId, $id) !== null && $this->definitions->archive($tenantId, $id, $actorId);
    }

    /** Le cours délivre cette qualification : « réussir le cours » s’ajoute à ses conditions. */
    public function linkCourse(int $tenantId, int $id, string $courseKey, ?int $actorId): void
    {
        if ($this->definitions->find($tenantId, $id) === null) {
            throw new RuntimeException('not_found');
        }
        if (!$this->rulesReady()) {
            throw new RuntimeException('cred_rules_pending');
        }
        $settings = $this->rules->settings($tenantId, $id);
        $this->saveRule($tenantId, $id, self::withCourse($settings['rule'], $courseKey), $actorId, ['auto_enabled' => true]);
    }

    /** @param list<string> $olderKeys versions précédentes du cours, retirées elles aussi */
    public function unlinkCourse(int $tenantId, int $id, string $courseKey, array $olderKeys, ?int $actorId): void
    {
        if ($this->definitions->find($tenantId, $id) === null || !$this->rulesReady()) {
            throw new RuntimeException('not_found');
        }
        $settings = $this->rules->settings($tenantId, $id);
        $this->saveRule($tenantId, $id, self::withoutCourses($settings['rule'], array_merge([$courseKey], $olderKeys)), $actorId, []);
    }

    /**
     * Cours terminé : les qualifications et brevets qu’il délivre sont évalués tout de suite (sans attendre la tâche
     * planifiée). Renvoie le nombre de fiches évaluées.
     *
     * @param list<string> $olderKeys
     * @param int $except fiche déjà évaluée par l’appelant (qualification d’un cours intégré)
     */
    public function awardForCourse(int $tenantId, string $courseKey, array $olderKeys = [], int $except = 0): int
    {
        $n = 0;
        foreach ($this->forCourse($tenantId, $courseKey, $olderKeys) as $c) {
            if ((int) $c['id'] === $except) {
                continue;
            }
            $this->rules->runAuto($tenantId, (int) $c['id']);
            $n++;
        }

        return $n;
    }

    /**
     * Réglages d’obtention avec une nouvelle règle : les autres réglages (prérequis de grade ou de fonction,
     * visibilité, délivrance à la main) restent ceux du référentiel.
     *
     * @param array<string, mixed> $rule
     * @param array{auto_enabled?: bool, auto_mode?: string} $override
     */
    private function saveRule(int $tenantId, int $id, array $rule, ?int $actorId, array $override): void
    {
        $s = $this->rules->settings($tenantId, $id);
        $auto = $rule !== [] && ($override['auto_enabled'] ?? (!$s['exists'] || !empty($s['auto_enabled'])));
        $this->rules->saveFromInput($tenantId, $id, [
            // Sans délivrance automatique, la qualification reste au moins délivrable à la main.
            'allow_manual' => $auto ? $s['allow_manual'] : 1,
            'auto_enabled' => $auto ? 1 : 0,
            'auto_mode' => $override['auto_mode'] ?? $s['auto_mode'],
            'rule_json' => $rule !== [] ? json_encode($rule, JSON_UNESCAPED_UNICODE) : '',
            'prereq_grade_id' => $s['prereq_grade_id'],
            'prereq_job_role_id' => $s['prereq_job_role_id'],
            'show_on_profile' => $s['show_on_profile'],
            'show_on_orbat' => $s['show_on_orbat'],
            'min_holders_per_unit' => $s['min_holders_per_unit'],
        ], $actorId);
    }

    /** Type « Brevet » du référentiel de la communauté, créé au premier brevet. */
    private function brevetTypeId(int $tenantId, bool $create = true): int
    {
        try {
            $types = $this->referentiel->listTypes($tenantId);
            foreach ($types as $type) {
                if (strtoupper((string) ($type['code'] ?? '')) === self::BREVET_TYPE_CODE) {
                    return (int) $type['id'];
                }
            }
            foreach ($types as $type) {
                if (preg_match('/^\s*brevet/iu', (string) ($type['name'] ?? '')) === 1) {
                    return (int) $type['id'];
                }
            }

            return $create ? $this->referentiel->createType($tenantId, self::BREVET_TYPE_NAME, self::BREVET_TYPE_CODE) : 0;
        } catch (\Throwable) {
            // Référentiel sans types : le brevet se reconnaît alors à son nom ou à son gabarit de PDF.
            return 0;
        }
    }

    /** Code proposé à partir du nom : initiales en majuscules (« Brevet de chef de groupe » → BRV-BDCDG-…). */
    public static function codeFrom(string $name, string $kind): string
    {
        $ascii = strtoupper((string) preg_replace('/[^A-Za-z0-9 ]+/', ' ', (string) (iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name)));
        $words = preg_split('/\s+/', trim($ascii), -1, PREG_SPLIT_NO_EMPTY) ?: ['X'];
        $initials = count($words) === 1 ? substr($words[0], 0, 8) : implode('', array_map(static fn (string $w): string => ctype_digit($w) ? $w : $w[0], array_slice($words, 0, 8)));

        return ($kind === 'brevet' ? 'BRV-' : 'QUAL-') . $initials . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 3));
    }

    private function writer(): QualificationReferenceWriter
    {
        return new QualificationReferenceWriter($this->pdo ??= Database::getPdo());
    }
}
