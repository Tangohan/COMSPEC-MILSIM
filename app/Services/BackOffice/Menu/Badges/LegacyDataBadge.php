<?php

declare(strict_types=1);

namespace App\Services\BackOffice\Menu\Badges;

use App\Services\BackOffice\Menu\BadgeProvider;
use App\Services\BackOffice\Menu\PendingTaskDetail;
use App\Services\BackOffice\Menu\PendingTaskDetailProvider;
use App\Services\LegacyData\LegacyDataNotice;

/**
 * « Données anciennes à vérifier » : nombre de points à vérifier (membres sans prénom ni nom, dossiers de recrutement
 * anciens sans bilan, membres sans unité, grade ou portrait). Jamais bloquant, jamais rouge : l'avis informe, rien
 * n'est supprimé. Les nombres viennent de LegacyDataNotice (mis en cache 10 minutes par communauté) ; le texte de la
 * tâche, avec ses nombres, est composé par LegacyDataNotice::compose().
 */
final class LegacyDataBadge implements BadgeProvider, PendingTaskDetailProvider
{
    public function badge(int $tenantId, int $userId): ?array
    {
        $lines = LegacyDataNotice::lines(LegacyDataNotice::counts($tenantId));

        return ['count' => count($lines), 'important' => false];
    }

    public function detail(int $tenantId, int $userId, string $locale): ?PendingTaskDetail
    {
        return LegacyDataNotice::compose(LegacyDataNotice::counts($tenantId), $locale === 'en' ? 'en' : 'fr');
    }
}
