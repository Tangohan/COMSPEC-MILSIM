<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Session;
use App\Support\SseAccessCatalog;

/**
 * Qui agit sur les dossiers d'intérêt : la communauté et le compte viennent de la session (jamais de la requête), les droits
 * de SseAccessPolicy (section « dossiers » : lire, écrire, supprimer, exporter, gérer) et le plafond d'habilitation de
 * SseClearanceService sont lus une fois par le contrôleur et posés ici.
 */
final class SseInterestActor
{
    /**
     * @param array<string, bool> $readSections droit de lecture des sections qu'un lien peut viser (fichiers, terrain, dossiers)
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly ?int $userId,
        public readonly string $label,
        public readonly bool $canRead,
        public readonly bool $canWrite,
        public readonly bool $canDelete,
        public readonly bool $canExport,
        public readonly bool $canManage,
        public readonly string $maxLevel = 'interne',
        public readonly bool $isGuest = false,
        public readonly array $readSections = [],
    ) {
    }

    /**
     * L'acteur de la session courante : communauté, compte et droits de la section « dossiers » (SseAccessPolicy), plafond
     * d'habilitation (SseClearanceService). Utilisé par le contrôleur, la recherche globale et JNET : un seul endroit.
     */
    public static function fromSession(?SseAccessCodeService $access = null, ?SseClearanceService $clearance = null): self
    {
        $access ??= new SseAccessCodeService();
        $tenant = $access->tenantId();
        $tenant = $tenant > 0 ? $tenant : (int) Session::get('tenant_id');
        $guest = $access->isGuest();
        $ctx = ['guest' => $guest, 'tenant_id' => $tenant];
        $allows = static fn (string $section, string $level): bool => SseAccessPolicy::sessionAllows($section, $level, $ctx);
        $userId = (int) Session::get('user_id');
        $label = trim((string) (Session::get('sse_guest_label') ?? Session::get('display_name') ?? Session::get('callsign') ?? ''));
        try {
            $level = ($clearance ?? new SseClearanceService($access))->maxLevel();
        } catch (\Throwable) {
            $level = 'interne';
        }

        return new self(
            $tenant,
            $userId > 0 && !$guest ? $userId : null,
            $label !== '' ? $label : 'Analyste',
            $allows('dossiers', SseAccessCatalog::LEVEL_READ),
            $allows('dossiers', SseAccessCatalog::LEVEL_WRITE),
            $allows('dossiers', SseAccessCatalog::LEVEL_DELETE),
            $allows('dossiers', SseAccessCatalog::LEVEL_EXPORT),
            $allows('dossiers', SseAccessCatalog::LEVEL_MANAGE),
            $level,
            $guest,
            [
                'dossiers' => $allows('dossiers', SseAccessCatalog::LEVEL_READ),
                'fichiers' => $allows('fichiers', SseAccessCatalog::LEVEL_READ),
                'terrain' => $allows('terrain', SseAccessCatalog::LEVEL_READ),
            ]
        );
    }
    public function maxRank(): int
    {
        return SseRedactionService::levelRank($this->maxLevel);
    }

    /** Filtre du dépôt : accès nominatifs (contournés par les gestionnaires) et plafond d'habilitation. */
    public function viewer(): array
    {
        return ['user_id' => $this->userId, 'bypass_acl' => $this->canManage, 'max_rank' => $this->maxRank()];
    }

    public function canReadSection(string $section): bool
    {
        return $this->canRead && ($this->readSections[$section] ?? false);
    }
}
