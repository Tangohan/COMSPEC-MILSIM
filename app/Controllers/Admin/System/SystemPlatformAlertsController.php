<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PlatformAlertRepository;

final class SystemPlatformAlertsController
{
    public function __construct(
        private ?PlatformAlertRepository $alerts = null
    ) {
        $this->alerts ??= new PlatformAlertRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        return Response::view('layout.main', [
            'content' => 'admin.system.platform_alerts_index',
            'title' => 'Alertes plateforme',
            'platformAlerts' => $this->alerts->allOrdered(),
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        return Response::view('layout.main', [
            'content' => 'admin.system.platform_alerts_form',
            'title' => 'Nouvelle alerte plateforme',
            'platformAlert' => null,
            'formAction' => url('admin/system/alerts'),
            'formMethod' => 'post',
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        if (! Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/system/alerts'));
        }
        $data = $this->normalize($request);
        if (($data['_error'] ?? '') !== '') {
            Session::flash('error', $data['_error']);
            unset($data['_error']);

            return Response::redirect(url('admin/system/alerts/create'));
        }
        unset($data['_error']);
        $this->alerts->insert($data);
        Session::flash('success', 'Alerte créée.');

        return Response::redirect(url('admin/system/alerts'));
    }

    public function edit(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $row = $id > 0 ? $this->alerts->findById($id) : null;
        if (! $row) {
            Session::flash('error', 'Alerte introuvable.');

            return Response::redirect(url('admin/system/alerts'));
        }

        return Response::view('layout.main', [
            'content' => 'admin.system.platform_alerts_form',
            'title' => 'Modifier l’alerte #' . $id,
            'platformAlert' => $row,
            'formAction' => url('admin/system/alerts/' . $id . '/update'),
            'formMethod' => 'post',
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        if (! Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/system/alerts'));
        }
        $id = (int) ($params['id'] ?? 0);
        $row = $id > 0 ? $this->alerts->findById($id) : null;
        if (! $row) {
            Session::flash('error', 'Alerte introuvable.');

            return Response::redirect(url('admin/system/alerts'));
        }
        $data = $this->normalize($request);
        if (($data['_error'] ?? '') !== '') {
            Session::flash('error', $data['_error']);
            unset($data['_error']);

            return Response::redirect(url('admin/system/alerts/' . $id . '/edit'));
        }
        unset($data['_error']);
        $this->alerts->update($id, $data);
        Session::flash('success', 'Alerte enregistrée.');

        return Response::redirect(url('admin/system/alerts'));
    }

    public function delete(Request $request, array $params = []): Response
    {
        if (! Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/system/alerts'));
        }
        $id = (int) ($params['id'] ?? 0);
        if ($id > 0 && $this->alerts->findById($id)) {
            $this->alerts->delete($id);
            Session::flash('success', 'Alerte supprimée.');
        }

        return Response::redirect(url('admin/system/alerts'));
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

        $audience = [
            'guest' => $request->input('aud_guest') === '1' || $request->input('aud_guest') === 'on',
            'authenticated' => $request->input('aud_auth') === '1' || $request->input('aud_auth') === 'on',
            'free' => $request->input('aud_free') === '1' || $request->input('aud_free') === 'on',
            'paid' => $request->input('aud_paid') === '1' || $request->input('aud_paid') === 'on',
        ];

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
            'audience_json' => $audience,
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

    /** @return string|null|false null si vide, false si invalide */
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
