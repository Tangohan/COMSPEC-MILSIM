<?php

declare(strict_types=1);

namespace App\Services\Effectifs\Dossier;

use App\Services\Effectifs\Dossier\Adapters\CareerStepsAdapter;
use App\Services\Effectifs\Dossier\Adapters\IntegrationStepsAdapter;
use App\Services\Effectifs\Dossier\Adapters\ReliabilityStepsAdapter;

/**
 * Gestes d'un responsable sur le dossier d'un membre. Chaque geste :
 *  - retrouve l'étape dans le fil du membre (donc dans cette communauté et ce membre : une clé d'un autre dossier est « introuvable ») ;
 *  - vérifie que le geste est permis sur ce type d'étape (MemberDossierTimeline::actionsFor) ;
 *  - délègue au service qui porte l'étape quand il existe (parcours d'intégration, campagne de fiabilisation, notifications) ;
 *  - écrit l'historique (avant, après, motif) et le journal d'audit.
 * Une qualification délivrée, un document signé, un bilan rédigé ou une confirmation de fiche ne se valident jamais ici.
 * Un responsable ne valide pas son propre dossier : sur lui-même, seule la note interne reste permise.
 */
final class MemberDossierStepActions
{
    public const MAX_CUSTOM_PER_MEMBER = 60;
    public const REMIND_COOLDOWN_HOURS = 24;

    public function __construct(
        private MemberDossierTimeline $timeline,
        private DossierStore $store,
        private DossierPorts $ports,
    ) {
    }

    /** @return array{ok: bool, message: string} */
    public function complete(int $tenantId, int $actorId, int $userId, string $key, string $reason = ''): array
    {
        $g = $this->guard($tenantId, $actorId, $userId, $key, DossierCatalog::ACTION_COMPLETE);
        if (isset($g['error'])) {
            return $g['error'];
        }
        $step = $g['step'];
        $reason = self::text($reason, 500);
        if ($step['source_type'] === IntegrationStepsAdapter::SOURCE) {
            // Une étape que le membre doit faire lui-même (ou une étape qui se valide seule : lecture, bilan, formation)
            // ne se valide à sa place qu'avec un motif : c'est la validation forcée du service d'intégration.
            $force = $step['owner_kind'] === DossierCatalog::OWNER_MEMBER
                || in_array($step['step_type'] ?? '', ['document_read', 'stage_bilan', 'lms_optional', 'personnel_dossier', 'event_invite', 'matrix_assign'], true);
            if ($force && $reason === '') {
                return self::fail('Indiquez un motif : cette étape revient normalement au membre ou se valide d’elle-même.');
            }
            $r = $this->ports->completeIntegrationStep($tenantId, (int) $step['integration_id'], (int) $step['source_id'], $actorId, $force, $reason);
            if (!$r['ok']) {
                return self::fail((string) ($r['message'] ?? 'Validation impossible.'));
            }
        } else {
            $this->store->update($tenantId, $userId, (int) $step['source_id'], ['status' => 'done', 'done_at' => date('Y-m-d H:i:s')]);
        }

        return $this->done($tenantId, $actorId, $userId, $step, DossierCatalog::ACTION_COMPLETE, ['state' => $step['state']], ['state' => DossierCatalog::STATE_DONE], $reason, 'Étape marquée terminée.');
    }

    /** @return array{ok: bool, message: string} */
    public function reopen(int $tenantId, int $actorId, int $userId, string $key, string $reason = ''): array
    {
        $g = $this->guard($tenantId, $actorId, $userId, $key, DossierCatalog::ACTION_REOPEN);
        if (isset($g['error'])) {
            return $g['error'];
        }
        $step = $g['step'];
        $reason = self::text($reason, 500);
        if ($step['source_type'] === IntegrationStepsAdapter::SOURCE) {
            $ok = $this->ports->updateIntegrationStep($tenantId, (int) $step['integration_id'], (int) $step['source_id'], $actorId, [
                'status' => 'pending', 'completed_at' => null, 'validated_by' => null, 'force_reason' => null,
            ], 'step_reopened', 'Étape à refaire : ' . $step['title']);
            if (!$ok) {
                return self::fail('Étape introuvable.');
            }
        } else {
            $this->store->update($tenantId, $userId, (int) $step['source_id'], ['status' => 'todo', 'done_at' => null]);
        }

        return $this->done($tenantId, $actorId, $userId, $step, DossierCatalog::ACTION_REOPEN, ['state' => $step['state']], ['state' => DossierCatalog::STATE_TODO], $reason, 'Étape remise à faire.');
    }

