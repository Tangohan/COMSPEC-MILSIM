<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

/**
 * Parcours « étapes du dossier » sur le portail candidat (/enlistment/suivi/{token}).
 * Étapes alignées sur le parcours métier ; infobulles basées sur le journal, le fil et les pièces (sans corps des notes internes).
 */
final class EnlistmentCandidatePortalJourneyService
{
    /**
     * Suite d’instruction non terminale (dossier reste « soumis ») : mise en attente ou entretien.
     * Source : journal (métadonnée followup_action) puis repli sur le commentaire d’instruction.
     *
     * @param array<string, mixed> $enlistment
     * @param list<array<string, mixed>> $timelineRows
     *
     * @return 'pending'|'interview'|null
     */
    public function resolveInstructionFollowup(array $enlistment, array $timelineRows): ?string
    {
        $status = (string) ($enlistment['status'] ?? 'submitted');
        if ($status !== 'submitted') {
            return null;
        }

        $latestAction = null;
        $latestTs = null;
        foreach ($timelineRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $meta = $row['metadata'] ?? null;
            $meta = is_array($meta) ? $meta : [];
            $action = (string) ($meta['followup_action'] ?? '');
            if ($action !== 'pending' && $action !== 'interview') {
                continue;
            }
            $ts = $this->parseTs((string) ($row['created_at'] ?? ''));
            if ($ts === null) {
                $ts = 0;
            }
            if ($latestTs === null || $ts >= $latestTs) {
                $latestTs = $ts;
                $latestAction = $action;
            }
        }
        if ($latestAction !== null) {
            return $latestAction;
        }

        $comment = (string) ($enlistment['reviewer_comment'] ?? '');
        if ($comment === '') {
            return null;
        }
        $posPending = mb_strrpos($comment, '[MISE EN ATTENTE]');
        $posInterview = mb_strrpos($comment, '[DEMANDE ENTRETIEN]');
        $hasPending = $posPending !== false;
        $hasInterview = $posInterview !== false;
        if (!$hasPending && !$hasInterview) {
            return null;
        }
        if ($hasPending && !$hasInterview) {
            return 'pending';
        }
        if ($hasInterview && !$hasPending) {
            return 'interview';
        }

        return ((int) $posInterview) >= ((int) $posPending) ? 'interview' : 'pending';
    }

    /**
     * Compte membre déjà lié à la candidature (colonne submitter_user_id).
     *
     * @param array<string, mixed> $enlistment
     */
    public function isMemberAccountLinked(array $enlistment): bool
    {
        return (int) ($enlistment['submitter_user_id'] ?? 0) > 0;
    }

    /**
     * Fil / pièces clos : refus, non-admission, ou acceptation avec rattachement déjà effectué.
     *
     * @param array<string, mixed> $enlistment
     */
    public function isPortalMessagingClosed(array $enlistment): bool
    {
        $status = (string) ($enlistment['status'] ?? '');
        if ($status === 'rejected' || $status === 'blocked') {
            return true;
        }

        return $status === 'reviewed' && $this->isMemberAccountLinked($enlistment);
    }

    /**
     * Message flash métier quand le fil (ou l’envoi de pièces) est clos.
     *
     * @param array<string, mixed> $enlistment
     * @param 'message'|'upload' $context
     */
    public function portalMessagingClosedFlash(array $enlistment, string $context = 'message'): string
    {
        $status = (string) ($enlistment['status'] ?? '');
        $isUpload = $context === 'upload';
        if ($status === 'rejected') {
            return $isUpload
                ? 'Ce dossier est clos — candidature refusée. L’envoi de pièces est désactivé.'
                : 'Ce dossier est clos — candidature refusée. Les messages sont désactivés.';
        }
        if ($status === 'blocked') {
            return $isUpload
                ? 'Ce dossier est clos — candidature non admise. L’envoi de pièces est désactivé.'
                : 'Ce dossier est clos — candidature non admise. Les messages sont désactivés.';
        }

        return $isUpload
            ? 'Ce dossier est clos — rattachement effectué. L’envoi de pièces est désactivé.'
            : 'Ce dossier est clos — rattachement effectué. Les messages sont désactivés.';
    }

