<?php

declare(strict_types=1);

namespace App\Services\Cooperation;

use App\Support\CooperationDictionary;

/**
 * Règles de transition du cycle de vie d’une coopération (sans accès base : testables unitairement).
 *
 * Les décisions s’appuient sur la ligne mission (status, cooperation_phase) et la liste des
 * participants (tenant_id, tenant_name, role, status). Les contrôles RBAC (pilotage, habilitations)
 * restent dans le contrôleur et s’ajoutent à ces règles.
 */
final class CooperationTransitionRules
{
    /** Phases où plus aucune action de cycle de vie n’est possible. */
    private const TERMINAL_PHASES = ['closed', 'archived', 'cancelled'];

    /** @param array<string, mixed> $mission */
    public static function isTerminal(array $mission): bool
    {
        if ((string) ($mission['status'] ?? '') === 'archived') {
            return true;
        }

        return in_array(CooperationDictionary::effectivePhase($mission), self::TERMINAL_PHASES, true);
    }

    /**
     * Une invitation est possible tant que la coopération n’est ni clôturée ni annulée.
     * Pendant l’exécution (status active), il s’agit d’un renfort : confirmation explicite requise.
     *
     * @param array<string, mixed> $mission
     * @return array{allowed: bool, reinforcement: bool, reason: string}
     */
    public static function invitation(array $mission): array
    {
        if (self::isTerminal($mission)) {
            return ['allowed' => false, 'reinforcement' => false, 'reason' => 'mission_terminal'];
        }
        $status = (string) ($mission['status'] ?? '');
        if (!in_array($status, ['draft', 'pending', 'active'], true)) {
            return ['allowed' => false, 'reinforcement' => false, 'reason' => 'mission_terminal'];
        }

        return ['allowed' => true, 'reinforcement' => $status === 'active', 'reason' => ''];
    }

    /**
     * Unité invitable pour cette coopération ?
     *
     * @param list<array<string, mixed>> $participants
     * @return array{allowed: bool, reason: string}
     */
    public static function canInviteTenant(int $tenantId, int $inviterTenantId, array $participants): array
    {
        if ($tenantId <= 0 || $tenantId === $inviterTenantId) {
            return ['allowed' => false, 'reason' => 'invalid_tenant'];
        }
        $p = self::participantFor($tenantId, $participants);
        if ($p === null) {
            return ['allowed' => true, 'reason' => ''];
        }
        $st = (string) ($p['status'] ?? '');
        if ($st === 'invited') {
            return ['allowed' => false, 'reason' => 'already_invited'];
        }
        if ($st === 'active') {
            return ['allowed' => false, 'reason' => 'already_engaged'];
        }

        // declined / left : une nouvelle invitation est possible.
        return ['allowed' => true, 'reason' => ''];
    }

    /**
     * Liste d’unités pour le sélecteur d’invitation : exclut sa propre unité, marque celles déjà engagées.
     *
     * @param list<array<string, mixed>> $tenants lignes {id, name}
     * @param list<array<string, mixed>> $participants
     * @return list<array{id: int, name: string, selectable: bool, state: string, state_label: string}>
     */
    public static function invitablePicker(array $tenants, array $participants, int $inviterTenantId): array
    {
        $out = [];
        foreach ($tenants as $t) {
            $id = (int) ($t['id'] ?? 0);
            if ($id <= 0 || $id === $inviterTenantId) {
                continue;
            }
            $p = self::participantFor($id, $participants);
            $state = $p !== null ? (string) ($p['status'] ?? '') : '';
            $selectable = $state === '' || in_array($state, ['declined', 'left'], true);
            $out[] = [
                'id' => $id,
                'name' => (string) ($t['name'] ?? ''),
                'selectable' => $selectable,
                'state' => $state,
                'state_label' => $state !== '' ? CooperationDictionary::participantStateLabel($state) : '',
            ];
        }
        usort($out, static function (array $a, array $b): int {
            if ($a['selectable'] !== $b['selectable']) {
                return $a['selectable'] ? -1 : 1;
            }

            return strcasecmp($a['name'], $b['name']);
        });

        return $out;
    }

