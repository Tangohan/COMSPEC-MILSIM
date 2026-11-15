<?php

declare(strict_types=1);

namespace App\Services\Effectifs\Dossier\Adapters;

use App\Services\Effectifs\Dossier\DossierCatalog;

/**
 * Formation et qualifications : qualifications visées (personnel_qualifications en candidature, en formation ou en
 * évaluation) et inscriptions aux formations de l'Academy (training_enrollments). Lecture seule : une qualification
 * se délivre dans sa matrice (règles d'attribution), une formation se valide dans l'Academy.
 */
final class FormationStepsAdapter extends AbstractDossierAdapter
{
    public const SOURCE_QUALIFICATION = 'qualification';
    public const SOURCE_ENROLLMENT = 'enrollment';

    public function track(): string
    {
        return DossierCatalog::TRACK_FORMATION;
    }

    public function stepsFor(int $tenantId, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }
        $marks = self::marks($userIds);
        $out = [];

        $qualifs = $this->rows(
            "SELECT id, user_id, qualification_name, admin_status, created_at, updated_at FROM personnel_qualifications
              WHERE tenant_id = ? AND user_id IN ($marks) AND admin_status IN ('candidate', 'in_training', 'in_evaluation') ORDER BY id ASC",
            array_merge([$tenantId], $userIds)
        );
        foreach ($qualifs as $q) {
            $status = (string) $q['admin_status'];
            $out[(int) $q['user_id']][] = $this->make([
                'source_type' => self::SOURCE_QUALIFICATION,
                'source_id' => (string) $q['id'],
                'track' => DossierCatalog::TRACK_FORMATION,
                'title' => 'Qualification : ' . trim((string) $q['qualification_name']),
                'description' => match ($status) {
                    'candidate' => 'Candidat à la qualification.',
                    'in_evaluation' => 'Évaluation en cours.',
                    default => 'En formation.',
                },
                'state' => $status === 'candidate' ? DossierCatalog::STATE_TODO : DossierCatalog::STATE_IN_PROGRESS,
                'owner_kind' => DossierCatalog::OWNER_INSTRUCTOR,
                'opened_on' => $q['created_at'],
                'updated_at' => $q['updated_at'] ?: $q['created_at'],
                'source_label' => 'Qualifications',
                'source_url' => effectifs_workspace_url('qualifications'),
                'pos' => 10,
            ]);
        }

        $since = date('Y-m-d H:i:s', strtotime('-180 days'));
        $enrolments = $this->rows(
            "SELECT e.id, e.user_id, e.status, e.assigned_at, e.started_at, e.completed_at, e.expires_at, c.title
               FROM training_enrollments e JOIN training_courses c ON c.id = e.course_id
              WHERE e.tenant_id = ? AND e.user_id IN ($marks)
                AND (e.status IN ('assigned', 'in_progress', 'pending_approval', 'failed') OR (e.status = 'completed' AND e.completed_at >= ?))
              ORDER BY e.id ASC",
            array_merge([$tenantId], $userIds, [$since])
        );
        foreach ($enrolments as $e) {
            $status = (string) $e['status'];
            $state = match ($status) {
                'completed' => DossierCatalog::STATE_DONE,
                'failed' => DossierCatalog::STATE_BLOCKED,
                'in_progress' => DossierCatalog::STATE_IN_PROGRESS,
                default => DossierCatalog::STATE_TODO,
            };
            $out[(int) $e['user_id']][] = $this->make([
                'source_type' => self::SOURCE_ENROLLMENT,
                'source_id' => (string) $e['id'],
                'track' => DossierCatalog::TRACK_FORMATION,
                'title' => 'Formation : ' . trim((string) $e['title']),
                'description' => match ($status) {
                    'pending_approval' => 'Inscription en attente d’approbation.',
                    'failed' => 'Formation non validée : à reprendre avec un instructeur.',
                    default => '',
                },
                'state' => $state,
                'owner_kind' => $status === 'pending_approval' || $status === 'failed' ? DossierCatalog::OWNER_INSTRUCTOR : DossierCatalog::OWNER_MEMBER,
                'opened_on' => $e['assigned_at'],
                'due_on' => $state === DossierCatalog::STATE_DONE ? null : $e['expires_at'],
                'done_at' => $e['completed_at'],
                'updated_at' => $e['completed_at'] ?: ($e['started_at'] ?: $e['assigned_at']),
                'source_label' => 'Academy',
                'source_url' => effectifs_workspace_url('qualifications'),
                'pos' => 20,
            ]);
        }

        return $out;
    }
}
