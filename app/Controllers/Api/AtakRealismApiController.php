<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakRealismRepository;
use App\Repositories\TacticalPhonePairingRepository;
use App\Repositories\TenantAdminSettingsRepository;
use App\Repositories\UserRepository;
use App\Support\ComspecApiKeyAuth;
use App\Support\SteamId;

final class AtakRealismApiController
{
    /** @var array<string, mixed>|null */
    private ?array $jsonBodyCache = null;

    public function __construct(
        private ?AtakRealismRepository $realismRepository = null,
        private ?TacticalPhonePairingRepository $pairingRepository = null,
        private ?UserRepository $userRepository = null,
        private ?TenantAdminSettingsRepository $adminSettings = null,
    ) {
        $this->realismRepository ??= new AtakRealismRepository();
        $this->pairingRepository ??= new TacticalPhonePairingRepository();
        $this->userRepository ??= new UserRepository();
        $this->adminSettings ??= new TenantAdminSettingsRepository();
    }

    public function terminals(Request $request, array $params = []): Response
    {
        $tenantId = $this->resolveTenantId($request);
        if ($tenantId < 1) {
            return Response::json(['ok' => false, 'error' => 'Connexion requise.'], 401);
        }
        if ($request->method() === 'GET') {
            if ($this->isGameClient()) {
                $terminalUid = trim((string) $request->query('terminal_uid', ''));
                if ($terminalUid === '') {
                    return Response::json(['ok' => false, 'error' => 'Identifiant terminal requis.'], 422);
                }

                return $this->terminalStatusForGame($tenantId, $terminalUid);
            }

            return Response::json(['ok' => true, 'terminals' => $this->realismRepository->listTerminals($tenantId)]);
        }
        if (!$this->writeAllowed($request)) {
            return Response::json(['ok' => false, 'error' => 'Session expirée.'], 419);
        }

        $body = $this->body($request);
        $pairingToken = trim((string) ($body['pairing_token'] ?? $request->input('pairing_token', '')));
        if ($pairingToken !== '') {
            $pairing = $this->pairingRepository->findValidByToken($pairingToken);
            if ($pairing === null || (int) ($pairing['tenant_id'] ?? 0) !== $tenantId) {
                return Response::json(['ok' => false, 'error' => 'Liaison téléphone introuvable ou expirée.'], 422);
            }
            $body['pairing_code'] = $body['pairing_code'] ?? ($pairing['code'] ?? null);
        }

        $body = $body + [
            'terminal_uid' => $request->input('terminal_uid'),
            'terminal_label' => $request->input('terminal_label'),
            'terminal_type' => $request->input('terminal_type'),
            'platform_label' => $request->input('platform_label'),
            'operator_callsign' => $request->input('operator_callsign'),
            'user_id' => $request->input('user_id'),
            'status' => $request->input('status'),
            'notes' => $request->input('notes'),
        ];
        $body['user_id'] = $this->resolveUserId($tenantId, $body);
        $terminal = $this->realismRepository->upsertTerminal($tenantId, $body);

        return Response::json([
            'ok' => true,
            'terminal' => $terminal,
            'atak_defaults' => $this->atakDefaults($tenantId),
        ]);
    }

    public function certificates(Request $request, array $params = []): Response
    {
        $tenantId = $this->resolveTenantId($request);
        if ($tenantId < 1) {
            return Response::json(['ok' => false, 'error' => 'Connexion requise.'], 401);
        }
        if ($request->method() === 'GET') {
            if ($this->isGameClient()) {
                return Response::json(['ok' => false, 'error' => 'Consultation non autorisée.'], 403);
            }

            return Response::json(['ok' => true, 'certificates' => $this->realismRepository->listCertificates($tenantId)]);
        }
        if (!$this->writeAllowed($request)) {
            return Response::json(['ok' => false, 'error' => 'Session expirée.'], 419);
        }

        $defaults = $this->atakDefaults($tenantId);
        if ($this->isGameClient() && !($defaults['automatic_pairing'] ?? true)) {
            return Response::json([
                'ok' => false,
                'error' => 'automatic_pairing_disabled',
                'message' => 'L’enregistrement automatique du terminal est désactivé par votre communauté.',
            ], 403);
        }

        $body = $this->body($request) + [
            'certificate_ref' => $request->input('certificate_ref'),
            'authority_label' => $request->input('authority_label'),
            'terminal_id' => $request->input('terminal_id'),
            'user_id' => $request->input('user_id'),
            'certificate_type' => $request->input('certificate_type'),
            'status' => $request->input('status'),
            'common_name' => $request->input('common_name'),
            'serial_number' => $request->input('serial_number'),
            'fingerprint_sha256' => $request->input('fingerprint_sha256'),
            'valid_from' => $request->input('valid_from'),
            'expires_at' => $request->input('expires_at'),
            'duration_days' => $request->input('duration_days'),
            'revoked_reason' => $request->input('revoked_reason'),
        ];
        if (!isset($body['duration_days']) || (int) $body['duration_days'] < 1) {
            $body['duration_days'] = (int) ($defaults['certificate_duration_days'] ?? 365);
        }
        $body['user_id'] = $this->resolveUserId($tenantId, $body);
        if (($body['status'] ?? '') === '') {
            $body['status'] = 'active';
        }
        $certificate = $this->realismRepository->issueCertificate($tenantId, $body);

        return Response::json([
            'ok' => true,
            'certificate' => $certificate,
            'atak_defaults' => $defaults,
        ]);
    }