    /**
     * Une unité peut-elle répondre (accepter / refuser) à une invitation ?
     *
     * @param array<string, mixed> $mission
     * @param list<array<string, mixed>> $participants
     * @return array{allowed: bool, reason: string}
     */
    public static function canRespondToInvitation(array $mission, int $tenantId, array $participants): array
    {
        if (self::isTerminal($mission)) {
            return ['allowed' => false, 'reason' => 'mission_terminal'];
        }
        $p = self::participantFor($tenantId, $participants);
        if ($p === null) {
            return ['allowed' => false, 'reason' => 'not_invited'];
        }
        if ((string) ($p['role'] ?? '') === 'lead') {
            return ['allowed' => false, 'reason' => 'not_invited'];
        }
        if ((string) ($p['status'] ?? '') !== 'invited') {
            return ['allowed' => false, 'reason' => 'no_pending_invitation'];
        }

        return ['allowed' => true, 'reason' => ''];
    }

    /**
     * Le lancement exige au moins un partenaire confirmé et aucune invitation en attente.
     * Les unités ayant refusé ou retirées sont ignorées (et listées comme telles).
     *
     * @param array<string, mixed> $mission
     * @param list<array<string, mixed>> $participants
     * @return array{
     *   ok: bool,
     *   reason: string,
     *   accepted: list<string>,
     *   pending: list<string>,
     *   ignored: list<string>
     * }
     */
    public static function launchReadiness(array $mission, array $participants, bool $counterProposalPending = false): array
    {
        $accepted = [];
        $pending = [];
        $ignored = [];
        foreach ($participants as $p) {
            if ((string) ($p['role'] ?? '') === 'lead') {
                continue;
            }
            $name = (string) ($p['tenant_name'] ?? ('Unité #' . (int) ($p['tenant_id'] ?? 0)));
            match ((string) ($p['status'] ?? '')) {
                'active' => $accepted[] = $name,
                'invited' => $pending[] = $name,
                default => $ignored[] = $name,
            };
        }
        $base = ['accepted' => $accepted, 'pending' => $pending, 'ignored' => $ignored];

        if (self::isTerminal($mission)) {
            return ['ok' => false, 'reason' => 'mission_terminal'] + $base;
        }
        if ((string) ($mission['status'] ?? '') === 'active') {
            return ['ok' => false, 'reason' => 'already_active'] + $base;
        }
        if ($counterProposalPending) {
            return ['ok' => false, 'reason' => 'counter_proposal_pending'] + $base;
        }
        if ($pending !== []) {
            return ['ok' => false, 'reason' => 'invitations_pending'] + $base;
        }
        if ($accepted === []) {
            return ['ok' => false, 'reason' => 'no_partner_accepted'] + $base;
        }

        return ['ok' => true, 'reason' => ''] + $base;
    }

    /**
     * Retrait d’une unité (invitation en attente ou unité engagée). Jamais l’unité support.
     *
     * @param array<string, mixed> $mission
     * @param list<array<string, mixed>> $participants
     * @return array{allowed: bool, reason: string, was: string}
     */
    public static function canRemovePartner(array $mission, int $targetTenantId, int $actorTenantId, array $participants): array
    {
        if (self::isTerminal($mission)) {
            return ['allowed' => false, 'reason' => 'mission_terminal', 'was' => ''];
        }
        $p = self::participantFor($targetTenantId, $participants);
        if ($p === null) {
            return ['allowed' => false, 'reason' => 'not_participant', 'was' => ''];
        }
        $role = (string) ($p['role'] ?? '');
        $st = (string) ($p['status'] ?? '');
        if ($role === 'lead' || (int) ($mission['created_by_tenant_id'] ?? 0) === $targetTenantId) {
            return ['allowed' => false, 'reason' => 'cannot_remove_lead', 'was' => $st];
        }
        if ($targetTenantId === $actorTenantId) {
            return ['allowed' => false, 'reason' => 'cannot_remove_self', 'was' => $st];
        }
        if (!in_array($st, ['invited', 'active'], true)) {
            return ['allowed' => false, 'reason' => 'already_out', 'was' => $st];
        }

        return ['allowed' => true, 'reason' => '', 'was' => $st];
    }

    /**
     * Annulation d’une proposition : avant lancement uniquement, motif obligatoire.
     *
     * @param array<string, mixed> $mission
     * @return array{allowed: bool, reason: string}
     */
    public static function canCancelProposal(array $mission, string $motive): array
    {
        if (self::isTerminal($mission)) {
            return ['allowed' => false, 'reason' => 'mission_terminal'];
        }
        if (!in_array((string) ($mission['status'] ?? ''), ['draft', 'pending'], true)) {
            return ['allowed' => false, 'reason' => 'already_active'];
        }
        if (mb_strlen(trim($motive)) < 3) {
            return ['allowed' => false, 'reason' => 'motive_required'];
        }

        return ['allowed' => true, 'reason' => ''];
    }

