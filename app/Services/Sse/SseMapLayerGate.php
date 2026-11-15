<?php

declare(strict_types=1);

namespace App\Services\Sse;

/**
 * Qui peut lire quoi dans les calques SSE des cartes (contrôles d'identité, lieux, pistes de téléphones).
 *
 * Un SEUL point d'appel des gardes SSE pour les calques : quand la politique d'accès par section (SseAccessPolicy) sera
 * en place, seule l'implémentation de cette interface change (SessionSseMapLayerGate → adaptateur de la politique), ni le
 * service, ni le contrôleur, ni les cartes.
 */
interface SseMapLayerGate
{
    /** Communauté du lecteur ; 0 si la porte d'entrée SSE n'est pas franchie (alors rien n'est servi). */
    public function tenantId(): int;

    public function userId(): ?int;

    /** Libellé pour le journal d'audit (jamais d'identité réelle : indicatif ou nom affiché du compte). */
    public function actorLabel(): string;

    public function canReadControls(): bool;

    public function canReadPlaces(): bool;

    /** Droit sur les fichiers de personnes : nom de la fiche sur un point de contrôle. Sans lui, point anonyme. */
    public function canReadPersonFiles(): bool;

    /** Droit sur l'exploitation numérique (téléphones extraits) : pistes de déplacement. */
    public function canReadDigitalExploitation(): bool;
}
