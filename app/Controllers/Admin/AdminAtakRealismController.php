<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakRealismRepository;
use App\Repositories\AtakDeviceLogRepository;
use App\Services\Rbac\RolePermissionMatrixCatalog;
use App\Support\ModuleFeatureAccess;

final class AdminAtakRealismController
{
    public function __construct(
        private ?AtakRealismRepository $realismRepository = null,
        private ?AtakDeviceLogRepository $deviceLogRepository = null,
    ) {
        $this->realismRepository ??= new AtakRealismRepository();
        $this->deviceLogRepository ??= new AtakDeviceLogRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        $forbidden = ModuleFeatureAccess::guardAtak('view');
        if ($forbidden instanceof Response) {
            return $forbidden;
        }

        $this->realismRepository->repairCorruptIdentitiesForTenant($tenantId);
        $this->realismRepository->persistWebSessionClassification($tenantId);
        $split = AtakRealismRepository::partitionTerminals($this->realismRepository->listTerminals($tenantId));

        return Response::view('layout.main', [
            'content' => 'admin.atak_realism.index',
            'title' => 'Parc de terminaux',
            'atakRealismTerminals' => $split['physical'],
            'atakRealismWebSessions' => $split['web'],
            'canManageAtakTerminals' => ModuleFeatureAccess::allows(RolePermissionMatrixCatalog::MODULE_ATAK, 'manage'),
            'csrfToken' => Csrf::token(),
        ]);
    }

    public function certificates(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        $forbidden = ModuleFeatureAccess::guardAtak('view');
        if ($forbidden instanceof Response) {
            return $forbidden;
        }

        $this->realismRepository->repairCorruptIdentitiesForTenant($tenantId);
        $this->realismRepository->persistWebSessionClassification($tenantId);
        $this->realismRepository->ensureDefaultCryptoDomain($tenantId);

        return Response::view('layout.main', [
            'content' => 'admin.atak_realism.certificates',
            'title' => 'Certificats ATAK',
            'atakRealismTerminals' => $this->realismRepository->listPhysicalTerminals($tenantId),
            'atakRealismCertificates' => $this->realismRepository->listCertificates($tenantId),
            'atakCryptoDomains' => $this->realismRepository->listCryptoDomains($tenantId),
            'csrfToken' => Csrf::token(),
        ]);
    }

    public function terminalJournal(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        $forbidden = ModuleFeatureAccess::guardAtak('view');
        if ($forbidden instanceof Response) {
            return $forbidden;
        }

        $id = (int) ($params['id'] ?? 0);
        $terminal = $this->realismRepository->findTerminalById($tenantId, $id);
        if ($terminal === null) {
            Session::flash('error', 'Cet appareil est introuvable dans le parc.');

            return Response::redirect(url('back-office/atak/realisme'));
        }

        $uid = trim((string) ($terminal['terminal_uid'] ?? ''));
        $level = trim((string) $request->query('niveau', ''));
        $search = trim((string) $request->query('q', ''));
        $beforeId = (int) $request->query('avant', 0);
        $rows = $this->deviceLogRepository->listForTerminal(
            $tenantId,
            $uid,
            200,
            $level !== '' ? $level : null,
            $search !== '' ? $search : null,
            $beforeId > 0 ? $beforeId : null
        );
        $total = $this->deviceLogRepository->countForTerminal($tenantId, $uid);

        return Response::view('layout.main', [
            'content' => 'admin.atak_realism.logs',
            'title' => 'Journal de l’appareil',
            'atakDeviceLogTerminal' => $terminal,
            'atakDeviceLogRows' => $rows,
            'atakDeviceLogTotal' => $total,
            'atakDeviceLogLevel' => $level,
            'atakDeviceLogQuery' => $search,
            'atakDeviceLogHasMore' => count($rows) >= 200,
        ]);
    }

    public function storeTerminal(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');
            return Response::redirect(url('back-office/atak/realisme'));
        }
        $forbidden = ModuleFeatureAccess::guardAtak('manage');
        if ($forbidden instanceof Response) {
            return $forbidden;
        }

