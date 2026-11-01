<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Core\Database;
use App\Repositories\AcademyRepository;
use App\Repositories\CompetencyGradeRequirementRepository;
use App\Repositories\CompetencyLinkRepository;
use App\Repositories\CompetencyModuleRepository;
use App\Repositories\CompetencyPathwayRepository;
use PDO;

/**
 * Rassemble, pour une communauté, tout ce à quoi une compétence peut être reliée : référentiel des compétences
 * et ses liens, cours Academy, formations, modules ALPHA → DELTA, qualifications, emplois, dotation.
 *
 * Chaque source est lue séparément et tolère une table absente (module non installé) : elle est alors vide.
 * Le résultat alimente CompetencyPathwayGenerator et la page du référentiel.
 */
class CompetencyCatalogService
{
    private ?PDO $pdo = null;

    public function __construct(
        private CompetencyGradeRequirementRepository $requirements,
        private CompetencyLinkRepository $links,
        private CompetencyModuleRepository $modules,
        private CompetencyPathwayRepository $pathways,
    ) {}

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    /**
     * @param bool $staffLinks adresses du back-office (référentiel) plutôt que celles des membres (Academy)
     * @return array<string, list<array<string, mixed>>>
     */
    public function build(int $tenantId, bool $staffLinks, array $gradeLabels = []): array
    {
        $linksByRequirement = $this->links->listForTenant($tenantId);
        $competencies = [];
        foreach ($this->safe(fn (): array => $this->requirements->listForTenant($tenantId)) as $r) {
            $id = (int) $r['id'];
            $gradeId = (int) ($r['grade_id'] ?? 0);
            $competencies[] = [
                'id' => $id,
                'ref' => (string) $id,
                'label' => (string) $r['label'],
                'title' => (string) $r['label'],
                'palier' => (string) $r['palier'],
                'palier_order' => (int) ($r['palier_order'] ?? 0),
                'sort_order' => (int) ($r['sort_order'] ?? 0),
                'grade_id' => $gradeId,
                'grade_label' => $gradeId > 0 ? ($gradeLabels[$gradeId] ?? '') : '',
                'acquisition_level' => (string) ($r['acquisition_level'] ?? ''),
                'validation_mode' => (string) ($r['validation_mode'] ?? ''),
                'description' => trim((string) ($r['description'] ?? '')),
                'links' => $linksByRequirement[$id] ?? [],
            ];
        }

        return [
            'competencies' => $competencies,
            'academy_courses' => $this->academyCourses($tenantId, $staffLinks),
            'training_courses' => $this->trainingCourses($tenantId),
            'modules' => $this->competencyModules($tenantId, $staffLinks),
            'qualifications' => $this->qualifications($tenantId, $staffLinks),
            'job_roles' => $this->jobRoles($tenantId, $staffLinks),
            'equipment' => $staffLinks ? $this->equipment($tenantId) : [],
        ];
    }

