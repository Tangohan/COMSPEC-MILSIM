<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Core\Database;
use App\Repositories\TenantRepository;
use PDO;

/**
 * Détecte les écarts de configuration pour une communauté existante (rattrapage).
 */
final class TenantOnboardingHealthService
{
    private TenantRepository $tenantRepository;

    public function __construct(?TenantRepository $tenantRepository = null)
    {
        $this->tenantRepository = $tenantRepository ?? new TenantRepository();
    }

    /**
     * @return array{
     *     gaps: list<string>,
     *     needs_recovery: bool,
     *     checklist: list<array{
     *         id: string,
     *         label: string,
     *         detail: string,
     *         done: bool,
     *         auto_fixable: bool,
     *         action_href: string,
     *         action_label: string
     *     }>,
     *     progress: array{done: int, total: int, percent: int},
     *     can_auto_apply: bool,
     *     auto_apply_summary: string
     * }
     */
    public function analyze(int $tenantId): array
    {
        $settings = $this->tenantRepository->getSettings($tenantId);
        $ver = (int) ($settings['onboarding_wizard_version'] ?? 0);
        $gradeSystem = trim((string) ($settings['grade_system_code'] ?? ''));

        $pdo = Database::getPdo();
        $st = $pdo->prepare('SELECT COUNT(*) FROM units WHERE tenant_id = ? AND parent_id IS NULL');
        $st->execute([$tenantId]);
        $roots = (int) $st->fetchColumn();

        $chk = $pdo->prepare(
            'SELECT 1 FROM roles r
             INNER JOIN role_permissions rp ON rp.role_id = r.id
             INNER JOIN permissions p ON p.id = rp.permission_id
             WHERE r.tenant_id = ? AND p.slug IN (\'admin.access\', \'admin.organization\')
             LIMIT 1'
        );
        $chk->execute([$tenantId]);
        $hasAdminRole = (bool) $chk->fetchColumn();

        $checklist = [
            $this->checklistItem(
                'wizard',
                'Parcours de création enregistré',
                'Votre communauté a été créée avant le parcours guidé actuel ou celui-ci n’a pas encore été finalisé.',
                $ver >= 2,
                true,
                'back-office/configuration-initiale',
                'Ouvrir l’assistant de démarrage'
            ),
            $this->checklistItem(
                'grades',
                'Référentiel de grades',
                'Aucun référentiel de grades n’est encore associé à votre communauté.',
                $gradeSystem !== '',
                true,
                'back-office/referentiels/grades',
                'Consulter le référentiel des grades'
            ),
            $this->checklistItem(
                'orbat',
                'Structure des effectifs',
                'Il manque une unité racine dans l’organigramme (ORBAT).',
                $roots >= 1,
                true,
                'back-office/organisation-effectifs',
                'Ouvrir la structure des effectifs'
            ),
            $this->checklistItem(
                'admin_access',
                'Accès administration',
                'Aucun rôle ne permet encore de gérer le back-office de la communauté.',
                $hasAdminRole,
                false,
                'back-office/roles',
                'Gérer les rôles communautaires'
            ),
        ];

        $gaps = [];
        foreach ($checklist as $item) {
            if (!$item['done']) {
                $gaps[] = $item['detail'];
            }
        }

        $doneCount = count(array_filter($checklist, static fn (array $i): bool => $i['done']));
        $total = count($checklist);
        $canAutoApply = false;
        foreach ($checklist as $item) {
            if (!$item['done'] && $item['auto_fixable']) {
                $canAutoApply = true;
                break;
            }
        }

        return [
            'gaps' => $gaps,
            'needs_recovery' => $gaps !== [],
            'checklist' => $checklist,
            'progress' => [
                'done' => $doneCount,
                'total' => $total,
                'percent' => $total > 0 ? (int) round(($doneCount / $total) * 100) : 100,
            ],
            'can_auto_apply' => $canAutoApply,
            'auto_apply_summary' => 'Applique le référentiel français, une structure d’exemple (État-major → section → équipe) uniquement s’il n’existe aucune unité racine, et enregistre le parcours de création. Les données déjà présentes ne sont pas supprimées.',
        ];
    }

    /**
     * Applique des valeurs par défaut non destructives : grade FR + arbre ORBAT minimal si besoin.
     *
     * @return list<string> Résumé lisible des actions effectuées
     */
    public function applyFrDefaults(int $tenantId): array
    {
        $applied = [];
        $settings = $this->tenantRepository->getSettings($tenantId);
        if (trim((string) ($settings['grade_system_code'] ?? '')) === '') {
            $this->tenantRepository->mergeSettings($tenantId, [
                'grade_system_code' => 'FR_CLASSIC',
            ]);
            $applied[] = 'Référentiel de grades français associé à la communauté.';
        }

        $pdo = Database::getPdo();
        $st = $pdo->prepare('SELECT COUNT(*) FROM units WHERE tenant_id = ? AND parent_id IS NULL');
        $st->execute([$tenantId]);
        if ((int) $st->fetchColumn() > 0) {
            return $applied;
        }

        $units = [
            ['key' => 'g1', 'parent_key' => '', 'name' => 'État-major', 'slug' => 'etat-major', 'type' => 'group', 'display_order' => 0],
            ['key' => 's1', 'parent_key' => 'g1', 'name' => '1re section', 'slug' => '1re-section', 'type' => 'section', 'display_order' => 0],
            ['key' => 't1', 'parent_key' => 's1', 'name' => '1re équipe', 'slug' => '1re-equipe', 'type' => 'team', 'display_order' => 0],
        ];
        $this->insertUnitsTree($pdo, $tenantId, $units);
        $applied[] = 'Structure d’exemple créée (État-major, 1re section, 1re équipe).';

        return $applied;
    }

    /**
     * @return array{
     *     id: string,
     *     label: string,
     *     detail: string,
     *     done: bool,
     *     auto_fixable: bool,
     *     action_href: string,
     *     action_label: string
     * }
     */
    private function checklistItem(
        string $id,
        string $label,
        string $detail,
        bool $done,
        bool $autoFixable,
        string $actionPath,
        string $actionLabel
    ): array {
        return [
            'id' => $id,
            'label' => $label,
            'detail' => $detail,
            'done' => $done,
            'auto_fixable' => $autoFixable,
            'action_href' => url($actionPath),
            'action_label' => $actionLabel,
        ];
    }

    /** @param list<array{key: string, parent_key: string, name: string, slug: string, type: string, display_order: int}> $units */
    private function insertUnitsTree(PDO $pdo, int $tenantId, array $units): void
    {
        /** @var array<string, int> $keyToId */
        $keyToId = [];
        $remaining = $units;
        $guard = 0;
        while ($remaining !== [] && $guard++ < 1000) {
            $next = [];
            $progress = false;
            foreach ($remaining as $u) {
                $pk = $u['parent_key'] ?? '';
                $parentId = null;
                if ($pk !== '') {
                    if (!isset($keyToId[$pk])) {
                        $next[] = $u;

                        continue;
                    }
                    $parentId = $keyToId[$pk];
                }
                $ins = $pdo->prepare(
                    'INSERT INTO units (tenant_id, parent_id, name, slug, type, code, commander_user_id, display_order, updated_at) VALUES (?, ?, ?, ?, ?, NULL, NULL, ?, NOW())'
                );
                $ins->execute([$tenantId, $parentId, $u['name'], $u['slug'], $u['type'], (int) ($u['display_order'] ?? 0)]);
                $keyToId[$u['key']] = (int) $pdo->lastInsertId();
                $progress = true;
            }
            if (!$progress && $next !== []) {
                throw new \RuntimeException('Impossible de créer la structure d’unités : hiérarchie incohérente.');
            }
            $remaining = $next;
        }
    }
}
