<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakRealismRepository;
use App\Support\ModuleFeatureAccess;

final class AdminAtakRealismController
{
    public function __construct(
        private ?AtakRealismRepository $realismRepository = null,
    ) {
        $this->realismRepository ??= new AtakRealismRepository();
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

        return Response::view('layout.main', [
            'content' => 'admin.atak_realism.index',
            'title' => 'Parc de terminaux',
            'atakRealismTerminals' => $this->realismRepository->listTerminals($tenantId),
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
        $this->realismRepository->ensureDefaultCryptoDomain($tenantId);

        return Response::view('layout.main', [
            'content' => 'admin.atak_realism.certificates',
            'title' => 'Certificats ATAK',
            'atakRealismTerminals' => $this->realismRepository->listTerminals($tenantId),
            'atakRealismCertificates' => $this->realismRepository->listCertificates($tenantId),
            'atakCryptoDomains' => $this->realismRepository->listCryptoDomains($tenantId),
            'csrfToken' => Csrf::token(),
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

        $this->realismRepository->upsertTerminal($tenantId, [
            'terminal_uid' => $request->input('terminal_uid'),
            'terminal_label' => $request->input('terminal_label'),
            'terminal_type' => $request->input('terminal_type'),
            'platform_label' => $request->input('platform_label'),
            'operator_callsign' => $request->input('operator_callsign'),
            'user_id' => $request->input('user_id'),
            'status' => $request->input('status'),
            'notes' => $request->input('notes'),
        ]);
        Session::flash('success', 'Terminal ATAK enregistré.');

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
