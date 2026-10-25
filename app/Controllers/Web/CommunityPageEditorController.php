<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantRepository;
use App\Services\Community\CommunityPageEditorAccess;
use App\Services\Community\PublicPageBuilder;

/**
 * Édition en place de la vitrine /c/{slug} : brouillon, publication, images, rendu d’un bloc.
 * Chaque appel vérifie le jeton CSRF (en-tête X-CSRF-Token) et que la personne gère CETTE communauté.
 */
final class CommunityPageEditorController
{
    private const MAX_PAYLOAD = 400_000;
    private const MAX_IMAGE_BYTES = 6 * 1024 * 1024;

    public function __construct(
        private TenantRepository $tenantRepository,
        private CommunityPageEditorAccess $access,
    ) {}

    public function saveDraft(Request $request, array $params = []): Response
    {
        [$tenant, $err] = $this->guard($request, $params);
        if ($err !== null) {
            return $err;
        }
        $payload = $this->jsonBody();
        if ($payload === null) {
            return Response::json(['ok' => false, 'error' => 'Contenu illisible : rechargez la page et réessayez.'], 422);
        }
        $tid = (int) $tenant['id'];
        $settings = $this->tenantRepository->getSettings($tid);
        $community = is_array($settings['community'] ?? null) ? $settings['community'] : [];
        $community['public_page_builder_draft'] = PublicPageBuilder::normalizeDraft($payload, (int) Session::get('user_id'));
        $this->tenantRepository->mergeSettings($tid, ['community' => $community]);

        return Response::json(['ok' => true, 'saved_at' => $community['public_page_builder_draft']['updated_at'], 'message' => 'Brouillon enregistré']);
    }

    public function publish(Request $request, array $params = []): Response
    {
        [$tenant, $err] = $this->guard($request, $params);
        if ($err !== null) {
            return $err;
        }
        $tid = (int) $tenant['id'];
        $settings = $this->tenantRepository->getSettings($tid);
        $community = is_array($settings['community'] ?? null) ? $settings['community'] : [];
        $payload = $this->jsonBody();
        if (is_array($payload) && $payload !== []) {
            $community['public_page_builder_draft'] = PublicPageBuilder::normalizeDraft($payload, (int) Session::get('user_id'));
        }
        if (!PublicPageBuilder::hasDraft($community)) {
            return Response::json(['ok' => false, 'error' => 'Aucune modification à publier.'], 409);
        }
        $community = PublicPageBuilder::publish($community);
        $this->tenantRepository->mergeSettings($tid, ['community' => $community]);

        return Response::json(['ok' => true, 'message' => 'Page publiée', 'url' => url('c/' . rawurlencode((string) $tenant['slug']))]);
    }

    public function discard(Request $request, array $params = []): Response
    {
        [$tenant, $err] = $this->guard($request, $params);
        if ($err !== null) {
            return $err;
        }
        $tid = (int) $tenant['id'];
        $settings = $this->tenantRepository->getSettings($tid);
        $community = is_array($settings['community'] ?? null) ? $settings['community'] : [];
        unset($community['public_page_builder_draft']);
        $this->tenantRepository->mergeSettings($tid, ['community' => $community]);

        return Response::json(['ok' => true, 'message' => 'Brouillon abandonné']);
    }

    /** Rendu HTML d’un bloc de la bibliothèque (ajout ou réglage sans recharger la page). */
    public function renderBlock(Request $request, array $params = []): Response
    {
        [$tenant, $err] = $this->guard($request, $params);
        if ($err !== null) {
            return $err;
        }
        $payload = $this->jsonBody() ?? [];
        $block = null;
        if (isset($payload['new_type']) && is_string($payload['new_type'])) {
            $block = PublicPageBuilder::starterBlock($payload['new_type']);
        } elseif (is_array($payload['block'] ?? null)) {
            $layout = PublicPageBuilder::normalize(['blocks' => [$payload['block']]]);
            foreach ($layout['blocks'] as $b) {
                if ($b['type'] !== 'section') {
                    $block = $b;
                    break;
                }
            }
        }
        if ($block === null) {
            return Response::json(['ok' => false, 'error' => 'Type de bloc inconnu.'], 422);
        }
        $html = Response::view('community.partials.page_block', [
            'pbBlock' => $block,
            'pbEditing' => true,
        ])->body();
        if (class_exists(\App\Support\HtmlTranslator::class) && function_exists('html_lang') && html_lang() !== 'fr') {
            $html = \App\Support\HtmlTranslator::translate($html);
        }

        return Response::json(['ok' => true, 'block' => $block, 'html' => $html]);
    }

