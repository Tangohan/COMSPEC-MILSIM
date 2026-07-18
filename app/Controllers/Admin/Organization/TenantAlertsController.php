<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantAlertRepository;
use App\Support\TenantAlertVisuals;

final class TenantAlertsController
{
    private const MAX_IMAGE_BYTES = 12 * 1024 * 1024;

    /** @var list<string> */
    private const ALLOWED_IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

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
            'title' => 'Annonces & alertes',
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
            'title' => 'Nouvelle annonce',
            'tenantAlert' => null,
            'formAction' => url('back-office/alerts'),
            'formMethod' => 'post',
            'tenantAlertVisualsEnabled' => true,
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('dashboard'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/alerts'));
        }
        $data = $this->normalize($request, $tenantId, null);
        if (($data['_error'] ?? '') !== '') {
            Session::flash('error', $data['_error']);

            return Response::redirect(url('back-office/alerts/create'));
        }
        unset($data['_error']);
        $this->alerts->insert($tenantId, $data);
        Session::flash('success', 'L’annonce a été créée. Les membres la verront selon la période et l’activation choisies.');

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
        if (!$row) {
            Session::flash('error', 'Annonce introuvable.');

            return Response::redirect(url('back-office/alerts'));
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.tenant_alerts_form',
            'title' => 'Modifier l’annonce',
            'tenantAlert' => $row,
            'formAction' => url('back-office/alerts/' . $id . '/update'),
            'formMethod' => 'post',
            'tenantAlertVisualsEnabled' => true,
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('dashboard'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/alerts'));
        }
        $id = (int) ($params['id'] ?? 0);
        $row = $id > 0 ? $this->alerts->findByIdForTenant($id, $tenantId) : null;
        if (!$row) {
            Session::flash('error', 'Annonce introuvable.');

            return Response::redirect(url('back-office/alerts'));
        }
        $data = $this->normalize($request, $tenantId, $row);
        if (($data['_error'] ?? '') !== '') {
            Session::flash('error', $data['_error']);

            return Response::redirect(url('back-office/alerts/' . $id . '/edit'));
        }
        unset($data['_error']);
        $this->alerts->update($id, $tenantId, $data);
        Session::flash('success', 'Les modifications ont été enregistrées.');

        return Response::redirect(url('back-office/alerts'));
    }

    public function delete(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('dashboard'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/alerts'));
        }
        $id = (int) ($params['id'] ?? 0);
        $row = $id > 0 ? $this->alerts->findByIdForTenant($id, $tenantId) : null;
        if ($row) {
            $this->deleteStoredFile(isset($row['image_path']) ? (string) $row['image_path'] : null);
            $this->deleteStoredFile(isset($row['banner_path']) ? (string) $row['banner_path'] : null);
            $this->alerts->delete($id, $tenantId);
            Session::flash('success', 'L’annonce a été supprimée.');
        }

        return Response::redirect(url('back-office/alerts'));
    }

    /**
     * @param array<string, mixed>|null $existing
     * @return array<string, mixed>
     */
    private function normalize(Request $request, int $tenantId, ?array $existing): array
    {
        $kind = trim((string) $request->input('kind', 'info'));
        if (!in_array($kind, TenantAlertVisuals::kindKeys(), true)) {
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
            return ['_error' => 'Adresse du lien invalide. Indiquez une page du portail (chemin commençant par /) ou une adresse web complète.'];
        }
        $coupon = trim((string) $request->input('coupon_code', ''));
        $starts = $this->parseDt($request->input('starts_at'));
        $ends = $this->parseDt($request->input('ends_at'));
        if ($starts !== null && $ends !== null && $ends < $starts) {
            return ['_error' => 'La date de fin doit être postérieure au début.'];
        }

        $accent = TenantAlertVisuals::sanitizeHexColor((string) $request->input('accent_color', ''));
        $iconKey = trim((string) $request->input('icon_key', 'auto'));
        if (!in_array($iconKey, TenantAlertVisuals::iconKeys(), true)) {
            $iconKey = 'auto';
        }
        if ($iconKey === 'auto') {
            $iconKey = null;
        }

        $imagePath = isset($existing['image_path']) ? (string) ($existing['image_path'] ?? '') : '';
        $bannerPath = isset($existing['banner_path']) ? (string) ($existing['banner_path'] ?? '') : '';
        if ($imagePath === '') {
            $imagePath = null;
        }
        if ($bannerPath === '') {
            $bannerPath = null;
        }

        if ($request->input('remove_image') === '1') {
            $this->deleteStoredFile($imagePath);
            $imagePath = null;
        }
        if ($request->input('remove_banner') === '1') {
            $this->deleteStoredFile($bannerPath);
            $bannerPath = null;
        }

        $imgUp = $this->processUpload($tenantId, 'image_file', 'image', 1200);
        if ($imgUp['error'] !== null) {
            return ['_error' => $imgUp['error']];
        }
        if ($imgUp['path'] !== null) {
            $this->deleteStoredFile($imagePath);
            $imagePath = $imgUp['path'];
        }

        $banUp = $this->processUpload($tenantId, 'banner_file', 'banner', 1800);
        if ($banUp['error'] !== null) {
            return ['_error' => $banUp['error']];
        }
        if ($banUp['path'] !== null) {
            $this->deleteStoredFile($bannerPath);
            $bannerPath = $banUp['path'];
        }

        return [
            'kind' => $kind,
            'display_style' => \App\Support\AlertDisplayStyle::sanitizeTenant(
                (string) $request->input('display_style', \App\Support\AlertDisplayStyle::CLASSIC)
            ),
            'title' => $title,
            'body' => $body === '' ? null : $body,
            'cta_label' => $ctaLabel === '' ? null : $ctaLabel,
            'cta_url' => $ctaUrl,
            'coupon_code' => $coupon === '' ? null : $coupon,
            'accent_color' => $accent,
            'icon_key' => $iconKey,
            'image_path' => $imagePath,
            'banner_path' => $bannerPath,
            'starts_at' => $starts,
            'ends_at' => $ends,
            'sort_order' => (int) $request->input('sort_order', 0),
            'is_active' => $request->input('is_active') === '1' || $request->input('is_active') === 'on',
            '_error' => '',
        ];
    }

