<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Core\Database;
use App\Repositories\AcademyRepository;
use App\Repositories\GradeRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\QualificationDefinitionRepository;
use App\Repositories\UnitRepository;
use App\Services\OrganizationCatalog\RealUnitKitDefinitions;
use App\Support\OrbatMaskMode;
use App\Support\QualificationSuggestion\UnitNatureDetector;
use App\Support\VisibilityLevel;
use PDO;
use Throwable;

/**
 * Qualifications et brevets suggérés à un membre : rassemble ce que MemberQualificationSuggester croise (fonctions et
 * leurs prérequis, unité d’affectation et sa chaîne dans l’ORBAT, unité réelle du catalogue de modèles, grade,
 * qualifications détenues, cours Academy et inscriptions) et garde les suggestions ignorées par membre (table
 * member_qualification_suggestion_dismissals, étape 3895).
 *
 * Confidentialité : pour le membre lui-même (« Mes qualifications »), les unités parentes masquées ou restreintes de
 * l’ORBAT ne sont pas lues ; les raisons affichées ne citent jamais le nom d’une unité, seulement sa nature.
 */
final class MemberQualificationSuggestionService
{
    public const TABLE = 'member_qualification_suggestion_dismissals';
    public const KEY_PATTERN = '/\A(q:[a-z]{2,4}-[a-z0-9-]{2,60}|d:\d{1,10})\z/';

    private PDO $pdo;

