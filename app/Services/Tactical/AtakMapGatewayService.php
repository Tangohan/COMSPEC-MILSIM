<?php

declare(strict_types=1);

namespace App\Services\Tactical;

use App\Repositories\AtakMapGatewayRepository;
use App\Repositories\TenantRepository;

/**
 * Orchestration passerelle ATAK inter-communautés.
 */
final class AtakMapGatewayService
{
    public function __construct(
        private AtakMapGatewayRepository $gateways,
        private TenantRepository $tenants,
    ) {}

    public function schemaReady(): bool
    {
        return $this->gateways->schemaReady();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listDecoratedForTenant(int $tenantId): array
    {
        $this->gateways->expireStale();
        $rows = $this->gateways->listForTenant($tenantId);
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->decorate($row, $tenantId);
        }

        return $out;
    }

    /**
     * @return array{ok: bool, error?: string, gateway?: array<string, mixed>, code?: string}
     */
    public function create(
        int $hostTenantId,
        int $userId,
        string $label,
        bool $shareUnits,
        bool $shareMarkers,
        int $hostMapId = 1
    ): array {
        if (!$this->schemaReady()) {
            return ['ok' => false, 'error' => 'Passerelle indisponible pour le moment. Réessayez après maintenance.'];
        }
        if ($hostTenantId < 1 || $userId < 1) {
            return ['ok' => false, 'error' => 'Session communauté invalide.'];
        }
        if (!$shareUnits && !$shareMarkers) {
            return ['ok' => false, 'error' => 'Choisissez au moins un élément à partager (positions ou marqueurs).'];
        }
        $created = $this->gateways->createOpen(
            $hostTenantId,
            $userId,
            $label,
            $shareUnits,
            $shareMarkers,
            $hostMapId
        );
        if ($created === null) {
            return ['ok' => false, 'error' => 'Impossible de créer le code de passerelle.'];
        }
        $gw = $this->gateways->findById((int) $created['id']);

        return [
            'ok' => true,
            'code' => (string) $created['code'],
            'gateway' => $gw !== null ? $this->decorate($gw, $hostTenantId) : null,
        ];
    }

    /**
     * @return array{ok: bool, error?: string, gateway?: array<string, mixed>}
     */
    public function redeem(int $partnerTenantId, int $userId, string $code, int $partnerMapId = 1): array
    {
        if (!$this->schemaReady()) {
            return ['ok' => false, 'error' => 'Passerelle indisponible pour le moment.'];
        }
        $this->gateways->expireStale();
        $code = strtoupper(preg_replace('/\s+/', '', $code) ?? '');
        if (strlen($code) < 6) {
            return ['ok' => false, 'error' => 'Code invalide. Vérifiez la saisie.'];
        }
        $gw = $this->gateways->findByCode($code);
        if ($gw === null) {
            return ['ok' => false, 'error' => 'Aucun code correspondant. Demandez un nouveau code à l’autre communauté.'];
        }
        $status = (string) ($gw['status'] ?? '');
        if ($status === AtakMapGatewayRepository::STATUS_EXPIRED) {
            return ['ok' => false, 'error' => 'Ce code a expiré. Demandez un nouveau code.'];
        }
        if ($status === AtakMapGatewayRepository::STATUS_REVOKED) {
            return ['ok' => false, 'error' => 'Cette passerelle a été annulée.'];
        }
        if ($status !== AtakMapGatewayRepository::STATUS_OPEN) {
            return ['ok' => false, 'error' => 'Ce code n’est plus disponible pour une nouvelle liaison.'];
        }
        $host = (int) ($gw['host_tenant_id'] ?? 0);
        if ($host === $partnerTenantId) {
            return ['ok' => false, 'error' => 'Vous ne pouvez pas rejoindre une passerelle créée par votre propre communauté.'];
        }
        $ok = $this->gateways->attachPartner((int) $gw['id'], $partnerTenantId, $partnerMapId);
        if (!$ok) {
            return ['ok' => false, 'error' => 'Impossible d’attacher votre communauté. Le code a peut‑être déjà été utilisé.'];
        }
        $fresh = $this->gateways->findById((int) $gw['id']);

        return [
            'ok' => true,
            'gateway' => $fresh !== null ? $this->decorate($fresh, $partnerTenantId) : null,
        ];
    }

