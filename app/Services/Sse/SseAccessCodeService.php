<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Session;
use App\Repositories\SseAccessCodeRepository;
use App\Repositories\SseCaseRepository;

final class SseAccessCodeService
{
    public const SESSION_UNTIL = 'sse_clearance_until';
    public const SESSION_GUEST = 'sse_guest';
    public const SESSION_TENANT = 'sse_tenant_id';
    public const SESSION_SCOPE = 'sse_case_scope';
    public const SESSION_CODE_ID = 'sse_access_code_id';
    public const SESSION_ACK_VERSION = 'sse_confidentiality_version';
    public const SESSION_ACK_AT = 'sse_confidentiality_accepted_at';
    public const SESSION_ACK_TENANT = 'sse_confidentiality_tenant_id';
    public const SESSION_ACK_CODE_ID = 'sse_confidentiality_code_id';

    /** Version du texte d’engagement — à incrémenter si le texte change. */
    public const CONFIDENTIALITY_VERSION = '2026-08-06';

    public function __construct(private ?SseAccessCodeRepository $repo = null)
    {
        $this->repo ??= new SseAccessCodeRepository();
    }

    /**
     * @return array{ok: true, plain: string, id: int}|array{ok: false, message: string}
     */
    public function issue(
        int $tenantId,
        int $createdBy,
        string $label,
        string $grantType,
        int $ttlHours,
        int $sessionTtlMinutes,
        int $maxUses,
        ?int $caseId = null,
        string $clearanceLevel = SseCaseRepository::CLASS_INTERNAL
    ): array {
        $ttlHours = max(1, min(72, $ttlHours));
        $sessionTtlMinutes = max(30, min(72 * 60, $sessionTtlMinutes));
        $maxUses = max(1, min(50, $maxUses));
        $grantType = $grantType === 'guest' ? 'guest' : 'member';
        // Une habilitation inconnue retombe au plancher : on n'accorde jamais par
        // défaut de valeur, seulement par défaut de refus.
        if (!isset(SseRedactionService::LEVELS[$clearanceLevel])) {
            $clearanceLevel = SseCaseRepository::CLASS_INTERNAL;
        }

        $plain = $this->generatePlainCode();
        $id = $this->repo->create([
            'tenant_id' => $tenantId,
            'code_hash' => hash('sha256', $plain),
            'code_hint' => substr($plain, 0, 4) . '···',
            'label' => $label !== '' ? $label : 'Accès temporaire',
            'grant_type' => $grantType,
            'clearance_level' => $clearanceLevel,
            'case_id' => $caseId,
            'created_by' => $createdBy,
            'expires_at' => date('Y-m-d H:i:s', time() + $ttlHours * 3600),
            'session_ttl_minutes' => $sessionTtlMinutes,
            'max_uses' => $maxUses,
        ]);
        $this->repo->logEvent($tenantId, 'issue', $id, $caseId, $createdBy, null, $label);

        return ['ok' => true, 'plain' => $plain, 'id' => $id];
    }

    /**
     * @return array{ok: true, tenant_id: int, guest: bool, case_scope: list<int>|string}|array{ok: false, message: string}
     */
    public function redeem(string $plain, ?int $userId, bool $hasSseAccessPerm, ?string $actorLabel = null): array
    {
        $row = $this->repo->findValidByPlainCode($plain);
        if ($row === null) {
            return ['ok' => false, 'message' => 'Code invalide, expiré ou déjà utilisé.'];
        }
        $tenantId = (int) $row['tenant_id'];
        $grantType = (string) ($row['grant_type'] ?? 'member');

        if ($grantType === 'member') {
            if ($userId === null || $userId < 1) {
                return ['ok' => false, 'message' => 'Ce code est réservé aux membres connectés. Connectez-vous puis réessayez.'];
            }
            if (!$hasSseAccessPerm) {
                return ['ok' => false, 'message' => 'Votre rôle ne permet pas d’accéder au renseignement interpersonnel. Demandez une habilitation à votre commandement.'];
            }
            $sessionTenant = (int) Session::get('tenant_id');
            if ($sessionTenant > 0 && $sessionTenant !== $tenantId) {
                return ['ok' => false, 'message' => 'Ce code n’appartient pas à votre communauté.'];
            }
        } elseif ($userId !== null && $userId > 0) {
            $sessionTenant = (int) Session::get('tenant_id');
            if ($sessionTenant > 0 && $sessionTenant !== $tenantId) {
                return ['ok' => false, 'message' => 'Ce code n’appartient pas à votre communauté.'];
            }
        }

        $this->repo->incrementUse((int) $row['id']);
        $ttl = max(30, (int) ($row['session_ttl_minutes'] ?? 240));
        $until = time() + $ttl * 60;
        $caseId = isset($row['case_id']) && (int) $row['case_id'] > 0 ? (int) $row['case_id'] : null;
        $guest = ($grantType === 'guest') || $userId === null || $userId < 1;

        Session::set(self::SESSION_UNTIL, $until);
        Session::set(self::SESSION_GUEST, $guest);
        Session::set(self::SESSION_TENANT, $tenantId);
        Session::set(self::SESSION_CODE_ID, (int) $row['id']);
        Session::set(self::SESSION_SCOPE, $caseId !== null ? [$caseId] : 'all');
        $this->clearConfidentialityAck();

        // Habilitation de lecture portée par le code. Absente ou inconnue : plancher.
        $carried = (string) ($row['clearance_level'] ?? '');
        Session::set(
            SseClearanceService::SESSION_LEVEL,
            isset(SseRedactionService::LEVELS[$carried]) ? $carried : SseCaseRepository::CLASS_INTERNAL
        );
        if ($guest) {
            if ($userId === null || $userId < 1) {
                Session::set('tenant_id', $tenantId);
            }
            Session::set(
                'sse_guest_label',
                $actorLabel ?: (string) Session::get('display_name', 'Invité renseignement')
            );
        }

        $this->repo->logEvent(
            $tenantId,
            'redeem',
            (int) $row['id'],
            $caseId,
            $userId,
            $actorLabel,
            $guest ? 'session_invite' : 'session_membre'
        );

        return [
            'ok' => true,
            'tenant_id' => $tenantId,
            'guest' => $guest,
            'case_scope' => $caseId !== null ? [$caseId] : 'all',
        ];
    }

