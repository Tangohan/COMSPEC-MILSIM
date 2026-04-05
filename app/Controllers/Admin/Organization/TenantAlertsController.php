<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantAlertRepository;

final class TenantAlertsController
{
    public function __construct(
        private ?TenantAlertRepository $alerts = null
    ) {
        $this->alerts ??= new TenantAlertRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('dashboard'));
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.tenant_alerts_index',
            'title' => 'Alertes communauté',
            'tenantAlerts' => $this->alerts->allForTenantOrdered($tenantId),
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('dashboard'));
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.tenant_alerts_form',
            'title' => 'Nouvelle alerte communauté',
            'tenantAlert' => null,
            'formAction' => url('back-office/alerts'),
            'formMethod' => 'post',
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('dashboard'));
        }
        if (! Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/alerts'));
        }
        $data = $this->normalize($request);
        if (($data['_error'] ?? '') !== '') {
            Session::flash('error', $data['_error']);
            unset($data['_error']);

            return Response::redirect(url('back-office/alerts/create'));
        }
        unset($data['_error']);
        $this->alerts->insert($tenantId, $data);
        Session::flash('success', 'Alerte créée.');

        return Response::redirect(url('back-office/alerts'));
    }

    public function edit(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('dashboard'));
        }
        $id = (int) ($params['id'] ?? 0);
        $row = $id > 0 ? $this->alerts->findByIdForTenant($id, $tenantId) : null;
        if (! $row) {
            Session::flash('error', 'Alerte introuvable.');

            return Response::redirect(url('back-office/alerts'));
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.tenant_alerts_form',
            'title' => 'Modifier l’alerte #' . $id,
            'tenantAlert' => $row,
            'formAction' => url('back-office/alerts/' . $id . '/update'),
            'formMethod' => 'post',
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('dashboard'));
        }
        if (! Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/alerts'));
        }
        $id = (int) ($params['id'] ?? 0);
        $row = $id > 0 ? $this->alerts->findByIdForTenant($id, $tenantId) : null;
        if (! $row) {
            Session::flash('error', 'Alerte introuvable.');

            return Response::redirect(url('back-office/alerts'));
        }
        $data = $this->normalize($request);
        if (($data['_error'] ?? '') !== '') {
            Session::flash('error', $data['_error']);
            unset($data['_error']);

            return Response::redirect(url('back-office/alerts/' . $id . '/edit'));
        }
        unset($data['_error']);
        $this->alerts->update($id, $tenantId, $data);
        Session::flash('success', 'Alerte enregistrée.');

        return Response::redirect(url('back-office/alerts'));
    }

    public function delete(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('dashboard'));
        }
        if (! Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/alerts'));
        }
        $id = (int) ($params['id'] ?? 0);
        if ($id > 0 && $this->alerts->findByIdForTenant($id, $tenantId)) {
            $this->alerts->delete($id, $tenantId);
            Session::flash('success', 'Alerte supprimée.');
        }

        return Response::redirect(url('back-office/alerts'));
    }

    /** @return array<string, mixed> */
    private function normalize(Request $request): array
    {
        $kind = trim((string) $request->input('kind', 'info'));
        if (! in_array($kind, ['discount', 'novelty', 'info', 'urgent'], true)) {
            $kind = 'info';
        }
        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            return ['_error' => 'Le titre est obligatoire.'];
        }
        $body = trim((string) $request->input('body', ''));
        $ctaLabel = trim((string) $request->input('cta_label', ''));
        $ctaUrlRaw = trim((string) $request->input('cta_url', ''));
        $ctaUrl = $ctaUrlRaw === '' ? null : $this->sanitizeUrl($ctaUrlRaw);
        if ($ctaUrl === false) {
            return ['_error' => 'URL du lien invalide (utilisez une adresse https:// ou un chemin commençant par /).'];
        }
        $coupon = trim((string) $request->input('coupon_code', ''));
        $starts = $this->parseDt($request->input('starts_at'));
        $ends = $this->parseDt($request->input('ends_at'));
        if ($starts !== null && $ends !== null && $ends < $starts) {
            return ['_error' => 'La date de fin doit être postérieure au début.'];
        }

        return [
            'kind' => $kind,
            'title' => $title,
            'body' => $body === '' ? null : $body,
            'cta_label' => $ctaLabel === '' ? null : $ctaLabel,
            'cta_url' => $ctaUrl,
            'coupon_code' => $coupon === '' ? null : $coupon,
            'starts_at' => $starts,
            'ends_at' => $ends,
            'sort_order' => (int) $request->input('sort_order', 0),
            'is_active' => $request->input('is_active') === '1' || $request->input('is_active') === 'on',
            '_error' => '',
        ];
    }

    private function parseDt(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $s = trim((string) $raw);
        $t = strtotime($s);

        return $t !== false ? date('Y-m-d H:i:s', $t) : null;
    }

    /** @return string|null|false */
    private function sanitizeUrl(string $url): string|false|null
    {
        if ($url === '') {
            return null;
        }
        if ($url[0] === '/') {
            return $url;
        }
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        return false;
    }
}
