<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\Courrier\CourrierDocumentRepository;
use App\Repositories\DocumentCategoryRepository;
use App\Repositories\DocumentRepository;
use App\Repositories\TenantDashboardPinRepository;
use App\Services\Dashboard\TenantDashboardPinService;

final class DashboardPinsAdminController
{
    public function __construct(
        private TenantDashboardPinRepository $pinRepository,
        private DocumentRepository $documentRepository,
        private DocumentCategoryRepository $categoryRepository,
        private CourrierDocumentRepository $courrierDocumentRepository,
        private TenantDashboardPinService $pinService,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $deny = $this->denyUnlessManage();
        if ($deny !== null) {
            return $deny;
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        $rows = $this->pinRepository->listOrderedForTenant($tenantId);
        $displayRows = [];
        foreach ($rows as $row) {
            $displayRows[] = [
                'row' => $row,
                'summary' => $this->summarizePinForAdmin($row, $tenantId),
            ];
        }

        return Response::view('layout.main', [
            'title' => 'Raccourcis du tableau de bord',
            'content' => 'admin.organization.dashboard_pins',
            'dashboardPins' => $displayRows,
            'categories' => $this->categoryRepository->listForTenant($tenantId),
            'documents' => $this->documentRepository->listForTenant($tenantId, null, null, null, null, null, null, null, 'title_asc'),
            'courrierDocs' => $this->courrierDocumentRepository->listForTenant($tenantId, null, null, null, null, 200, 0),
            'maxPins' => TenantDashboardPinRepository::MAX_PINS,
            'previewPins' => $this->pinService->listResolvedPinsForViewer($tenantId, $userId),
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        $deny = $this->denyUnlessManage();
        if ($deny !== null) {
            return $deny;
        }
        $tenantId = (int) Session::get('tenant_id');

        return Response::view('layout.main', [
            'title' => 'Ajouter un raccourci',
            'content' => 'admin.organization.dashboard_pins_form',
            'pin' => null,
            'categories' => $this->categoryRepository->listForTenant($tenantId),
            'documents' => $this->documentRepository->listForTenant($tenantId, null, null, null, null, null, null, null, 'title_asc'),
            'courrierDocs' => $this->courrierDocumentRepository->listForTenant($tenantId, null, null, null, null, 200, 0),
            'formAction' => url('back-office/dashboard-pins/store'),
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $deny = $this->denyUnlessManage();
        if ($deny !== null) {
            return $deny;
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/dashboard-pins'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if ($this->pinRepository->countForTenant($tenantId) >= TenantDashboardPinRepository::MAX_PINS) {
            Session::flash('error', 'Nombre maximum de raccourcis atteint (' . TenantDashboardPinRepository::MAX_PINS . ').');

            return Response::redirect(url('back-office/dashboard-pins'));
        }

        $type = trim((string) $request->input('pin_type'));
        $userId = (int) Session::get('user_id');
        $payload = $this->buildPayloadFromRequest($request, $type, $tenantId);
        if (is_string($payload)) {
            Session::flash('error', $payload);

            return Response::redirect(url('back-office/dashboard-pins/create'));
        }
        $payload['created_by'] = $userId > 0 ? $userId : null;

        $this->pinRepository->create($tenantId, $payload);
        Session::flash('success', 'Raccourci ajouté.');

        return Response::redirect(url('back-office/dashboard-pins'));
    }

    public function edit(Request $request, array $params = []): Response
    {
        $deny = $this->denyUnlessManage();
        if ($deny !== null) {
            return $deny;
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $pin = $this->pinRepository->findById($id, $tenantId);
        if (!$pin) {
            Session::flash('error', 'Raccourci introuvable.');

            return Response::redirect(url('back-office/dashboard-pins'));
        }

        return Response::view('layout.main', [
            'title' => 'Modifier un raccourci',
            'content' => 'admin.organization.dashboard_pins_form',
            'pin' => $pin,
            'categories' => $this->categoryRepository->listForTenant($tenantId),
            'documents' => $this->documentRepository->listForTenant($tenantId, null, null, null, null, null, null, null, 'title_asc'),
            'courrierDocs' => $this->courrierDocumentRepository->listForTenant($tenantId, null, null, null, null, 200, 0),
            'formAction' => url('back-office/dashboard-pins/' . $id . '/update'),
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        $deny = $this->denyUnlessManage();
        if ($deny !== null) {
            return $deny;
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/dashboard-pins'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $existing = $this->pinRepository->findById($id, $tenantId);
        if (!$existing) {
            Session::flash('error', 'Raccourci introuvable.');

            return Response::redirect(url('back-office/dashboard-pins'));
        }

        $type = trim((string) $request->input('pin_type'));
        $payload = $this->buildPayloadFromRequest($request, $type, $tenantId);
        if (is_string($payload)) {
            Session::flash('error', $payload);

            return Response::redirect(url('back-office/dashboard-pins/' . $id . '/edit'));
        }

        $this->pinRepository->update($id, $tenantId, $payload);
        Session::flash('success', 'Raccourci mis à jour.');

        return Response::redirect(url('back-office/dashboard-pins'));
    }

    public function delete(Request $request, array $params = []): Response
    {
        $deny = $this->denyUnlessManage();
        if ($deny !== null) {
            return $deny;
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/dashboard-pins'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if ($this->pinRepository->delete($id, $tenantId)) {
            Session::flash('success', 'Raccourci supprimé.');
        } else {
            Session::flash('error', 'Suppression impossible.');
        }

        return Response::redirect(url('back-office/dashboard-pins'));
    }

    public function reorder(Request $request, array $params = []): Response
    {
        $deny = $this->denyUnlessManage();
        if ($deny !== null) {
            return $deny;
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/dashboard-pins'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $orderRaw = $request->input('order');
        $ids = [];
        if (is_array($orderRaw)) {
            foreach ($orderRaw as $v) {
                $ids[] = (int) $v;
            }
        }
        if ($ids !== []) {
            $this->pinRepository->reorder($tenantId, $ids);
            Session::flash('success', 'Ordre mis à jour.');
        }

        return Response::redirect(url('back-office/dashboard-pins'));
    }

    public function move(Request $request, array $params = []): Response
    {
        $deny = $this->denyUnlessManage();
        if ($deny !== null) {
            return $deny;
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/dashboard-pins'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $dir = trim((string) $request->input('direction'));
        $rows = $this->pinRepository->listOrderedForTenant($tenantId);
        $orderedIds = array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $rows);
        $idx = array_search($id, $orderedIds, true);
        if ($idx === false) {
            return Response::redirect(url('back-office/dashboard-pins'));
        }
        if ($dir === 'up' && $idx > 0) {
            $tmp = $orderedIds[$idx - 1];
            $orderedIds[$idx - 1] = $orderedIds[$idx];
            $orderedIds[$idx] = $tmp;
        } elseif ($dir === 'down' && $idx < count($orderedIds) - 1) {
            $tmp = $orderedIds[$idx + 1];
            $orderedIds[$idx + 1] = $orderedIds[$idx];
            $orderedIds[$idx] = $tmp;
        }
        $this->pinRepository->reorder($tenantId, $orderedIds);

        return Response::redirect(url('back-office/dashboard-pins'));
    }

    private function denyUnlessManage(): ?Response
    {
        if (!Session::get('user_id')) {
            return Response::redirect(url('login'));
        }
        if (Gate::getInstance()->deny('dashboard.pins.manage')) {
            Session::flash('error', 'Vous n’avez pas la permission de gérer les raccourcis du tableau de bord.');

            return Response::redirect(url('dashboard'));
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function summarizePinForAdmin(array $row, int $tenantId): string
    {
        $type = (string) ($row['pin_type'] ?? '');
        $title = trim((string) ($row['title'] ?? ''));

        return match ($type) {
            'document_category' => 'Dossier : ' . $this->categoryLabel((int) ($row['document_category_id'] ?? 0), $tenantId) . ($title !== '' ? ' — ' . $title : ''),
            'document' => 'Document : ' . $this->documentLabel((int) ($row['document_id'] ?? 0), $tenantId) . ($title !== '' ? ' — ' . $title : ''),
            'courrier_document' => 'Courrier : ' . $this->courrierLabel((int) ($row['courrier_document_id'] ?? 0), $tenantId) . ($title !== '' ? ' — ' . $title : ''),
            'external_url' => 'Lien : ' . ($title !== '' ? $title : (string) ($row['external_url'] ?? '')),
            'notice' => 'Consigne : ' . ($title !== '' ? $title : '—'),
            default => $type,
        };
    }

    private function categoryLabel(int $id, int $tenantId): string
    {
        if ($id <= 0) {
            return '?';
        }
        $c = $this->categoryRepository->findById($id, $tenantId);

        return $c ? (string) ($c['name'] ?? '#') : '?';
    }

    private function documentLabel(int $id, int $tenantId): string
    {
        if ($id <= 0) {
            return '?';
        }
        $d = $this->documentRepository->findById($id, $tenantId);

        return $d ? (string) ($d['title'] ?? '#') : '?';
    }

    private function courrierLabel(int $id, int $tenantId): string
    {
        if ($id <= 0) {
            return '?';
        }
        $d = $this->courrierDocumentRepository->findById($id, $tenantId);
        if (!$d) {
            return '?';
        }
        $ref = trim((string) ($d['reference_number'] ?? ''));
        $t = trim((string) ($d['title'] ?? ''));

        return $ref !== '' ? $ref : ($t !== '' ? $t : '#' . $id);
    }

    /**
     * @return array<string, mixed>|string error message
     */
    private function buildPayloadFromRequest(Request $request, string $type, int $tenantId): array|string
    {
        $allowed = ['document_category', 'document', 'courrier_document', 'external_url', 'notice'];
        if (!in_array($type, $allowed, true)) {
            return 'Type de raccourci invalide.';
        }

        $title = trim((string) $request->input('title'));
        if (strlen($title) > 500) {
            return 'Titre trop long.';
        }
        $base = ['pin_type' => $type, 'title' => $title !== '' ? $title : null];

        if ($type === 'external_url') {
            $url = trim((string) $request->input('external_url'));
            if ($url === '' || !preg_match('#^https?://#i', $url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
                return 'URL invalide (http ou https requis).';
            }
            if (strlen($url) > 2000) {
                return 'URL trop longue.';
            }

            return array_merge($base, [
                'external_url' => $url,
                'document_category_id' => null,
                'document_id' => null,
                'courrier_document_id' => null,
                'notice_body' => null,
            ]);
        }

        if ($type === 'notice') {
            $body = (string) $request->input('notice_body');
            if (trim($body) === '') {
                return 'Saisissez le texte de la consigne.';
            }

            return array_merge($base, [
                'external_url' => null,
                'document_category_id' => null,
                'document_id' => null,
                'courrier_document_id' => null,
                'notice_body' => $body,
            ]);
        }

        if ($type === 'document_category') {
            $cid = (int) $request->input('document_category_id');
            $c = $this->categoryRepository->findById($cid, $tenantId);
            if (!$c) {
                return 'Dossier documentaire invalide.';
            }

            return array_merge($base, [
                'document_category_id' => $cid,
                'document_id' => null,
                'courrier_document_id' => null,
                'external_url' => null,
                'notice_body' => null,
            ]);
        }

        if ($type === 'document') {
            $did = (int) $request->input('document_id');
            $d = $this->documentRepository->findById($did, $tenantId);
            if (!$d) {
                return 'Document invalide.';
            }

            return array_merge($base, [
                'document_category_id' => null,
                'document_id' => $did,
                'courrier_document_id' => null,
                'external_url' => null,
                'notice_body' => null,
            ]);
        }

        $cdid = (int) $request->input('courrier_document_id');
        $cd = $this->courrierDocumentRepository->findById($cdid, $tenantId);
        if (!$cd) {
            return 'Courrier invalide.';
        }

        return array_merge($base, [
            'document_category_id' => null,
            'document_id' => null,
            'courrier_document_id' => $cdid,
            'external_url' => null,
            'notice_body' => null,
        ]);
    }
}
