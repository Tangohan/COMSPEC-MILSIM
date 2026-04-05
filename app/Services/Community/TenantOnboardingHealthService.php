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
     * @return array{gaps: list<string>, needs_recovery: bool}
     */
    public function analyze(int $tenantId): array
    {
        $gaps = [];
        $settings = $this->tenantRepository->getSettings($tenantId);
        $ver = (int) ($settings['onboarding_wizard_version'] ?? 0);
        if ($ver < 2) {
            $gaps[] = 'L’assistant d’onboarding complet (version 2) n’a pas été enregistré pour cette communauté.';
        }
        if (trim((string) ($settings['grade_system_code'] ?? '')) === '') {
            $gaps[] = 'Aucun référentiel de grades n’est associé à la communauté (paramètre manquant).';
        }

        $pdo = Database::getPdo();
        $st = $pdo->prepare('SELECT COUNT(*) FROM units WHERE tenant_id = ? AND parent_id IS NULL');
        $st->execute([$tenantId]);
        $roots = (int) $st->fetchColumn();
        if ($roots < 1) {
            $gaps[] = 'Aucune unité racine dans l’ORBAT.';
        }

        $chk = $pdo->prepare(
            'SELECT 1 FROM roles r
             INNER JOIN role_permissions rp ON rp.role_id = r.id
             INNER JOIN permissions p ON p.id = rp.permission_id
             WHERE r.tenant_id = ? AND p.slug IN (\'admin.access\', \'admin.organization\')
             LIMIT 1'
        );
        $chk->execute([$tenantId]);
        if (!$chk->fetchColumn()) {
            $gaps[] = 'Aucun rôle ne dispose d’un accès d’administration (permissions manquantes).';
        }

        return [
            'gaps' => $gaps,
            'needs_recovery' => $gaps !== [],
        ];
    }

    /**
     * Applique des valeurs par défaut non destructives : grade FR + arbre ORBAT minimal si besoin.
     */
    public function applyFrDefaults(int $tenantId): void
    {
        $settings = $this->tenantRepository->getSettings($tenantId);
        if (trim((string) ($settings['grade_system_code'] ?? '')) === '') {
            $this->tenantRepository->mergeSettings($tenantId, [
                'grade_system_code' => 'FR_CLASSIC',
            ]);
        }

        $pdo = Database::getPdo();
        $st = $pdo->prepare('SELECT COUNT(*) FROM units WHERE tenant_id = ? AND parent_id IS NULL');
        $st->execute([$tenantId]);
        if ((int) $st->fetchColumn() > 0) {
            return;
        }

        $units = [
            ['key' => 'g1', 'parent_key' => '', 'name' => 'État-major', 'slug' => 'etat-major', 'type' => 'group', 'display_order' => 0],
            ['key' => 's1', 'parent_key' => 'g1', 'name' => '1re section', 'slug' => '1re-section', 'type' => 'section', 'display_order' => 0],
            ['key' => 't1', 'parent_key' => 's1', 'name' => '1re équipe', 'slug' => '1re-equipe', 'type' => 'team', 'display_order' => 0],
        ];
        $this->insertUnitsTree($pdo, $tenantId, $units);
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
                throw new \RuntimeException('Hiérarchie d’unités invalide.');
            }
            $remaining = $next;
        }
    }
}
