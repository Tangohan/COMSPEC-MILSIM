<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Support\RoleplayBilanPolicy;
use App\Support\RoleplayDeadlinePolicy;
use DateTimeImmutable;
use Throwable;

/**
 * Synthèse lisible du suivi d’un membre : étape d’immersion, échéances et parcours.
 *
 * @phpstan-type Deadline array{key: string, title: string, date: ?string, date_label: ?string, fallback: string, overdue: bool, note: ?string, accent: string}
 * @phpstan-type PhaseSummary array{label: string, next_label: ?string, effect: string, items: list<array<string, mixed>>, eligible: bool, is_last: bool, remaining: int}
 */
final class PersonnelMemberFollowupSnapshot
{
    /**
     * @param array<string, mixed> $profile
     * @param array<string, mixed> $cfg
     * @param array<string, mixed>|null $phaseChecklist
     * @return array{
     *     visible: bool,
     *     show_immersion: bool,
     *     show_parcours: bool,
     *     progress: ?int,
     *     stage: string,
     *     status: string,
     *     track: string,
     *     function: string,
     *     origin_label: string,
     *     tutor_label: ?string,
     *     notes: string,
     *     deadlines: list<Deadline>,
     *     overdue_count: int,
     *     phase: ?PhaseSummary,
     *     probation: ?array{label: string, ends_label: string, active: bool},
     *     attention: bool,
     *     attention_items: list<string>
     * }
     */
    public static function build(
        array $profile,
        array $cfg,
        ?array $phaseChecklist,
        ?string $joinedAt,
        ?string $tutorLabel = null
    ): array {
        $enabled = !empty($cfg['enabled']);
        $dueOpts = RoleplayFollowupSettings::dueListOptions($cfg);
        $deadlines = [];

        if ($enabled) {
            if (!empty($dueOpts['show_interview'])) {
                $deadlines[] = self::deadline(
                    'interview',
                    'Prochain entretien individuel',
                    (string) ($profile['rp_next_interview_date'] ?? ''),
                    'À planifier',
                    null
                );
            }
            if (!empty($dueOpts['show_medical'])) {
                $deadlines[] = self::deadline(
                    'medical',
                    'Visite médicale',
                    (string) ($profile['rp_medical_due_date'] ?? ''),
                    'Échéance non renseignée',
                    self::medicalNote($profile)
                );
            }
            if (!empty($dueOpts['show_rotation'])) {
                $kind = RoleplayDeadlinePolicy::rotationKindLabel((string) ($profile['rp_rotation_kind'] ?? 'service'));
                $deadlines[] = self::deadline(
                    'rotation',
                    'Rotation',
                    (string) ($profile['rp_service_rotation_date'] ?? ''),
                    'Non planifiée',
                    $kind !== '' ? ('Objet : ' . $kind) : null
                );
            }
            if (!empty($dueOpts['show_bilan'])) {
                $lastReview = trim((string) ($profile['rp_last_review_at'] ?? '')) ?: null;
                $due = RoleplayBilanPolicy::nextReviewDueAt($joinedAt, $lastReview, $dueOpts['cadence']);
                $overdue = RoleplayBilanPolicy::isOverdue($joinedAt, $lastReview, $dueOpts['cadence']);
                $dueStr = $due?->format('Y-m-d');
                $deadlines[] = [
                    'key' => 'bilan',
                    'title' => 'Prochain bilan',
                    'date' => $dueStr,
                    'date_label' => self::dateFr($dueStr),
                    'fallback' => 'Date à confirmer',
                    'overdue' => $overdue,
                    'note' => $lastReview !== null
                        ? ('Dernier bilan : ' . (self::dateFr($lastReview) ?? $lastReview))
                        : 'Aucun bilan encore enregistré',
                    'accent' => $overdue
                        ? 'border-rose-300 bg-rose-50/70'
                        : 'border-slate-200 bg-slate-50/70',
                ];
            }
        }

        $phase = self::phaseSummary($phaseChecklist);
        $showParcours = $phase !== null;
        $probation = $enabled ? self::probation($profile, $cfg, $joinedAt) : null;

        $attentionItems = [];
        foreach ($deadlines as $d) {
            if (!empty($d['overdue'])) {
                $attentionItems[] = $d['title'] . ' : échéance dépassée';
            }
        }
        if ($phase !== null && ($phase['remaining'] ?? 0) > 0 && ($phase['next_label'] ?? null) !== null) {
            $attentionItems[] = 'Il reste des conditions pour passer à « ' . $phase['next_label'] . ' ».';
        }

        $originRaw = trim((string) ($profile['rp_recruitment_origin'] ?? ''));
        $originLabel = match ($originRaw) {
            'internal' => 'Interne',
            'external' => 'Externe',
            default => '',
        };

        $progressRaw = $profile['rp_followup_progress'] ?? null;
        $progress = $progressRaw !== null && $progressRaw !== ''
            ? max(0, min(100, (int) $progressRaw))
            : null;

        $tutor = $tutorLabel !== null ? trim($tutorLabel) : '';

        return [
            'visible' => $enabled || $showParcours,
            'show_immersion' => $enabled,
            'show_parcours' => $showParcours,
            'progress' => $progress,
            'stage' => trim((string) ($profile['rp_followup_stage'] ?? '')),
            'status' => trim((string) ($profile['rp_followup_status'] ?? '')),
            'track' => trim((string) ($profile['rp_recruitment_stream'] ?? '')),
            'function' => trim((string) ($profile['rp_operational_function'] ?? '')),
            'origin_label' => $originLabel,
            'tutor_label' => $tutor !== '' ? $tutor : null,
            'notes' => trim((string) ($profile['rp_followup_notes'] ?? '')),
            'deadlines' => $deadlines,
            'overdue_count' => count(array_filter($deadlines, static fn (array $d): bool => !empty($d['overdue']))),
            'phase' => $phase,
            'probation' => $probation,
            'attention' => $attentionItems !== [],
            'attention_items' => $attentionItems,
        ];
    }