    /**
     * @return array{ok: bool, error?: string, gateway?: array<string, mixed>, activated?: bool}
     */
    public function accept(int $tenantId, int $userId, int $gatewayId): array
    {
        if (!$this->schemaReady()) {
            return ['ok' => false, 'error' => 'Passerelle indisponible pour le moment.'];
        }
        $gw = $this->gateways->findById($gatewayId);
        if ($gw === null || !$this->gateways->tenantIsParty($gw, $tenantId)) {
            return ['ok' => false, 'error' => 'Passerelle introuvable pour votre communauté.'];
        }
        $status = (string) ($gw['status'] ?? '');
        if (!in_array($status, [AtakMapGatewayRepository::STATUS_PENDING, AtakMapGatewayRepository::STATUS_ACTIVE], true)) {
            return ['ok' => false, 'error' => 'Cette passerelle n’attend plus de validation.'];
        }
        $this->gateways->recordAcceptance($gatewayId, $tenantId, $userId);
        $activated = $this->gateways->activateIfBilateral($gatewayId);
        $fresh = $this->gateways->findById($gatewayId);

        return [
            'ok' => true,
            'activated' => $activated || ((string) ($fresh['status'] ?? '') === AtakMapGatewayRepository::STATUS_ACTIVE),
            'gateway' => $fresh !== null ? $this->decorate($fresh, $tenantId) : null,
        ];
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public function revoke(int $tenantId, int $gatewayId, ?string $reason = null): array
    {
        if (!$this->schemaReady()) {
            return ['ok' => false, 'error' => 'Passerelle indisponible pour le moment.'];
        }
        $ok = $this->gateways->revoke($gatewayId, $tenantId, $reason);
        if (!$ok) {
            return ['ok' => false, 'error' => 'Impossible d’annuler cette passerelle.'];
        }

        return ['ok' => true];
    }

    /**
     * @return array<string, mixed>
     */
    public function decorate(array $gateway, int $viewerTenantId): array
    {
        $id = (int) ($gateway['id'] ?? 0);
        $hostId = (int) ($gateway['host_tenant_id'] ?? 0);
        $partnerId = (int) ($gateway['partner_tenant_id'] ?? 0);
        $accepted = $this->gateways->acceptedTenantIds($id);
        $hostTenant = $hostId > 0 ? $this->tenants->findById($hostId) : null;
        $partnerTenant = $partnerId > 0 ? $this->tenants->findById($partnerId) : null;
        $hostName = is_array($hostTenant) ? community_display_name($hostTenant) : ('Communauté #' . $hostId);
        $partnerName = $partnerId > 0
            ? (is_array($partnerTenant) ? community_display_name($partnerTenant) : ('Communauté #' . $partnerId))
            : null;

        $status = (string) ($gateway['status'] ?? '');
        $statusLabel = match ($status) {
            AtakMapGatewayRepository::STATUS_OPEN => 'En attente de l’autre communauté',
            AtakMapGatewayRepository::STATUS_PENDING => 'En attente de validation des deux côtés',
            AtakMapGatewayRepository::STATUS_ACTIVE => 'Active',
            AtakMapGatewayRepository::STATUS_REVOKED => 'Annulée',
            AtakMapGatewayRepository::STATUS_EXPIRED => 'Expirée',
            default => 'État inconnu',
        };

        $viewerAccepted = in_array($viewerTenantId, $accepted, true);
        $peerId = $this->gateways->peerTenantId($gateway, $viewerTenantId);
        $peerAccepted = $peerId > 0 && in_array($peerId, $accepted, true);
        $role = $viewerTenantId === $hostId ? 'host' : ($viewerTenantId === $partnerId ? 'partner' : 'none');

        return [
            'id' => $id,
            'join_code' => (string) ($gateway['join_code'] ?? ''),
            'status' => $status,
            'status_label' => $statusLabel,
            'label' => trim((string) ($gateway['label'] ?? '')),
            'share_units' => !empty($gateway['share_units']),
            'share_markers' => !empty($gateway['share_markers']),
            'host_tenant_id' => $hostId,
            'partner_tenant_id' => $partnerId > 0 ? $partnerId : null,
            'host_name' => $hostName,
            'partner_name' => $partnerName,
            'peer_name' => $role === 'host' ? $partnerName : ($role === 'partner' ? $hostName : null),
            'role' => $role,
            'viewer_accepted' => $viewerAccepted,
            'peer_accepted' => $peerAccepted,
            'can_accept' => $status === AtakMapGatewayRepository::STATUS_PENDING && !$viewerAccepted && $role !== 'none',
            'can_revoke' => in_array($status, [
                AtakMapGatewayRepository::STATUS_OPEN,
                AtakMapGatewayRepository::STATUS_PENDING,
                AtakMapGatewayRepository::STATUS_ACTIVE,
            ], true) && $role !== 'none',
            'expires_at' => (string) ($gateway['expires_at'] ?? ''),
            'activated_at' => (string) ($gateway['activated_at'] ?? ''),
            'created_at' => (string) ($gateway['created_at'] ?? ''),
        ];
    }
}
