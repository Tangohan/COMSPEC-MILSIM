<?php

declare(strict_types=1);

namespace App\Services\BackOffice\Menu;

/**
 * À implémenter en plus de BadgeProvider quand l'explication d'une tâche dépend de la communauté (dates, nom de la
 * formule…). Sinon, les textes fixes de la clé « task » de config/back_office_menu.php suffisent.
 */
interface PendingTaskDetailProvider
{
    /**
     * @param string $locale « fr » ou « en »
     * @return PendingTaskDetail|null null : retomber sur le texte fixe de la config, ou sur l'affichage simple
     */
    public function detail(int $tenantId, int $userId, string $locale): ?PendingTaskDetail;
}