    /**
     * Où chaque élément lié est utilisé ailleurs : postes ORBAT et membres d’un emploi, parcours enregistrés.
     *
     * @return array{billets_by_job_role: array<string, list<string>>, members_by_job_role: array<string, int>, pathways_by_competency: array<string, int>}
     */
    public function usage(int $tenantId): array
    {
        $billets = [];
        foreach ($this->safe(function () use ($tenantId): array {
            $st = $this->pdo()->prepare('SELECT job_role_id, title FROM orbat_billets WHERE tenant_id = ? AND is_active = 1 AND job_role_id IS NOT NULL ORDER BY title ASC');
            $st->execute([$tenantId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }) as $row) {
            $billets[(string) $row['job_role_id']][] = (string) $row['title'];
        }
        $members = [];
        foreach ($this->safe(function () use ($tenantId): array {
            $st = $this->pdo()->prepare('SELECT personnel_job_role_id, COUNT(DISTINCT user_id) AS n FROM personnel_profile_job_roles WHERE tenant_id = ? GROUP BY personnel_job_role_id');
            $st->execute([$tenantId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }) as $row) {
            $members[(string) $row['personnel_job_role_id']] = (int) $row['n'];
        }
        $pathways = [];
        foreach ($this->pathways->listForTenant($tenantId) as $p) {
            $refs = [];
            if (($p['target_kind'] ?? '') === 'competency') {
                $refs[(string) $p['target_ref']] = true;
            }
            foreach ($p['steps'] as $s) {
                if (($s['ref_type'] ?? '') === 'competency' && ($s['ref'] ?? '') !== '') {
                    $refs[(string) $s['ref']] = true;
                }
            }
            foreach (array_keys($refs) as $ref) {
                $pathways[(string) $ref] = ($pathways[(string) $ref] ?? 0) + 1;
            }
        }

        return ['billets_by_job_role' => $billets, 'members_by_job_role' => $members, 'pathways_by_competency' => $pathways];
    }

    /** @return list<array<string, mixed>> */
    private function academyCourses(int $tenantId, bool $staffLinks): array
    {
        $out = [];
        foreach ($this->safe(fn (): array => (new AcademyRepository($this->pdo()))->courses($tenantId)) as $c) {
            $key = (string) ($c['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $out[] = [
                'ref' => $key,
                'title' => self::text($c['title'] ?? $key),
                'summary' => self::text($c['summary'] ?? ''),
                'duration_min' => (int) ($c['duration_minutes'] ?? 0) ?: null,
                'url' => url(($staffLinks ? 'academy/studio?course=' : 'academy/classroom?course=') . rawurlencode($key)),
            ];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function trainingCourses(int $tenantId): array
    {
        $out = [];
        foreach ($this->safe(function () use ($tenantId): array {
            $st = $this->pdo()->prepare("SELECT id, title, slug, short_description FROM training_courses WHERE tenant_id = ? AND visibility = 'published' ORDER BY title ASC");
            $st->execute([$tenantId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }) as $row) {
            $out[] = [
                'ref' => (string) $row['id'],
                'title' => (string) $row['title'],
                'summary' => (string) ($row['short_description'] ?? ''),
                'duration_min' => null,
                'url' => url('academy/formations/' . rawurlencode((string) $row['slug'])),
            ];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function competencyModules(int $tenantId, bool $staffLinks): array
    {
        $out = [];
        foreach ($this->safe(fn (): array => $this->modules->listForTenant($tenantId)) as $m) {
            if (empty($m['tenant_active'])) {
                continue;
            }
            $out[] = [
                'ref' => (string) $m['id'],
                'code' => (string) ($m['code'] ?? ''),
                'title' => (string) ($m['name'] ?? $m['code'] ?? ''),
                'phase' => (string) ($m['module_type'] ?? 'ALPHA'),
                'prereq_refs' => array_map('strval', (array) ($m['prereq_ids'] ?? [])),
                'duration_min' => $m['duration_min'] ?? null,
                'url' => $staffLinks ? training_lms_admin_url('competences/modules') : url('academy/formations/competences'),
            ];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function qualifications(int $tenantId, bool $staffLinks): array
    {
        $rows = $this->safe(function () use ($tenantId): array {
            $st = $this->pdo()->prepare('SELECT id, code, name, category, level_rank, requires_exam FROM personnel_qualification_definitions WHERE tenant_id = ? AND is_active = 1 ORDER BY level_rank ASC, name ASC');
            $st->execute([$tenantId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        });
        $prereqs = [];
        foreach ($this->safe(function () use ($tenantId): array {
            $st = $this->pdo()->prepare("SELECT qualification_id, required_qualification_id FROM qualification_prerequisites WHERE tenant_id = ? AND requirement_type = 'obtention'");
            $st->execute([$tenantId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }) as $p) {
            $prereqs[(int) $p['qualification_id']][] = (string) $p['required_qualification_id'];
        }
        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $out[] = [
                'ref' => (string) $id,
                'code' => (string) $row['code'],
                'title' => (string) $row['name'],
                'category' => (string) ($row['category'] ?? ''),
                'prereq_refs' => $prereqs[$id] ?? [],
                'requires_exam' => (int) ($row['requires_exam'] ?? 0) === 1,
                'url' => $staffLinks ? url('back-office/referentiels/qualifications/' . $id . '/edit') : null,
            ];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function jobRoles(int $tenantId, bool $staffLinks): array
    {
        $out = [];
        foreach ($this->safe(function () use ($tenantId): array {
            $st = $this->pdo()->prepare('SELECT id, name, description FROM personnel_job_roles WHERE tenant_id = ? ORDER BY sort_order ASC, name ASC');
            $st->execute([$tenantId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }) as $row) {
            $out[] = [
                'ref' => (string) $row['id'],
                'title' => (string) $row['name'],
                'summary' => (string) ($row['description'] ?? ''),
                'url' => $staffLinks ? url('back-office/personnel-job-roles/roles/' . (int) $row['id'] . '/edit') : null,
            ];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function equipment(int $tenantId): array
    {
        $out = [];
        foreach ($this->safe(function () use ($tenantId): array {
            $st = $this->pdo()->prepare('SELECT id, name, category FROM equipment_item_definitions WHERE tenant_id = ? AND archived_at IS NULL ORDER BY category ASC, name ASC');
            $st->execute([$tenantId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }) as $row) {
            $out[] = [
                'ref' => (string) $row['id'],
                'title' => (string) $row['name'],
                'category' => (string) ($row['category'] ?? ''),
                'url' => url('back-office/referentiels/dotation/' . (int) $row['id']),
            ];
        }

        return $out;
    }

    /** Intitulé bilingue de l’Academy ({fr, en}) ou texte simple. */
    private static function text(mixed $value): string
    {
        if (is_array($value)) {
            return (string) ($value['fr'] ?? $value['en'] ?? reset($value) ?: '');
        }

        return (string) $value;
    }

    /**
     * @param callable(): list<array<string, mixed>> $read
     * @return list<array<string, mixed>>
     */
    private function safe(callable $read): array
    {
        try {
            return array_values($read());
        } catch (\Throwable) {
            return [];
        }
    }
}