    public function __construct(private QualificationAwardRuleService $rules, ?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    public function dismissReady(): bool
    {
        try {
            $this->pdo->query('SELECT 1 FROM ' . self::TABLE . ' LIMIT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param bool $selfView lecture par le membre lui-même : unités masquées de la chaîne ignorées
     * @return array<string, mixed> MemberQualificationSuggester::suggest() + unit_name, dismiss_ready
     */
    public function forMember(int $tenantId, int $userId, bool $selfView = false, string $lang = 'fr'): array
    {
        $snapshot = $this->rules->memberSnapshot($tenantId, $userId);
        [$texts, $country, $unitName] = $this->unitTexts($tenantId, $userId, (int) $snapshot['unit_id'], $selfView);
        if ($country === '') {
            // Pays de l’unité réelle de référence déclarée par la communauté, s’il y en a une.
            try {
                $aff = \App\Core\Container::get(\App\Repositories\TenantRepository::class)->getSettings($tenantId)['community']['unit_affiliation'] ?? null;
                if (is_array($aff) && !empty($aff['is_real'])) {
                    $country = strtoupper(trim((string) ($aff['country'] ?? '')));
                }
            } catch (Throwable) {
            }
        }
        [$jobs, $requirements] = $this->jobs($tenantId, $userId);
        $definitions = [];
        try {
            foreach ((new QualificationDefinitionRepository())->listForTenant($tenantId) as $d) {
                $definitions[(int) $d['id']] = $d;
            }
        } catch (Throwable) {
        }
        $names = [];
        try {
            $names = $this->rules->names($tenantId);
        } catch (Throwable) {
        }
        $enrolled = [];
        try {
            foreach ((new AcademyRepository($this->pdo))->states($tenantId, $userId) as $key => $state) {
                $enrolled[(string) $key] = !empty($state['completed']) ? 'completed' : 'enrolled';
            }
        } catch (Throwable) {
        }

        $result = MemberQualificationSuggester::suggest([
            'lang' => $lang,
            'jobs' => $jobs,
            'job_requirements' => $requirements,
            'unit_texts' => $texts,
            'country' => $country,
            'definitions' => $definitions,
            'held' => $snapshot['held'],
            'grade_nato' => $this->gradeNato($tenantId, (int) $snapshot['grade_id']),
            'dismissed' => $this->dismissedKeys($tenantId, $userId),
            'courses' => is_array($names['builtin'] ?? null) ? $names['builtin'] : [],
            'enrolled' => $enrolled,
        ], fn (int $defId): array => $this->rules->suggestionState($tenantId, $userId, $defId), fn (array $c): ?float => $this->rules->measureFor($tenantId, $userId, $c));

        $result['unit_name'] = $unitName;
        $result['nature_labels'] = array_map(static fn (string $n): string => UnitNatureDetector::label($n), array_keys(array_slice($result['natures'], 0, 3, true)));
        $result['dismiss_ready'] = $this->dismissReady();

        return $result;
    }

    /**
     * Textes de l’unité principale et de ses parents (nom, code, type, étiquettes), pondérés par la distance, plus
     * la lignée de l’unité réelle correspondante ; pays de cette unité réelle.
     *
     * @return array{0: list<array{text: string, weight: float}>, 1: string, 2: string}
     */
    private function unitTexts(int $tenantId, int $userId, int $unitId, bool $selfView): array
    {
        $units = [];
        try {
            $repo = new UnitRepository($this->pdo);
            foreach ($repo->allForTenant($tenantId) as $u) {
                $units[(int) $u['id']] = $u;
            }
            if ($unitId < 1 || !isset($units[$unitId])) {
                $unitId = (int) ($repo->unitIdsForUser($tenantId, $userId)[0] ?? 0);
            }
        } catch (Throwable) {
            return [[], '', ''];
        }
        $texts = [];
        $country = '';
        $depth = 0;
        $seen = [];
        $id = $unitId;
        $ownName = '';
        while ($id > 0 && isset($units[$id]) && !isset($seen[$id]) && $depth < 8) {
            $seen[$id] = true;
            $u = $units[$id];
            $masked = OrbatMaskMode::normalize((string) ($u['orbat_mask_mode'] ?? '')) !== OrbatMaskMode::NONE
                || in_array(VisibilityLevel::normalize((string) ($u['visibility_level'] ?? '')), [VisibilityLevel::RESTRICTED, VisibilityLevel::HIDDEN], true);
            if ($depth === 0) {
                $ownName = (string) ($u['name'] ?? '');
            }
            if (!($selfView && $masked && $depth > 0)) {
                $w = UnitNatureDetector::weight($depth);
                foreach (['name', 'code', 'type', 'public_tags', 'echelon'] as $col) {
                    $v = trim((string) ($u[$col] ?? ''));
                    if ($v !== '') {
                        $texts[] = ['text' => $v, 'weight' => $w];
                    }
                }
                $slug = (string) ($u['slug'] ?? '');
                $node = RealUnitKitDefinitions::findNode($slug, (string) ($u['name'] ?? ''), (string) ($u['code'] ?? ''));
                if ($node !== null) {
                    foreach (RealUnitKitDefinitions::lineage($node) as $i => $line) {
                        $texts[] = ['text' => $line, 'weight' => UnitNatureDetector::weight($depth + $i)];
                    }
                    if ($country === '') {
                        $country = RealUnitKitDefinitions::countryOf($node);
                    }
                }
            }
            $id = (int) ($u['parent_id'] ?? 0);
            $depth++;
        }

        return [$texts, $country, $ownName];
    }

    /** @return array{0: list<array<string, mixed>>, 1: array<int, list<int>>} fonctions du membre, qualifications exigées par fonction */
    private function jobs(int $tenantId, int $userId): array
    {
        $jobs = [];
        $requirements = [];
        try {
            $repo = new PersonnelJobRoleRepository();
            if (!$repo->tablesExist()) {
                return [[], []];
            }
            $rows = $repo->listPivotAssignmentsForUsers($tenantId, [$userId])[$userId] ?? [];
            $deco = (new JobRoleEnrichmentService($this->pdo))->decorations($tenantId);
            $roleIds = [];
            foreach ($rows as $r) {
                $rid = (int) ($r['personnel_job_role_id'] ?? 0);
                $roleIds[] = $rid;
                $jobs[] = [
                    'role_id' => $rid,
                    'name' => trim((string) ($r['role_name'] ?? '')),
                    'label_en' => trim((string) ($r['role_label_en'] ?? '')),
                    'detail' => trim((string) ($r['role_detail'] ?? '')),
                    'abbreviation' => (string) ($deco[$rid]['abbreviation'] ?? ''),
                ];
            }
            $prereq = new JobPrerequisiteService($this->pdo);
            foreach ($prereq->requirementsByRole($tenantId, $roleIds) as $rid => $items) {
                foreach ($items as $item) {
                    if (($item['type'] ?? '') === 'qualification' && (int) ($item['definition_id'] ?? 0) > 0) {
                        $requirements[(int) $rid][] = (int) $item['definition_id'];
                    }
                }
            }
        } catch (Throwable) {
        }

        return [$jobs, $requirements];
    }

    private function gradeNato(int $tenantId, int $gradeId): string
    {
        if ($gradeId < 1) {
            return '';
        }
        try {
            foreach ((new GradeRepository())->listForTenant($tenantId) as $g) {
                if ((int) $g['id'] === $gradeId) {
                    return strtoupper(str_replace(' ', '', (string) ($g['label_otan'] ?? '')));
                }
            }
        } catch (Throwable) {
        }

        return '';
    }

    /** @return array<string, true> */
    public function dismissedKeys(int $tenantId, int $userId): array
    {
        try {
            $st = $this->pdo->prepare('SELECT suggestion_key FROM ' . self::TABLE . ' WHERE tenant_id = ? AND user_id = ?');
            $st->execute([$tenantId, $userId]);

            return array_fill_keys(array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []), true);
        } catch (Throwable) {
            return [];
        }
    }

    /** Ignore (ou reprend, $restore) une suggestion pour ce membre. */
    public function dismiss(int $tenantId, int $userId, string $key, int $actorId, bool $restore = false): bool
    {
        if ($tenantId < 1 || $userId < 1 || preg_match(self::KEY_PATTERN, $key) !== 1 || !$this->dismissReady()) {
            return false;
        }
        if ($restore) {
            $this->pdo->prepare('DELETE FROM ' . self::TABLE . ' WHERE tenant_id = ? AND user_id = ? AND suggestion_key = ?')->execute([$tenantId, $userId, $key]);

            return true;
        }
        $exists = $this->pdo->prepare('SELECT 1 FROM ' . self::TABLE . ' WHERE tenant_id = ? AND user_id = ? AND suggestion_key = ?');
        $exists->execute([$tenantId, $userId, $key]);
        if ($exists->fetchColumn() === false) {
            $this->pdo->prepare('INSERT INTO ' . self::TABLE . ' (tenant_id, user_id, suggestion_key, dismissed_by, dismissed_at) VALUES (?, ?, ?, ?, ?)')
                ->execute([$tenantId, $userId, $key, $actorId > 0 ? $actorId : null, date('Y-m-d H:i:s')]);
        }

        return true;
    }
}
