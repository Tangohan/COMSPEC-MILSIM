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
use App\Repositories\UnitRepository;
use App\Services\Effectifs\UnitCodeService;
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

            return Response::redirect(url('back-office'));
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
        $renumber = null;
        $importPreview = null;
        $contextSample = ['unit' => 'ALPHA', 'grade' => 'SGT'];
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
            if ((string) $request->query('renumeroter', '') === '1') {
                $scope = (string) $request->query('portee', 'off') === 'all' ? 'all' : 'off';
                $numbering = (string) $request->query('numero', 'keep') === 'next' ? 'next' : 'keep';
                try {
                    $renumber = ['scope' => $scope, 'numbering' => $numbering]
                        + $this->memberNumbers->planRenumber($tenantId, $members, $scope, $numbering);
                } catch (\Throwable $e) {
                    error_log('[matricules] aperçu de renumérotation impossible : ' . $e->getMessage());
                    $renumber = ['scope' => $scope, 'numbering' => $numbering, 'error' => 'Aperçu impossible pour le moment.', 'rows' => []];
                }
            }
            $importPreview = Session::get(self::IMPORT_SESSION_KEY);
            if (!is_array($importPreview) || (int) ($importPreview['tenant_id'] ?? 0) !== $tenantId) {
                $importPreview = null;
            }
            $actorId = (int) Session::get('user_id');
            $sample = $this->memberNumbers->contextsFor($tenantId, [$actorId], '{UNIT}{GRADE}')[$actorId] ?? [];
            $contextSample = [
                'unit' => (string) (($sample['unit'] ?? '') !== '' ? $sample['unit'] : 'ALPHA'),
                'grade' => (string) (($sample['grade'] ?? '') !== '' ? $sample['grade'] : 'SGT'),
            ];
        }

        return Response::view('layout.main', [
            'title' => 'Configuration des matricules',
            'content' => 'admin.organization.member_numbers',
            'isBackOfficeShell' => true,
            'boPageGroup' => 'Organisation',
            'boPageTitle' => 'Matricules d’organisation',
            'boPageKicker' => 'PERSONNEL · CONFIGURATION',
            'boPageSubtitle' => 'Identifiant métier propre à la communauté — distinct de l’identifiant plateforme permanent.',
            'memberNumberUnitCodes' => (new UnitCodeService())->sync($tenantId),
            'memberNumberUnits' => (new UnitRepository())->allForTenant($tenantId),
            'memberNumberCanManageCodes' => $this->gate->allows('admin.organization') || $this->gate->allows('admin.access'),
            'memberNumberSchemaReady' => $schemaReady,
            'memberNumberConfig' => $config,
            'memberNumberPreview' => $preview,
            'memberNumberModes' => TenantMemberNumberService::MODES,
            'memberNumberCsrf' => Csrf::token(),
            'memberNumberMembers' => $members,
            'memberNumberAnalysis' => $analysis,
            'memberNumberAudit' => $audit,
            'memberNumberTenantToken' => $schemaReady ? $this->memberNumbers->tenantToken($tenantId) : '',
            'memberNumberRenumber' => $renumber,
            'memberNumberImportPreview' => $importPreview,
            'memberNumberContextSample' => $contextSample,
            'showPortalFooter' => false,
        ]);
    }

    /** Préfixe de communauté et segments des unités de l’organigramme. */
    public function saveOrganigramPrefix(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        if (!$this->canManage() || (!$this->gate->allows('admin.organization') && !$this->gate->allows('admin.access'))) {
            Session::flash('error', 'Seuls les administrateurs de la communauté peuvent modifier les préfixes de l’organigramme.');
            return $this->back();
        }
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1 || !Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');
            return $this->back();
        }
        $codes = new UnitCodeService();
        $unitId = (int) $request->input('unit_id', 0);
        if ($unitId > 0) {
            $units = new UnitRepository();
            $unit = $units->findById($unitId, $tenantId);
            if ($unit === null) {
                Session::flash('error', 'Unité introuvable.');
                return $this->back();
            }
            $normalized = null;
            $error = UnitCodeService::validateSegment($units->allForTenant($tenantId), $unitId, (string) $request->input('segment', ''), $normalized);
            if ($error === null && (string) ($unit['code'] ?? '') !== (string) $normalized) {
                if (!$units->update($unitId, $tenantId, ['code' => $normalized !== '' ? $normalized : null])) {
                    $error = 'Enregistrement du code impossible.';
                } else {
                    UnitCodeService::forget($tenantId);
                    $codes->sync($tenantId);
                }
            }
        } else {
            $error = $codes->changeCommunityCode($tenantId, (string) $request->input('community_code', ''));
        }
        Session::flash($error === null ? 'success' : 'error', $error ?? 'Préfixe enregistré. Les codes de l’organigramme ont été recalculés.');
        return Response::redirect(url('back-office/organisation/matricules') . '#mn-organigramme');
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

    /** Lit le fichier et prépare un aperçu ligne par ligne ; rien n’est écrit avant « Appliquer ». */
    public function importCsv(Request $request, array $params = []): Response
    {
        $guard = $this->guardManage(true, $request);
        if ($guard instanceof Response) {
            return $guard;
        }
        $tenantId = (int) Session::get('tenant_id');
        $file = $_FILES['csv_file'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Session::flash('error', 'Fichier manquant ou invalide.');

            return $this->back();
        }
        $parsed = self::parseImportCsv((string) ($file['tmp_name'] ?? ''));
        if (isset($parsed['error'])) {
            Session::flash('error', $parsed['error']);

            return $this->back();
        }
        Session::set(self::IMPORT_SESSION_KEY, [
            'tenant_id' => $tenantId,
            'file' => mb_substr(basename((string) ($file['name'] ?? 'fichier.csv')), 0, 120),
            'rows' => $this->previewImportRows($tenantId, $parsed['rows']),
        ]);

        return Response::redirect(url('back-office/organisation/matricules') . '#mn-import-title');
    }

    /** Applique les lignes valides de l’aperçu d’import (chacune est revérifiée au moment de l’écriture). */
    public function applyImport(Request $request, array $params = []): Response
    {
        $guard = $this->guardManage(true, $request);
        if ($guard instanceof Response) {
            return $guard;
        }
        $tenantId = (int) Session::get('tenant_id');
        $preview = Session::get(self::IMPORT_SESSION_KEY);
        Session::forget(self::IMPORT_SESSION_KEY);
        if (!is_array($preview) || (int) ($preview['tenant_id'] ?? 0) !== $tenantId) {
            Session::flash('error', 'Aucun import en attente : choisissez de nouveau le fichier.');

            return $this->back();
        }
        $ok = 0;
        $errors = [];
        foreach ((array) ($preview['rows'] ?? []) as $row) {
            if (($row['status'] ?? '') !== 'ok') {
                continue;
            }
            $r = $this->memberNumbers->importForUser($tenantId, (int) $row['user_id'], (string) $row['number'], (int) Session::get('user_id'));
            if (!empty($r['ok'])) {
                ++$ok;
            } else {
                $errors[] = (string) $row['email'] . ' : ' . (string) ($r['error'] ?? 'échec');
            }
        }
        $msg = $ok . ' matricule(s) importé(s).';
        if ($errors !== []) {
            $msg .= ' ' . count($errors) . ' refusé(s) au moment de l’écriture : ' . implode(' · ', array_slice($errors, 0, 5));
        }
        Session::flash($ok > 0 || $errors === [] ? 'success' : 'error', $msg);

        return $this->back();
    }

    public function cancelImport(Request $request, array $params = []): Response
    {
        $guard = $this->guardManage(true, $request);
        if ($guard instanceof Response) {
            return $guard;
        }
        Session::forget(self::IMPORT_SESSION_KEY);
        Session::flash('success', 'Import abandonné : rien n’a été modifié.');

        return $this->back();
    }

    /**
     * Lecture du fichier : séparateur « ; » ou « , » détecté, colonnes email et tenant_member_number.
     *
     * @return array{error?: string, rows: list<array{line: int, email: string, number: string}>}
     */
    public static function parseImportCsv(string $path): array
    {
        if ($path === '' || !is_readable($path)) {
            return ['error' => 'Impossible de lire le fichier.', 'rows' => []];
        }
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            return ['error' => 'Impossible d’ouvrir le fichier.', 'rows' => []];
        }
        $probe = fgets($fh);
        $sep = (is_string($probe) && substr_count($probe, ';') >= substr_count($probe, ',')) ? ';' : ',';
        rewind($fh);
        $header = fgetcsv($fh, 0, $sep, '"', '\\');
        if (!is_array($header) || $header === [] || $header === [null]) {
            fclose($fh);

            return ['error' => 'La première ligne (les titres de colonnes) est introuvable.', 'rows' => []];
        }
        $map = [];
        foreach ($header as $i => $col) {
            $key = strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $col) ?? (string) $col));
            $map[$key] = (int) $i;
        }
        if (!isset($map['tenant_member_number'], $map['email'])) {
            fclose($fh);

            return ['error' => 'Colonnes attendues : « email » et « tenant_member_number » (celles du fichier exporté).', 'rows' => []];
        }
        $rows = [];
        $line = 1;
        while (($row = fgetcsv($fh, 0, $sep, '"', '\\')) !== false) {
            ++$line;
            if ($row === [null] || $row === []) {
                continue;
            }
            $email = trim((string) ($row[$map['email']] ?? ''));
            $number = trim((string) ($row[$map['tenant_member_number']] ?? ''));
            if ($email === '' && $number === '') {
                continue;
            }
            $rows[] = ['line' => $line, 'email' => $email, 'number' => $number];
            if (count($rows) >= self::IMPORT_MAX_ROWS) {
                break;
            }
        }
        fclose($fh);

        return ['rows' => $rows];
    }

    /**
     * Statut de chaque ligne sans rien écrire : ok (sera appliquée), same (déjà ce matricule), error (refusée).
     *
     * @param list<array{line: int, email: string, number: string}> $rows
     * @return list<array{line: int, email: string, number: string, user_id: int, name: string, current: string, status: string, message: string}>
     */
    private function previewImportRows(int $tenantId, array $rows): array
    {
        $numberCount = [];
        $emailCount = [];
        foreach ($rows as $r) {
            if ($r['number'] !== '') {
                $numberCount[mb_strtolower($r['number'])] = ($numberCount[mb_strtolower($r['number'])] ?? 0) + 1;
            }
            $emailCount[mb_strtolower($r['email'])] = ($emailCount[mb_strtolower($r['email'])] ?? 0) + 1;
        }
        $out = [];
        foreach ($rows as $r) {
            $item = $r + ['user_id' => 0, 'name' => '', 'current' => '', 'status' => 'error', 'message' => ''];
            if ($r['email'] === '' || $r['number'] === '') {
                $item['message'] = 'Ligne incomplète : il faut l’adresse e-mail et le matricule.';
            } elseif (($emailCount[mb_strtolower($r['email'])] ?? 0) > 1) {
                $item['message'] = 'Ce membre apparaît plusieurs fois dans le fichier.';
            } elseif (($numberCount[mb_strtolower($r['number'])] ?? 0) > 1) {
                $item['message'] = 'Ce matricule apparaît plusieurs fois dans le fichier.';
            } else {
                $user = $this->users->findByEmail($tenantId, $r['email']);
                if ($user === null) {
                    $item['message'] = 'Aucun membre de la communauté avec cette adresse.';
                } else {
                    $uid = (int) $user['id'];
                    $item['user_id'] = $uid;
                    $item['name'] = trim((string) ($user['display_name'] ?? '')) ?: trim((string) ($user['callsign'] ?? ''));
                    $item['current'] = (string) ($this->memberNumbers->getForUser($tenantId, $uid) ?? '');
                    if (mb_strtolower($item['current']) === mb_strtolower($r['number'])) {
                        $item['status'] = 'same';
                        $item['message'] = 'Déjà ce matricule.';
                    } else {
                        $error = $this->memberNumbers->checkManual($tenantId, $uid, $r['number']);
                        if ($error !== null) {
                            $item['message'] = $error;
                        } else {
                            $item['status'] = 'ok';
                        }
                    }
                }
            }
            $out[] = $item;
        }

        return $out;
    }

    private const IMPORT_SESSION_KEY = 'member_number_import_preview';

    private const IMPORT_MAX_ROWS = 5000;

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

    /** Rétablit l’ancienne valeur d’une ligne de l’historique. */
    public function restore(Request $request, array $params = []): Response
    {
        $guard = $this->guardManage(true, $request);
        if ($guard instanceof Response) {
            return $guard;
        }
        $r = $this->memberNumbers->restoreFromAudit((int) Session::get('tenant_id'), (int) $request->input('audit_id', 0), (int) Session::get('user_id'));
        Session::flash(
            !empty($r['ok']) ? 'success' : 'error',
            !empty($r['ok']) ? 'Matricule ' . (string) ($r['value'] ?? '') . ' rétabli.' : (string) ($r['error'] ?? 'Rétablissement impossible.')
        );

        return $this->back();
    }

    /** Applique la renumérotation vue en aperçu (empreinte vérifiée). */
    public function renumber(Request $request, array $params = []): Response
    {
        $guard = $this->guardManage(true, $request);
        if ($guard instanceof Response) {
            return $guard;
        }
        $tenantId = (int) Session::get('tenant_id');
        $scope = (string) $request->input('portee', 'off') === 'all' ? 'all' : 'off';
        $numbering = (string) $request->input('numero', 'keep') === 'next' ? 'next' : 'keep';
        $r = $this->memberNumbers->applyRenumber(
            $tenantId,
            $this->users->listTenantMemberNumbersForTenant($tenantId),
            $scope,
            $numbering,
            (string) $request->input('empreinte', ''),
            (int) Session::get('user_id')
        );
        if (empty($r['ok'])) {
            Session::flash('error', (string) ($r['error'] ?? 'Renumérotation impossible.'));

            return Response::redirect(url('back-office/organisation/matricules') . '?renumeroter=1&portee=' . $scope . '&numero=' . $numbering . '#mn-renum-title');
        }
        $msg = (int) $r['changed'] . ' matricule(s) renuméroté(s).';
        if ((int) ($r['failed'] ?? 0) > 0) {
            $msg .= ' ' . (int) $r['failed'] . ' refusé(s) et laissé(s) inchangé(s).';
        }
        if ((int) ($r['conflicts'] ?? 0) > 0) {
            $msg .= ' ' . (int) $r['conflicts'] . ' en conflit, laissé(s) inchangé(s).';
        }
        Session::flash('success', $msg);

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
