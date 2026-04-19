<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

/**
 * Parcours « étapes du dossier » sur le portail candidat (/enlistment/suivi/{token}).
 * S’appuie sur le journal interne (timeline) sans exposer le corps des notes.
 */
final class EnlistmentCandidatePortalJourneyService
{
    /**
     * @param array<string, mixed> $enlistment
     * @param list<array<string, mixed>> $timelineRows
     *
     * @return list<array{id: string, label: string, hint: string, state: string, foot?: string}>
     */
    public function buildSteps(array $enlistment, array $timelineRows, int $messageCount, int $attachmentCount): array
    {
        $status = (string) ($enlistment['status'] ?? 'submitted');

        $hasInstruction = false;
        $hasModeration = false;
        $hasEscalation = false;
        $hasRestore = false;
        $staffNoteCount = 0;
        $hasDecisionEvent = false;
        $hasAdhesionEvent = false;
        $hasPortalActivity = $messageCount > 0 || $attachmentCount > 0;

        foreach ($timelineRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $summary = trim((string) ($row['summary'] ?? ''));
            $step = (string) ($row['step_code'] ?? '');
            $entryKind = (string) ($row['entry_kind'] ?? '');
            $fam = $this->timelineFamily($row);

            if ($summary === 'Modération automatique du portail' || $fam === 'moderation') {
                $hasModeration = true;
                continue;
            }
            if ($fam === 'platform_escalation' || str_contains($summary, 'Assistance site sollicitée')) {
                $hasEscalation = true;
            }
            if ($fam === 'platform_assist' || $fam === 'moderation_override' || str_contains($summary, 'rétablissement')) {
                $hasRestore = true;
            }
            if ($entryKind === 'staff_note') {
                ++$staffNoteCount;
            }
            if ($step === 'decision') {
                $hasDecisionEvent = true;
            }
            if ($step === 'adhesion') {
                $hasAdhesionEvent = true;
            }
            if ($step === 'instruction') {
                $hasInstruction = true;
            }
            if (in_array($step, ['portal', 'communication'], true)) {
                $hasPortalActivity = true;
            }
            if (in_array($fam, ['portal_message', 'portal_upload', 'portal_options', 'email_notify'], true)) {
                $hasPortalActivity = true;
            }
        }

        $decisionDone = in_array($status, ['reviewed', 'rejected', 'blocked'], true) || $hasDecisionEvent;
        $adhesionSkipped = in_array($status, ['rejected', 'blocked'], true);
        $adhesionDone = $status === 'reviewed' || $hasAdhesionEvent;

        $raw = [
            [
                'id' => 'reception',
                'label' => 'Réception du dossier',
                'hint' => 'Votre candidature est bien enregistrée côté communauté.',
                'done' => true,
                'skipped' => false,
            ],
            [
                'id' => 'instruction',
                'label' => 'Instruction et arbitrage',
                'hint' => 'Analyse du dossier par l’équipe recrutement.',
                'done' => $hasInstruction || $decisionDone,
                'skipped' => false,
            ],
            [
                'id' => 'suivi',
                'label' => 'Suivi et échanges',
                'hint' => 'Messages, pièces et enregistrements vocaux avec l’équipe sur ce fil sécurisé.',
                'done' => $hasPortalActivity || $decisionDone,
                'skipped' => false,
            ],
            [
                'id' => 'moderation',
                'label' => 'Modération automatique',
                'hint' => 'Contrôles de sécurité sur les contenus envoyés depuis le portail.',
                'done' => $hasModeration,
                'skipped' => false,
            ],
            [
                'id' => 'escalation',
                'label' => 'Assistance site (escalade)',
                'hint' => 'Sollicitation des équipes plateforme en cas de blocage lié à la modération automatique.',
                'done' => $hasEscalation,
                'skipped' => false,
            ],
            [
                'id' => 'restore',
                'label' => 'Rétablissement du suivi',
                'hint' => 'Levée des blocages et réouverture de l’accès au portail si une suspension a eu lieu.',
                'done' => $hasRestore,
                'skipped' => false,
            ],
            [
                'id' => 'staff_notes',
                'label' => 'Notes d’étape (équipe)',
                'hint' => 'Repères internes laissés par l’équipe sur les étapes du dossier (sans afficher le texte ici).',
                'done' => $staffNoteCount > 0,
                'skipped' => false,
                'foot' => $staffNoteCount > 0 ? ($staffNoteCount === 1 ? '1 note enregistrée dans le journal du dossier.' : $staffNoteCount . ' notes enregistrées dans le journal du dossier.') : null,
            ],
            [
                'id' => 'decision',
                'label' => 'Décision',
                'hint' => match ($status) {
                    'reviewed' => 'Dossier accepté par la communauté.',
                    'rejected' => 'Dossier refusé. Vous pouvez écrire à l’équipe pour des précisions.',
                    'blocked' => 'Dossier classé non admis.',
                    default => 'L’équipe rend sa décision sur ce dossier.',
                },
                'done' => $decisionDone,
                'skipped' => false,
            ],
            [
                'id' => 'adhesion',
                'label' => 'Rattachement au compte membre',
                'hint' => $adhesionSkipped
                    ? 'Sans suite d’adhésion pour ce dossier.'
                    : 'Finalisation de l’accès à la communauté lorsque la candidature est acceptée.',
                'done' => $adhesionDone,
                'skipped' => $adhesionSkipped,
            ],
        ];

        $firstPending = null;
        $out = [];
        foreach ($raw as $i => $def) {
            $skipped = !empty($def['skipped']);
            if ($skipped) {
                $state = 'skipped';
            } elseif (!empty($def['done'])) {
                $state = 'done';
            } elseif ($firstPending === null) {
                $firstPending = $i;
                $state = 'current';
            } else {
                $state = 'upcoming';
            }
            $item = [
                'id' => (string) $def['id'],
                'label' => (string) $def['label'],
                'hint' => (string) $def['hint'],
                'state' => $state,
            ];
            if (!empty($def['foot'])) {
                $item['foot'] = (string) $def['foot'];
            }
            $out[] = $item;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function timelineFamily(array $row): ?string
    {
        $meta = $row['metadata'] ?? null;
        if (!is_array($meta)) {
            return null;
        }
        $f = $meta['timeline_family'] ?? null;

        return is_string($f) && $f !== '' ? $f : null;
    }
}