    /**
     * @param array<string, mixed> $enlistment
     * @param list<array<string, mixed>> $timelineRows
     * @param list<array<string, mixed>> $messages
     * @param list<array<string, mixed>> $attachments
     *
     * @return list<array{id: string, label: string, hint: string, state: string, tooltip?: string, pause_kind?: string, current_note?: string}>
     *         ids : reception, portal_moderation_filter, portal_moderation_incident, instruction, suivi, decision, adhesion
     */
    public function buildSteps(
        array $enlistment,
        array $timelineRows,
        array $messages,
        array $attachments,
    ): array {
        $status = (string) ($enlistment['status'] ?? 'submitted');
        $terminal = in_array($status, ['reviewed', 'rejected', 'blocked'], true);
        $followup = $this->resolveInstructionFollowup($enlistment, $timelineRows);
        $memberLinked = $this->isMemberAccountLinked($enlistment);

        $hasRecruiterMessage = false;
        foreach ($messages as $m) {
            if (is_array($m) && (string) ($m['entry_kind'] ?? '') !== 'candidate') {
                $hasRecruiterMessage = true;
                break;
            }
        }

        $hasPortalActivity = $messages !== [] || $attachments !== [];
        $hasModerationTimeline = $this->timelineHasModerationMarker($timelineRows);
        $lastIncidentTs = $this->lastModerationEventTs($timelineRows, 'moderation');
        $lastResolutionTs = $this->lastModerationEventTs($timelineRows, 'moderation_override');
        $incidentResolved = $hasModerationTimeline && $lastResolutionTs !== null
            && ($lastIncidentTs === null || $lastResolutionTs >= $lastIncidentTs);
        $incidentOpen = $hasModerationTimeline && !$incidentResolved;

        $stReception = 'done';
        $stInstruction = 'upcoming';
        $stSuivi = 'upcoming';
        $stDecision = 'upcoming';
        $stAdhesion = 'upcoming';
        $stModFilter = $hasPortalActivity ? 'done' : 'upcoming';
        $stModIncident = $incidentOpen ? 'incident' : ($hasModerationTimeline ? 'done' : 'upcoming');

        if ($status === 'submitted') {
            if (!$hasRecruiterMessage) {
                $stInstruction = 'current';
            } else {
                $stInstruction = 'done';
                $stSuivi = 'current';
            }
        } elseif ($status === 'reviewed') {
            $stInstruction = 'done';
            $stSuivi = 'done';
            $stDecision = 'done';
            // Avant : toujours « current » même si submitter_user_id était déjà renseigné.
            $stAdhesion = $memberLinked ? 'done' : 'current';
        } elseif ($status === 'rejected' || $status === 'blocked') {
            $stInstruction = 'done';
            $stSuivi = 'done';
            $stDecision = 'done';
            $stAdhesion = 'cancelled';
        }

        $tooltips = $this->buildStepTooltips($enlistment, $timelineRows, $messages, $attachments, $followup);
        $isRejected = $status === 'rejected';
        $isBlocked = $status === 'blocked';
        $isJourneyClosedNegative = $isRejected || $isBlocked;

        $instructionHint = 'L’équipe examine votre dossier et tranche les suites possibles.';
        $suiviHint = 'Échanges sur le fil, envoi de pièces ou d’enregistrements vocaux si l’équipe l’autorise.';
        $decisionHint = match ($status) {
            'reviewed' => 'Dossier accepté par la communauté.',
            'rejected' => 'Candidature refusée — dossier clos.',
            'blocked' => 'Candidature non admise — dossier clos.',
            default => 'L’équipe rend sa décision sur ce dossier.',
        };
        if ($isJourneyClosedNegative) {
            $instructionHint = 'Instruction terminée.';
            $suiviHint = 'Échanges clos.';
        } elseif ($status === 'reviewed' && $memberLinked) {
            $suiviHint = 'Échanges clos — rattachement effectué.';
        }
        if ($followup === 'pending') {
            $instructionHint = 'L’équipe a temporairement mis votre dossier en attente. Le traitement reprendra plus tard.';
            $suiviHint = 'Votre dossier est en attente. Consultez le fil ci-dessous : l’équipe vous y a laissé un message.';
            $decisionHint = 'La décision finale n’a pas encore été rendue — dossier temporairement mis en attente.';
        } elseif ($followup === 'interview') {
            $instructionHint = 'L’équipe souhaite échanger avec vous (entretien) avant de trancher.';
            $suiviHint = 'Un entretien a été proposé. Suivez les consignes sur le fil pour convenir d’un créneau.';
            $decisionHint = 'La décision finale viendra après l’entretien proposé par l’équipe.';
        }

        $adhesionHint = 'Une fois accepté, l’équipe vous indiquera comment finaliser votre accès membre.';
        if ($status === 'reviewed') {
            $adhesionHint = $memberLinked
                ? 'Compte membre rattaché.'
                : 'Après acceptation : création ou liaison de votre compte sur le portail selon les consignes de l’équipe.';
        } elseif ($isRejected) {
            $adhesionHint = 'Non applicable — candidature refusée.';
        } elseif ($isBlocked) {
            $adhesionHint = 'Non applicable — candidature non admise.';
        } elseif ($terminal) {
            $adhesionHint = 'Étape non requise dans l’état actuel du dossier.';
        }

        $steps = [
            [
                'id' => 'reception',
                'label' => 'Réception du dossier',
                'hint' => 'Votre candidature est enregistrée et prise en compte.',
                'state' => $stReception,
            ],
            [
                'id' => 'portal_moderation_filter',
                'label' => 'Contrôle automatique (portail)',
                'hint' => 'Chaque message ou pièce transmis sur le fil est vérifié selon les règles de sécurité de la communauté.',
                'state' => $stModFilter,
            ],
            [
                'id' => 'portal_moderation_incident',
                'label' => $incidentOpen ? 'Incident de modération détecté' : 'Modération et suites d’incident',
                'hint' => $incidentOpen
                    ? 'Un contenu refusé par le filtre automatique a déclenché des restrictions d’accès. L’équipe recrutement a été alertée.'
                    : 'En cas de contenu refusé ou d’accès restreint, l’équipe est informée et peut rétablir le suivi.',
                'state' => $stModIncident,
            ],
        ];
        if ($incidentResolved) {
            $steps[] = [
                'id' => 'portal_moderation_resolved',
                'label' => 'Incident corrigé — accès rétabli',
                'hint' => 'L’équipe a levé les restrictions liées à l’incident : les échanges peuvent reprendre normalement.',
                'state' => 'done',
            ];
        }
        $steps = array_merge($steps, [
            [
                'id' => 'instruction',
                'label' => 'Instruction et arbitrage',
                'hint' => $instructionHint,
                'state' => $stInstruction,
            ],
            [
                'id' => 'suivi',
                'label' => 'Suivi, pièces et messages',
                'hint' => $suiviHint,
                'state' => $stSuivi,
            ],
            [
                'id' => 'decision',
                'label' => 'Décision',
                'hint' => $decisionHint,
                'state' => $stDecision,
            ],
            [
                'id' => 'adhesion',
                'label' => 'Rattachement au compte membre',
                'hint' => $adhesionHint,
                'state' => $stAdhesion,
            ],
        ]);

        foreach ($steps as $i => $def) {
            $id = (string) ($def['id'] ?? '');
            if ($isJourneyClosedNegative) {
                // Mode refusé / non admis : parcours clos — pas d’infobulles ni de notes d’étape en cours.
                continue;
            }
            if ($id !== '' && isset($tooltips[$id]) && $tooltips[$id] !== '') {
                $steps[$i]['tooltip'] = $tooltips[$id];
            }
            if ($followup !== null && ($def['state'] ?? '') === 'current') {
                $steps[$i]['pause_kind'] = $followup;
                $steps[$i]['current_note'] = $followup === 'interview'
                    ? 'Entretien proposé — l’équipe souhaite échanger avec vous. Suivez les consignes sur le fil (et le courriel si vous l’avez reçu).'
                    : 'Dossier mis en attente — le traitement est temporairement suspendu. Vous serez informé de la suite sur ce fil ou par e-mail.';
            }
        }

        return $steps;
    }

