<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Session;
use App\Support\SseAccessCatalog;
use App\Support\SsePersonWork;

/**
 * Qui regarde la page « Personnes » : communauté, compte, droits de la section « fichiers » (voir, écrire, exporter, gérer),
 * habilitation de lecture et droits sur les avis de recherche. Calculé une fois par requête depuis les gardes existantes du
 * portail (SseAccessPolicy, SseClearanceService, SseWantedAccess) : ce n'est pas un nouveau droit.
 *
 * Écrire (classer, verrouiller, clore) exige un compte identifié (jamais une session invitée), le droit « écrire » de la section
 * « fichiers » et, fiche par fiche, une habilitation au moins égale à la classification de la fiche.
 */
final class SsePersonViewer
{
    public function __construct(
        public readonly int $tenantId,
        public readonly ?int $userId,
        public readonly bool $guest,
        public readonly bool $canRead,
        public readonly bool $canWrite,
        public readonly bool $canManage,
        public readonly bool $canExport,
        public readonly string $level,
        public readonly bool $canSeeTruth = false,
        public readonly bool $canWanted = false,
        public readonly string $label = '',
        public readonly bool $canCases = false,
    ) {
    }

    /** Droits de la session courante. */
    public static function fromSession(?SseAccessCodeService $access = null, ?SseClearanceService $clearance = null): self
    {
        $access ??= new SseAccessCodeService();
        $clearance ??= new SseClearanceService($access);
        $guest = $access->isGuest();
        $userId = (int) Session::get('user_id');
        $tenantId = $access->tenantId();
        $tenantId = $tenantId > 0 ? $tenantId : (int) Session::get('tenant_id');
        $ctx = ['guest' => $guest, 'tenant_id' => $tenantId];
        $allows = static function (string $level) use ($ctx): bool {
            try {
                return SseAccessPolicy::sessionAllows('fichiers', $level, $ctx);
            } catch (\Throwable) {
                return false;
            }
        };
        $signed = !$guest && $userId > 0;
        $wanted = SseWantedAccess::fromSession($access, $clearance);
        try {
            $level = $clearance->maxLevel();
        } catch (\Throwable) {
            $level = 'interne';
        }

        return new self(
            $tenantId,
            $userId > 0 ? $userId : null,
            $guest,
            $allows(SseAccessCatalog::LEVEL_READ),
            $signed && $allows(SseAccessCatalog::LEVEL_WRITE),
            $signed && $allows(SseAccessCatalog::LEVEL_MANAGE),
            $allows(SseAccessCatalog::LEVEL_EXPORT),
            $level,
            $wanted->canSeeTruth(),
            $wanted->canManage(),
            trim((string) (Session::get('callsign') ?? Session::get('display_name') ?? '')),
            $signed && self::policy('dossiers', SseAccessCatalog::LEVEL_WRITE, $ctx)
        );
    }

    /** @param array<string, mixed> $ctx */
    private static function policy(string $section, string $level, array $ctx): bool
    {
        try {
            return SseAccessPolicy::sessionAllows($section, $level, $ctx);
        } catch (\Throwable) {
            return false;
        }
    }

    /** Rang de l'habilitation (0 CUI, 1 C, 2 S, 3 TS). */
    public function rank(): int
    {
        return max(0, SsePersonWork::rank($this->level));
    }

    /** Étiquette de l'acteur pour le journal. */
    public function actor(): string
    {
        return $this->label !== '' ? $this->label : ($this->userId !== null ? 'Compte ' . $this->userId : 'Invité');
    }
}
