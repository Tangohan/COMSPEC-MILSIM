<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantMemberNumberConfigRepository;
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
        $this->gate ??= Gate::getInstance();
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
        $members = [];
        $analysis = null;
        $audit = [];
        if ($schemaReady) {
            try {
                $members = $this->users->listTenantMemberNumbersForTenant($tenantId);
                $analysis = $this->memberNumbers->analyzeRegistry($tenantId, $members);
            } catch (\Throwable $e) {
                error_log('[matricules] registre indisponible : ' . $e->getMessage());
            }
            try {
                $audit = (new TenantMemberNumberConfigRepository())->listRecentAuditForTenant($tenantId, 12);
            } catch (\Throwable $e) {
                error_log('[matricules] historique indisponible : ' . $e->getMessage());
            }
        }

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
            'memberNumberMembers' => $members,
            'memberNumberAnalysis' => $analysis,
            'memberNumberAudit' => $audit,
            'memberNumberTenantToken' => $schemaReady ? $this->memberNumbers->tenantToken($tenantId) : '',
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
        $header = fgetcsv($fh, 0, $sep, '"', '\\');
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

        while (($row = fgetcsv($fh, 0, $sep, '"', '\\')) !== false) {
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

    /** Export du registre (membres actifs et matricules), réimportable tel quel. */
    public function exportCsv(Request $request, array $params = []): Response
    {
        $guard = $this->guardManage(false);
        if ($guard instanceof Response) {
            return $guard;
        }
        $tenantId = (int) Session::get('tenant_id');
        $members = $this->users->listTenantMemberNumbersForTenant($tenantId);
        if ((string) $request->query('filtre', '') === 'sans') {
            $members = array_values(array_filter($members, static fn (array $m): bool => $m['tenant_member_number'] === ''));
        }

        return (new Response())
            ->setStatusCode(200)
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="matricules-' . date('Ymd-Hi') . '.csv"')
            ->header('Cache-Control', 'private, no-store')
            ->setBody(self::registryCsv($members));
    }

    /**
     * CSV « ; » avec BOM (ouvrable dans Excel), colonnes reconnues par l’import.
     *
     * @param list<array{display_name: string, callsign: string, email: string, tenant_member_number: string, athena_identifier: string}> $members
     */
    public static function registryCsv(array $members): string
    {
        $fh = fopen('php://temp', 'w+b');
        if ($fh === false) {
            return '';
        }
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, ['nom', 'indicatif', 'email', 'tenant_member_number', 'matricule_plateforme'], ';', '"', '\\');
        foreach ($members as $m) {
            fputcsv($fh, array_map(
                static fn (string $v): string => self::csvSafe($v),
                [$m['display_name'], $m['callsign'], $m['email'], $m['tenant_member_number'], $m['athena_identifier']]
            ), ';', '"', '\\');
        }
        rewind($fh);
        $csv = (string) stream_get_contents($fh);
        fclose($fh);

        return $csv;
    }

    /** Attribue le prochain matricule à un membre, ou à tous les membres qui n’en ont pas. */
    public function assignNext(Request $request, array $params = []): Response
    {
        $guard = $this->guardManage(true, $request);
        if ($guard instanceof Response) {
            return $guard;
        }
        $tenantId = (int) Session::get('tenant_id');
        $actorId = (int) Session::get('user_id');
        $config = $this->memberNumbers->getConfig($tenantId);
        if (empty($config['enabled']) || (string) ($config['mode'] ?? 'free') === 'free') {
            Session::flash('error', 'La génération demande d’activer les matricules et de choisir le mode automatique ou assisté.');

            return $this->back();
        }

        $userId = (int) $request->input('user_id', 0);
        if ($userId > 0) {
            $r = $this->memberNumbers->assignNext($tenantId, $userId, $actorId, 'Attribution depuis la page Matricules', 'assisted');
            Session::flash(
                !empty($r['ok']) ? 'success' : 'error',
                !empty($r['ok']) ? 'Matricule ' . (string) ($r['value'] ?? '') . ' attribué.' : (string) ($r['error'] ?? 'Attribution impossible.')
            );

            return $this->back();
        }

        $without = [];
        foreach ($this->users->listTenantMemberNumbersForTenant($tenantId) as $m) {
            if ($m['tenant_member_number'] === '') {
                $without[] = (int) $m['id'];
            }
        }
        if ($without === []) {
            Session::flash('success', 'Tous les membres actifs ont déjà un matricule.');

            return $this->back();
        }
        $r = $this->memberNumbers->assignNextToMembersWithout($tenantId, $without, $actorId, self::BULK_LIMIT);
        $msg = $r['assigned'] . ' matricule(s) attribué(s).';
        $left = count($without) - $r['assigned'] - $r['failed'];
        if ($left > 0) {
            $msg .= ' ' . $left . ' membre(s) restent à traiter : relancez l’attribution.';
        }
        if ($r['failed'] > 0) {
            $msg .= ' ' . $r['failed'] . ' échec(s) : ' . implode(' · ', array_slice($r['errors'], 0, 3));
        }
        Session::flash($r['assigned'] > 0 || $r['failed'] === 0 ? 'success' : 'error', $msg);

        return $this->back();
    }

    /** Saisie ou correction du matricule d’un membre (vide = retirer). */
    public function updateMember(Request $request, array $params = []): Response
    {
        $guard = $this->guardManage(true, $request);
        if ($guard instanceof Response) {
            return $guard;
        }
        $tenantId = (int) Session::get('tenant_id');
        $value = trim((string) $request->input('value', ''));
        $r = $this->memberNumbers->assignManual(
            $tenantId,
            (int) $request->input('user_id', 0),
            $value,
            (int) Session::get('user_id'),
            'Correction depuis la page Matricules',
            'manual'
        );
        if (!empty($r['ok'])) {
            Session::flash('success', $value === '' ? 'Matricule retiré.' : 'Matricule ' . $value . ' enregistré.');
        } else {
            Session::flash('error', (string) ($r['error'] ?? 'Enregistrement impossible.'));
        }

        return $this->back();
    }

    /** Recale la prochaine séquence juste après le plus grand numéro déjà attribué. */
    public function realignSequence(Request $request, array $params = []): Response
    {
        $guard = $this->guardManage(true, $request);
        if ($guard instanceof Response) {
            return $guard;
        }
        $tenantId = (int) Session::get('tenant_id');
        $analysis = $this->memberNumbers->analyzeRegistry($tenantId, $this->users->listTenantMemberNumbersForTenant($tenantId));
        $next = (int) ($analysis['suggested_next'] ?? 1);
        $ok = $this->memberNumbers->setNextSequence($tenantId, $next);
        Session::flash($ok ? 'success' : 'error', $ok ? 'Prochaine séquence recalée sur ' . $next . '.' : 'Recalage impossible.');

        return $this->back();
    }

    private const BULK_LIMIT = 200;

    private function guardManage(bool $post, ?Request $request = null): ?Response
    {
        if (!$this->authService->check() || !$this->canManage()) {
            return Response::redirect(url('login'));
        }
        if ($post && ($request === null || !Csrf::validate((string) $request->input('_csrf_token')))) {
            Session::flash('error', 'Session expirée.');

            return $this->back();
        }
        if ((int) Session::get('tenant_id') < 1 || !$this->memberNumbers->schemaReady()) {
            Session::flash('error', 'Module indisponible.');

            return $this->back();
        }

        return null;
    }

    private function back(): Response
    {
        return Response::redirect(url('back-office/organisation/matricules'));
    }

    /** Neutralise les formules quand le fichier est ouvert dans un tableur. */
    private static function csvSafe(string $v): string
    {
        return $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $v : $v;
    }

    private function canManage(): bool
    {
        return $this->gate->allows('personnel.member_number.manage')
            || $this->gate->allows('admin.organization')
            || $this->gate->allows('admin.members.manage');
    }
}
