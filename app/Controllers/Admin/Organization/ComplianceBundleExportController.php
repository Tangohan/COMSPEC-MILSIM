<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TrainingEnrollmentRepository;
use App\Services\Auth\AuthService;
use App\Services\Audit\AuditService;

final class ComplianceBundleExportController
{
    public function __construct(
        private AuthService $auth,
        private TrainingEnrollmentRepository $enrollments,
        private AuditService $audit,
    ) {}

    public function form(Request $request, array $params = []): Response
    {
        $user = $this->auth->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.compliance.export')) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if ($tenantId < 2) {
            return Response::redirect(url('dashboard'));
        }

        return Response::view('layout.main', [
            'title' => 'Export dossier formation',
            'content' => 'admin.organization.compliance_export',
        ]);
    }

    public function download(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/conformite/export-dossier'));
        }
        $user = $this->auth->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.compliance.export')) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $actorId = (int) $user['id'];
        if ($tenantId < 2) {
            return Response::redirect(url('dashboard'));
        }
        $anonymize = $request->input('anonymize') === '1' || $request->input('anonymize') === 'on';

        $rows = $this->enrollments->listCompletedForComplianceExport($tenantId);
        $secret = (string) (function_exists('env') ? env('APP_KEY', 'salt') : 'salt');
        $zip = new \ZipArchive();
        $tmp = tempnam(sys_get_temp_dir(), 'cs_compliance_');
        if ($tmp === false) {
            Session::flash('error', 'Impossible de préparer l’archive.');

            return Response::redirect(url('back-office/conformite/export-dossier'));
        }
        @unlink($tmp);
        if ($zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            Session::flash('error', 'Impossible de créer l’archive.');

            return Response::redirect(url('back-office/conformite/export-dossier'));
        }

        $fh = fopen('php://temp', 'r+');
        if ($fh === false) {
            $zip->close();
            @unlink($tmp);
            Session::flash('error', 'Erreur interne.');

            return Response::redirect(url('back-office/conformite/export-dossier'));
        }
        fputcsv($fh, ['Identifiant', 'Parcours', 'Date de fin', 'N° attestation', 'Fichier PDF'], ';');
        foreach ($rows as $r) {
            $uid = (int) ($r['user_id'] ?? 0);
            $label = $anonymize
                ? 'Opérateur-' . substr(hash('sha256', $secret . ':' . $tenantId . ':' . $uid), 0, 12)
                : (string) ($r['display_name'] ?? $r['email'] ?? ('#' . $uid));
            fputcsv($fh, [
                $label,
                (string) ($r['course_title'] ?? ''),
                (string) ($r['completed_at'] ?? ''),
                (string) ($r['certificate_number'] ?? ''),
                !empty($r['pdf_path']) ? 'certificats/attestation_' . (int) ($r['certificate_id'] ?? 0) . '.pdf' : '',
            ], ';');
        }
        rewind($fh);
        $csv = stream_get_contents($fh) ?: '';
        fclose($fh);
        $zip->addFromString('formations_terminees.csv', $csv);
        $zip->addFromString(
            'lisez_moi.txt',
            "Export généré le " . date('d/m/Y H:i') . ".\n"
            . ($anonymize ? "Mode pseudonymisé : les personnes ne sont pas nommées en clair.\n" : "Mode nominatif : données sensibles — conservation et partage conformes à votre cadre.\n")
        );
        $nPdf = 0;
        foreach ($rows as $r) {
            $pdfPath = trim((string) ($r['pdf_path'] ?? ''));
            $certId = (int) ($r['certificate_id'] ?? 0);
            if ($pdfPath === '' || $certId < 1) {
                continue;
            }
            if (!str_starts_with($pdfPath, '/') && !preg_match('#^[A-Za-z]:#', $pdfPath)) {
                $pdfPath = base_path($pdfPath);
            }
            if (!is_file($pdfPath) || !is_readable($pdfPath)) {
                continue;
            }
            $zip->addFile($pdfPath, 'certificats/attestation_' . $certId . '.pdf');
            $nPdf++;
        }
        $zip->close();

        try {
            $this->audit->log(
                'compliance.training_bundle_export',
                $tenantId,
                $actorId,
                'training_export',
                null,
                null,
                json_encode(['anonymized' => $anonymize, 'rows' => count($rows), 'pdf_files' => $nPdf], JSON_UNESCAPED_UNICODE)
            );
        } catch (\Throwable) {
        }

        $response = new Response();
        $response->header('Content-Type', 'application/zip');
        $response->header('Content-Disposition', 'attachment; filename="dossier-formations.zip"');
        $response->setBody((string) file_get_contents($tmp));
        @unlink($tmp);

        return $response;
    }
}