    /**
     * @param array<string, mixed> $profile
     */
    private static function medicalNote(array $profile): string
    {
        $medical = trim((string) (($profile['rp_blood_type_confirmed'] ?? '') !== ''
            ? $profile['rp_blood_type_confirmed']
            : ($profile['blood_type'] ?? '')));
        $arma = trim((string) ($profile['rp_arma_blood_type'] ?? ''));
        if ($medical !== '') {
            $note = 'Groupe sanguin : ' . $medical;
            if ($arma !== '' && $arma !== $medical) {
                $note .= ' · En jeu : ' . $arma;
            }

            return $note;
        }
        if ($arma !== '') {
            return 'En jeu : ' . $arma . ' — à confirmer au bilan';
        }

        return 'Groupe sanguin à confirmer au bilan';
    }

    /**
     * @return Deadline
     */
    private static function deadline(string $key, string $title, string $raw, string $fallback, ?string $note): array
    {
        $raw = trim($raw);
        $date = $raw !== '' ? $raw : null;
        if ($date !== null && strlen($date) > 10) {
            $date = substr($date, 0, 10);
        }
        $overdue = false;
        if ($date !== null && preg_match('/^\d{4}-\d{2}-\d{2}/', $date) === 1) {
            $overdue = $date < (new DateTimeImmutable('today'))->format('Y-m-d');
        }

        return [
            'key' => $key,
            'title' => $title,
            'date' => $date,
            'date_label' => self::dateFr($date),
            'fallback' => $fallback,
            'overdue' => $overdue,
            'note' => $note,
            'accent' => $overdue
                ? 'border-rose-300 bg-rose-50/70'
                : ($key === 'interview' ? 'border-emerald-300 bg-emerald-50/60' : 'border-slate-200 bg-slate-50/70'),
        ];
    }

    /**
     * @param array<string, mixed>|null $phaseChecklist
     * @return PhaseSummary|null
     */
    private static function phaseSummary(?array $phaseChecklist): ?array
    {
        if ($phaseChecklist === null) {
            return null;
        }
        $phase = is_array($phaseChecklist['phase'] ?? null) ? $phaseChecklist['phase'] : null;
        $label = trim((string) ($phase['label'] ?? ''));
        if ($label === '') {
            return null;
        }
        $next = is_array($phaseChecklist['next'] ?? null) ? $phaseChecklist['next'] : null;
        $nextLabel = $next !== null ? (trim((string) ($next['label'] ?? '')) ?: null) : null;
        $items = is_array($phaseChecklist['evaluation']['items'] ?? null)
            ? $phaseChecklist['evaluation']['items']
            : [];
        $remaining = 0;
        foreach ($items as $item) {
            if (is_array($item) && empty($item['passed'])) {
                $remaining++;
            }
        }

        return [
            'label' => $label,
            'next_label' => $nextLabel,
            'effect' => (string) ($phaseChecklist['effect'] ?? 'manual_gate'),
            'items' => $items,
            'eligible' => !empty($phaseChecklist['evaluation']['eligible']),
            'is_last' => $nextLabel === null,
            'remaining' => $remaining,
        ];
    }

    /**
     * @param array<string, mixed> $profile
     * @param array<string, mixed> $cfg
     * @return array{label: string, ends_label: string, active: bool}|null
     */
    private static function probation(array $profile, array $cfg, ?string $joinedAt): ?array
    {
        $prob = is_array($cfg['probation'] ?? null) ? $cfg['probation'] : [];
        $days = max(0, (int) ($prob['duration_days'] ?? 0));
        if ($days < 1) {
            return null;
        }
        $startRaw = trim((string) ($profile['enlistment_date'] ?? ''));
        if ($startRaw === '') {
            $startRaw = trim((string) $joinedAt);
        }
        if ($startRaw === '') {
            return null;
        }
        try {
            $start = new DateTimeImmutable($startRaw);
            $end = $start->modify('+' . $days . ' days');
        } catch (Throwable) {
            return null;
        }
        $today = new DateTimeImmutable('today');
        $active = $end >= $today;
        if (!$active) {
            return null;
        }

        return [
            'label' => trim((string) ($prob['bilan_label'] ?? '')) ?: 'Période d’essai',
            'ends_label' => $end->format('d/m/Y'),
            'active' => true,
        ];
    }

    private static function dateFr(?string $date): ?string
    {
        $raw = trim((string) $date);
        if ($raw === '') {
            return null;
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return null;
        }

        return date('d/m/Y', $ts);
    }
}
