<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Gate;

/**
 * Capacités de consultation / gestion de la confidentialité pour un lecteur.
 */
final class OrgVisibilityCapabilities
{
    public bool $viewRestrictedPersonnel = false;
    public bool $viewHiddenPersonnel = false;
    public bool $viewRestrictedUnits = false;
    public bool $viewHiddenUnits = false;
    public bool $managePersonnelVisibility = false;
    public bool $manageUnitVisibility = false;
    public bool $viewVisibilityHistory = false;
    public bool $bypassAll = false;

    public static function fromGate(?Gate $gate = null): self
    {
        $gate ??= Gate::getInstance();
        $caps = new self();

        $caps->bypassAll = $gate->allows('admin.organization') || $gate->allows('admin.access');
        $caps->viewRestrictedPersonnel = $caps->bypassAll
            || $gate->allows('personnel.view_restricted')
            || $gate->allows('personnel.sensitive.view')
            || $gate->allows('organization.orbat.manage');
        $caps->viewHiddenPersonnel = $caps->bypassAll
            || $gate->allows('personnel.view_hidden')
            || $gate->allows('organization.orbat.manage');
        $caps->viewRestrictedUnits = $caps->bypassAll
            || $gate->allows('organization.view_restricted_units')
            || $gate->allows('organization.orbat.manage');
        $caps->viewHiddenUnits = $caps->bypassAll
            || $gate->allows('organization.view_hidden_units')
            || $gate->allows('organization.orbat.manage');
        $caps->managePersonnelVisibility = $caps->bypassAll
            || $gate->allows('personnel.manage_visibility');
        $caps->manageUnitVisibility = $caps->bypassAll
            || $gate->allows('organization.manage_unit_visibility')
            || $gate->allows('organization.orbat.manage');
        $caps->viewVisibilityHistory = $caps->bypassAll
            || $caps->managePersonnelVisibility
            || $caps->manageUnitVisibility
            || $gate->allows('personnel.sensitive.view');

        return $caps;
    }

    public function canSeePersonnelLevel(string $level): bool
    {
        if ($this->bypassAll) {
            return true;
        }
        $level = VisibilityLevel::normalize($level);

        return match ($level) {
            VisibilityLevel::HIDDEN => $this->viewHiddenPersonnel,
            VisibilityLevel::RESTRICTED => $this->viewRestrictedPersonnel || $this->viewHiddenPersonnel,
            VisibilityLevel::ANONYMIZED => true, // visible anonymisé — la redaction est ailleurs
            default => true,
        };
    }

    public function canSeeUnitLevel(string $level): bool
    {
        if ($this->bypassAll) {
            return true;
        }
        $level = VisibilityLevel::normalize($level);

        return match ($level) {
            VisibilityLevel::HIDDEN => $this->viewHiddenUnits,
            VisibilityLevel::RESTRICTED => $this->viewRestrictedUnits || $this->viewHiddenUnits,
            VisibilityLevel::ANONYMIZED => true,
            default => true,
        };
    }

    public function shouldHidePersonnelCompletely(string $level): bool
    {
        return VisibilityLevel::normalize($level) === VisibilityLevel::HIDDEN
            && !$this->canSeePersonnelLevel(VisibilityLevel::HIDDEN);
    }

    public function shouldAnonymizePersonnel(string $level): bool
    {
        $level = VisibilityLevel::normalize($level);
        if ($level !== VisibilityLevel::ANONYMIZED) {
            return false;
        }

        return !$this->viewRestrictedPersonnel && !$this->viewHiddenPersonnel && !$this->bypassAll;
    }

    public function shouldRedactAssignment(string $assignmentLevel, string $unitLevel = VisibilityLevel::NORMAL): bool
    {
        if ($this->bypassAll || $this->viewRestrictedUnits || $this->viewHiddenUnits) {
            return false;
        }
        $eff = VisibilityLevel::mostRestrictive($assignmentLevel, $unitLevel);

        return in_array($eff, [VisibilityLevel::RESTRICTED, VisibilityLevel::HIDDEN, VisibilityLevel::ANONYMIZED], true)
            && !$this->canSeeUnitLevel($eff);
    }

    /**
     * Mode « Voir comme » pour un administrateur : simule le périmètre d’un lecteur.
     * Roles : member | cadre | command.
     */
    public function asPreview(?string $role): self
    {
        if ($role === null || $role === '') {
            return $this;
        }
        if (!$this->managePersonnelVisibility && !$this->bypassAll) {
            return $this;
        }
        $role = strtolower(trim($role));
        if (!in_array($role, ['member', 'cadre', 'command'], true)) {
            return $this;
        }
        $caps = clone $this;
        $caps->bypassAll = false;
        $caps->managePersonnelVisibility = false;
        $caps->manageUnitVisibility = false;
        $caps->viewVisibilityHistory = false;
        if ($role === 'member') {
            $caps->viewRestrictedPersonnel = false;
            $caps->viewHiddenPersonnel = false;
            $caps->viewRestrictedUnits = false;
            $caps->viewHiddenUnits = false;
        } elseif ($role === 'cadre') {
            $caps->viewRestrictedPersonnel = true;
            $caps->viewHiddenPersonnel = false;
            $caps->viewRestrictedUnits = true;
            $caps->viewHiddenUnits = false;
        } else {
            $caps->viewRestrictedPersonnel = true;
            $caps->viewHiddenPersonnel = true;
            $caps->viewRestrictedUnits = true;
            $caps->viewHiddenUnits = true;
        }

        return $caps;
    }
}
