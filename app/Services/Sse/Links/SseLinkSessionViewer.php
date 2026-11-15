<?php

declare(strict_types=1);

namespace App\Services\Sse\Links;

use App\Core\Database;
use App\Core\Session;
use App\Repositories\SseInterestCaseRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseAccessPolicy;
use App\Services\Sse\SseClearanceService;
use App\Services\Sse\SseMemberImageGate;
use App\Services\Sse\SseWantedAccess;
use App\Support\SseAccessCatalog;

/**
 * Le lecteur réel du graphe de liaison : la session du portail. Chaque réponse vient des règles qui existent déjà
 * (SseAccessPolicy pour les sections, SseClearanceService pour l'habilitation, SseMemberImageGate pour les identités de
 * membres, codes d'accès pour la portée des dossiers) ; en cas d'erreur la réponse est « non » et l'élément reste restreint.
 */
final class SseLinkSessionViewer implements SseLinkViewer
{
    private SseAccessCodeService $access;

    private SseClearanceService $clearance;

    private SseWantedAccess $rights;

    /** @var array<string, bool> */
    private array $sections = [];

    public function __construct(?SseAccessCodeService $access = null, ?SseClearanceService $clearance = null, ?SseWantedAccess $rights = null)
    {
        $this->access = $access ?? new SseAccessCodeService();
        $this->clearance = $clearance ?? new SseClearanceService($this->access);
        $this->rights = $rights ?? SseWantedAccess::fromSession($this->access, $this->clearance);
    }

    public function tenantId(): int
    {
        return $this->rights->tenantId();
    }

    public function userId(): ?int
    {
        return $this->rights->userId();
    }

    public function isGuest(): bool
    {
        try {
            return $this->access->isGuest() || $this->userId() === null;
        } catch (\Throwable) {
            return true;
        }
    }

    public function canRead(string $section): bool
    {
        return $this->holds($section, SseAccessCatalog::LEVEL_READ);
    }

    public function canWrite(string $section): bool
    {
        return !$this->isGuest() && $this->holds($section, SseAccessCatalog::LEVEL_WRITE);
    }

    /** Droit « gérer » d'une section (fenêtre « Rattacher » : réglage de la communauté, propositions d'alignement). */
    public function canManage(string $section): bool
    {
        return !$this->isGuest() && $this->holds($section, SseAccessCatalog::LEVEL_MANAGE);
    }

    private function holds(string $section, string $level): bool
    {
        $key = $section . ':' . $level;
        if (!isset($this->sections[$key])) {
            try {
                $this->sections[$key] = SseAccessPolicy::sessionAllows($section, $level, ['guest' => $this->access->isGuest(), 'tenant_id' => $this->tenantId()]);
            } catch (\Throwable) {
                $this->sections[$key] = false;
            }
        }

        return $this->sections[$key];
    }

    public function canSeeTruth(): bool
    {
        return $this->rights->canSeeTruth();
    }

    public function clearanceAllows(string $classification): bool
    {
        try {
            return $this->clearance->allows($classification);
        } catch (\Throwable) {
            return false;
        }
    }

    public function memberAllowed(int $ownerUserId): bool
    {
        try {
            $state = ['class' => SseMemberImageGate::CLASS_MEMBER, 'owner' => $ownerUserId, 'identity_id' => null];

            return (bool) (SseMemberImageGate::forSession()->decide($this->tenantId(), $state)['allowed'] ?? false);
        } catch (\Throwable) {
            return false;
        }
    }

    public function caseScope(): ?array
    {
        try {
            $scope = $this->access->caseScope();

            return $scope === null ? null : array_values(array_map('intval', $scope));
        } catch (\Throwable) {
            return [];
        }
    }

    public function canOpenInterest(int $interestCaseId): bool
    {
        try {
            $row = Database::getInstance()->fetchOne(
                'SELECT id, tenant_id, created_by FROM sse_interest_cases WHERE id = :i AND tenant_id = :t',
                ['i' => $interestCaseId, 't' => $this->tenantId()]
            );
            if ($row === null) {
                return false;
            }
            $uid = (int) Session::get('user_id');

            return (new SseInterestCaseRepository())->userCanAccessCase($row, $uid > 0 ? $uid : null, $this->holds('dossiers', SseAccessCatalog::LEVEL_MANAGE));
        } catch (\Throwable) {
            return false;
        }
    }
}
