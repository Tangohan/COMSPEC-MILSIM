<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\GradeRepository;

/**
 * Règles de validation : cohérence nation / catégorie / grade / format.
 */
class GradeValidationService
{
    private const MILITARY_CATEGORIES = ['OFFICIER', 'SOUS_OFFICIER', 'MDR'];

    public function __construct(
        private GradeRepository $gradeRepository
    ) {
    }

    /**
     * Valide le profil utilisateur (grade, catégorie, nationalité, format).
     * @param array<string, mixed> $user
     * @return list<array{type: 'error'|'warning', message: string}>
     */
    public function validateUserProfile(array $user): array
    {
        $issues = [];
        $categoryCode = $user['professional_category_code'] ?? null;
        $gradeId = $user['grade_id'] ?? null;
        $nationalityCode = $user['nationality_code'] ?? null;
        $preferredFormat = $user['preferred_grade_format'] ?? 'classic';

        $isMilitary = $categoryCode !== null && in_array($categoryCode, self::MILITARY_CATEGORIES, true);

        if ($isMilitary && empty($gradeId)) {
            $issues[] = ['type' => 'warning', 'message' => 'Profil militaire sans grade renseigné.'];
        }

        if (!empty($gradeId)) {
            $grade = $this->gradeRepository->findById((int) $gradeId);
            if (!$grade) {
                $issues[] = ['type' => 'error', 'message' => 'Le grade sélectionné n’existe plus dans le référentiel.'];
            } else {
                if ($categoryCode !== null && ($grade['category_code'] ?? '') !== $categoryCode) {
                    $issues[] = ['type' => 'warning', 'message' => 'La catégorie de personnel ne correspond pas à celle du grade choisi.'];
                }
                if ($preferredFormat === 'otan' && empty($grade['label_otan'])) {
                    $issues[] = ['type' => 'warning', 'message' => 'Affichage OTAN demandé, mais ce grade n’a pas de libellé OTAN défini.'];
                }
                $gradeCountry = $grade['country_code'] ?? null;
                if ($nationalityCode !== null && $gradeCountry !== null && $gradeCountry !== $nationalityCode) {
                    $issues[] = ['type' => 'warning', 'message' => 'Incohérence de nation : le grade et la nationalité du profil ne correspondent pas.'];
                }
            }
        } elseif ($categoryCode !== null && in_array($categoryCode, self::MILITARY_CATEGORIES, true)) {
            // déjà traité au-dessus
        }

        return $issues;
    }

    /**
     * Indique si le profil a des erreurs bloquantes.
     * @param list<array{type: string, message: string}> $issues
     */
    public function hasErrors(array $issues): bool
    {
        foreach ($issues as $i) {
            if (($i['type'] ?? '') === 'error') {
                return true;
            }
        }
        return false;
    }
}