    /**
     * @return array{path: ?string, error: ?string}
     */
    private function processUpload(int $tenantId, string $field, string $prefix, int $maxWidth): array
    {
        $file = $_FILES[$field] ?? null;
        if (!is_array($file)) {
            return ['path' => null, 'error' => null];
        }
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE) {
            return ['path' => null, 'error' => null];
        }
        if ($err !== UPLOAD_ERR_OK) {
            return ['path' => null, 'error' => 'Envoi d’image impossible. Vérifiez la taille (max. 12 Mo) et réessayez.'];
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['path' => null, 'error' => 'Fichier image invalide. Réessayez.'];
        }
        if ((int) ($file['size'] ?? 0) > self::MAX_IMAGE_BYTES) {
            return ['path' => null, 'error' => 'Image trop volumineuse : limite de 12 Mo.'];
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: '';
        if (!in_array($mime, self::ALLOWED_IMAGE_MIMES, true)) {
            return ['path' => null, 'error' => 'Format non pris en charge. Utilisez JPG, PNG ou WebP.'];
        }

        $dirRel = 'uploads/tenant-alerts/' . $tenantId;
        $dirAbs = base_path('public/' . $dirRel);
        if (!is_dir($dirAbs) && !@mkdir($dirAbs, 0755, true) && !is_dir($dirAbs)) {
            return ['path' => null, 'error' => 'Le stockage des images n’est pas disponible pour le moment.'];
        }

        $ext = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $name = $prefix . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destAbs = $dirAbs . '/' . $name;
        $destRel = $dirRel . '/' . $name;

        if (!$this->writeImage($tmp, $destAbs, $maxWidth, $ext === 'png')) {
            return ['path' => null, 'error' => 'Impossible de traiter cette image. Essayez avec un autre fichier.'];
        }

        return ['path' => $destRel, 'error' => null];
    }

    private function writeImage(string $tmpPath, string $destPath, int $maxWidth, bool $keepAlpha): bool
    {
        if (!function_exists('imagecreatefromstring')) {
            return @copy($tmpPath, $destPath);
        }
        $bin = @file_get_contents($tmpPath);
        if ($bin === false) {
            return false;
        }
        $im = @imagecreatefromstring($bin);
        if (!$im) {
            return false;
        }
        $w = imagesx($im);
        $h = imagesy($im);
        if ($w < 1 || $h < 1) {
            imagedestroy($im);

            return false;
        }
        if ($w > $maxWidth) {
            $newH = max(1, (int) round($h * ($maxWidth / $w)));
            $scaled = imagecreatetruecolor($maxWidth, $newH);
            if ($scaled === false) {
                imagedestroy($im);

                return false;
            }
            if ($keepAlpha) {
                imagealphablending($scaled, false);
                imagesavealpha($scaled, true);
                $transparent = imagecolorallocatealpha($scaled, 0, 0, 0, 127);
                imagefilledrectangle($scaled, 0, 0, $maxWidth, $newH, $transparent);
            }
            imagecopyresampled($scaled, $im, 0, 0, 0, 0, $maxWidth, $newH, $w, $h);
            imagedestroy($im);
            $im = $scaled;
        }

        $ok = false;
        $ext = strtolower(pathinfo($destPath, PATHINFO_EXTENSION));
        if ($ext === 'png') {
            $ok = imagepng($im, $destPath, 6);
        } elseif ($ext === 'webp' && function_exists('imagewebp')) {
            $ok = imagewebp($im, $destPath, 82);
        } else {
            $ok = imagejpeg($im, $destPath, 85);
        }
        imagedestroy($im);

        return (bool) $ok;
    }

    private function deleteStoredFile(?string $relativePath): void
    {
        $relativePath = trim((string) $relativePath);
        if ($relativePath === '' || str_contains($relativePath, '..')) {
            return;
        }
        if (!str_starts_with(str_replace('\\', '/', $relativePath), 'uploads/tenant-alerts/')) {
            return;
        }
        $abs = base_path('public/' . ltrim(str_replace('\\', '/', $relativePath), '/'));
        if (is_file($abs)) {
            @unlink($abs);
        }
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
