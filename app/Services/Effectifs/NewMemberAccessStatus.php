<?php

declare(strict_types=1);

namespace App\Services\Effectifs;

use App\Core\Container;
use App\Repositories\TenantRepository;
use App\Services\Personnel\PersonnelDutyPositionService;
use App\Services\Personnel\PersonnelLifecycleSettings;
use App\Services\Sse\SseAccessMemberRights;
use App\Services\Sse\SseClearanceService;
use App\Services\Sse\SseMemberClearance;
use App\Support\AdministrativePositionCatalog;

/**
 * Page « Ajouter un membre », étape « Accès et statut » : habilitation (classification SSE) et position de service.
 *
 * Aucune règle propre : la liste et les droits sont ceux de la fiche du membre.
 *  - Habilitation : SseMemberClearance::grantable (jamais au-dessus de l'habilitation de la personne qui crée la fiche),
 *    droit de gestion SSE (SseAccessMemberRights::visibleTo), écriture par SseAccessMemberRights::changeClearance (motif,
 *    journal d'audit SSE). Par défaut : aucune habilitation, le niveau le plus bas.
 *  - Position : AdministrativePositionCatalog et PersonnelDutyPositionService::applyPosition, droit « statut des comptes »
 *    comme l'onglet « Position et statut ». Par défaut : la position d'arrivée de la communauté (En formation). Le passage
 *    direct en service actif suit la durée minimale de formation de la communauté. Le service n'a pas de date d'effet.
 *
 * Les accès aux données sont injectables (fonctions) ; par défaut ce sont les services du site.
 */
final class NewMemberAccessStatus
{
    public const REASON = 'Habilitation accordée à la création de la fiche';
    public const DEFAULT_POSITION = AdministrativePositionCatalog::TRAINING;

    /**
     * @param \Closure(int, int): bool|null $canClearance droit de gestion SSE de la personne qui agit (communauté, personne)
     * @param \Closure(): string|null $ceiling habilitation de la personne qui agit (sa session)
     * @param \Closure(int, int, string, int, string, string): array{ok: bool, message: string}|null $grantClearance
     * @param \Closure(int): int|null $trainingDays durée minimale de formation de la communauté, en jours
     * @param \Closure(int, int, string, int): string|null $applyPosition résultat de PersonnelDutyPositionService::applyPosition
     * @param \Closure(int, int, int, string): void|null $audit journal des fiches (communauté, personne, membre, valeur)
     * @param \Closure(int): list<array<string, mixed>>|null $positions positions de la communauté
     */
    public function __construct(
        private ?\Closure $canClearance = null,
        private ?\Closure $ceiling = null,
        private ?\Closure $grantClearance = null,
        private ?\Closure $trainingDays = null,
        private ?\Closure $applyPosition = null,
        private ?\Closure $audit = null,
        private ?\Closure $positions = null,
    ) {
    }

