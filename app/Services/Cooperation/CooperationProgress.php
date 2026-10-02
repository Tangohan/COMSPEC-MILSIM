<?php

declare(strict_types=1);

namespace App\Services\Cooperation;

use App\Support\CooperationDictionary;

/**
 * Parcours unique en cinq grandes étapes, calculé à partir des trois champs historiques
 * (status, cooperation_phase, operational_stage) et des participants.
 *
 *   1. Cadrage  →  2. Invitations & négociation  →  3. Préparation  →  4. Exécution  →  5. Clôture & REX
 *
 * Calcul pur (aucun accès base) : les URL et le contexte du lecteur sont fournis par l’appelant.
 */
final class CooperationProgress
{
    public const STEP_FRAMING = 1;
    public const STEP_INVITATIONS = 2;
    public const STEP_PREPARATION = 3;
    public const STEP_EXECUTION = 4;
    public const STEP_CLOSURE = 5;

    /** @return array<int, string> */
    public static function stepLabels(): array
    {
        return [
            self::STEP_FRAMING => 'Cadrage',
            self::STEP_INVITATIONS => 'Invitations & négociation',
            self::STEP_PREPARATION => 'Préparation',
            self::STEP_EXECUTION => 'Exécution',
            self::STEP_CLOSURE => 'Clôture & REX',
        ];
    }

    /** Étape courante (1 à 5) d’après les champs de la mission. */
    public static function currentStep(array $mission): int
    {
        $status = (string) ($mission['status'] ?? '');
        if ($status === 'draft') {
            return self::STEP_FRAMING;
        }
        if ($status === 'pending') {
            return self::STEP_INVITATIONS;
        }
        if ($status === 'archived') {
            return CooperationDictionary::effectivePhase($mission) === 'cancelled' ? self::STEP_INVITATIONS : self::STEP_CLOSURE;
        }
        $stage = (string) ($mission['operational_stage'] ?? '');

        return match ($stage) {
            'execution' => self::STEP_EXECUTION,
            'closed_aar', 'corrective_actions' => self::STEP_CLOSURE,
            default => self::STEP_PREPARATION,
        };
    }

    /**
     * Badge d’état (même rendu dans la liste et sur chaque page).
     *
     * @param array<string, mixed> $mission
     * @return array{key: string, label: string, variant: string}
     */
    public static function stateBadge(array $mission): array
    {
        $phase = CooperationDictionary::effectivePhase($mission);
        $variant = match ($phase) {
            'draft' => 'neutral',
            'proposed', 'negotiating', 'validated_pending' => 'warning',
            'preparing' => 'info',
            'active' => 'success',
            'suspended' => 'warning',
            'cancelled' => 'danger',
            default => 'neutral',
        };

        return ['key' => $phase, 'label' => CooperationDictionary::phaseLabel($phase), 'variant' => $variant];
    }

