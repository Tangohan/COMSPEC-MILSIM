<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\PersonnelProfileRepository;
use App\Repositories\SseAuditLogRepository;
use App\Repositories\SseCaseRepository;
use App\Services\Rbac\EffectivePermissionsService;

/**
 * Niveau d'habilitation SSE d'un membre, pour la fiche effectif : le plafond de lecture qu'il aurait en session (règle
 * unique SseClearanceService::resolve), d'où il vient, qui l'a inscrit et quand, et la modification du niveau inscrit sur
 * sa fiche opérateur (personnel_profiles.clearance_level, la voie que l'encadrement utilise déjà).
 *
 * Garde-fous : on n'accorde pas plus haut que son propre plafond, on ne modifie pas la fiche d'un membre dont le niveau
 * dépasse le sien, un motif est obligatoire, chaque changement est inscrit au journal d'audit SSE (objet member_clearance).
 * Les habilitations qui viennent des niveaux d'accès (permissions atak.sse.clearance.*) ou des droits SSE existants ne se
 * changent pas d'ici : elles se règlent sur le niveau d'accès du membre. Le niveau de la fiche n'a pas d'échéance propre.
 */
final class SseMemberClearance
{
    public const OBJECT = 'member_clearance';
    public const REASON_MIN = 5;

    /** @var \Closure(int, int, string): bool|null droit d'un membre : (membre, communauté, slug) */
    private ?\Closure $can;

    /** @var \Closure(int): ?string|null niveau de la fiche opérateur du membre */
    private ?\Closure $profileLevel;

    /** @var \Closure(int, ?string): bool|null enregistre le niveau de la fiche */
    private ?\Closure $saveLevel;

    /** @var \Closure(array<string, mixed>): void|null écrit le journal d'audit */
    private ?\Closure $audit;

    /** @var \Closure(int, int): ?array<string, mixed>|null dernier changement inscrit : (communauté, membre) */
    private ?\Closure $last;

    /**
     * @param \Closure(int, int, string): bool|null $can
     * @param \Closure(int): ?string|null $profileLevel
     * @param \Closure(int, ?string): bool|null $saveLevel
     * @param \Closure(array<string, mixed>): void|null $audit
     * @param \Closure(int, int): ?array<string, mixed>|null $last
     */
    public function __construct(?\Closure $can = null, ?\Closure $profileLevel = null, ?\Closure $saveLevel = null, ?\Closure $audit = null, ?\Closure $last = null)
    {
        $this->can = $can;
        $this->profileLevel = $profileLevel;
        $this->saveLevel = $saveLevel;
        $this->audit = $audit;
        $this->last = $last;
    }

    /**
     * @return array{level: string, label: string, source: string, source_label: string, profile_level: ?string, profile_label: string, last: ?array<string, mixed>}
     */
    public function view(int $tenantId, int $memberId): array
    {
        $profile = $this->profileOf($memberId);
        $resolved = SseClearanceService::resolve(fn (string $slug): bool => $this->canOf($memberId, $tenantId, $slug), $profile);

        return [
            'level' => $resolved['level'],
            'label' => SseRedactionService::levelLabel($resolved['level']),
            'source' => $resolved['source'],
            'source_label' => self::sourceLabel($resolved['source']),
            'profile_level' => $profile,
            'profile_label' => $profile !== null ? SseRedactionService::levelLabel($profile) : '',
            'last' => $this->lastOf($tenantId, $memberId),
        ];
    }

    public static function sourceLabel(string $source): string
    {
        return match ($source) {
            'explicit' => 'Habilitation explicite portée par ses niveaux d’accès.',
            'role' => 'Reportée de ses droits SSE existants.',
            'profile' => 'Inscrite sur sa fiche opérateur.',
            default => 'Aucune habilitation : information non classifiée contrôlée (CUI) seulement.',
        };
    }

    /**
     * Niveaux que la personne peut inscrire : jusqu'à son propre plafond, jamais au-delà (pas d'escalade).
     *
     * @return array<string, string> code => libellé
     */
    public function grantable(string $actorCeiling): array
    {
        $out = [];
        $ceiling = SseRedactionService::levelRank($actorCeiling);
        foreach (SseRedactionService::LEVELS as $level => $rank) {
            if ($rank >= 1 && $rank <= $ceiling) {
                $out[$level] = SseRedactionService::levelLabel($level);
            }
        }

        return $out;
    }

    /** Peut-elle modifier la fiche de ce membre ? Pas si son niveau inscrit dépasse le plafond de la personne. */
    public function mayChange(int $memberId, string $actorCeiling): bool
    {
        $profile = $this->profileOf($memberId);

        return $profile === null || SseRedactionService::levelRank($profile) <= SseRedactionService::levelRank($actorCeiling);
    }

