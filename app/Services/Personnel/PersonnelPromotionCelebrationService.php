<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\GradeRepository;
use App\Repositories\TenantAlertRepository;
use App\Repositories\UserRepository;

/**
 * Publie automatiquement une annonce dashboard (tenant_alerts) quand un changement de grade
 * est une VRAIE promotion (sort_order supérieur, même système de grade) — pas une simple
 * modification latérale ou une rétrogradation, qui ne doivent pas être célébrées publiquement.
 */
final class PersonnelPromotionCelebrationService
{
    public function __construct(
        private GradeRepository $gradeRepository,
        private TenantAlertRepository $tenantAlertRepository,
        private UserRepository $userRepository,
    ) {}

    public function celebrateIfPromotion(int $tenantId, int $targetUserId, ?int $beforeGradeId, ?int $afterGradeId): void
    {
        if ($tenantId < 1 || $targetUserId < 1) {
            return;
        }
        if ($afterGradeId === null || $afterGradeId < 1) {
            return;
        }
        // Pas de grade avant, ou grade inchangé : rien à célébrer (l'affectation initiale d'un
        // grade à l'arrivée n'est pas une "promotion").
        if ($beforeGradeId === null || $beforeGradeId < 1 || $beforeGradeId === $afterGradeId) {
            return;
        }

        try {
            $before = $this->gradeRepository->findById($beforeGradeId, $tenantId);
            $after = $this->gradeRepository->findById($afterGradeId, $tenantId);
            if (!$before || !$after) {
                return;
            }
            // Comparer deux grades n'a de sens que dans le même système hiérarchique.
            if ((int) ($before['grade_system_id'] ?? -1) !== (int) ($after['grade_system_id'] ?? -2)) {
                return;
            }
            // sort_order croissant = grade plus élevé dans le catalogue (voir GradeRepository).
            if ((int) ($after['sort_order'] ?? 0) <= (int) ($before['sort_order'] ?? 0)) {
                return;
            }

            $target = $this->userRepository->findById($targetUserId, $tenantId);
            if (!$target) {
                return;
            }
            $targetName = trim((string) ($target['display_name'] ?? ''));
            if ($targetName === '') {
                $targetName = trim((string) ($target['callsign'] ?? ''));
            }
            if ($targetName === '') {
                return;
            }
            $newGradeLabel = trim((string) ($after['label_long'] ?? '')) ?: trim((string) ($after['label_short'] ?? ''));
            if ($newGradeLabel === '') {
                return;
            }

            $this->tenantAlertRepository->insert($tenantId, [
                'kind' => 'promotion',
                'display_style' => 'classic',
                'accent_color' => 'amber',
                'icon_key' => 'trophy',
                'title' => 'Promotion — ' . $targetName,
                'body' => $targetName . ' est promu(e) ' . $newGradeLabel . '. Félicitations !',
                'is_active' => 1,
                'sort_order' => 0,
            ]);
        } catch (\Throwable) {
            // Best effort : ne doit jamais faire échouer la sauvegarde du dossier personnel.
        }
    }
}