    /**
     * Clôture : coopération lancée (ou brouillon jamais envoyé), jamais deux fois.
     *
     * @param array<string, mixed> $mission
     * @return array{allowed: bool, reason: string}
     */
    public static function canClose(array $mission): array
    {
        if (self::isTerminal($mission)) {
            return ['allowed' => false, 'reason' => 'mission_terminal'];
        }
        if ((string) ($mission['status'] ?? '') === 'pending') {
            return ['allowed' => false, 'reason' => 'use_cancel'];
        }

        return ['allowed' => true, 'reason' => ''];
    }

    /** Délai minimal entre deux relances d’une même unité. */
    public const REMINDER_MIN_INTERVAL_SECONDS = 86400;

    /**
     * Suspension : coopération lancée et non déjà suspendue, motif obligatoire.
     *
     * @param array<string, mixed> $mission
     * @return array{allowed: bool, reason: string}
     */
    public static function canSuspend(array $mission, string $motive): array
    {
        if (self::isTerminal($mission)) {
            return ['allowed' => false, 'reason' => 'mission_terminal'];
        }
        if ((string) ($mission['status'] ?? '') !== 'active') {
            return ['allowed' => false, 'reason' => 'not_launched'];
        }
        if (CooperationDictionary::effectivePhase($mission) === 'suspended') {
            return ['allowed' => false, 'reason' => 'already_suspended'];
        }
        if (mb_strlen(trim($motive)) < 3) {
            return ['allowed' => false, 'reason' => 'motive_required'];
        }

        return ['allowed' => true, 'reason' => ''];
    }

    /**
     * @param array<string, mixed> $mission
     * @return array{allowed: bool, reason: string, phase: string}
     */
    public static function canResume(array $mission): array
    {
        if (self::isTerminal($mission) || CooperationDictionary::effectivePhase($mission) !== 'suspended') {
            return ['allowed' => false, 'reason' => 'not_suspended', 'phase' => ''];
        }

        return ['allowed' => true, 'reason' => '', 'phase' => self::phaseForStage((string) ($mission['operational_stage'] ?? ''))];
    }

    /** Phase cohérente avec l’étape de conduite (préparation tant que l’exécution n’a pas commencé). */
    public static function phaseForStage(string $operationalStage): string
    {
        return in_array($operationalStage, ['execution', 'closed_aar', 'corrective_actions'], true) ? 'active' : 'preparing';
    }

    /**
     * Conduite (étape, points de situation) : coopération non clôturée et non suspendue.
     *
     * @param array<string, mixed> $mission
     * @return array{allowed: bool, reason: string}
     */
    public static function canConduct(array $mission): array
    {
        if (self::isTerminal($mission)) {
            return ['allowed' => false, 'reason' => 'mission_terminal'];
        }
        if (CooperationDictionary::effectivePhase($mission) === 'suspended') {
            return ['allowed' => false, 'reason' => 'suspended'];
        }

        return ['allowed' => true, 'reason' => ''];
    }

    /**
     * Relance d’une unité dont l’invitation est en attente (au plus une fois par 24 h).
     *
     * @param array<string, mixed> $mission
     * @param list<array<string, mixed>> $participants
     * @return array{allowed: bool, reason: string}
     */
    public static function canRemind(array $mission, int $tenantId, array $participants, ?string $lastReminderAt, ?int $now = null): array
    {
        if (self::isTerminal($mission)) {
            return ['allowed' => false, 'reason' => 'mission_terminal'];
        }
        $p = self::participantFor($tenantId, $participants);
        if ($p === null || (string) ($p['status'] ?? '') !== 'invited') {
            return ['allowed' => false, 'reason' => 'no_pending_invitation'];
        }
        $now ??= time();
        $last = $lastReminderAt !== null ? strtotime($lastReminderAt) : false;
        if ($last !== false && ($now - $last) < self::REMINDER_MIN_INTERVAL_SECONDS) {
            return ['allowed' => false, 'reason' => 'reminder_too_soon'];
        }

        return ['allowed' => true, 'reason' => ''];
    }