    /** @return array{ok: bool, message: string} */
    public function postpone(int $tenantId, int $actorId, int $userId, string $key, string $dueOn, string $reason, ?string $today = null): array
    {
        $g = $this->guard($tenantId, $actorId, $userId, $key, DossierCatalog::ACTION_POSTPONE);
        if (isset($g['error'])) {
            return $g['error'];
        }
        $step = $g['step'];
        $today ??= date('Y-m-d');
        $day = DossierCatalog::day($dueOn);
        $reason = self::text($reason, 500);
        if ($day === null) {
            return self::fail('Indiquez une date d’échéance valide.');
        }
        if ($day < $today) {
            return self::fail('La nouvelle échéance ne peut pas être dans le passé.');
        }
        if (mb_strlen($reason) < 3) {
            return self::fail('Indiquez le motif du report.');
        }
        if ($step['source_type'] === IntegrationStepsAdapter::SOURCE) {
            $ok = $this->ports->updateIntegrationStep($tenantId, (int) $step['integration_id'], (int) $step['source_id'], $actorId, ['due_at' => $day . ' 23:59:00'], 'step_postponed', 'Échéance reportée au ' . $day . ' : ' . $step['title']);
            if (!$ok) {
                return self::fail('Étape introuvable.');
            }
        } elseif ($step['source_type'] === MemberDossierTimeline::CUSTOM) {
            $this->store->update($tenantId, $userId, (int) $step['source_id'], ['due_on' => $day]);
        } else {
            $this->overlay($tenantId, $actorId, $userId, $step, ['due_on' => $day]);
        }

        return $this->done($tenantId, $actorId, $userId, $step, DossierCatalog::ACTION_POSTPONE, ['due_on' => $step['due_on']], ['due_on' => $day], $reason, 'Échéance reportée au ' . $day . '.');
    }

    /** @return array{ok: bool, message: string} */
    public function reassign(int $tenantId, int $actorId, int $userId, string $key, int $ownerUserId, string $reason = ''): array
    {
        $g = $this->guard($tenantId, $actorId, $userId, $key, DossierCatalog::ACTION_REASSIGN);
        if (isset($g['error'])) {
            return $g['error'];
        }
        $step = $g['step'];
        $reason = self::text($reason, 500);
        if ($ownerUserId > 0 && !$this->ports->isTenantUser($tenantId, $ownerUserId)) {
            return self::fail('Ce responsable n’appartient pas à la communauté.');
        }
        if ($step['source_type'] === IntegrationStepsAdapter::SOURCE) {
            $ok = $this->ports->updateIntegrationStep($tenantId, (int) $step['integration_id'], (int) $step['source_id'], $actorId, ['responsible_user_id' => $ownerUserId > 0 ? $ownerUserId : null], 'step_reassigned', 'Responsable changé : ' . $step['title']);
            if (!$ok) {
                return self::fail('Étape introuvable.');
            }
        } elseif ($step['source_type'] === MemberDossierTimeline::CUSTOM) {
            $this->store->update($tenantId, $userId, (int) $step['source_id'], ['owner_user_id' => $ownerUserId > 0 ? $ownerUserId : null]);
        } else {
            $this->overlay($tenantId, $actorId, $userId, $step, ['owner_user_id' => $ownerUserId > 0 ? $ownerUserId : null]);
        }

        return $this->done($tenantId, $actorId, $userId, $step, DossierCatalog::ACTION_REASSIGN, ['owner_user_id' => $step['owner_user_id']], ['owner_user_id' => $ownerUserId], $reason, 'Responsable modifié.');
    }

    /** @return array{ok: bool, message: string} */
    public function note(int $tenantId, int $actorId, int $userId, string $key, string $note): array
    {
        $g = $this->guard($tenantId, $actorId, $userId, $key, DossierCatalog::ACTION_NOTE);
        if (isset($g['error'])) {
            return $g['error'];
        }
        $step = $g['step'];
        $note = self::text($note, 2000);
        if ($note === '') {
            return self::fail('Écrivez la note à ajouter.');
        }
        if ($step['source_type'] === IntegrationStepsAdapter::SOURCE) {
            $this->ports->addIntegrationNote($tenantId, (int) $step['integration_id'], $actorId, $step['title'] . ' : ' . $note);
        } elseif ($step['source_type'] === MemberDossierTimeline::CUSTOM) {
            $this->store->update($tenantId, $userId, (int) $step['source_id'], ['note' => $note]);
        } else {
            $this->overlay($tenantId, $actorId, $userId, $step, ['note' => $note]);
        }

        return $this->done($tenantId, $actorId, $userId, $step, DossierCatalog::ACTION_NOTE, null, ['note' => $note], '', 'Note ajoutée.');
    }

