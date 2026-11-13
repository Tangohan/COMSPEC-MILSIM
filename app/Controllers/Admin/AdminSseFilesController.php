<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseAuditLogRepository;
use App\Repositories\SseFileRecordRepository;
use App\Repositories\SseFileRepository;
use App\Services\Sse\SseFileAdminService;
use App\Services\Sse\SsePhoneExtractionService;
use App\Support\SseRecordsCountryPacks;

/**
 * Back-office › Renseignement › Fichiers SSE : fiches de la communauté (recherche, doublons, fusion), extractions de
 * téléphones à modérer, journal complet des consultations, réglages du module (pays par défaut, conservation, droits).
 *
 * Droit : atak.sse.files.admin, ou administration de la communauté (admin.access / admin.organization). Toutes les
 * actions portent la communauté de la session et le jeton CSRF ; elles sont inscrites au journal d'audit SSE.
 */
final class AdminSseFilesController
{
    public const BASE = 'back-office/renseignement/fichiers';

    public const TABS = ['fiches' => 'Fiches', 'extractions' => 'Extractions de téléphones', 'journal' => 'Journal des consultations', 'reglages' => 'Réglages'];

    public function __construct(private ?SseFileRecordRepository $records = null)
    {
    }

    public static function allowed(): bool
    {
        if ((int) Session::get('tenant_id') < 1 || (int) Session::get('user_id') < 1) {
            return false;
        }

        return (function_exists('is_platform_admin') && is_platform_admin())
            || (function_exists('can') && (can('atak.sse.files.admin') || can('admin.access') || can('admin.organization')));
    }

    public function index(Request $request, array $params = []): Response
    {
        if (!self::allowed()) {
            return $this->deny();
        }
        $tenantId = (int) Session::get('tenant_id');
        $tab = (string) ($params['tab'] ?? 'fiches');
        $tab = isset(self::TABS[$tab]) ? $tab : 'fiches';
        $data = ['content' => 'admin.sse_files.index', 'title' => 'Fichiers SSE', 'tab' => $tab, 'q' => mb_substr(trim($request->queryString('q')), 0, 80)];
        $page = max(1, (int) $request->queryString('page', '1'));
        try {
            $admin = new SseFileAdminService($this->records());
            match ($tab) {
                'fiches' => $data += ['subjects' => $admin->subjects($tenantId, $data['q'], $page), 'duplicates' => $admin->duplicates($tenantId), 'page' => $page],
                'extractions' => $data += ['extractions' => (new SsePhoneExtractionService($this->records()))->list($tenantId, ['q' => $data['q'], 'status' => $request->queryString('etat'), 'page' => $page], true), 'status' => $request->queryString('etat'), 'page' => $page],
                'journal' => $data += ['rows' => (new SseFileRepository())->listQueries($tenantId, ['q' => $data['q'], 'source' => $request->queryString('source'), 'limit' => 300])],
                default => $data += ['settings' => $this->records()->settings($tenantId), 'countries' => SseRecordsCountryPacks::codes()],
            };
        } catch (\Throwable $e) {
            error_log('Back-office fichiers SSE : ' . $e->getMessage());
            Session::flash('error', 'Les fichiers ne répondent pas pour le moment (étapes de migration 4340 et 4350 passées ?).');
        }

        return Response::view('layout.main', $data);
    }

    public function merge(Request $request, array $params = []): Response
    {
        if (!self::allowed() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            return $this->deny();
        }
        $tenantId = (int) Session::get('tenant_id');
        $from = self::ref($request->inputString('from'));
        $into = self::ref($request->inputString('into'));
        $ok = (new SseFileAdminService($this->records()))->merge($tenantId, $from, $into, (int) Session::get('user_id'));
        if ($ok) {
            $this->audit($tenantId, 'file.merge', $into, 'IDT-' . $from . ' fusionnée dans IDT-' . $into);
        }
        Session::flash($ok ? 'success' : 'error', $ok ? 'Fiches fusionnées : IDT-' . $from . ' rejoint IDT-' . $into . '.' : 'Fusion impossible : vérifiez les deux références (IDT-…), différentes et de la communauté.');

        return Response::redirect(url(self::BASE));
    }