    /**
     * Données du formulaire : ce que la personne peut choisir, et pourquoi un champ est verrouillé.
     *
     * @return array{
     *   clearance: array{can: bool, options: array<string, string>, default: string},
     *   position: array{can: bool, options: list<array{slug: string, label: string, summary: string, disabled: bool, note: string}>, default: string, training_days: int}
     * }
     */
    public function form(int $tenantId, int $actorId, bool $canManageStatus): array
    {
        $canClearance = $tenantId > 0 && $actorId > 0 && $this->canClearanceOf($tenantId, $actorId);
        $options = ['' => self::noneLabel()];
        if ($canClearance) {
            $options += (new SseMemberClearance())->grantable($this->ceilingOf());
        }
        $training = $this->trainingDaysOf($tenantId);
        $positions = [];
        foreach ($this->positionsOf($tenantId) as $p) {
            $slug = (string) ($p['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $blocked = $slug === AdministrativePositionCatalog::ACTIVE && $training > 0;
            $positions[] = [
                'slug' => $slug,
                'label' => (string) ($p['label'] ?? $slug),
                'summary' => (string) ($p['summary'] ?? ''),
                'disabled' => $blocked,
                'note' => $blocked ? str_replace('%1', (string) $training, 'Après %1 jours de formation, règle de la communauté.') : '',
            ];
        }

        return [
            'clearance' => ['can' => $canClearance, 'options' => $options, 'default' => ''],
            'position' => ['can' => $canManageStatus && $positions !== [], 'options' => $positions, 'default' => self::DEFAULT_POSITION, 'training_days' => $training],
        ];
    }

    public static function noneLabel(): string
    {
        return 'Aucune : information non classifiée contrôlée (CUI) seulement';
    }

    /**
     * Vérifie le choix avant la création de la fiche. Un champ verrouillé (droit manquant) est ignoré, pas refusé.
     *
     * @param array<string, mixed> $form résultat de form()
     */
    public function validate(array $form, string $level, string $position): ?string
    {
        $level = strtolower(trim($level));
        if ($level !== '' && !empty($form['clearance']['can']) && !isset($form['clearance']['options'][$level])) {
            return 'Vous ne pouvez pas accorder une habilitation plus haute que la vôtre.';
        }
        $position = trim($position);
        if ($position !== '' && !empty($form['position']['can'])) {
            $found = null;
            foreach ($form['position']['options'] as $opt) {
                if ($opt['slug'] === $position) {
                    $found = $opt;
                    break;
                }
            }
            if ($found === null) {
                return 'Position de service inconnue.';
            }
            if ($found['disabled']) {
                return str_replace('%1', (string) $form['position']['training_days'], 'Un nouveau membre passe en service actif après %1 jours de formation, règle de la communauté.');
            }
        }

        return null;
    }

    /**
     * Inscrit l'habilitation et la position choisies sur la fiche qui vient d'être créée.
     * Un volet qui échoue n'annule pas la création : il remonte un avertissement lisible.
     *
     * @param array<string, mixed> $form résultat de form()
     * @return list<string> avertissements
     */
    public function apply(int $tenantId, int $actorId, string $actorLabel, int $userId, string $level, string $position, array $form): array
    {
        $warnings = [];
        $level = strtolower(trim($level));
        if ($level !== '' && !empty($form['clearance']['can']) && isset($form['clearance']['options'][$level])) {
            try {
                $res = $this->grant($tenantId, $actorId, $actorLabel, $userId, $level, self::REASON);
            } catch (\Throwable $e) {
                error_log('[nouveau-membre] habilitation : ' . $e->getMessage());
                $res = ['ok' => false, 'message' => ''];
            }
            if (!$res['ok']) {
                $warnings[] = 'L’habilitation n’a pas été inscrite : réglez-la depuis l’onglet Accès de la fiche.';
                if ($res['message'] !== '') {
                    $warnings[] = $res['message'];
                }
            }
        }
        $position = trim($position);
        if ($position !== '' && $position !== self::DEFAULT_POSITION && !empty($form['position']['can']) && $this->validate($form, '', $position) === null) {
            try {
                $result = $this->applyPositionOf($tenantId, $userId, $position, $actorId);
            } catch (\Throwable $e) {
                error_log('[nouveau-membre] position : ' . $e->getMessage());
                $result = PersonnelDutyPositionService::RESULT_INVALID;
            }
            if ($result === PersonnelDutyPositionService::RESULT_CHANGED) {
                $this->journal($tenantId, $actorId, $userId, AdministrativePositionCatalog::label($position, $tenantId));
            } elseif ($result !== PersonnelDutyPositionService::RESULT_UNCHANGED) {
                $warnings[] = 'La position de service n’a pas été changée. Réglez-la depuis l’onglet Position et statut de la fiche.';
            }
        }

        return $warnings;
    }

    // ------------------------------------------------------------------ Accès aux données (injectables)

    private function canClearanceOf(int $tenantId, int $actorId): bool
    {
        if ($this->canClearance !== null) {
            return (bool) ($this->canClearance)($tenantId, $actorId);
        }
        try {
            return (new SseAccessMemberRights())->visibleTo($tenantId, $actorId);
        } catch (\Throwable) {
            return false;
        }
    }

    private function ceilingOf(): string
    {
        if ($this->ceiling !== null) {
            return (string) ($this->ceiling)();
        }
        try {
            return (new SseClearanceService())->maxLevel();
        } catch (\Throwable) {
            return '';
        }
    }

    /** @return array{ok: bool, message: string} */
    private function grant(int $tenantId, int $actorId, string $actorLabel, int $userId, string $level, string $reason): array
    {
        if ($this->grantClearance !== null) {
            return ($this->grantClearance)($tenantId, $actorId, $actorLabel, $userId, $level, $reason);
        }

        return (new SseAccessMemberRights())->changeClearance($tenantId, $actorId, $actorLabel, $userId, $level, $reason);
    }

    private function trainingDaysOf(int $tenantId): int
    {
        if ($this->trainingDays !== null) {
            return max(0, (int) ($this->trainingDays)($tenantId));
        }
        try {
            return PersonnelLifecycleSettings::resolve((new TenantRepository())->getSettings($tenantId))['training_days'];
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return list<array<string, mixed>> */
    private function positionsOf(int $tenantId): array
    {
        if ($this->positions !== null) {
            return ($this->positions)($tenantId);
        }
        try {
            return AdministrativePositionCatalog::all($tenantId);
        } catch (\Throwable) {
            return AdministrativePositionCatalog::builtIn();
        }
    }

    private function applyPositionOf(int $tenantId, int $userId, string $slug, int $actorId): string
    {
        if ($this->applyPosition !== null) {
            return (string) ($this->applyPosition)($tenantId, $userId, $slug, $actorId);
        }

        return Container::get(PersonnelDutyPositionService::class)->applyPosition($tenantId, $userId, $slug, $actorId);
    }

    private function journal(int $tenantId, int $actorId, int $userId, string $value): void
    {
        try {
            if ($this->audit !== null) {
                ($this->audit)($tenantId, $actorId, $userId, $value);

                return;
            }
            Container::get(\App\Services\Admin\AdminAuditService::class)->logUserUpdated($tenantId, $actorId, $userId, 'position_service', mb_substr($value, 0, 190));
        } catch (\Throwable) {
            // Le journal ne défait pas une position déjà enregistrée.
        }
    }
}
