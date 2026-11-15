<?php

declare(strict_types=1);

namespace App\Services\Jnet;

/**
 * Stockage des épingles de l'espace commun (JnetPinRepository en production, une version en mémoire dans les tests).
 * Tolère l'absence de la table : ready() vaut false, les lectures sont vides.
 */
interface JnetPinStore
{
    public function ready(): bool;

    /**
     * Épingles de la communauté, de la première à la dernière.
     *
     * @return list<array{id: int, target_type: string, target_id: string, position: int, pinned_by: int, pinned_at: string}>
     */
    public function all(int $tenantId): array;

    /** Ajoute en dernière position ; retourne l'identifiant, ou 0 si l'élément est déjà épinglé. */
    public function add(int $tenantId, string $type, string $id, int $userId): int;

    public function remove(int $tenantId, int $pinId): void;

    /**
     * Applique cet ordre aux épingles de la communauté.
     *
     * @param list<int> $ids
     */
    public function setOrder(int $tenantId, array $ids): void;
}