    /**
     * @param array<string, mixed> $mission
     * @param list<array<string, mixed>> $participants
     * @param array{
     *   viewer_tenant_id?: int,
     *   can_pilot?: bool,
     *   counter_pending?: bool,
     *   consent_done?: bool,
     *   rex_done?: bool,
     *   sitrep_count?: int,
     *   urls?: array<string, string>
     * } $ctx
     * @return array{
     *   current: int,
     *   total: int,
     *   short: string,
     *   current_label: string,
     *   next_label: string,
     *   heading: string,
     *   state: array{key: string, label: string, variant: string},
     *   cancelled: bool,
     *   suspended: bool,
     *   steps: list<array{index: int, label: string, done: bool, active: bool, blocked_reason: string}>,
     *   next_action: array{label: string, description: string, href: string, actor: string, actor_is_viewer: bool, tone: string}|null
     * }
     */
    public static function compute(array $mission, array $participants, array $ctx = []): array
    {
        $labels = self::stepLabels();
        $current = self::currentStep($mission);
        $phase = CooperationDictionary::effectivePhase($mission);
        $cancelled = $phase === 'cancelled';
        $suspended = $phase === 'suspended';
        $archived = (string) ($mission['status'] ?? '') === 'archived';
        $viewerTid = (int) ($ctx['viewer_tenant_id'] ?? 0);
        $canPilot = !empty($ctx['can_pilot']);
        $counterPending = !empty($ctx['counter_pending']);
        $consentDone = !array_key_exists('consent_done', $ctx) || !empty($ctx['consent_done']);
        $urls = is_array($ctx['urls'] ?? null) ? $ctx['urls'] : [];
        $u = static fn (string $k): string => (string) ($urls[$k] ?? '');

        $support = self::supportName($mission, $participants);
        $ready = CooperationTransitionRules::launchReadiness($mission, $participants, $counterPending);
        $viewer = CooperationTransitionRules::participantFor($viewerTid, $participants);
        $viewerStatus = (string) ($viewer['status'] ?? '');

        $next = null;
        $blocked = '';

        $mk = static function (string $label, string $description, string $href, string $actor, bool $isViewer, string $tone = 'emerald'): array {
            return [
                'label' => $label,
                'description' => $description,
                'href' => $href,
                'actor' => $actor,
                'actor_is_viewer' => $isViewer,
                'tone' => $tone,
            ];
        };

        if ($cancelled) {
            $motive = trim((string) ($mission['closure_motive'] ?? ''));
            $blocked = 'Proposition annulée' . ($motive !== '' ? ' — ' . $motive : '') . '.';
        } elseif ($suspended) {
            $blocked = 'Coopération suspendue : les échanges et la conduite sont gelés jusqu’à la reprise.';
            $next = $canPilot
                ? $mk('Reprendre la coopération', 'Lève la suspension : le fil commun et la conduite redeviennent disponibles.', $u('conduct'), 'Vous', true, 'amber')
                : $mk('En attente de reprise', 'L’unité support doit lever la suspension.', $u('show'), $support, false, 'slate');
        } elseif ($viewerStatus === 'invited' && !$archived) {
            $next = $mk('Répondre à l’invitation', 'Acceptez pour rejoindre la coopération, ou refusez en indiquant éventuellement un motif.', $u('invitation'), 'Vous', true, 'emerald');
        } elseif ($current === self::STEP_FRAMING) {
            $next = $canPilot
                ? $mk('Inviter une première unité', 'Complétez le cadrage si besoin, puis invitez les unités partenaires.', $u('participants'), 'Vous', true)
                : $mk('Cadrage en cours', 'L’unité support prépare la proposition.', $u('show'), $support, false, 'slate');
        } elseif ($current === self::STEP_INVITATIONS) {
            $answered = count($ready['accepted']) + count($ready['ignored']);
            $totalInvited = $answered + count($ready['pending']);
            if ($counterPending) {
                $blocked = 'Une contre-proposition attend la réponse de l’unité support.';
                $next = $canPilot
                    ? $mk('Traiter la contre-proposition', 'Intégrez-la ou refusez-la avant de lancer.', $u('negotiate'), 'Vous', true, 'amber')
                    : $mk('Contre-proposition en cours d’examen', 'L’unité support doit l’intégrer ou la refuser.', $u('negotiate'), $support, false, 'slate');
            } elseif ($ready['pending'] !== []) {
                $blocked = CooperationTransitionRules::reasonLabel('invitations_pending');
                $desc = sprintf(
                    '%d unité%s sur %d %s accepté — en attente de %s.',
                    count($ready['accepted']),
                    count($ready['accepted']) > 1 ? 's' : '',
                    $totalInvited,
                    count($ready['accepted']) > 1 ? 'ont' : 'a',
                    implode(', ', $ready['pending'])
                );
                $next = $canPilot
                    ? $mk('Relancer', $desc, $u('participants'), implode(', ', $ready['pending']), false, 'amber')
                    : $mk('En attente des autres unités', $desc, $u('show'), implode(', ', $ready['pending']), false, 'slate');
            } elseif ($ready['accepted'] === []) {
                $blocked = CooperationTransitionRules::reasonLabel('no_partner_accepted');
                $next = $canPilot
                    ? $mk('Inviter d’autres unités', 'Aucune unité n’a encore accepté : le lancement nécessite au moins un partenaire confirmé.', $u('participants'), 'Vous', true, 'amber')
                    : null;
            } else {
                $next = $canPilot
                    ? $mk('Lancer la coopération', 'Toutes les unités ont répondu (' . implode(', ', $ready['accepted']) . ' engagée' . (count($ready['accepted']) > 1 ? 's' : '') . ').', $u('launch'), 'Vous', true)
                    : $mk('En attente du lancement', 'Toutes les unités ont répondu ; l’unité support peut lancer la coopération.', $u('show'), $support, false, 'slate');
            }
        } elseif ($current === self::STEP_PREPARATION || $current === self::STEP_EXECUTION) {
            $stage = (string) ($mission['operational_stage'] ?? 'opord_draft');
            if (!$consentDone && in_array($viewerStatus, ['active'], true)) {
                $next = $mk('Valider votre autorisation de partage', 'Indispensable pour lire et écrire sur l’espace commun (code reçu par e-mail).', $u('consent'), 'Vous', true, 'amber');
            } elseif ($current === self::STEP_PREPARATION) {
                $opordEmpty = trim((string) ($mission['opord_text'] ?? '')) === '';
                if ($canPilot && $stage === 'command_validation') {
                    $next = $mk('Passer en exécution', 'Le commandement a validé : ouvrez les points de situation.', $u('conduct'), 'Vous', true);
                } elseif ($canPilot && $opordEmpty) {
                    $blocked = 'L’ordre d’opération doit être rédigé avant la validation du commandement.';
                    $next = $mk('Rédiger l’ordre d’opération', 'Intentions, objectif, organisation : base de la validation du commandement.', $u('conduct'), 'Vous', true);
                } elseif ($canPilot) {
                    $next = $mk('Demander la validation du commandement', 'L’ordre d’opération est rédigé.', $u('conduct'), 'Vous', true);
                } else {
                    $next = $mk('Préparation en cours', 'L’unité support rédige l’ordre d’opération ; échangez sur l’espace commun.', $u('exchange'), $support, false, 'slate');
                }
            } else {
                $aarEmpty = trim((string) ($mission['aar_summary'] ?? '')) === '';
                $next = $canPilot
                    ? ($aarEmpty
                        ? $mk('Ajouter un point de situation', (int) ($ctx['sitrep_count'] ?? 0) . ' point(s) de situation enregistré(s). Rédigez le bilan pour passer à la clôture.', $u('conduct'), 'Vous', true)
                        : $mk('Passer au bilan', 'Le bilan est rédigé : clôturez la phase d’exécution.', $u('conduct'), 'Vous', true))
                    : $mk('Suivre l’exécution', 'Points de situation et échanges sur l’espace commun.', $u('exchange'), $support, false, 'slate');
            }
        } else {
            // Étape 5
            if (!$archived) {
                $next = $canPilot
                    ? $mk('Clôturer la coopération', 'Retire les accès partagés, ferme le fil commun et ouvre les retours d’expérience.', $u('archive'), 'Vous', true)
                    : $mk('Clôture en préparation', 'L’unité support finalise le bilan et les actions correctives.', $u('show'), $support, false, 'slate');
            } elseif ($viewerStatus === 'active' && empty($ctx['rex_done'])) {
                $next = $mk('Rédiger votre retour d’expérience', 'Quelques minutes pour capitaliser : ce qui a fonctionné, les difficultés, vos recommandations.', $u('rex'), 'Vous', true);
            }
        }

        $steps = [];
        foreach ($labels as $i => $label) {
            if ($cancelled) {
                $isDone = $i < $current;
            } elseif ($archived) {
                // Clôturée : tout est fait, sauf l’étape 5 tant qu’un REX est attendu du lecteur.
                $isDone = $i < self::STEP_CLOSURE || $next === null;
            } else {
                $isDone = $i < $current;
            }
            $isActive = !$isDone && $i === $current;
            $steps[] = [
                'index' => $i,
                'label' => $label,
                'done' => $isDone,
                'active' => $isActive,
                'blocked_reason' => $isActive ? $blocked : '',
            ];
        }

        $state = self::stateBadge($mission);
        $nextLabel = $current < self::STEP_CLOSURE ? $labels[$current + 1] : '';
        $heading = $cancelled
            ? 'Proposition annulée à l’étape ' . $current . ' sur 5 — ' . $labels[$current]
            : 'Étape ' . $current . ' sur 5 — ' . $labels[$current];

        return [
            'current' => $current,
            'total' => 5,
            'short' => $current . '/5',
            'current_label' => $labels[$current],
            'next_label' => $cancelled || ($archived && $next === null) ? '' : $nextLabel,
            'heading' => $heading,
            'state' => $state,
            'cancelled' => $cancelled,
            'suspended' => $suspended,
            'steps' => $steps,
            'next_action' => $next,
        ];
    }

    /**
     * Le lecteur doit-il agir ? (pastille « action requise » dans la liste)
     *
     * @param array<string, mixed>|null $nextAction
     */
    public static function actionRequiredForViewer(?array $nextAction): bool
    {
        return is_array($nextAction) && !empty($nextAction['actor_is_viewer']);
    }

    /**
     * @param array<string, mixed> $mission
     * @param list<array<string, mixed>> $participants
     */
    private static function supportName(array $mission, array $participants): string
    {
        $lead = (int) ($mission['created_by_tenant_id'] ?? 0);
        foreach ($participants as $p) {
            if ((int) ($p['tenant_id'] ?? 0) === $lead || (string) ($p['role'] ?? '') === 'lead') {
                return (string) ($p['tenant_name'] ?? 'Unité support');
            }
        }

        return 'Unité support';
    }
}