    private function terminalStatusForGame(int $tenantId, string $terminalUid): Response
    {
        $terminal = $this->realismRepository->findTerminalByUid($tenantId, $terminalUid);
        $terminalId = (int) ($terminal['id'] ?? 0);
        $certificate = $terminalId > 0
            ? $this->realismRepository->findLatestCertificateForTerminal($tenantId, $terminalId)
            : null;
        $defaults = $this->atakDefaults($tenantId);

        return Response::json([
            'ok' => true,
            'terminal' => $terminal,
            'certificate' => $certificate,
            'atak_defaults' => $defaults,
        ]);
    }

    private function resolveTenantId(Request $request): int
    {
        $matched = ComspecApiKeyAuth::matchedTenantId();
        if ($matched !== null && $matched > 0) {
            return $matched;
        }
        $sid = Session::get('tenant_id');
        if ($sid !== null && $sid !== '') {
            $n = (int) $sid;

            return $n > 0 ? $n : 0;
        }
        $body = $this->body($request);
        if (!empty($body['tenant_id'])) {
            $n = (int) $body['tenant_id'];

            return $n > 0 ? $n : 0;
        }
        $q = $request->query('tenant_id');
        if ($q !== null && $q !== '') {
            $n = (int) $q;

            return $n > 0 ? $n : 0;
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function resolveUserId(int $tenantId, array $body): int
    {
        $userId = (int) ($body['user_id'] ?? 0);
        if ($userId > 0) {
            return $userId;
        }
        $sessionUser = (int) Session::get('user_id');
        if ($sessionUser > 0) {
            return $sessionUser;
        }
        if (!$this->isGameClient()) {
            return 0;
        }
        $steamRaw = trim((string) ($body['steam_uid'] ?? ''));
        $steam = $steamRaw !== '' ? SteamId::normalize($steamRaw) : null;
        if ($steam === null) {
            return 0;
        }
        $user = $this->userRepository->findBySteamIdForTenant($tenantId, $steam);

        return (int) ($user['id'] ?? 0);
    }

    private function writeAllowed(Request $request): bool
    {
        if ($this->isGameClient()) {
            return true;
        }

        return $this->csrfOk($request);
    }

    private function isGameClient(): bool
    {
        return ComspecApiKeyAuth::extractPresentedKey() !== '';
    }

    /**
     * @return array<string, mixed>
     */
    private function atakDefaults(int $tenantId): array
    {
        $settings = $this->adminSettings->getForTenant($tenantId);

        return is_array($settings['atak_defaults'] ?? null) ? $settings['atak_defaults'] : [];
    }

    private function csrfOk(Request $request): bool
    {
        $body = $this->body($request);
        $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $request->input('_csrf_token') ?? ($body['_csrf_token'] ?? ''));

        return Csrf::validate($token);
    }

    /**
     * @return array<string, mixed>
     */
    private function body(Request $request): array
    {
        if ($this->jsonBodyCache !== null) {
            return $this->jsonBodyCache;
        }
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') {
            $this->jsonBodyCache = [];

            return $this->jsonBodyCache;
        }
        $decoded = json_decode($raw, true);
        $this->jsonBodyCache = is_array($decoded) ? $decoded : [];

        return $this->jsonBodyCache;
    }
}
