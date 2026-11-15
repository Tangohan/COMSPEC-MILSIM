<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Controllers\Web\SseIdWorkshopController;
use App\Core\Session;
use App\Repositories\SseCaseRepository;

/**
 * Adaptateur des droits de l'avis de recherche : une seule classe à remplacer le jour où une politique d'accès commune du
 * portail SSE (SseAccessPolicy) prend le relais des gardes actuelles. Elle ne crée aucun droit : elle réutilise les gardes
 * qui existent.
 *
 *  - lire (fiche, liste des avis, détections) : accès au portail SSE, comme les fichiers ;
 *  - placer, lever un avis, traiter une détection : gestion des dossiers SSE, attribution des accès ou gestion des fichiers
 *    (atak.sse.case.manage, atak.sse.grant, atak.sse.files.admin, admin.access), jamais en invité ;
 *  - générer le PDF : ces mêmes droits de gestion ET ceux de l'atelier de papiers (gestion SSE et habilitation Encadrement) ;
 *  - vérité de jeu (nature d'une identité, rattachement à une fiche) : habilitation Confidentiel.
 *
 * La porte d'entrée (droit d'entrer et confidentialité du portail) reste celle des routes (SsePortalAccessMiddleware et
 * SseConfidentialityMiddleware).
 */
final class SseWantedAccess
{
    public function __construct(
        private bool $guest,
        private bool $manage,
        private bool $workshopManage,
        private bool $truth,
        private int $tenantId,
        private ?int $userId,
        private string $actorLabel = '',
    ) {
    }

    /** Droits de la session courante, depuis les gardes existantes du portail. */
    public static function fromSession(?SseAccessCodeService $access = null, ?SseClearanceService $clearance = null): self
    {
        $access ??= new SseAccessCodeService();
        $clearance ??= new SseClearanceService($access);
        $guest = $access->isGuest();
        $userId = (int) Session::get('user_id');
        $can = static function (array $perms): bool {
            if (!function_exists('can')) {
                return false;
            }
            foreach ($perms as $p) {
                if (can($p)) {
                    return true;
                }
            }

            return false;
        };
        $signed = !$guest && $userId > 0;
        $manage = $signed && $can(['atak.sse.case.manage', 'atak.sse.grant', 'atak.sse.files.admin', 'admin.access']);
        $sseManage = $signed && $can(['atak.sse.case.manage', 'atak.sse.grant', 'admin.access']);
        $command = self::allows($clearance, SseCaseRepository::CLASS_COMMAND);
        $truth = self::allows($clearance, SseCaseRepository::CLASS_CONFIDENTIAL);
        $workshop = SseIdWorkshopController::rightsFor(!$signed, $sseManage, $command, $truth);
        $tenantId = $access->tenantId();
        $label = trim((string) (Session::get('callsign') ?? Session::get('display_name') ?? ''));

        return new self($guest, $manage, $workshop['manage'], $truth, $tenantId > 0 ? $tenantId : (int) Session::get('tenant_id'), $userId > 0 ? $userId : null, $label);
    }

    private static function allows(SseClearanceService $clearance, string $level): bool
    {
        try {
            return $clearance->allows($level);
        } catch (\Throwable) {
            return false;
        }
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    public function actorLabel(): string
    {
        return $this->actorLabel;
    }

    /** Lire la fiche, les avis et les détections : accès au portail SSE (la porte des routes), invité compris. */
    public function canRead(): bool
    {
        return $this->tenantId > 0;
    }

    /** Placer ou lever un avis, traiter une détection, régler la notice. */
    public function canManage(): bool
    {
        return !$this->guest && $this->userId !== null && $this->manage;
    }

    /** Générer et télécharger le PDF de la notice : gestion des fichiers et droits de l'atelier de papiers. */
    public function canGeneratePdf(): bool
    {
        return $this->canManage() && $this->workshopManage;
    }

    /** Vérité de jeu : nature de l'identité, rattachement à une fiche personne. */
    public function canSeeTruth(): bool
    {
        return $this->truth;
    }

    /** Sections de la chronologie que le lecteur peut voir. @return list<string> */
    public function timelineSections(): array
    {
        return $this->truth ? ['base', 'truth'] : ['base'];
    }
}