    /**
     * Inscrit (ou retire, avec $level null) le niveau d'habilitation de la fiche du membre.
     *
     * @return array{ok: bool, message: string}
     */
    public function change(int $tenantId, int $actorId, string $actorLabel, int $memberId, ?string $level, string $reason, string $actorCeiling): array
    {
        if ($tenantId < 1 || $actorId < 1 || $memberId < 1) {
            return ['ok' => false, 'message' => 'Membre introuvable.'];
        }
        $level = $level !== null ? strtolower(trim($level)) : null;
        if ($level === '') {
            $level = null;
        }
        if ($level !== null && !isset($this->grantable($actorCeiling)[$level])) {
            return ['ok' => false, 'message' => 'Vous ne pouvez pas accorder un niveau plus haut que votre propre habilitation.'];
        }
        if (!$this->mayChange($memberId, $actorCeiling)) {
            return ['ok' => false, 'message' => 'Le niveau inscrit pour ce membre dépasse votre habilitation : vous ne pouvez pas le modifier.'];
        }
        $reason = mb_substr(trim((string) preg_replace('/\s+/u', ' ', $reason)), 0, 500);
        if (mb_strlen($reason) < self::REASON_MIN) {
            return ['ok' => false, 'message' => 'Un motif est obligatoire (5 caractères au moins).'];
        }
        $before = $this->profileOf($memberId);
        if ($before === $level) {
            return ['ok' => false, 'message' => 'Ce niveau est déjà celui de la fiche.'];
        }
        if (!$this->save($memberId, $level)) {
            return ['ok' => false, 'message' => 'Rien n’a été enregistré : réessayez.'];
        }
        $this->journal([
            'tenant_id' => $tenantId,
            'actor_user_id' => $actorId,
            'actor_label' => $actorLabel,
            'action' => $level === null ? 'member_clearance.remove' : 'member_clearance.set',
            'object_type' => self::OBJECT,
            'object_id' => $memberId,
            'reason' => $reason,
            'before' => ['level' => $before],
            'after' => ['level' => $level],
        ]);

        return ['ok' => true, 'message' => $level === null ? 'Habilitation de la fiche retirée.' : 'Habilitation de la fiche enregistrée.'];
    }

    // ------------------------------------------------------------------ Accès aux données (injectables)

    private function canOf(int $memberId, int $tenantId, string $slug): bool
    {
        if ($this->can !== null) {
            return (bool) ($this->can)($memberId, $tenantId, $slug);
        }
        static $memo = [];
        $key = $tenantId . ':' . $memberId;
        if (!array_key_exists($key, $memo)) {
            try {
                $memo[$key] = (new EffectivePermissionsService())->forUser($memberId, $tenantId);
            } catch (\Throwable) {
                $memo[$key] = null;
            }
        }

        return $memo[$key]?->allows($slug) ?? false;
    }

    private function profileOf(int $memberId): ?string
    {
        if ($this->profileLevel !== null) {
            return ($this->profileLevel)($memberId);
        }
        try {
            $row = (new PersonnelProfileRepository())->getByUserId($memberId);
        } catch (\Throwable) {
            return null;
        }

        return SseClearanceService::normalizeProfileLevel(is_array($row) ? ($row['clearance_level'] ?? '') : '');
    }

    private function save(int $memberId, ?string $level): bool
    {
        if ($this->saveLevel !== null) {
            return (bool) ($this->saveLevel)($memberId, $level);
        }
        try {
            return (new PersonnelProfileRepository())->update($memberId, ['clearance_level' => $level, 'clearance_reviewed_at' => date('Y-m-d H:i:s')]);
        } catch (\Throwable) {
            return false;
        }
    }

    /** @param array<string, mixed> $row */
    private function journal(array $row): void
    {
        try {
            if ($this->audit !== null) {
                ($this->audit)($row);

                return;
            }
            (new SseAuditLogRepository())->write($row);
        } catch (\Throwable) {
            // Le journal ne défait pas un changement déjà enregistré sur la fiche.
        }
    }

    /** @return array<string, mixed>|null */
    private function lastOf(int $tenantId, int $memberId): ?array
    {
        try {
            if ($this->last !== null) {
                return ($this->last)($tenantId, $memberId);
            }
            $rows = (new SseAuditLogRepository())->listForTenant($tenantId, ['object_type' => self::OBJECT, 'object_id' => $memberId, 'limit' => 1]);

            return $rows[0] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }
}
