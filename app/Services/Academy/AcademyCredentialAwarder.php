<?php

declare(strict_types=1);

namespace App\Services\Academy;

use App\Core\Container;
use App\Core\Database;
use App\Repositories\QualificationAwardRepository;

/**
 * Attribution des qualifications et brevets d'un cours terminé. Logique unique, appelée par la validation manuelle d'un
 * formateur (AcademyController) comme par la validation automatique d'Athena (AcademyAutoValidation, auteur système = 0).
 * Ne bloque jamais la validation : toute erreur est journalisée.
 */
final class AcademyCredentialAwarder
{
    /**
     * Cours intégré : sa qualification Athena Academy est créée si besoin dans le référentiel puis attribuée par la règle
     * automatique. Tout cours : les qualifications et brevets du Studio qui le demandent (ou une version qu'il remplace)
     * sont évalués aussitôt.
     *
     * @param int $actorId formateur, ou 0 pour le système (Athena)
     * @return list<int> attributions apparues pour ce stagiaire pendant l'appel
     */
    public function awardCompleted(int $tenantId, int $learnerId, string $key, int $actorId): array
    {
        try {
            $awards = new QualificationAwardRepository();
            $before = array_column($awards->listForUser($learnerId, $tenantId), 'id');
            $builtIn = AcademyBuiltInQualifications::forPdo(Database::getPdo());
            $definitionId = 0;
            if (isset(AcademyBuiltInCourses::qualifications()[$key])) {
                $definitionId = $builtIn->ensureDefinition($tenantId, $key, function_exists('locale') ? (string) locale() : 'fr', $actorId > 0 ? $actorId : null);
                if ($definitionId > 0) {
                    Container::get(\App\Services\Personnel\QualificationAwardRuleService::class)->runAuto($tenantId, $definitionId);
                }
            }
            Container::get(AcademyStudioCredentials::class)->awardForCourse($tenantId, $key, $builtIn->lineage($tenantId)[$key] ?? [], $definitionId);

            return array_values(array_map('intval', array_diff(array_column($awards->listForUser($learnerId, $tenantId), 'id'), $before)));
        } catch (\Throwable $e) {
            error_log('[academy] qualification du cours ' . $key . ' : ' . $e->getMessage());

            return [];
        }
    }
}