    public function clearSession(): void
    {
        Session::forgetMany([
            self::SESSION_UNTIL,
            self::SESSION_GUEST,
            self::SESSION_TENANT,
            self::SESSION_SCOPE,
            self::SESSION_CODE_ID,
            'sse_guest_label',
            self::SESSION_ACK_VERSION,
            self::SESSION_ACK_AT,
            self::SESSION_ACK_TENANT,
            self::SESSION_ACK_CODE_ID,
            SseClearanceService::SESSION_LEVEL,
        ]);
    }

    /**
     * Entrée commandement / admin : session SSE sans code (délivrance des accès).
     */
    public function establishStaffClearance(int $tenantId, int $sessionTtlMinutes = 240): void
    {
        $ttl = max(30, min(72 * 60, $sessionTtlMinutes));
        Session::set(self::SESSION_UNTIL, time() + $ttl * 60);
        Session::set(self::SESSION_GUEST, false);
        Session::set(self::SESSION_TENANT, $tenantId);
        Session::set(self::SESSION_SCOPE, 'all');
        Session::set(self::SESSION_CODE_ID, 0);
        $this->clearConfidentialityAck();
        // Le personnel n'est pas plafonné par un code : son habilitation vient de
        // ses permissions, calculées à la lecture.
        Session::set(SseClearanceService::SESSION_LEVEL, null);
    }

    public function hasAcceptedConfidentiality(): bool
    {
        if (!$this->hasActiveClearance()) {
            return false;
        }
        $version = (string) Session::get(self::SESSION_ACK_VERSION, '');
        if ($version !== self::CONFIDENTIALITY_VERSION) {
            return false;
        }
        $ackTenant = (int) Session::get(self::SESSION_ACK_TENANT, 0);
        $sessionTenant = $this->tenantId();
        if ($ackTenant < 1 || $sessionTenant < 1 || $ackTenant !== $sessionTenant) {
            return false;
        }
        $ackCode = (int) Session::get(self::SESSION_ACK_CODE_ID, -1);
        $sessionCode = (int) Session::get(self::SESSION_CODE_ID, 0);

        return $ackCode === $sessionCode;
    }

    public function acceptConfidentiality(?int $userId = null, ?string $actorLabel = null): void
    {
        $tenantId = $this->tenantId();
        $codeId = (int) Session::get(self::SESSION_CODE_ID, 0);
        Session::set(self::SESSION_ACK_VERSION, self::CONFIDENTIALITY_VERSION);
        Session::set(self::SESSION_ACK_AT, time());
        Session::set(self::SESSION_ACK_TENANT, $tenantId);
        Session::set(self::SESSION_ACK_CODE_ID, $codeId);
        if ($tenantId > 0) {
            $this->repo->logEvent(
                $tenantId,
                'confidentiality_ack',
                $codeId > 0 ? $codeId : null,
                null,
                $userId,
                $actorLabel,
                'version:' . self::CONFIDENTIALITY_VERSION
            );
        }
    }

    private function clearConfidentialityAck(): void
    {
        Session::forgetMany([
            self::SESSION_ACK_VERSION,
            self::SESSION_ACK_AT,
            self::SESSION_ACK_TENANT,
            self::SESSION_ACK_CODE_ID,
        ]);
    }

    /**
     * Habilitation commandement (délivrer des codes) ou admin communauté.
     */
    public function canEnterAsStaff(): bool
    {
        $userId = (int) Session::get('user_id');
        $tenantId = (int) Session::get('tenant_id');
        if ($userId < 1 || $tenantId < 1) {
            return false;
        }

        return function_exists('can') && (can('atak.sse.grant') || can('admin.access') || can('admin.organization'));
    }

    public function hasActiveClearance(): bool
    {
        $until = (int) Session::get(self::SESSION_UNTIL, 0);
        if ($until < time()) {
            $this->clearSession();

            return false;
        }
        $tenant = (int) Session::get(self::SESSION_TENANT, 0);

        return $tenant > 0;
    }

    public function isGuest(): bool
    {
        return (bool) Session::get(self::SESSION_GUEST, false);
    }

    public function tenantId(): int
    {
        return (int) Session::get(self::SESSION_TENANT, 0);
    }

    /**
     * @return list<int>|null null = tous les dossiers
     */
    public function caseScope(): ?array
    {
        $scope = Session::get(self::SESSION_SCOPE, 'all');
        if ($scope === 'all' || $scope === null) {
            return null;
        }
        if (!is_array($scope)) {
            return [];
        }

        return array_values(array_map('intval', $scope));
    }

    private function generatePlainCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $out = '';
        for ($i = 0; $i < 8; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $out;
    }
}