    public function moderate(Request $request, array $params = []): Response
    {
        if (!self::allowed() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            return $this->deny();
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $phones = new SsePhoneExtractionService($this->records());
        $action = $request->inputString('action');
        if ($action === 'purge' && $request->inputString('confirm') === 'SUPPRIMER') {
            $ok = $phones->purge($tenantId, $id);
            $msg = 'Extraction supprimée définitivement.';
        } elseif ($action === 'purge') {
            $ok = false;
            $msg = 'Tapez SUPPRIMER pour confirmer la suppression définitive.';
        } else {
            $ok = $phones->moderate($tenantId, $id, $action === 'hide' ? 'hidden' : 'visible', (int) Session::get('user_id'), $request->inputString('note'));
            $msg = $action === 'hide' ? 'Extraction masquée aux membres.' : 'Extraction visible par les membres.';
        }
        if ($ok) {
            $this->audit($tenantId, 'phone_extraction.' . $action, $id, $msg);
        }
        Session::flash($ok ? 'success' : 'error', $ok ? $msg : ($action === 'purge' ? $msg : 'Extraction introuvable.'));

        return Response::redirect(url(self::BASE . '/extractions'));
    }

    public function settingsSave(Request $request, array $params = []): Response
    {
        if (!self::allowed() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            return $this->deny();
        }
        $tenantId = (int) Session::get('tenant_id');
        $country = strtoupper($request->inputString('default_country'));
        $this->records()->saveSettings($tenantId, [
            'default_country' => SseRecordsCountryPacks::exists($country) ? $country : SseRecordsCountryPacks::DEFAULT,
            'query_retention_days' => (int) $request->input('query_retention_days', 0),
            'extraction_retention_days' => (int) $request->input('extraction_retention_days', 0),
            'extractions_enabled' => $request->inputString('extractions_enabled') === '1',
            'extractions_visibility' => $request->inputString('extractions_visibility'),
            'member_photo_upload' => $request->inputString('member_photo_upload') === '1',
            'xref_enabled' => $request->inputString('xref_enabled') === '1',
            'purpose_required' => $request->inputString('purpose_required') === '1',
        ], (int) Session::get('user_id'));
        $settings = $this->records()->settings($tenantId);
        $purged = (new SseFileAdminService($this->records()))->purge($tenantId, (int) $settings['query_retention_days'], (int) $settings['extraction_retention_days']);
        $this->audit($tenantId, 'file.settings', 0, 'Réglages des fichiers enregistrés');
        Session::flash('success', 'Réglages enregistrés.' . ($purged['queries'] + $purged['extractions'] > 0 ? ' Purge : ' . $purged['queries'] . ' consultation(s), ' . $purged['extractions'] . ' extraction(s).' : ''));

        return Response::redirect(url(self::BASE . '/reglages'));
    }

    private static function ref(string $v): int
    {
        return preg_match('/^(?:IDT-)?0*(\d{1,10})$/i', trim($v), $m) === 1 ? (int) $m[1] : 0;
    }

    private function audit(int $tenantId, string $action, int $objectId, string $reason): void
    {
        try {
            (new SseAuditLogRepository())->write([
                'tenant_id' => $tenantId, 'actor_user_id' => (int) Session::get('user_id'), 'actor_label' => 'Back-office · Fichiers SSE',
                'action' => $action, 'object_type' => 'sse_files', 'object_id' => $objectId, 'reason' => $reason,
            ]);
        } catch (\Throwable $e) {
            error_log('Back-office fichiers SSE : audit indisponible : ' . $e->getMessage());
        }
    }

    private function deny(): Response
    {
        if ((int) Session::get('user_id') < 1) {
            return Response::redirect(url('login'));
        }
        Session::flash('error', 'Réservé à la gestion des fichiers SSE.');

        return Response::redirect(url('back-office'));
    }

    private function records(): SseFileRecordRepository
    {
        return $this->records ??= new SseFileRecordRepository();
    }
}