    /** Ignorer une étape facultative (motif obligatoire). @return array{ok: bool, message: string} */
    public function skip(int $tenantId, int $actorId, int $userId, string $key, string $reason): array
    {
        $g = $this->guard($tenantId, $actorId, $userId, $key, DossierCatalog::ACTION_SKIP);
        if (isset($g['error'])) {
            return $g['error'];
        }
        $step = $g['step'];
        $reason = self::text($reason, 500);
        if (mb_strlen($reason) < 3) {
            return self::fail('Indiquez le motif : une étape ignorée doit rester explicable.');
        }
        if ($step['source_type'] === IntegrationStepsAdapter::SOURCE) {
            $ok = $this->ports->updateIntegrationStep($tenantId, (int) $step['integration_id'], (int) $step['source_id'], $actorId, ['status' => 'skipped', 'force_reason' => mb_substr($reason, 0, 500)], 'step_skipped', 'Étape ignorée : ' . $step['title']);
            if (!$ok) {
                return self::fail('Étape introuvable.');
            }
        } else {
            $this->store->update($tenantId, $userId, (int) $step['source_id'], ['status' => 'skipped']);
        }

        return $this->done($tenantId, $actorId, $userId, $step, DossierCatalog::ACTION_SKIP, ['state' => $step['state']], ['state' => DossierCatalog::STATE_SKIPPED], $reason, 'Étape ignorée.');
    }

    /** @return array{ok: bool, message: string} */
    public function unskip(int $tenantId, int $actorId, int $userId, string $key, string $reason = ''): array
    {
        $g = $this->guard($tenantId, $actorId, $userId, $key, DossierCatalog::ACTION_UNSKIP);
        if (isset($g['error'])) {
            return $g['error'];
        }
        $step = $g['step'];
        $reason = self::text($reason, 500);
        if ($step['source_type'] === IntegrationStepsAdapter::SOURCE) {
            $ok = $this->ports->updateIntegrationStep($tenantId, (int) $step['integration_id'], (int) $step['source_id'], $actorId, ['status' => 'pending', 'force_reason' => null], 'step_unskipped', 'Étape rétablie : ' . $step['title']);
            if (!$ok) {
                return self::fail('Étape introuvable.');
            }
        } else {
            $this->store->update($tenantId, $userId, (int) $step['source_id'], ['status' => 'todo']);
        }

        return $this->done($tenantId, $actorId, $userId, $step, DossierCatalog::ACTION_UNSKIP, ['state' => $step['state']], ['state' => DossierCatalog::STATE_TODO], $reason, 'Étape rétablie.');
    }

    /**
     * Relance le membre : une notification, jamais deux fois en moins de REMIND_COOLDOWN_HOURS pour la même étape.
     *
     * @return array{ok: bool, message: string}
     */
    public function remind(int $tenantId, int $actorId, int $userId, string $key, ?int $now = null): array
    {
        $g = $this->guard($tenantId, $actorId, $userId, $key, DossierCatalog::ACTION_REMIND);
        if (isset($g['error'])) {
            return $g['error'];
        }
        $step = $g['step'];
        $now ??= time();
        $last = $this->store->lastEventAt($tenantId, $userId, $step['key'], 'remind');
        if ($last !== null && ($ts = strtotime($last)) !== false && ($now - $ts) < self::REMIND_COOLDOWN_HOURS * 3600) {
            return self::fail('Le membre a déjà été relancé pour cette étape il y a moins de ' . self::REMIND_COOLDOWN_HOURS . ' h.');
        }
        if ($step['source_type'] === ReliabilityStepsAdapter::SOURCE_CAMPAIGN) {
            $r = $this->ports->remindCampaign($tenantId, $actorId, (int) $step['source_id'], $userId);
            if (!$r['ok']) {
                return self::fail($r['message']);
            }
        } elseif (!$this->ports->notifyMember($tenantId, $userId, (string) $step['title'], $step['due_on'], self::memberPath($step))) {
            return self::fail('La notification n’a pas pu être déposée.');
        }
        $this->store->addEvent($tenantId, $userId, $step['key'], (string) $step['title'], 'remind', $actorId, null, ['due_on' => $step['due_on']]);
        $this->ports->audit($tenantId, $actorId, $userId, $step['key'], 'remind', (string) json_encode(['step' => $step['title']], JSON_UNESCAPED_UNICODE));

        return ['ok' => true, 'message' => 'Le membre a été relancé.'];
    }

