<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\PersonnelServiceHistoryRepository;

/**
 * Écriture systématique du journal de service (affectation, promotion, décoration…).
 * Les échecs d’écriture ne doivent jamais bloquer l’action RH.
 */
final class PersonnelServiceHistoryWriter
{
    public function __construct(
        private PersonnelServiceHistoryRepository $history,
    ) {
    }

    public function record(
        int $userId,
        string $eventType,
        string $title,
        string $description = '',
        ?string $eventDate = null,
        ?int $createdBy = null,
        ?string $reasonLabel = null
    ): void {
        if ($userId < 1 || trim($title) === '') {
            return;
        }
        $allowed = ['assignment', 'promotion', 'qualification', 'deployment', 'award', 'discipline', 'note'];
        if (!in_array($eventType, $allowed, true)) {
            $eventType = 'note';
        }
        try {
            $this->history->add(
                $userId,
                $eventType,
                trim($title),
                trim($description),
                $eventDate !== null && trim($eventDate) !== '' ? substr(trim($eventDate), 0, 10) : date('Y-m-d'),
                $createdBy !== null && $createdBy > 0 ? $createdBy : null,
                $reasonLabel
            );
        } catch (\Throwable) {
            // Journal optionnel : ne jamais faire échouer la décision RH.
        }
    }

    /**
     * @param list<string> $appliedKeys grade|role|job_role|unit|permissions
     * @param array{grade?: ?string, role?: ?string, job_role?: ?string, unit?: ?string} $labels
     */
    public function recordElevation(
        int $userId,
        array $appliedKeys,
        array $labels,
        ?int $actorUserId = null,
        ?string $reasonLabel = null
    ): void {
        if ($appliedKeys === []) {
            return;
        }
        $parts = [];
        if (in_array('grade', $appliedKeys, true) && !empty($labels['grade'])) {
            $parts[] = 'Grade : ' . $labels['grade'];
        }
        if (in_array('job_role', $appliedKeys, true) && !empty($labels['job_role'])) {
            $parts[] = 'Fonction : ' . $labels['job_role'];
        }
        if (in_array('unit', $appliedKeys, true) && !empty($labels['unit'])) {
            $parts[] = 'Unité : ' . $labels['unit'];
        }
        if (in_array('role', $appliedKeys, true) && !empty($labels['role'])) {
            $parts[] = 'Accès : ' . $labels['role'];
        }
        if (in_array('permissions', $appliedKeys, true)) {
            $parts[] = 'Droits d’accès ajustés';
        }
        $isPromotion = in_array('grade', $appliedKeys, true);
        $this->record(
            $userId,
            $isPromotion ? 'promotion' : 'assignment',
            $isPromotion ? 'Élévation confirmée' : 'Changement d’affectation confirmé',
            $parts !== [] ? implode(' · ', $parts) : 'Décision d’élévation appliquée.',
            date('Y-m-d'),
            $actorUserId,
            $reasonLabel ?? 'Élévation acceptée'
        );
    }

    public function recordAward(
        int $userId,
        string $awardName,
        string $citation = '',
        ?string $awardedAt = null,
        ?int $actorUserId = null,
        ?string $authority = null
    ): void {
        $name = trim($awardName);
        if ($name === '') {
            $name = 'Décoration';
        }
        $detail = trim($citation);
        if ($detail === '' && $authority !== null && trim($authority) !== '') {
            $detail = 'Autorité : ' . trim($authority);
        }
        $this->record(
            $userId,
            'award',
            $name,
            $detail !== '' ? $detail : 'Distinction enregistrée.',
            $awardedAt,
            $actorUserId,
            $authority
        );
    }

    /**
     * @param list<string> $diffLines
     */
    public function recordCorrectionApplied(
        int $userId,
        array $diffLines,
        ?int $actorUserId = null,
        ?string $note = null
    ): void {
        $lines = array_values(array_filter(array_map(
            static fn (mixed $l): string => trim((string) $l),
            $diffLines
        ), static fn (string $l): bool => $l !== ''));
        $hasOrbat = false;
        foreach ($lines as $line) {
            $lower = mb_strtolower($line);
            if (
                str_contains($lower, 'unité')
                || str_contains($lower, 'affectation')
                || str_contains($lower, 'grade')
                || str_contains($lower, 'emploi')
                || str_contains($lower, 'fonction')
            ) {
                $hasOrbat = true;
                break;
            }
        }
        $this->record(
            $userId,
            $hasOrbat ? 'assignment' : 'note',
            $hasOrbat ? 'Correction d’affectation confirmée' : 'Correction de dossier confirmée',
            $lines !== [] ? implode("\n", array_slice($lines, 0, 8)) : 'Demande de correction appliquée.',
            date('Y-m-d'),
            $actorUserId,
            $note
        );
    }
}
