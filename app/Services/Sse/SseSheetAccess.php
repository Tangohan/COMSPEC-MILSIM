<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Session;
use App\Repositories\SseCaseRepository;
use App\Support\SseAccessCatalog as Rights;
use App\Support\SseSheetCatalog as D;

/**
 * Droits d'émettre et de rédiger les fiches PDF : une seule classe qui dit qui peut quoi, sur les sections existantes de la
 * politique d'accès du portail (SseAccessPolicy). Elle ne crée aucun droit.
 *
 *  - rédiger et enregistrer un brouillon, lire les documents émis : lire la section « fichiers » (jamais en invité) ;
 *  - émettre : les droits de D::MODELS[..]['needs'] (exporter « fichiers » ou « papiers », écrire pour la vigilance) ; l'avis de
 *    recherche garde ceux de SseWantedAccess::canGeneratePdf() ;
 *  - gérer les trames de phrases de la communauté : gestion des fichiers SSE (SseWantedAccess::canManage()) ;
 *  - habilitation de diffusion (plafond des données lisibles et de la classification) : celle de SseClearanceService.
 */
final class SseSheetAccess
{
    /** @param \Closure(string, string): bool $allows (section, niveau) => autorisé pour la session */
    public function __construct(
        private SseWantedAccess $wanted,
        private \Closure $allows,
        private bool $signed,
        private string $level = SseCaseRepository::CLASS_INTERNAL,
    ) {
    }

    public static function fromSession(?SseAccessCodeService $access = null, ?SseClearanceService $clearance = null): self
    {
        $access ??= new SseAccessCodeService();
        $clearance ??= new SseClearanceService($access);
        $wanted = SseWantedAccess::fromSession($access, $clearance);
        $guest = $access->isGuest();
        $tenantId = $wanted->tenantId();
        $context = ['guest' => $guest, 'tenant_id' => $tenantId];
        $allows = static function (string $section, string $level) use ($context): bool {
            try {
                return SseAccessPolicy::sessionAllows($section, $level, $context);
            } catch (\Throwable) {
                return false;
            }
        };
        try {
            $level = $clearance->maxLevel();
        } catch (\Throwable) {
            $level = SseCaseRepository::CLASS_INTERNAL;
        }

        return new self($wanted, $allows, !$guest && (int) Session::get('user_id') > 0, $level);
    }

    public function tenantId(): int
    {
        return $this->wanted->tenantId();
    }

    public function userId(): ?int
    {
        return $this->wanted->userId();
    }

    public function actorLabel(): string
    {
        return $this->wanted->actorLabel();
    }

    public function wanted(): SseWantedAccess
    {
        return $this->wanted;
    }

    /** Habilitation de diffusion du rédacteur : plafond des données en clair et de la classification. */
    public function level(): string
    {
        return $this->level;
    }

    /** Lire et rédiger : section « fichiers » en lecture, jamais en invité. */
    public function canDraft(): bool
    {
        return $this->signed && $this->wanted->canRead();
    }

    /** Gérer les trames de phrases de la communauté. */
    public function canManagePhrases(): bool
    {
        return $this->wanted->canManage();
    }

    /** Pourquoi le modèle ne peut pas être émis (texte à montrer), ou null si c'est permis. */
    public function denial(string $model): ?string
    {
        if (!D::exists($model)) {
            return 'Modèle inconnu.';
        }
        if (!$this->signed) {
            return 'Une session invitée ne peut pas émettre de document : connectez-vous avec votre compte.';
        }
        if (!$this->wanted->canRead()) {
            return 'Droit manquant : lire « ' . Rights::sectionLabel('fichiers') . ' ». Demandez-le à un gestionnaire des accès SSE.';
        }
        if ($model === 'notice') {
            if (!$this->wanted->canManage()) {
                return 'Droit manquant : écrire dans « ' . Rights::sectionLabel('fichiers') . ' » (gestion des dossiers SSE). Demandez-le à un gestionnaire des accès SSE.';
            }

            return $this->wanted->canGeneratePdf() ? null : 'Droit manquant : exporter depuis « ' . Rights::sectionLabel('papiers') . ' » avec l’habilitation Encadrement. Demandez-le à un gestionnaire des accès SSE.';
        }
        foreach (D::MODELS[$model]['needs'] as [$section, $level]) {
            if (!($this->allows)($section, $level)) {
                return 'Droit manquant : ' . mb_strtolower(Rights::levelLabel($level)) . ' dans « ' . Rights::sectionLabel($section) . ' ». Demandez-le à un gestionnaire des accès SSE.';
            }
        }

        return null;
    }

    public function can(string $model): bool
    {
        return $this->denial($model) === null;
    }

    /** Droit de chaque modèle : motif de refus ou null. @return array<string, ?string> */
    public function denials(): array
    {
        $out = [];
        foreach (array_keys(D::MODELS) as $model) {
            $out[$model] = $this->denial($model);
        }

        return $out;
    }
}
