<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Score de complétion du dossier personnel, partagé entre le tableur RH admin
 * (EffectifsWorkspaceController) et l’espace RH du membre lui-même — même heuristique,
 * une seule source de vérité pour éviter la dérive entre les deux affichages.
 */
final class PersonnelDossierCompleteness
{
    /**
     * @param array<string, mixed> $user Ligne `users` (display_name, callsign, email…)
     * @param array<string, mixed> $rich Ligne enrichie (grade, fonction, dates…) issue de
     *   UserRepository::listEffectifsRosterByIds()
     * @return array{score:int, filled:int, total:int, missing:list<string>}
     */
    public static function evaluate(array $user, array $rich, bool $hasAssignment): array
    {
        $checks = [
            'Nom affiché' => trim((string) ($user['display_name'] ?? $rich['character_name'] ?? '')) !== '',
            'Indicatif' => trim((string) ($user['callsign'] ?? '')) !== '',
            'Matricule' => trim((string) ($rich['matricule_internal'] ?? $rich['service_number'] ?? '')) !== '',
            'Grade' => trim((string) ($rich['grade_short'] ?? $rich['grade_long'] ?? '')) !== '',
            'Affectation' => $hasAssignment,
            'Fonction' => trim((string) ($rich['job_role_display'] ?? '')) !== '',
            'Date d’engagement' => trim((string) ($rich['enlistment_date_resolved'] ?? '')) !== '',
            'Niveau d’habilitation' => trim((string) ($rich['clearance_level'] ?? '')) !== '',
            'Revue d’habilitation' => !empty($rich['clearance_reviewed_at']),
            'Score de disponibilité renseigné' => (int) ($rich['readiness_score'] ?? 0) > 0,
            'Adresse e-mail' => trim((string) ($user['email'] ?? '')) !== '',
        ];

        $total = count($checks);
        $filled = 0;
        $missing = [];
        foreach ($checks as $label => $ok) {
            if ($ok) {
                $filled++;
            } else {
                $missing[] = $label;
            }
        }

        return [
            'score' => $total > 0 ? (int) round(($filled / $total) * 100) : 0,
            'filled' => $filled,
            'total' => $total,
            'missing' => $missing,
        ];
    }
}
