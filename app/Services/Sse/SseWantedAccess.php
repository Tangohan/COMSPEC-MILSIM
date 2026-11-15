<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Controllers\Web\SseIdWorkshopController;
use App\Core\Session;
use App\Repositories\SseCaseRepository;
use App\Support\SseAccessCatalog as Rights;

/**
 * Adaptateur des droits de l'avis de recherche : une seule classe qui dit qui peut quoi, à remplacer si la politique d'accès
 * du portail SSE (SseAccessPolicy) change d'interface. Elle ne crée aucun droit : elle interroge la politique sur les
 * sections qui existent déjà (règles de la communauté comprises) et l'habilitation de lecture.
 *
 *  - lire (avis, détections, chronologie des avis) : lire la section « fichiers » ;
 *  - placer, lever un avis, traiter une détection, régler la notice : écrire dans la section « fichiers » (gestion des
 *    dossiers SSE, attribution des accès ou gestion des fichiers), jamais en invité ;
 *  - générer le PDF : ce même droit d'écrire ET celui de l'atelier de papiers (écrire dans « papiers » avec l'habilitation
 *    Encadrement) ET celui d'exporter de la section « papiers » ;
 *  - vérité de jeu (nature d'une identité, rattachement à une fiche) : habilitation Secret (S).
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
        private bool $read = true,
    ) {
    }

    /** Droits de la session courante, depuis les gardes existantes du portail. */
    public static function fromSession(?SseAccessCodeService $access = null, ?SseClearanceService $clearance = null): self
    {
        $access ??= new SseAccessCodeService();
        $clearance ??= new SseClearanceService($access);
        $guest = $access->isGuest();
        $userId = (int) Session::get('user_id');
        $signed = !$guest && $userId > 0;
        $tenantId = $access->tenantId();
        $tenantId = $tenantId > 0 ? $tenantId : (int) Session::get('tenant_id');
        $context = ['guest' => $guest, 'tenant_id' => $tenantId];
        $allows = static function (string $section, string $level) use ($context): bool {
            try {
                return SseAccessPolicy::sessionAllows($section, $level, $context);
            } catch (\Throwable) {
                return false;
            }
        };
        $read = $allows('fichiers', Rights::LEVEL_READ);
        $manage = $signed && $allows('fichiers', Rights::LEVEL_WRITE);
        $command = self::allows($clearance, SseCaseRepository::CLASS_COMMAND);
        $truth = self::allows($clearance, SseCaseRepository::CLASS_CONFIDENTIAL);
        // Atelier de papiers : écrire dans « papiers » avec l'habilitation Encadrement ; le PDF sort du bureau : exporter.
        $workshop = SseIdWorkshopController::rightsFor(!$signed, $signed && $allows('papiers', Rights::LEVEL_WRITE), $command, $truth);
        $label = trim((string) (Session::get('callsign') ?? Session::get('display_name') ?? ''));

        return new self(
            $guest,
            $manage,
            $workshop['manage'] && $allows('papiers', Rights::LEVEL_EXPORT),
            $truth,
            $tenantId,
            $userId > 0 ? $userId : null,
            $label,
            $read
        );
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

    /** Lire les avis, les détections et leur trace : lire la section « fichiers » (la porte du portail est celle des routes). */
    public function canRead(): bool
    {
        return $this->read && $this->tenantId > 0;
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
