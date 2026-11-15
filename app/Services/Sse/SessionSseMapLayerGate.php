<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Session;
use App\Repositories\SseCaseRepository;

/**
 * Gardes SSE actuelles appliquées aux calques des cartes (mêmes portes que la photothèque et JNET) :
 *
 *  - porte d'entrée : compte connecté (pas une session invitée), droit d'entrer au Bureau SSE sans code
 *    (SseAccessCodeService::canEnterWithoutCode), engagement de confidentialité accepté dans cette communauté ;
 *  - contrôles et lieux : la porte (les pages « Contrôles reçus » et « Sites exploités » n'exigent rien de plus en lecture) ;
 *  - noms de fiche : la porte, et si la communauté caviarde ses écrans de travail, l'habilitation Secret (S) ;
 *  - pistes de téléphones : la porte (la page « Téléphones du jeu » n'exige rien de plus en lecture).
 *
 * Remplaçable en bloc par l'adaptateur de SseAccessPolicy sans toucher au reste (voir SseMapLayerGate).
 */
final class SessionSseMapLayerGate implements SseMapLayerGate
{
    private ?bool $door = null;

    private ?bool $names = null;

    public function __construct(private ?SseAccessCodeService $access = null)
    {
        $this->access ??= new SseAccessCodeService();
    }

    public function tenantId(): int
    {
        return $this->door() ? (int) Session::get('tenant_id') : 0;
    }

    public function userId(): ?int
    {
        $id = (int) Session::get('user_id');

        return $id > 0 ? $id : null;
    }

    public function actorLabel(): string
    {
        $n = trim((string) (Session::get('callsign') ?: Session::get('display_name') ?: ''));

        return $n !== '' ? mb_substr($n, 0, 80) : 'Membre';
    }

    public function canReadControls(): bool
    {
        return $this->door();
    }

    public function canReadPlaces(): bool
    {
        return $this->door();
    }

    public function canReadPersonFiles(): bool
    {
        if ($this->names !== null) {
            return $this->names;
        }
        if (!$this->door()) {
            return $this->names = false;
        }
        try {
            $clearance = new SseClearanceService($this->access);
            $tenant = (int) Session::get('tenant_id');

            return $this->names = !$clearance->workingRedactionEnabled($tenant) || $clearance->allows(SseCaseRepository::CLASS_CONFIDENTIAL);
        } catch (\Throwable) {
            return $this->names = false;
        }
    }

    public function canReadDigitalExploitation(): bool
    {
        return $this->door();
    }

    private function door(): bool
    {
        if ($this->door !== null) {
            return $this->door;
        }
        $user = (int) Session::get('user_id');
        $tenant = (int) Session::get('tenant_id');
        if ($user < 1 || $tenant < 1) {
            return $this->door = false;
        }
        try {
            $sse = $this->access;

            return $this->door = $sse !== null
                && !$sse->isGuest()
                && $sse->canEnterWithoutCode()
                && $sse->hasAcceptedConfidentiality()
                && $sse->tenantId() === $tenant;
        } catch (\Throwable) {
            return $this->door = false;
        }
    }
}
