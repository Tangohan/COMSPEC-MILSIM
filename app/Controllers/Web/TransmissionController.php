<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\CommunityEventRepository;
use App\Repositories\ReconPoeDocumentRepository;
use App\Repositories\ReconPvEntryRepository;
use App\Repositories\ReconTransmissionSessionRepository;
use App\Services\Auth\AuthService;

/**
 * Transmission de renseignement : sessions de reconnaissance liées (ou non) à une mission,
 * fil de mini-PV (texte + captures), synthétisées en Plan d'Exécution (PoE) par le Mission Maker.
 */
final class TransmissionController
{
    private const MAX_IMAGE_BYTES = 10 * 1024 * 1024;

    /** @var list<string> */
    private const ALLOWED_IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private ReconTransmissionSessionRepository $sessions,
        private ReconPvEntryRepository $entries,
        private ReconPoeDocumentRepository $poeDocuments,
        private CommunityEventRepository $events,
        private AuthService $authService,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0 || !Gate::getInstance()->allows('intel.transmission.view')) {
            Session::flash('error', 'Accès réservé à la transmission de renseignement.');

            return Response::redirect(url('dashboard'));
        }

        $statusFilter = trim((string) $request->query('status', 'open'));
        if (!in_array($statusFilter, ['open', 'closed'], true)) {
            $statusFilter = null;
        }

        return Response::view('layout.main', [
            'content' => 'transmission.index',
            'title' => 'Transmission de renseignement',
            'transmissionSessions' => $this->sessions->listForTenant($tenantId, $statusFilter),
            'transmissionStatusFilter' => $statusFilter,
            'transmissionCanManage' => Gate::getInstance()->allows('intel.transmission.manage'),
            'transmissionUpcomingEvents' => $this->events->upcomingForTenant($tenantId, 25),
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $user = $this->authService->user();
        if ($tenantId <= 0 || !$user || !Gate::getInstance()->allows('intel.transmission.manage')) {
            Session::flash('error', 'Vous n’êtes pas habilité à ouvrir une session de transmission.');

            return Response::redirect(url('transmission'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('transmission'));
        }
        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            Session::flash('error', 'Indiquez un titre pour cette session (ex. nom de la mission).');

            return Response::redirect(url('transmission'));
        }
        $eventId = (int) $request->input('community_event_id', 0);
        if ($eventId > 0 && !$this->events->belongsToTenant($eventId, $tenantId)) {
            $eventId = 0;
        }

        $id = $this->sessions->create($tenantId, $title, (int) $user['id'], $eventId > 0 ? $eventId : null);
        Session::flash('success', 'Session de transmission ouverte.');

        return Response::redirect(url('transmission/' . $id));
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0 || !Gate::getInstance()->allows('intel.transmission.view')) {
            Session::flash('error', 'Accès réservé à la transmission de renseignement.');

            return Response::redirect(url('dashboard'));
        }
        $id = (int) ($params['id'] ?? 0);
        $sessionRow = $id > 0 ? $this->sessions->findByIdForTenant($id, $tenantId) : null;
        if (!$sessionRow) {
            Session::flash('error', 'Session de transmission introuvable.');

            return Response::redirect(url('transmission'));
        }

        return Response::view('layout.main', [
            'content' => 'transmission.show',
            'title' => trim((string) ($sessionRow['title'] ?? 'Transmission')),
            'transmissionSession' => $sessionRow,
            'transmissionEntries' => $this->entries->listForSession($id, $tenantId),
            'transmissionCanManage' => Gate::getInstance()->allows('intel.transmission.manage'),
            'transmissionCanContribute' => Gate::getInstance()->allows('intel.transmission.contribute'),
            'transmissionCanManagePoe' => Gate::getInstance()->allows('intel.poe.manage'),
            'transmissionPoeExists' => $this->poeDocuments->findBySessionId($id, $tenantId) !== null,
        ]);
    }

    public function storeEntry(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $user = $this->authService->user();
        $id = (int) ($params['id'] ?? 0);
        if ($tenantId <= 0 || !$user || !Gate::getInstance()->allows('intel.transmission.contribute')) {
            Session::flash('error', 'Vous n’êtes pas habilité à publier un compte-rendu de reconnaissance.');

            return Response::redirect(url('transmission/' . $id));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('transmission/' . $id));
        }
        $sessionRow = $id > 0 ? $this->sessions->findByIdForTenant($id, $tenantId) : null;
        if (!$sessionRow) {
            Session::flash('error', 'Session de transmission introuvable.');

            return Response::redirect(url('transmission'));
        }
        if ((string) ($sessionRow['status'] ?? '') !== 'open') {
            Session::flash('error', 'Cette session est fermée : impossible d’ajouter un compte-rendu.');

            return Response::redirect(url('transmission/' . $id));
        }

        $body = trim((string) $request->input('body', ''));
        $gridRef = trim((string) $request->input('grid_ref', ''));
        $terrain = trim((string) $request->input('terrain_text', ''));
        $adversary = trim((string) $request->input('adversary_text', ''));
        $mission = trim((string) $request->input('mission_text', ''));
        $means = trim((string) $request->input('means_text', ''));
        $engagement = trim((string) $request->input('engagement_frame_text', ''));
        $urgencyRaw = trim((string) $request->input('urgency', ''));
        $urgency = in_array($urgencyRaw, ReconPvEntryRepository::URGENCY_VALUES, true) ? $urgencyRaw : null;

        $capturedRaw = trim((string) $request->input('captured_at', ''));
        $capturedAt = $this->parseCapturedAt($capturedRaw);
        if ($capturedAt === null) {
            Session::flash('error', 'Indiquez l’heure de captation (date et heure de l’observation).');

            return Response::redirect(url('transmission/' . $id));
        }

        $uploads = $this->processAttachments($tenantId);
        if ($uploads['error'] !== null) {
            Session::flash('error', $uploads['error']);

            return Response::redirect(url('transmission/' . $id));
        }

        $hasStructured = $terrain !== '' || $adversary !== '' || $mission !== '' || $means !== '' || $engagement !== '' || $urgency !== null;
        if ($body === '' && !$hasStructured && $uploads['paths'] === []) {
            Session::flash('error', 'Renseignez au moins une section d’analyse, une synthèse, ou une capture d’écran.');

            return Response::redirect(url('transmission/' . $id));
        }

        $entryId = $this->entries->create($tenantId, $id, (int) $user['id'], [
            'body' => $body,
            'grid_ref' => $gridRef !== '' ? $gridRef : null,
            'captured_at' => $capturedAt,
            'terrain_text' => $terrain !== '' ? $terrain : null,
            'adversary_text' => $adversary !== '' ? $adversary : null,
            'mission_text' => $mission !== '' ? $mission : null,
            'means_text' => $means !== '' ? $means : null,
            'urgency' => $urgency,
            'engagement_frame_text' => $engagement !== '' ? $engagement : null,
        ]);
        foreach ($uploads['paths'] as $path) {
            $this->entries->addAttachment($tenantId, $entryId, $path, null);
        }
        Session::flash('success', 'Compte-rendu ajouté au fil.');

        return Response::redirect(url('transmission/' . $id) . '#pv-' . $entryId);
    }

    /**
     * Accepte datetime-local (YYYY-MM-DDTHH:MM) ou datetime SQL.
     */
    private function parseCapturedAt(string $raw): ?string
    {
        if ($raw === '') {
            return null;
        }
        $normalized = str_replace('T', ' ', $raw);
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $normalized) === 1) {
            $normalized .= ':00';
        }
        $ts = strtotime($normalized);
        if ($ts === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $ts);
    }

    public function close(Request $request, array $params = []): Response
    {
        return $this->toggleStatus($request, $params, true);
    }

    public function reopen(Request $request, array $params = []): Response
    {
        return $this->toggleStatus($request, $params, false);
    }

    private function toggleStatus(Request $request, array $params, bool $closing): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $user = $this->authService->user();
        $id = (int) ($params['id'] ?? 0);
        if ($tenantId <= 0 || !$user || !Gate::getInstance()->allows('intel.transmission.manage')) {
            Session::flash('error', 'Vous n’êtes pas habilité à gérer cette session.');

            return Response::redirect(url('transmission/' . $id));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('transmission/' . $id));
        }
        $ok = $closing
            ? $this->sessions->close($id, $tenantId, (int) $user['id'])
            : $this->sessions->reopen($id, $tenantId);
        Session::flash($ok ? 'success' : 'error', $ok
            ? ($closing ? 'Session fermée.' : 'Session rouverte.')
            : 'Action impossible (statut déjà à jour ou session introuvable).');

        return Response::redirect(url('transmission/' . $id));
    }

    public function poe(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0 || !Gate::getInstance()->allows('intel.transmission.view')) {
            Session::flash('error', 'Accès réservé à la transmission de renseignement.');

            return Response::redirect(url('dashboard'));
        }
        $id = (int) ($params['id'] ?? 0);
        $sessionRow = $id > 0 ? $this->sessions->findByIdForTenant($id, $tenantId) : null;
        if (!$sessionRow) {
            Session::flash('error', 'Session de transmission introuvable.');

            return Response::redirect(url('transmission'));
        }

        return Response::view('layout.main', [
            'content' => 'transmission.poe',
            'title' => 'Plan d’Exécution — ' . trim((string) ($sessionRow['title'] ?? '')),
            'transmissionSession' => $sessionRow,
            'transmissionEntries' => $this->entries->listForSession($id, $tenantId),
            'transmissionPoe' => $this->poeDocuments->findBySessionId($id, $tenantId),
            'transmissionCanManagePoe' => Gate::getInstance()->allows('intel.poe.manage'),
        ]);
    }

    public function savePoe(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $user = $this->authService->user();
        $id = (int) ($params['id'] ?? 0);
        if ($tenantId <= 0 || !$user || !Gate::getInstance()->allows('intel.poe.manage')) {
            Session::flash('error', 'Vous n’êtes pas habilité à rédiger le Plan d’Exécution.');

            return Response::redirect(url('transmission/' . $id . '/poe'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('transmission/' . $id . '/poe'));
        }
        $sessionRow = $id > 0 ? $this->sessions->findByIdForTenant($id, $tenantId) : null;
        if (!$sessionRow) {
            Session::flash('error', 'Session de transmission introuvable.');

            return Response::redirect(url('transmission'));
        }

        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            $title = 'Plan d’exécution — ' . trim((string) ($sessionRow['title'] ?? ''));
        }
        $sections = [];
        foreach (ReconPoeDocumentRepository::SECTIONS as $key) {
            $sections[$key] = trim((string) $request->input('section_' . $key, ''));
        }
        $this->poeDocuments->upsert($tenantId, $id, $title, $sections, (int) $user['id']);

        if ($request->input('publish') === '1') {
            $this->poeDocuments->publish($id, $tenantId, (int) $user['id']);
            Session::flash('success', 'Plan d’Exécution enregistré et publié.');
        } else {
            Session::flash('success', 'Brouillon du Plan d’Exécution enregistré.');
        }

        return Response::redirect(url('transmission/' . $id . '/poe'));
    }

    /**
     * @return array{paths: list<string>, error: ?string}
     */
    private function processAttachments(int $tenantId): array
    {
        $files = $_FILES['attachments'] ?? null;
        if (!is_array($files) || !isset($files['name']) || !is_array($files['name'])) {
            return ['paths' => [], 'error' => null];
        }
        $count = count($files['name']);
        if ($count === 0) {
            return ['paths' => [], 'error' => null];
        }
        if ($count > 6) {
            return ['paths' => [], 'error' => 'Maximum 6 captures par compte-rendu.'];
        }

        $dirRel = 'uploads/transmission/' . $tenantId;
        $dirAbs = public_file_path($dirRel);
        if (!is_dir($dirAbs) && !@mkdir($dirAbs, 0755, true) && !is_dir($dirAbs)) {
            return ['paths' => [], 'error' => 'Le stockage des captures n’est pas disponible pour le moment.'];
        }

        $paths = [];
        for ($i = 0; $i < $count; $i++) {
            $err = (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
            if ($err === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($err !== UPLOAD_ERR_OK) {
                return ['paths' => [], 'error' => 'Envoi d’une capture impossible (taille max 10 Mo par image).'];
            }
            $tmp = (string) ($files['tmp_name'][$i] ?? '');
            if ($tmp === '' || !is_uploaded_file($tmp)) {
                continue;
            }
            if ((int) ($files['size'][$i] ?? 0) > self::MAX_IMAGE_BYTES) {
                return ['paths' => [], 'error' => 'Capture trop volumineuse : limite de 10 Mo par image.'];
            }
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($tmp) ?: '';
            if (!in_array($mime, self::ALLOWED_IMAGE_MIMES, true)) {
                return ['paths' => [], 'error' => 'Format non pris en charge (JPG, PNG ou WebP uniquement).'];
            }
            $ext = match ($mime) {
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'jpg',
            };
            $name = 'pv-' . bin2hex(random_bytes(8)) . '.' . $ext;
            $destAbs = $dirAbs . '/' . $name;
            if (!$this->writeImage($tmp, $destAbs, 1600)) {
                return ['paths' => [], 'error' => 'Impossible de traiter une des images. Réessayez.'];
            }
            $paths[] = $dirRel . '/' . $name;
        }

        return ['paths' => $paths, 'error' => null];
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
        $ok = match ($ext) {
            'png' => imagepng($im, $destPath, 6),
            'webp' => function_exists('imagewebp') ? imagewebp($im, $destPath, 85) : imagejpeg($im, $destPath, 85),
            default => imagejpeg($im, $destPath, 85),
        };
        imagedestroy($im);

        return (bool) $ok;
    }
}
