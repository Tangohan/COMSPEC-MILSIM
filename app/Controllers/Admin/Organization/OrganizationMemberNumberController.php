<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UserRepository;
use App\Services\Auth\AuthService;
use App\Services\Personnel\TenantMemberNumberService;

/**
 * Back-office : configuration des matricules d’organisation + import CSV.
 */
final class OrganizationMemberNumberController
{
    public function __construct(
        private ?AuthService $authService = null,
        private ?TenantMemberNumberService $memberNumbers = null,
        private ?UserRepository $users = null,
        private ?Gate $gate = null,
    ) {
        $this->authService ??= Container::get(AuthService::class);
        $this->memberNumbers ??= Container::get(TenantMemberNumberService::class);
        $this->users ??= Container::get(UserRepository::class);
        $this->gate ??= Container::get(Gate::class);
    }

    public function index(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        if (!$this->canManage()) {
            Session::flash('error', 'Vous n’avez pas l’habilitation pour gérer les matricules d’organisation.');

            return Response::redirect(url('back-office/organisation-effectifs'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }

        $schemaReady = $this->memberNumbers->schemaReady();
        $config = $schemaReady ? $this->memberNumbers->getConfig($tenantId) : [];
        $preview = $schemaReady ? $this->memberNumbers->previewNext($tenantId) : null;

        return Response::view('layout.main', [
            'title' => 'Configuration des matricules',
            'content' => 'admin.organization.member_numbers',
            'isBackOfficeShell' => true,
            'boPageGroup' => 'Organisation',
            'boPageTitle' => 'Matricules d’organisation',
            'boPageKicker' => 'PERSONNEL · CONFIGURATION',
            'boPageSubtitle' => 'Identifiant métier propre à la communauté — distinct de l’identifiant plateforme permanent.',
            'memberNumberSchemaReady' => $schemaReady,
            'memberNumberConfig' => $config,
            'memberNumberPreview' => $preview,
            'memberNumberModes' => TenantMemberNumberService::MODES,
            'memberNumberCsrf' => Csrf::token(),
            'showPortalFooter' => false,
        ]);
    }

    public function save(Request $request, array $params = []): Response
    {
        if (!$this->authService->check() || !$this->canManage()) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/organisation/matricules'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1 || !$this->memberNumbers->schemaReady()) {
            Session::flash('error', 'Module indisponible.');

            return Response::redirect(url('back-office/organisation/matricules'));
        }

        $result = $this->memberNumbers->saveConfig($tenantId, [
            'enabled' => (bool) $request->input('enabled'),
            'label' => (string) $request->input('label', TenantMemberNumberService::DEFAULT_LABEL),
            'mode' => (string) $request->input('mode', 'free'),
            'pattern' => (string) $request->input('pattern', '{PREFIX}-{NUMBER:4}'),
            'prefix' => (string) $request->input('prefix', ''),
            'next_sequence' => (int) $request->input('next_sequence', 1),
            'unique_required' => (bool) $request->input('unique_required', 1),
            'required' => (bool) $request->input('required'),
        ]);

        Session::flash(
            $result['ok'] ? 'success' : 'error',
            $result['ok'] ? 'Configuration des matricules enregistrée.' : ($result['error'] ?? 'Échec.')
        );

        return Response::redirect(url('back-office/organisation/matricules'));
    }

    public function importCsv(Request $request, array $params = []): Response
    {
        if (!$this->authService->check() || !$this->canManage()) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/organisation/matricules'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $actorId = (int) Session::get('user_id');
        if ($tenantId < 1 || !$this->memberNumbers->schemaReady()) {
            Session::flash('error', 'Module indisponible.');

            return Response::redirect(url('back-office/organisation/matricules'));
        }

        $file = $_FILES['csv_file'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Session::flash('error', 'Fichier CSV manquant ou invalide.');

            return Response::redirect(url('back-office/organisation/matricules'));
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_readable($tmp)) {
            Session::flash('error', 'Impossible de lire le fichier.');

            return Response::redirect(url('back-office/organisation/matricules'));
        }

        $fh = fopen($tmp, 'rb');
        if ($fh === false) {
            Session::flash('error', 'Ouverture CSV impossible.');

            return Response::redirect(url('back-office/organisation/matricules'));
        }

        $probe = fgets($fh);
        $sep = (is_string($probe) && substr_count($probe, ';') >= substr_count($probe, ',')) ? ';' : ',';
        rewind($fh);
        $header = fgetcsv($fh, 0, $sep);
        if (!is_array($header) || $header === [] || $header === [null]) {
            fclose($fh);
            Session::flash('error', 'En-tête CSV introuvable.');

            return Response::redirect(url('back-office/organisation/matricules'));
        }

        $map = [];
        foreach ($header as $i => $col) {
            $key = strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $col) ?? (string) $col));
            $map[$key] = (int) $i;
        }
        if (!isset($map['tenant_member_number']) || !isset($map['email'])) {
            fclose($fh);
            Session::flash('error', 'Colonnes requises : email et tenant_member_number (séparateur ; ou ,).');

            return Response::redirect(url('back-office/organisation/matricules'));
        }

        $okCount = 0;
        $errCount = 0;
        $errors = [];

        while (($row = fgetcsv($fh, 0, $sep)) !== false) {
            if ($row === [null] || $row === []) {
                continue;
            }
            $email = trim((string) ($row[$map['email'] ?? -1] ?? ''));
            $number = trim((string) ($row[$map['tenant_member_number'] ?? -1] ?? ''));
            if ($email === '' && $number === '') {
                continue;
            }
            if ($email === '' || $number === '') {
                ++$errCount;
                $errors[] = 'Ligne incomplète (email + tenant_member_number requis).';
                continue;
            }
            $user = $this->users->findByEmail($tenantId, $email);
            if ($user === null) {
                ++$errCount;
                $errors[] = $email . ' : membre introuvable dans cette communauté.';
                continue;
            }
            $result = $this->memberNumbers->importForUser($tenantId, (int) $user['id'], $number, $actorId);
            if (!empty($result['ok'])) {
                ++$okCount;
            } else {
                ++$errCount;
                $errors[] = $email . ' : ' . ($result['error'] ?? 'échec');
            }
        }
        fclose($fh);

        $msg = $okCount . ' matricule(s) importé(s).';
        if ($errCount > 0) {
            $msg .= ' ' . $errCount . ' erreur(s).';
            if ($errors !== []) {
                $msg .= ' ' . implode(' · ', array_slice($errors, 0, 5));
            }
            Session::flash($okCount > 0 ? 'success' : 'error', $msg);
        } else {
            Session::flash('success', $msg);
        }

        return Response::redirect(url('back-office/organisation/matricules'));
    }

    private function canManage(): bool
    {
        return $this->gate->allows('personnel.member_number.manage')
            || $this->gate->allows('admin.organization')
            || $this->gate->allows('admin.members.manage');
    }
}
