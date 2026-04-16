<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Core\Database;
use PDO;

/**
 * Parcours de montée en puissance (stages métier, distinct du simple RBAC).
 */
final class PedagogyPathwayService
{
    public const PATHWAY_DEFAULT = 'montee_en_puissance';

    /** @return list<array{slug: string, label: string, sort: int}> */
    public static function stageCatalog(): array
    {
        return [
            ['slug' => 'instructor_stagiaire', 'label' => 'Encadrant stagiaire', 'sort' => 10],
            ['slug' => 'instructor_valide', 'label' => 'Encadrant validé', 'sort' => 20],
            ['slug' => 'formateur_stagiaire', 'label' => 'Concepteur stagiaire', 'sort' => 30],
            ['slug' => 'formateur_valide', 'label' => 'Concepteur validé', 'sort' => 40],
            ['slug' => 'formateur_instructeurs', 'label' => 'Référent validation des encadrants', 'sort' => 50],
            ['slug' => 'formateur_formateurs', 'label' => 'Référent gouvernance des concepteurs', 'sort' => 60],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function pathwayRowsForUser(int $tenantId, int $userId): array
    {
        $pdo = Database::getPdo();
        $st = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_pedagogy_pathway' LIMIT 1");
        if (!$st || !$st->fetch()) {
            return [];
        }
        $q = $pdo->prepare(
            'SELECT * FROM user_pedagogy_pathway WHERE tenant_id = ? AND user_id = ? ORDER BY pathway_slug, stage_slug'
        );
        $q->execute([$tenantId, $userId]);

        return $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