    /**
     * @param list<array<string, mixed>> $timelineRows
     */
    private function timelineHasModerationMarker(array $timelineRows): bool
    {
        foreach ($timelineRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $meta = $row['metadata'] ?? null;
            $meta = is_array($meta) ? $meta : [];
            $fam = (string) ($meta['timeline_family'] ?? '');
            if ($fam === 'moderation' || $fam === 'moderation_override') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $timelineRows
     */
    private function firstModerationTimelineTs(array $timelineRows): ?int
    {
        $min = null;
        foreach ($timelineRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $meta = $row['metadata'] ?? null;
            $meta = is_array($meta) ? $meta : [];
            $fam = (string) ($meta['timeline_family'] ?? '');
            if ($fam !== 'moderation' && $fam !== 'moderation_override') {
                continue;
            }
            $ts = $this->parseTs((string) ($row['created_at'] ?? ''));
            if ($ts === null) {
                continue;
            }
            if ($min === null || $ts < $min) {
                $min = $ts;
            }
        }

        return $min;
    }

    /**
     * Horodatage du dernier événement d’une famille de timeline donnée (« moderation » ou « moderation_override »).
     *
     * @param list<array<string, mixed>> $timelineRows
     */
    private function lastModerationEventTs(array $timelineRows, string $family): ?int
    {
        $max = null;
        foreach ($timelineRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $meta = $row['metadata'] ?? null;
            $meta = is_array($meta) ? $meta : [];
            if ((string) ($meta['timeline_family'] ?? '') !== $family) {
                continue;
            }
            $ts = $this->parseTs((string) ($row['created_at'] ?? ''));
            if ($ts === null) {
                continue;
            }
            if ($max === null || $ts > $max) {
                $max = $ts;
            }
        }

        return $max;
    }

    /**
     * @param array<string, mixed> $enlistment
     * @param list<array<string, mixed>> $timelineRows
     * @param list<array<string, mixed>> $messages
     * @param list<array<string, mixed>> $attachments
     * @param 'pending'|'interview'|null $followup
     *
     * @return array<string, string>
     */
    private function buildStepTooltips(
        array $enlistment,
        array $timelineRows,
        array $messages,
        array $attachments,
        ?string $followup = null,
    ): array {
        $status = (string) ($enlistment['status'] ?? 'submitted');
        $terminal = in_array($status, ['reviewed', 'rejected', 'blocked'], true);

        $timelineFirstTs = [];
        foreach ($timelineRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $code = trim((string) ($row['step_code'] ?? ''));
            if ($code === '') {
                continue;
            }
            $ts = $this->parseTs((string) ($row['created_at'] ?? ''));
            if ($ts === null) {
                continue;
            }
            if (!isset($timelineFirstTs[$code]) || $ts < $timelineFirstTs[$code]) {
                $timelineFirstTs[$code] = $ts;
            }
        }

        $firstStaffMsgTs = null;
        $firstAnyMsgTs = null;
        foreach ($messages as $m) {
            if (!is_array($m)) {
                continue;
            }
            $ts = $this->parseTs((string) ($m['created_at'] ?? ''));
            if ($ts === null) {
                continue;
            }
            if ($firstAnyMsgTs === null || $ts < $firstAnyMsgTs) {
                $firstAnyMsgTs = $ts;
            }
            $kind = (string) ($m['entry_kind'] ?? '');
            if ($kind !== 'candidate') {
                if ($firstStaffMsgTs === null || $ts < $firstStaffMsgTs) {
                    $firstStaffMsgTs = $ts;
                }
            }
        }

        $firstAttachmentTs = null;
        foreach ($attachments as $a) {
            if (!is_array($a)) {
                continue;
            }
            $ts = $this->parseTs((string) ($a['created_at'] ?? ''));
            if ($ts === null) {
                continue;
            }
            if ($firstAttachmentTs === null || $ts < $firstAttachmentTs) {
                $firstAttachmentTs = $ts;
            }
        }

        $createdEnlistment = $this->parseTs((string) ($enlistment['created_at'] ?? ''));
        $reviewedAt = $this->parseTs((string) ($enlistment['reviewed_at'] ?? ''));

        $receptionTs = $createdEnlistment;
        if (isset($timelineFirstTs['reception'])) {
            $receptionTs = $this->minTs($receptionTs, $timelineFirstTs['reception']);
        }

        $instructionTs = $this->minTs(
            $timelineFirstTs['instruction'] ?? null,
            $firstStaffMsgTs,
        );

        $suiviPortalTs = null;
        foreach (['suivi', 'portal', 'communication'] as $c) {
            if (isset($timelineFirstTs[$c])) {
                $suiviPortalTs = $this->minTs($suiviPortalTs, $timelineFirstTs[$c]);
            }
        }
        $suiviTs = $this->minTs($suiviPortalTs, $firstAnyMsgTs, $firstAttachmentTs);

        $decisionTs = $this->minTs($timelineFirstTs['decision'] ?? null, $terminal ? $reviewedAt : null);

        $out = [];

        $out['reception'] = $receptionTs !== null
            ? 'Dossier reçu le ' . $this->formatFr($receptionTs) . '.'
            : 'Date de réception non disponible.';

        $firstActivityTs = $this->minTs($firstAnyMsgTs, $firstAttachmentTs);
        $modTs = $this->firstModerationTimelineTs($timelineRows);
        if ($firstActivityTs !== null) {
            $out['portal_moderation_filter'] = 'Premier élément passé par le contrôle automatique le ' . $this->formatFr($firstActivityTs) . ' (message ou pièce).';
        } else {
            $out['portal_moderation_filter'] = 'Aucun message ni pièce encore reçu sur le fil — le filtre s’appliquera dès votre premier envoi.';
        }
        if ($modTs !== null) {
            $out['portal_moderation_incident'] = 'Incident ou rétablissement lié à la modération enregistré le ' . $this->formatFr($modTs) . '.';
        } else {
            $out['portal_moderation_incident'] = 'Aucun incident de modération automatique signalé sur ce dossier pour l’instant.';
        }

        if ($instructionTs !== null) {
            $out['instruction'] = 'Premier retour côté recrutement le ' . $this->formatFr($instructionTs) . ' (message ou entrée du journal).';
        } else {
            $out['instruction'] = 'Aucune trace datée d’instruction pour l’instant (en attente d’un message de l’équipe ou d’une entrée interne).';
        }

        if ($suiviTs !== null) {
            $parts = [];
            if ($firstAnyMsgTs !== null && $firstAnyMsgTs === $suiviTs) {
                $parts[] = 'premier message sur le fil';
            }
            if ($firstAttachmentTs !== null && $firstAttachmentTs === $suiviTs) {
                $parts[] = 'première pièce jointe';
            }
            if ($suiviPortalTs !== null && $suiviPortalTs === $suiviTs) {
                $parts[] = 'activité portail enregistrée';
            }
            $detail = $parts !== [] ? ' (' . implode(', ', $parts) . ')' : '';
            $out['suivi'] = 'Première activité sur le suivi le ' . $this->formatFr($suiviTs) . $detail . '.';
        } else {
            $out['suivi'] = 'Pas encore d’échange ni de pièce datés sur ce dossier.';
        }

        if ($decisionTs !== null) {
            $out['decision'] = 'Décision enregistrée le ' . $this->formatFr($decisionTs) . '.';
        } elseif ($terminal) {
            $out['decision'] = 'Décision actuelle sans horodatage précis (voir le fil pour le détail).';
        } elseif ($followup === 'pending') {
            $out['decision'] = 'Décision finale non rendue — dossier temporairement mis en attente par l’équipe.';
        } elseif ($followup === 'interview') {
            $out['decision'] = 'Décision finale non rendue — un entretien a été proposé avant de trancher.';
        } else {
            $out['decision'] = 'Décision en attente — aucune date pour l’instant.';
        }

        if ($followup === 'pending') {
            $out['suivi'] = ($out['suivi'] ?? '') . ' L’équipe a indiqué une mise en attente du dossier.';
            $out['instruction'] = ($out['instruction'] ?? '') . ' Suite : dossier mis en attente.';
        } elseif ($followup === 'interview') {
            $out['suivi'] = ($out['suivi'] ?? '') . ' L’équipe a proposé un entretien.';
            $out['instruction'] = ($out['instruction'] ?? '') . ' Suite : entretien proposé.';
        }

        if ($status === 'reviewed') {
            $memberLinked = $this->isMemberAccountLinked($enlistment);
            if ($reviewedAt !== null) {
                $line = 'Candidature acceptée le ' . $this->formatFr($reviewedAt) . '.';
                if ($memberLinked) {
                    $line .= isset($timelineFirstTs['adhesion'])
                        ? ' Compte membre rattaché (repère le ' . $this->formatFr($timelineFirstTs['adhesion']) . ').'
                        : ' Compte membre rattaché.';
                } elseif (isset($timelineFirstTs['adhesion'])) {
                    $line .= ' Repère « rattachement » le ' . $this->formatFr($timelineFirstTs['adhesion']) . '.';
                } else {
                    $line .= ' Finalisez votre rattachement membre avec l’équipe.';
                }
                $out['adhesion'] = $line;
            } else {
                $out['adhesion'] = $memberLinked
                    ? 'Candidature acceptée — compte membre rattaché.'
                    : 'Candidature acceptée — horodatage non disponible. Finalisez le rattachement avec l’équipe.';
            }
        } elseif ($terminal) {
            $out['adhesion'] = 'Non applicable après une décision de refus ou de non-admission.';
        } else {
            $out['adhesion'] = 'Étape ouverte après acceptation — aucune date pour l’instant.';
        }

        return $out;
    }

    private function parseTs(string $raw): ?int
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        $t = strtotime($raw);

        return $t !== false && $t > 0 ? $t : null;
    }

    private function formatFr(int $timestamp): string
    {
        return date('d/m/Y à H:i', $timestamp);
    }

    private function minTs(?int ...$candidates): ?int
    {
        $min = null;
        foreach ($candidates as $c) {
            if ($c === null) {
                continue;
            }
            if ($min === null || $c < $min) {
                $min = $c;
            }
        }

        return $min;
    }
}
