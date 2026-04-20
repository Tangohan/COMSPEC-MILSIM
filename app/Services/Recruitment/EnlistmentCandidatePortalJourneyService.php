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
     * @param array<string, mixed> $enlistment
     * @param list<array<string, mixed>> $timelineRows
     * @param list<array<string, mixed>> $messages
     * @param list<array<string, mixed>> $attachments
     *
     * @return list<array{id: string, label: string, hint: string, state: string, tooltip?: string}>
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

        $hasRecruiterMessage = false;
        foreach ($messages as $m) {
            if (is_array($m) && (string) ($m['entry_kind'] ?? '') !== 'candidate') {
                $hasRecruiterMessage = true;
                break;
            }
        }

        $hasPortalActivity = $messages !== [] || $attachments !== [];
        $hasModerationTimeline = $this->timelineHasModerationMarker($timelineRows);

        $stReception = 'done';
        $stInstruction = 'upcoming';
        $stSuivi = 'upcoming';
        $stDecision = 'upcoming';
        $stAdhesion = 'upcoming';
        $stModFilter = $hasPortalActivity ? 'done' : 'upcoming';
        $stModIncident = $hasModerationTimeline ? 'done' : 'upcoming';

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
            $stAdhesion = 'current';
        } elseif ($status === 'rejected' || $status === 'blocked') {
            $stInstruction = 'done';
            $stSuivi = 'done';
            $stDecision = 'done';
        }

        $tooltips = $this->buildStepTooltips($enlistment, $timelineRows, $messages, $attachments);

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
                'label' => 'Modération et suites d’incident',
                'hint' => 'En cas de contenu refusé ou d’accès restreint, l’équipe est informée et peut rétablir le suivi.',
                'state' => $stModIncident,
            ],
            [
                'id' => 'instruction',
                'label' => 'Instruction et arbitrage',
                'hint' => 'L’équipe examine votre dossier et tranche les suites possibles.',
                'state' => $stInstruction,
            ],
            [
                'id' => 'suivi',
                'label' => 'Suivi, pièces et messages',
                'hint' => 'Échanges sur le fil, envoi de pièces ou d’enregistrements vocaux si l’équipe l’autorise.',
                'state' => $stSuivi,
            ],
            [
                'id' => 'decision',
                'label' => 'Décision',
                'hint' => match ($status) {
                    'reviewed' => 'Dossier accepté par la communauté.',
                    'rejected' => 'Dossier refusé. Vous pouvez encore écrire sur le fil pour des précisions.',
                    'blocked' => 'Dossier classé non admis.',
                    default => 'L’équipe rend sa décision sur ce dossier.',
                },
                'state' => $stDecision,
            ],
            [
                'id' => 'adhesion',
                'label' => 'Rattachement au compte membre',
                'hint' => $status === 'reviewed'
                    ? 'Après acceptation : création ou liaison de votre compte sur le portail selon les consignes de l’équipe.'
                    : ($terminal && $status !== 'reviewed'
                        ? 'Étape non requise dans l’état actuel du dossier.'
                        : 'Une fois accepté, l’équipe vous indiquera comment finaliser votre accès membre.'),
                'state' => $stAdhesion,
            ],
        ];

        foreach ($steps as $i => $def) {
            $id = (string) ($def['id'] ?? '');
            if ($id !== '' && isset($tooltips[$id]) && $tooltips[$id] !== '') {
                $steps[$i]['tooltip'] = $tooltips[$id];
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
     * @param array<string, mixed> $enlistment
     * @param list<array<string, mixed>> $timelineRows
     * @param list<array<string, mixed>> $messages
     * @param list<array<string, mixed>> $attachments
     *
     * @return array<string, string>
     */
    private function buildStepTooltips(
        array $enlistment,
        array $timelineRows,
        array $messages,
        array $attachments,
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
        } else {
            $out['decision'] = 'Décision en attente — aucune date pour l’instant.';
        }

        if ($status === 'reviewed') {
            if ($reviewedAt !== null) {
                $line = 'Candidature acceptée le ' . $this->formatFr($reviewedAt) . '.';
                if (isset($timelineFirstTs['adhesion'])) {
                    $line .= ' Repère « rattachement » le ' . $this->formatFr($timelineFirstTs['adhesion']) . '.';
                } else {
                    $line .= ' Finalisez votre rattachement membre avec l’équipe.';
                }
                $out['adhesion'] = $line;
            } else {
                $out['adhesion'] = 'Candidature acceptée — horodatage non disponible. Finalisez le rattachement avec l’équipe.';
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
