<?php

declare(strict_types=1);

namespace App\Services\Effectifs\Dossier\Adapters;

use App\Services\Effectifs\Dossier\DossierCatalog;

/**
 * Recrutement : la candidature la plus récente du membre (enlistments), puis, une fois acceptée, les deux bilans à
 * 30 jours (celui du recruteur et celui du membre, table enlistment_retro_feedbacks). L'échéance des bilans est
 * calculée comme le fait déjà le recrutement : date de décision + 30 jours. Rien n'est modifiable ici : la décision
 * et les bilans se traitent dans Recrutement.
 */
final class RecruitmentStepsAdapter extends AbstractDossierAdapter
{
    public const SOURCE_ENLISTMENT = 'enlistment';
    public const SOURCE_RETRO_STAFF = 'enlistment_retro_staff';
    public const SOURCE_RETRO_MEMBER = 'enlistment_retro_member';
    public const RETRO_DAYS = 30;

    public function track(): string
    {
        return DossierCatalog::TRACK_RECRUITMENT;
    }

    public function stepsFor(int $tenantId, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }
        $rows = $this->rows(
            'SELECT id, submitter_user_id, status, reviewed_at, created_at FROM enlistments
              WHERE tenant_id = ? AND submitter_user_id IN (' . self::marks($userIds) . ') ORDER BY id ASC',
            array_merge([$tenantId], $userIds)
        );
        $latest = [];
        foreach ($rows as $r) {
            $latest[(int) $r['submitter_user_id']] = $r;
        }
        if ($latest === []) {
            return [];
        }
        $ids = array_map(static fn (array $r): int => (int) $r['id'], array_values($latest));
        $retros = [];
        foreach ($this->rows(
            'SELECT * FROM enlistment_retro_feedbacks WHERE tenant_id = ? AND enlistment_id IN (' . self::marks($ids) . ')',
            array_merge([$tenantId], $ids)
        ) as $rf) {
            $retros[(int) $rf['enlistment_id']][(string) $rf['feedback_scope']] = $rf;
        }
        $out = [];
        foreach ($latest as $uid => $e) {
            $eid = (int) $e['id'];
            $status = (string) $e['status'];
            $url = url('back-office/recruitments/' . $eid);
            $accepted = $status === 'reviewed';
            $state = match (true) {
                $accepted => DossierCatalog::STATE_DONE,
                in_array($status, ['rejected', 'blocked', 'cancelled'], true) => DossierCatalog::STATE_SKIPPED,
                default => DossierCatalog::STATE_IN_PROGRESS,
            };
            $steps = [$this->make([
                'source_type' => self::SOURCE_ENLISTMENT,
                'source_id' => (string) $eid,
                'track' => DossierCatalog::TRACK_RECRUITMENT,
                'title' => 'Décision sur la candidature',
                'description' => match ($status) {
                    'reviewed' => 'Candidature acceptée.',
                    'rejected' => 'Candidature refusée.',
                    'blocked' => 'Candidature bloquée.',
                    'cancelled' => 'Candidature annulée.',
                    default => 'La candidature attend une décision du recrutement.',
                },
                'state' => $state,
                'owner_kind' => DossierCatalog::OWNER_RECRUITMENT,
                'opened_on' => $e['created_at'],
                'done_at' => $accepted ? $e['reviewed_at'] : null,
                'updated_at' => $e['reviewed_at'] ?: $e['created_at'],
                'source_label' => 'Candidature',
                'source_url' => $url,
                'pos' => 10,
            ])];
            if ($accepted && DossierCatalog::day($e['reviewed_at']) !== null) {
                $due = date('Y-m-d', strtotime(substr((string) $e['reviewed_at'], 0, 10) . ' +' . self::RETRO_DAYS . ' days') ?: 0);
                foreach ([
                    [self::SOURCE_RETRO_STAFF, 'staff_one_month', 'Bilan du recruteur à 30 jours', DossierCatalog::OWNER_RECRUITMENT, 20],
                    [self::SOURCE_RETRO_MEMBER, 'candidate_return', 'Bilan du membre à 30 jours', DossierCatalog::OWNER_MEMBER, 30],
                ] as [$type, $scope, $title, $owner, $pos]) {
                    $done = $retros[$eid][$scope] ?? null;
                    $auto = $done !== null && DossierCatalog::stamp($done['auto_closed_at'] ?? null) !== null;
                    $steps[] = $this->make([
                        'source_type' => $type,
                        'source_id' => (string) $eid,
                        'track' => DossierCatalog::TRACK_RECRUITMENT,
                        'title' => $title,
                        'description' => $auto ? 'Clôturé automatiquement par Athena après un long retard.' : '',
                        'state' => $done !== null ? DossierCatalog::STATE_DONE : DossierCatalog::STATE_TODO,
                        'owner_kind' => $owner,
                        'opened_on' => $e['reviewed_at'],
                        'due_on' => $due,
                        'done_at' => $done['auto_closed_at'] ?? $done['created_at'] ?? null,
                        'updated_at' => $done['updated_at'] ?? $done['created_at'] ?? $e['reviewed_at'],
                        'source_label' => 'Bilan 30 jours',
                        'source_url' => $url,
                        'pos' => $pos,
                    ]);
                }
            }
            $out[$uid] = $steps;
        }

        return $out;
    }
}
