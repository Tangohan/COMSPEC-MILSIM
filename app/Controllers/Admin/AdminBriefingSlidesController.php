<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TacticalBriefingSlideRepository;

/**
 * Diapositives de briefing tactique — images consultées in-game (Arma/Eden Editor) via l'extension.
 */
final class AdminBriefingSlidesController
{
    private const MAX_IMAGE_BYTES = 12 * 1024 * 1024;

    /** @var list<string> */
    private const ALLOWED_IMAGE_MIMES = ['image/jpeg', 'image/png'];

    public function __construct(
        private ?TacticalBriefingSlideRepository $slides = null
    ) {
        $this->slides ??= new TacticalBriefingSlideRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('dashboard'));
        }

        return Response::view('layout.main', [
            'content' => 'admin.briefing_slides.index',
            'title' => 'Diapositives de briefing tactique',
            'briefingSlides' => $this->slides->allForTenant($tenantId),
            'briefingSlidesApiUrl' => url('api/atak/briefing-slides'),
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

            return Response::redirect(url('back-office/atak/briefing-slides'));
        }

        $title = trim((string) $request->input('title', ''));
        $upload = $this->processUpload($tenantId);
        if ($upload['error'] !== null) {
            Session::flash('error', $upload['error']);

            return Response::redirect(url('back-office/atak/briefing-slides'));
        }
        if ($upload['path'] === null) {
            Session::flash('error', 'Choisissez une image (JPG ou PNG).');

            return Response::redirect(url('back-office/atak/briefing-slides'));
        }

        $this->slides->insert($tenantId, [
            'title' => $title,
            'image_path' => $upload['path'],
            'sort_order' => (int) $request->input('sort_order', 0),
            'is_active' => $request->input('is_active') === '1' || $request->input('is_active') === 'on',
        ]);
        Session::flash('success', 'Diapositive ajoutée. Elle sera disponible côté Arma après actualisation dans le jeu.');

        return Response::redirect(url('back-office/atak/briefing-slides'));
    }

    public function update(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('dashboard'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/atak/briefing-slides'));
        }
        $id = (int) ($params['id'] ?? 0);
        $row = $id > 0 ? $this->slides->findByIdForTenant($id, $tenantId) : null;
        if (!$row) {
            Session::flash('error', 'Diapositive introuvable.');

            return Response::redirect(url('back-office/atak/briefing-slides'));
        }

        $imagePath = (string) ($row['image_path'] ?? '');
        $upload = $this->processUpload($tenantId);
        if ($upload['error'] !== null) {
            Session::flash('error', $upload['error']);

            return Response::redirect(url('back-office/atak/briefing-slides'));
        }
        if ($upload['path'] !== null) {
            $this->deleteStoredFile($imagePath);
            $imagePath = $upload['path'];
        }

        $this->slides->update($id, $tenantId, [
            'title' => trim((string) $request->input('title', '')),
            'image_path' => $imagePath,
            'sort_order' => (int) $request->input('sort_order', 0),
            'is_active' => $request->input('is_active') === '1' || $request->input('is_active') === 'on',
        ]);
        Session::flash('success', 'Diapositive mise à jour.');

        return Response::redirect(url('back-office/atak/briefing-slides'));
    }

    public function delete(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('dashboard'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/atak/briefing-slides'));
        }
        $id = (int) ($params['id'] ?? 0);
        $row = $id > 0 ? $this->slides->findByIdForTenant($id, $tenantId) : null;
        if ($row) {
            $this->deleteStoredFile(isset($row['image_path']) ? (string) $row['image_path'] : null);
            $this->slides->delete($id, $tenantId);
            Session::flash('success', 'Diapositive supprimée.');
        }

        return Response::redirect(url('back-office/atak/briefing-slides'));
    }

    /** @return array{path: ?string, error: ?string} */
    private function processUpload(int $tenantId): array
    {
        $file = $_FILES['image_file'] ?? null;
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
            return ['path' => null, 'error' => 'Format non pris en charge. Utilisez JPG ou PNG (le rendu in-game via texture est plus fiable en JPG).'];
        }

        $dirRel = 'uploads/briefing-slides/' . $tenantId;
        $dirAbs = base_path('public/' . $dirRel);
        if (!is_dir($dirAbs) && !@mkdir($dirAbs, 0755, true) && !is_dir($dirAbs)) {
            return ['path' => null, 'error' => 'Le stockage des images n’est pas disponible pour le moment.'];
        }

        $ext = $mime === 'image/png' ? 'png' : 'jpg';
        $name = 'slide-' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destAbs = $dirAbs . '/' . $name;
        $destRel = $dirRel . '/' . $name;

        if (!$this->writeImage($tmp, $destAbs, 1920)) {
            return ['path' => null, 'error' => 'Impossible de traiter cette image. Essayez avec un autre fichier.'];
        }

        return ['path' => $destRel, 'error' => null];
    }

    private function writeImage(string $tmpPath, string $destPath, int $maxWidth): bool
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
            imagecopyresampled($scaled, $im, 0, 0, 0, 0, $maxWidth, $newH, $w, $h);
            imagedestroy($im);
            $im = $scaled;
        }

        $ext = strtolower(pathinfo($destPath, PATHINFO_EXTENSION));
        $ok = $ext === 'png' ? imagepng($im, $destPath, 6) : imagejpeg($im, $destPath, 88);
        imagedestroy($im);

        return (bool) $ok;
    }

    private function deleteStoredFile(?string $relativePath): void
    {
        $relativePath = trim((string) $relativePath);
        if ($relativePath === '' || str_contains($relativePath, '..')) {
            return;
        }
        if (!str_starts_with(str_replace('\\', '/', $relativePath), 'uploads/briefing-slides/')) {
            return;
        }
        $abs = base_path('public/' . ltrim(str_replace('\\', '/', $relativePath), '/'));
        if (is_file($abs)) {
            @unlink($abs);
        }
    }
}