        $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '')));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        $this->realismRepository->upsertTerminal($tenantId, [
            'terminal_uid' => $request->input('terminal_uid'),
            'terminal_label' => $request->input('terminal_label'),
            'terminal_type' => $request->input('terminal_type'),
            'platform_label' => $request->input('platform_label'),
            'operator_callsign' => $request->input('operator_callsign'),
            'user_id' => $request->input('user_id'),
            'status' => $request->input('status'),
            'notes' => $request->input('notes'),
            'last_client_ip' => $request->ip(),
            'server_host' => $host,
        ]);
        Session::flash('success', 'Terminal ATAK enregistré.');

        return Response::redirect(url('back-office/atak/realisme'));
    }

    public function deleteTerminal(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/atak/realisme'));
        }
        $forbidden = ModuleFeatureAccess::guardAtak('manage');
        if ($forbidden instanceof Response) {
            return $forbidden;
        }

        $id = (int) ($params['id'] ?? $request->input('id') ?? 0);
        $existing = $this->realismRepository->findTerminalById($tenantId, $id);
        if ($existing === null || !$this->realismRepository->deleteTerminal($tenantId, $id)) {
            Session::flash('error', 'Impossible de retirer cet appareil. Il a peut-être déjà été enlevé.');
        } elseif (AtakRealismRepository::isWebSessionTerminal($existing)) {
            Session::flash('success', 'Session web dissociée du parc.');
        } else {
            Session::flash('success', 'Terminal supprimé du parc.');
        }

        return Response::redirect(url('back-office/atak/realisme'));
    }

    public function dissociateTerminal(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/atak/realisme'));
        }
        $forbidden = ModuleFeatureAccess::guardAtak('manage');
        if ($forbidden instanceof Response) {
            return $forbidden;
        }

        $id = (int) ($params['id'] ?? $request->input('id') ?? 0);
        $existing = $this->realismRepository->findTerminalById($tenantId, $id);
        if ($existing === null) {
            Session::flash('error', 'Cette fiche est introuvable dans le parc.');

            return Response::redirect(url('back-office/atak/realisme'));
        }

        if (AtakRealismRepository::isWebSessionTerminal($existing)) {
            if ($this->realismRepository->deleteTerminal($tenantId, $id)) {
                Session::flash('success', 'Session web dissociée du parc.');
            } else {
                Session::flash('error', 'Impossible de dissocier cette session web.');
            }

            return Response::redirect(url('back-office/atak/realisme'));
        }

        if ($this->realismRepository->markAsWebSession($tenantId, $id)) {
            Session::flash('success', 'Fiche dissociée : ce n’est plus un terminal, c’est une session web.');
        } else {
            Session::flash('error', 'Impossible de dissocier cette fiche.');
        }

        return Response::redirect(url('back-office/atak/realisme'));
    }

    public function deleteTerminalsSelection(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/atak/realisme'));
        }
        $forbidden = ModuleFeatureAccess::guardAtak('manage');
        if ($forbidden instanceof Response) {
            return $forbidden;
        }

        $rawIds = $request->input('ids', []);
        if (!is_array($rawIds)) {
            $rawIds = [];
        }
        $deleted = $this->realismRepository->deleteTerminals($tenantId, $rawIds);
        if ($deleted < 1) {
            Session::flash('error', 'Cochez au moins un appareil à supprimer du parc.');
        } elseif ($deleted === 1) {
            Session::flash('success', '1 appareil supprimé du parc.');
        } else {
            Session::flash('success', $deleted . ' appareils supprimés du parc.');
        }

        return Response::redirect(url('back-office/atak/realisme'));
    }

    public function deleteWebSessions(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/atak/realisme'));
        }
        $forbidden = ModuleFeatureAccess::guardAtak('manage');
        if ($forbidden instanceof Response) {
            return $forbidden;
        }

        $ids = [];
        foreach ($this->realismRepository->listTerminals($tenantId) as $row) {
            if (AtakRealismRepository::isWebSessionTerminal($row)) {
                $ids[] = (int) ($row['id'] ?? 0);
            }
        }
        $deleted = $this->realismRepository->deleteTerminals($tenantId, $ids);
        if ($deleted < 1) {
            Session::flash('error', 'Aucune session web à dissocier.');
        } elseif ($deleted === 1) {
            Session::flash('success', '1 session web dissociée du parc.');
        } else {
            Session::flash('success', $deleted . ' sessions web dissociées du parc.');
        }

        return Response::redirect(url('back-office/atak/realisme'));
    }

    public function storeCertificate(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');
            return Response::redirect(url('back-office/atak/certificats'));
        }
        $forbidden = ModuleFeatureAccess::guardAtak('manage');
        if ($forbidden instanceof Response) {
            return $forbidden;
        }

        $this->realismRepository->issueCertificate($tenantId, [
            'certificate_ref' => $request->input('certificate_ref'),
            'authority_label' => $request->input('authority_label'),
            'terminal_id' => $request->input('terminal_id'),
            'user_id' => $request->input('user_id'),
            'certificate_type' => $request->input('certificate_type'),
            'status' => $request->input('status') ?: 'active',
            'common_name' => $request->input('common_name'),
            'serial_number' => $request->input('serial_number'),
            'fingerprint_sha256' => $request->input('fingerprint_sha256'),
            'valid_from' => $request->input('valid_from'),
            'expires_at' => $request->input('expires_at'),
            'duration_days' => $request->input('duration_days'),
            'revoked_reason' => $request->input('revoked_reason'),
            'crypto_domain_id' => $request->input('crypto_domain_id'),
        ]);
        Session::flash('success', 'Certificat ATAK enregistré.');

        return Response::redirect(url('back-office/atak/certificats'));
    }

    public function storeCryptoDomain(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');
            return Response::redirect(url('back-office/atak/certificats'));
        }
        $forbidden = ModuleFeatureAccess::guardAtak('manage');
        if ($forbidden instanceof Response) {
            return $forbidden;
        }

        $this->realismRepository->upsertCryptoDomain($tenantId, [
            'domain_ref' => $request->input('domain_ref'),
            'label' => $request->input('label'),
            'faction_key' => $request->input('faction_key'),
            'status' => $request->input('status') ?: 'active',
        ]);
        Session::flash('success', 'Réseau de chiffrement enregistré.');

        return Response::redirect(url('back-office/atak/certificats'));
    }

    public function revokeCertificate(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');
            return Response::redirect(url('back-office/atak/certificats'));
        }
        $forbidden = ModuleFeatureAccess::guardAtak('manage');
        if ($forbidden instanceof Response) {
            return $forbidden;
        }

        $id = (int) ($params['id'] ?? $request->input('id') ?? 0);
        $reason = trim((string) $request->input('revoked_reason', ''));
        $updated = $this->realismRepository->revokeCertificate(
            $tenantId,
            $id,
            $reason !== '' ? $reason : 'Révoqué depuis le back-office'
        );
        if ($updated === null) {
            Session::flash('error', 'Certificat introuvable.');
        } else {
            Session::flash('success', 'Certificat révoqué.');
        }

        return Response::redirect(url('back-office/atak/certificats'));
    }

    public function deleteCertificate(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');
            return Response::redirect(url('back-office/atak/certificats'));
        }
        $forbidden = ModuleFeatureAccess::guardAtak('manage');
        if ($forbidden instanceof Response) {
            return $forbidden;
        }

        $id = (int) ($params['id'] ?? $request->input('id') ?? 0);
        if ($this->realismRepository->deleteCertificate($tenantId, $id)) {
            Session::flash('success', 'Certificat supprimé.');
        } else {
            Session::flash('error', 'Certificat introuvable.');
        }

        return Response::redirect(url('back-office/atak/certificats'));
    }
}