    /**
     * Relance en lot (vue équipe) : pour chaque membre, sa prochaine étape à faire par lui. Plafonné, sans doublon.
     *
     * @param list<int> $userIds
     * @return array{sent: int, skipped: int, capped: bool}
     */
    public function remindMany(int $tenantId, int $actorId, array $userIds, int $max = 25, ?int $now = null): array
    {
        $userIds = array_slice(array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $i): bool => $i > 0 && $i !== $actorId))), 0, 500);
        $sent = 0;
        $skipped = 0;
        $capped = false;
        foreach ($userIds as $uid) {
            if ($sent >= $max) {
                $capped = true;
                break;
            }
            $next = $this->timeline->forUser($tenantId, $uid)['steps'];
            $target = null;
            foreach (MemberDossierTimeline::filter($next, '', 'open') as $s) {
                if (in_array(DossierCatalog::ACTION_REMIND, $s['actions'], true)) {
                    $target = $s;
                    break;
                }
            }
            if ($target === null) {
                $skipped++;
                continue;
            }
            $r = $this->remind($tenantId, $actorId, $uid, (string) $target['key'], $now);
            $r['ok'] ? $sent++ : $skipped++;
        }

        return ['sent' => $sent, 'skipped' => $skipped, 'capped' => $capped];
    }

    /** @return array{ok: bool, message: string, id?: int} */
    public function addCustom(int $tenantId, int $actorId, int $userId, string $track, string $title, string $dueOn, int $ownerUserId, ?string $today = null): array
    {
        if ($actorId === $userId) {
            return self::fail('Vous ne modifiez pas votre propre dossier.');
        }
        if (!$this->store->available()) {
            return self::fail(self::degradedMessage());
        }
        $title = self::text($title, 180);
        $today ??= date('Y-m-d');
        if (mb_strlen($title) < 3) {
            return self::fail('Donnez un intitulé à l’étape (3 caractères au moins).');
        }
        if (!in_array($track, DossierCatalog::tracks(), true)) {
            return self::fail('Choisissez un parcours.');
        }
        $due = trim($dueOn) === '' ? null : DossierCatalog::day($dueOn);
        if (trim($dueOn) !== '' && ($due === null || $due < $today)) {
            return self::fail('L’échéance doit être une date à venir.');
        }
        if ($ownerUserId > 0 && !$this->ports->isTenantUser($tenantId, $ownerUserId)) {
            return self::fail('Ce responsable n’appartient pas à la communauté.');
        }
        $existing = array_filter($this->store->rowsForUsers($tenantId, [$userId])[$userId] ?? [], static fn (array $r): bool => ($r['source_type'] ?? null) === null || $r['source_type'] === '');
        if (count($existing) >= self::MAX_CUSTOM_PER_MEMBER) {
            return self::fail('Ce dossier compte déjà ' . self::MAX_CUSTOM_PER_MEMBER . ' étapes personnalisées.');
        }
        $id = $this->store->insert($tenantId, $userId, ['track' => $track, 'title' => $title, 'status' => 'todo', 'owner_user_id' => $ownerUserId, 'due_on' => $due, 'created_by' => $actorId]);
        $key = MemberDossierTimeline::CUSTOM . ':' . $id;
        $this->store->addEvent($tenantId, $userId, $key, $title, 'add', $actorId, null, ['track' => $track, 'due_on' => $due, 'owner_user_id' => $ownerUserId]);
        $this->ports->audit($tenantId, $actorId, $userId, $key, 'add', (string) json_encode(['title' => $title], JSON_UNESCAPED_UNICODE));

        return ['ok' => true, 'message' => 'Étape ajoutée au dossier.', 'id' => $id];
    }

    /** @return array{ok: bool, message: string} */
    public function remove(int $tenantId, int $actorId, int $userId, string $key, string $reason = ''): array
    {
        $g = $this->guard($tenantId, $actorId, $userId, $key, DossierCatalog::ACTION_REMOVE);
        if (isset($g['error'])) {
            return $g['error'];
        }
        $step = $g['step'];
        if ($step['source_type'] !== MemberDossierTimeline::CUSTOM || $step['state'] === DossierCatalog::STATE_DONE) {
            return self::fail('Seule une étape personnalisée non terminée peut être retirée.');
        }
        $this->store->delete($tenantId, $userId, (int) $step['source_id']);

        return $this->done($tenantId, $actorId, $userId, $step, DossierCatalog::ACTION_REMOVE, ['title' => $step['title'], 'due_on' => $step['due_on']], null, self::text($reason, 500), 'Étape retirée du dossier.');
    }

    /**
     * @return array{step: array<string, mixed>}|array{error: array{ok: bool, message: string}}
     */
    private function guard(int $tenantId, int $actorId, int $userId, string $key, string $action): array
    {
        if ($tenantId < 1 || $userId < 1 || $actorId < 1) {
            return ['error' => self::fail('Demande invalide.')];
        }
        if (!$this->store->available()) {
            return ['error' => self::fail(self::degradedMessage())];
        }
        if ($actorId === $userId && $action !== DossierCatalog::ACTION_NOTE) {
            return ['error' => self::fail('Vous ne pouvez pas valider ni modifier les étapes de votre propre dossier : demandez à un autre responsable.')];
        }
        $step = null;
        foreach ($this->timeline->forUser($tenantId, $userId)['steps'] as $s) {
            if ($s['key'] === $key) {
                $step = $s;
                break;
            }
        }
        if ($step === null) {
            return ['error' => self::fail('Étape introuvable dans ce dossier.')];
        }
        if (!in_array($action, $step['actions'], true)) {
            $why = $step['locked_reason'] !== '' ? ' ' . $step['locked_reason'] : '';

            return ['error' => self::fail('Ce geste n’est pas permis sur cette étape.' . $why)];
        }

        return ['step' => $step];
    }

    /**
     * Complément posé sur une étape d'un autre parcours (échéance, responsable, note) : une ligne par étape visée.
     *
     * @param array<string, mixed> $step
     * @param array<string, mixed> $fields
     */
    private function overlay(int $tenantId, int $actorId, int $userId, array $step, array $fields): void
    {
        $row = $this->store->findOverlay($tenantId, $userId, (string) $step['source_type'], (string) $step['source_id']);
        if ($row === null) {
            $this->store->insert($tenantId, $userId, $fields + [
                'track' => (string) $step['track'], 'title' => (string) $step['title'], 'status' => 'active', 'created_by' => $actorId,
                'source_type' => (string) $step['source_type'], 'source_id' => (string) $step['source_id'],
            ]);
        } else {
            $this->store->update($tenantId, $userId, (int) $row['id'], $fields);
        }
    }

    /**
     * @param array<string, mixed> $step
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     * @return array{ok: bool, message: string}
     */
    private function done(int $tenantId, int $actorId, int $userId, array $step, string $action, ?array $before, ?array $after, string $reason, string $message): array
    {
        $this->store->addEvent($tenantId, $userId, (string) $step['key'], (string) $step['title'], $action, $actorId, $before, $after, $reason);
        $this->ports->audit($tenantId, $actorId, $userId, (string) $step['key'], $action, (string) json_encode(['before' => $before, 'after' => $after, 'reason' => $reason], JSON_UNESCAPED_UNICODE));

        return ['ok' => true, 'message' => $message];
    }

    /** Page du membre où l'étape se traite (liste blanche, relue à l'affichage de la notification). */
    private static function memberPath(array $step): string
    {
        return match (true) {
            $step['source_type'] === IntegrationStepsAdapter::SOURCE => 'mon-integration',
            $step['source_type'] === ReliabilityStepsAdapter::SOURCE_CAMPAIGN => 'jnet/moi/fiabilisation',
            str_starts_with((string) $step['source_type'], 'enlistment') => 'jnet/moi',
            $step['source_type'] === CareerStepsAdapter::SOURCE_ELEVATION, $step['source_type'] === CareerStepsAdapter::SOURCE_MOBILITY => 'jnet/moi/carriere',
            $step['track'] === DossierCatalog::TRACK_FORMATION => 'jnet/moi/qualifications',
            default => 'jnet/moi',
        };
    }

    public static function degradedMessage(): string
    {
        return 'Le suivi est en lecture seule : la mise à jour de la base (étape « Suivi du dossier des membres ») n’a pas encore été appliquée.';
    }

    private static function text(string $v, int $max): string
    {
        return mb_substr(trim(preg_replace('/[^\S\n]+/u', ' ', strip_tags($v)) ?? ''), 0, $max);
    }

    /** @return array{ok: bool, message: string} */
    private static function fail(string $message): array
    {
        return ['ok' => false, 'message' => $message];
    }
}