    public function uploadImage(Request $request, array $params = []): Response
    {
        [$tenant, $err] = $this->guard($request, $params);
        if ($err !== null) {
            return $err;
        }
        $slug = strtolower(trim((string) ($tenant['slug'] ?? '')));
        if (!preg_match('/^[a-z0-9-]{1,80}$/', $slug)) {
            return Response::json(['ok' => false, 'error' => 'Définissez d’abord l’identifiant public de la communauté.'], 422);
        }
        $file = $_FILES['image'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return Response::json(['ok' => false, 'error' => 'Envoi impossible. Vérifiez la taille (6 Mo maximum) et réessayez.'], 422);
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp) || (int) ($file['size'] ?? 0) > self::MAX_IMAGE_BYTES) {
            return Response::json(['ok' => false, 'error' => 'Image trop volumineuse ou invalide (6 Mo maximum).'], 422);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return Response::json(['ok' => false, 'error' => 'Format non pris en charge. Utilisez JPG, PNG ou WebP.'], 422);
        }
        $rel = 'assets/img/communities/' . $slug . '/page';
        $dir = base_path('public/' . $rel);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return Response::json(['ok' => false, 'error' => 'Le stockage des images n’est pas disponible pour le moment.'], 500);
        }
        $name = gmdate('Ymd') . '-' . bin2hex(random_bytes(6));
        $ext = $this->writeImage($tmp, $mime, $dir . '/' . $name);
        if ($ext === null) {
            return Response::json(['ok' => false, 'error' => 'Impossible de traiter cette image. Essayez avec un autre fichier.'], 422);
        }
        $path = $rel . '/' . $name . '.' . $ext;

        return Response::json(['ok' => true, 'path' => $path, 'url' => PublicPageBuilder::imageUrl($path)]);
    }

    /** Réencode l’image (retire les métadonnées, borne la largeur) ; retourne l’extension écrite. */
    private function writeImage(string $tmp, string $mime, string $destNoExt): ?string
    {
        if (!function_exists('imagecreatefromstring')) {
            $ext = match ($mime) { 'image/png' => 'png', 'image/webp' => 'webp', default => 'jpg' };

            return @copy($tmp, $destNoExt . '.' . $ext) ? $ext : null;
        }
        $bin = @file_get_contents($tmp);
        $im = $bin !== false ? @imagecreatefromstring($bin) : false;
        if (!$im) {
            return null;
        }
        $w = imagesx($im);
        $h = imagesy($im);
        $maxW = 2400;
        if ($w > $maxW) {
            $nh = max(1, (int) round($h * $maxW / $w));
            $scaled = imagecreatetruecolor($maxW, $nh);
            if ($mime === 'image/png') {
                imagealphablending($scaled, false);
                imagesavealpha($scaled, true);
            }
            imagecopyresampled($scaled, $im, 0, 0, 0, 0, $maxW, $nh, $w, $h);
            imagedestroy($im);
            $im = $scaled;
        }
        if ($mime === 'image/png') {
            imagesavealpha($im, true);
            $ok = @imagepng($im, $destNoExt . '.png', 7);
            $ext = 'png';
        } else {
            $ok = @imagejpeg($im, $destNoExt . '.jpg', 86);
            $ext = 'jpg';
        }
        imagedestroy($im);

        return $ok ? $ext : null;
    }

    /**
     * @return array{0: array<string, mixed>, 1: ?Response}
     */
    private function guard(Request $request, array $params): array
    {
        $token = $request->header('X-CSRF-Token') ?? (string) $request->input('_csrf_token', '');
        if (!Csrf::validate($token)) {
            return [[], Response::json(['ok' => false, 'error' => 'Session expirée : rechargez la page.'], 419)];
        }
        $tenant = $this->tenantRepository->findBySlug((string) ($params['slug'] ?? ''));
        if (!$tenant) {
            return [[], Response::json(['ok' => false, 'error' => 'Communauté introuvable.'], 404)];
        }
        if (!$this->access->canEdit((int) $tenant['id'])) {
            return [[], Response::json(['ok' => false, 'error' => 'Vous n’avez pas les droits pour modifier cette page.'], 403)];
        }

        return [$tenant, null];
    }

    /** @return array<string, mixed>|null */
    private function jsonBody(): ?array
    {
        $raw = (string) file_get_contents('php://input', false, null, 0, self::MAX_PAYLOAD + 1);
        if ($raw === '') {
            return [];
        }
        if (strlen($raw) > self::MAX_PAYLOAD) {
            return null;
        }
        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }
}
