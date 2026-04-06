<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\PersonnelJobRoleRepository;
use PDO;

/**
 * Crée l’arborescence et les rôles métier par défaut pour un tenant (idempotent).
 */
final class PersonnelJobRoleBootstrapService
{
    public function __construct(
        private PersonnelJobRoleRepository $jobRoleRepository
    ) {}

    public function ensureDefaultsForTenant(PDO $pdo, int $tenantId): void
    {
        if (!$this->jobRoleRepository->tablesExist()) {
            return;
        }
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM personnel_job_roles WHERE tenant_id = ?');
        $stmt->execute([$tenantId]);
        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }

        $getCatId = static function (PDO $pdo, int $tenantId, string $slug) use (&$getCatId): int {
            $q = $pdo->prepare('SELECT id FROM personnel_job_role_categories WHERE tenant_id = ? AND slug = ? LIMIT 1');
            $q->execute([$tenantId, $slug]);
            $row = $q->fetch(PDO::FETCH_ASSOC);

            return $row ? (int) $row['id'] : 0;
        };

        $insCat = static function (PDO $pdo, int $tenantId, ?int $parentId, string $name, string $slug, int $order): int {
            $st = $pdo->prepare(
                'INSERT INTO personnel_job_role_categories (tenant_id, parent_id, name, slug, sort_order) VALUES (?, ?, ?, ?, ?)'
            );
            $st->execute([$tenantId, $parentId, $name, $slug, $order]);

            return (int) $pdo->lastInsertId();
        };

        $ensureCat = static function (PDO $pdo, int $tenantId, ?int $parentId, string $name, string $slug, int $order) use ($getCatId, $insCat): int {
            $id = $getCatId($pdo, $tenantId, $slug);
            if ($id > 0) {
                return $id;
            }

            return $insCat($pdo, $tenantId, $parentId, $name, $slug, $order);
        };

        $cCommand = $ensureCat($pdo, $tenantId, null, 'Commandement', 'cmd-root', 10);
        $cCombat = $ensureCat($pdo, $tenantId, null, 'Combat & appui', 'combat-root', 20);
        $cSoutien = $ensureCat($pdo, $tenantId, null, 'Soutien', 'soutien-root', 30);
        $cForm = $ensureCat($pdo, $tenantId, null, 'Formation & encadrement', 'formation-root', 40);

        $cEm = $ensureCat($pdo, $tenantId, $cCommand, 'État-major / Opérations', 'cmd-em', 1);
        $cInf = $ensureCat($pdo, $tenantId, $cCombat, 'Infanterie', 'combat-infanterie', 1);
        $cApp = $ensureCat($pdo, $tenantId, $cCombat, 'Appuis & feux', 'combat-appuis', 2);
        $cLog = $ensureCat($pdo, $tenantId, $cSoutien, 'Logistique', 'soutien-log', 1);
        $cFormDetail = $ensureCat($pdo, $tenantId, $cForm, 'Instruction', 'formation-inst', 1);

        $roles = [
            [$cEm, 'Officier opérations', 'officier-operations', 'Coordination des opérations et briefs.'],
            [$cEm, 'Chef de section', 'chef-de-section', 'Encadrement de section.'],
            [$cInf, 'Fusilier', 'fusilier', 'Combattant polyvalent.'],
            [$cInf, 'Grenadier', 'grenadier', 'Appui grenades / lourd léger.'],
            [$cApp, 'JTAC / FO', 'jtac', 'Guidage feu indirect.'],
            [$cApp, 'Medic / secouriste', 'medic', 'Soutien sanitaire.'],
            [$cLog, 'Logistique', 'logistique-r', 'Ravitaillement, transport.'],
            [$cFormDetail, 'Formateur', 'formateur', 'Pédagogie, montée en compétence.'],
            [$cFormDetail, 'Instructeur', 'instructeur', 'Instruction collective.'],
        ];

        $ins = $pdo->prepare(
            'INSERT INTO personnel_job_roles (tenant_id, category_id, name, slug, description, sort_order, is_system) VALUES (?, ?, ?, ?, ?, ?, 1)'
        );
        $sort = 0;
        foreach ($roles as $r) {
            [$cid, $name, $slug, $desc] = $r;
            if ($cid <= 0) {
                continue;
            }
            try {
                $ins->execute([$tenantId, $cid, $name, $slug, $desc, $sort++]);
            } catch (\Throwable) {
            }
        }
    }
}