    /** Relance automatique : fenêtre avant la date limite de réponse (J-2). */
    public const AUTO_REMINDER_WINDOW_SECONDS = 2 * 86400;

    /** Préavis d’expiration d’une autorisation de partage. */
    public const CONSENT_EXPIRY_NOTICE_SECONDS = 12 * 3600;

    /**
     * La proposition entre-t-elle dans la fenêtre de relance automatique (J-2 avant la date limite) ?
     * La relance de chaque unité reste soumise à canRemind() (une par 24 h).
     *
     * @param array<string, mixed> $mission
     */
    public static function autoReminderDue(array $mission, ?int $now = null): bool
    {
        if (self::isTerminal($mission) || (string) ($mission['status'] ?? '') !== 'pending') {
            return false;
        }
        $dl = trim((string) ($mission['proposal_deadline_at'] ?? ''));
        $ts = $dl !== '' ? strtotime($dl) : false;
        if ($ts === false) {
            return false;
        }
        $now ??= time();

        return $ts > $now && ($ts - $now) <= self::AUTO_REMINDER_WINDOW_SECONDS;
    }

    /**
     * Faut-il prévenir de l’expiration prochaine d’une autorisation de partage ?
     * Une seule fois par 24 h et par personne (lastNoticeAt).
     */
    public static function consentExpiryNoticeDue(?string $expiresAt, ?string $lastNoticeAt, ?int $now = null): bool
    {
        $exp = $expiresAt !== null && $expiresAt !== '' ? strtotime($expiresAt) : false;
        if ($exp === false) {
            return false;
        }
        $now ??= time();
        if ($exp <= $now || ($exp - $now) > self::CONSENT_EXPIRY_NOTICE_SECONDS) {
            return false;
        }
        $last = $lastNoticeAt !== null && $lastNoticeAt !== '' ? strtotime($lastNoticeAt) : false;

        return $last === false || ($now - $last) >= self::REMINDER_MIN_INTERVAL_SECONDS;
    }

    /** Message utilisateur pour une raison de refus. */
    public static function reasonLabel(string $reason): string
    {
        return match ($reason) {
            'mission_terminal' => 'Cette coopération est clôturée ou annulée : l’action n’est plus possible.',
            'already_active' => 'La coopération est déjà lancée.',
            'invalid_tenant' => 'Unité partenaire invalide.',
            'already_invited' => 'Cette unité a déjà une invitation en attente.',
            'already_engaged' => 'Cette unité participe déjà à la coopération.',
            'not_invited', 'not_participant' => 'Votre unité n’est pas invitée sur cette coopération.',
            'no_pending_invitation' => 'Aucune invitation en attente pour votre unité.',
            'counter_proposal_pending' => 'Une contre-proposition attend votre réponse : traitez-la avant de lancer.',
            'invitations_pending' => 'Des unités n’ont pas encore répondu : attendez leur réponse, relancez-les ou retirez leur invitation.',
            'no_partner_accepted' => 'Aucune unité partenaire n’a encore accepté : le lancement nécessite au moins un partenaire confirmé.',
            'cannot_remove_lead' => 'L’unité support ne peut pas être retirée.',
            'cannot_remove_self' => 'Vous ne pouvez pas retirer votre propre unité.',
            'already_out' => 'Cette unité a déjà refusé ou a déjà été retirée.',
            'motive_required' => 'Indiquez le motif de l’annulation (au moins 3 caractères).',
            'use_cancel' => 'La coopération n’est pas lancée : utilisez « Annuler la proposition ».',
            'not_launched' => 'La coopération doit être lancée pour être suspendue.',
            'already_suspended' => 'La coopération est déjà suspendue.',
            'not_suspended' => 'La coopération n’est pas suspendue.',
            'suspended' => 'La coopération est suspendue : reprenez-la avant de faire avancer la conduite.',
            'reminder_too_soon' => 'Cette unité a déjà été relancée il y a moins de 24 heures.',
            default => 'Action impossible dans l’état actuel de la coopération.',
        };
    }

    /**
     * @param list<array<string, mixed>> $participants
     * @return array<string, mixed>|null
     */
    public static function participantFor(int $tenantId, array $participants): ?array
    {
        foreach ($participants as $p) {
            if ((int) ($p['tenant_id'] ?? 0) === $tenantId) {
                return $p;
            }
        }

        return null;
    }
}
