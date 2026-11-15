<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\PhotoLibrary\PhotoLibraryService;
use App\Services\PhotoLibrary\PhotoViewer;
use App\Services\Security\FileRateLimiter;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseAccessPolicy;
use App\Services\Sse\SseClearanceService;
use App\Repositories\SseCaseRepository;
use App\Support\SseAccessCatalog;

/**
 * Photothèque de l'extranet (JNET) : la galerie des photos terrain envoyées par l'ATAK (recon_images), dans l'habillage
 * de views/jnet/_page.php. Pages : /jnet/photos (grille, filtres, recherche, tri), /jnet/photos/{id} (même page, photo
 * ouverte en plein écran), image servie par le serveur (masques déjà appliqués pour qui n'a pas le droit de voir dessous).
 * Écritures (visages étiquetés à la main, masques, commentaires, étiquettes, versement SSE) : POST en JSON, jeton CSRF
 * en champ « _csrf_token » ou en-tête X-CSRF-Token, et toutes les règles de droits refaites par PhotoLibraryService.
 * Aucune reconnaissance faciale : un humain pose le cadre et choisit qui c'est.
 */
class PhotoLibraryController
{
    public function __construct(private ?PhotoLibraryService $service = null, private ?JnetPortalController $portal = null)
    {
        $this->service ??= new PhotoLibraryService();
    }

    // ------------------------------------------------------------------ Pages

    public function index(Request $request, array $params = []): Response
    {
        return $this->page($request, 0);
    }

    public function show(Request $request, array $params = []): Response
    {
        return $this->page($request, (int) ($params['id'] ?? 0));
    }

    private function page(Request $request, int $openId): Response
    {
        $v = $this->viewer();
        if ($v === null) {
            return Response::redirect(url('login'));
        }
        $filters = self::filters($request);
        $sort = (string) $request->query('tri', 'recent');
        $data = $this->service->browse($v, $filters, $sort, (int) $request->query('page', 1));
        $open = null;
        $status = 200;
        if ($openId > 0) {
            $open = $this->service->detail($v, $openId);
            if ($open === null) {
                $status = 404;
                Session::flash('error', 'Photo introuvable.');
            }
        }
        $payload = [
            'photos' => $data,
            'photoFilters' => $filters,
            'photoSort' => isset(\App\Repositories\PhotoLibraryRepository::SORTS[$sort]) ? $sort : 'recent',
            'photoOpen' => $open,
            'photoViewer' => ['sse' => $v->sseDoor, 'manager' => $v->manager],
            'photoCsrf' => Csrf::token(),
            'photoBase' => url('jnet/photos'),
        ];
        $response = $this->shell('photos', 'Photothèque', $payload, ['jnet_photos.css'], ['jnet_photos.js']);
        if ($status !== 200 && $response->statusCode() === 200) {
            $response->setStatusCode($status);
        }

        return $response->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
    }

    /**
     * Rend la page dans la coque JNET en reprenant JnetPortalController::render (en-tête, rubriques, compte) : le
     * navigateur, les jetons et les rubriques restent ceux du reste de l'extranet, sans copie de leur code.
     *
     * @param array<string, mixed> $payload
     * @param list<string> $css
     * @param list<string> $js
     */
    protected function shell(string $view, string $title, array $payload, array $css, array $js): Response
    {
        $portal = $this->portal ?? new JnetPortalController();
        $render = \Closure::bind(
            fn (): Response => $this->render($view, $title, $payload, 'exploitation', $css, $js),
            $portal,
            JnetPortalController::class
        );

        return $render();
    }

    // ------------------------------------------------------------------ Lectures JSON et image

    public function data(Request $request, array $params = []): Response
    {
        $v = $this->viewer();
        $detail = $v !== null ? $this->service->detail($v, (int) ($params['id'] ?? 0)) : null;
        if ($detail === null) {
            return $this->notFoundJson();
        }

        return Response::json($detail)->header('Cache-Control', 'no-store, private');
    }

    /** Image servie : `?t=thumb` (miniature), `?t=file` (fichier entier), sinon grande vue ; jamais l'original à qui n'y a pas droit. */
    public function image(Request $request, array $params = []): Response
    {
        $v = $this->viewer();
        $variant = (string) $request->query('t', 'full');
        $variant = in_array($variant, ['thumb', 'file'], true) ? $variant : 'full';
        $img = $v !== null ? $this->service->image($v, (int) ($params['id'] ?? 0), $variant) : null;
        if ($img === null) {
            return (new Response())->setStatusCode(404)->header('Content-Type', 'text/plain; charset=utf-8')->header('Cache-Control', 'no-store, private')->setBody('Not found');
        }

        return (new Response())
            ->header('Content-Type', $img['mime'])
            ->header('Content-Length', (string) strlen($img['bytes']))
            ->header('Content-Disposition', 'inline')
            ->header('Cache-Control', 'private, max-age=60')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Referrer-Policy', 'no-referrer')
            ->setBody($img['bytes']);
    }

    /** Cibles d'un visage : membres, ou personnes des fichiers SSE (porte SSE seulement). */
    public function targets(Request $request, array $params = []): Response
    {
        $v = $this->viewer();
        if ($v === null) {
            return Response::json(['results' => []], 401);
        }

        return Response::json(['results' => $this->service->targets($v, (string) $request->query('type', 'member'), (string) $request->query('q', ''))])
            ->header('Cache-Control', 'no-store, private');
    }

    // ------------------------------------------------------------------ Écritures

    public function tagAdd(Request $request, array $params = []): Response
    {
        return $this->write($request, $params, 'tags', fn (PhotoViewer $v, int $id): array => $this->service->addTag($v, $id, $request->all()));
    }

    public function tagUpdate(Request $request, array $params = []): Response
    {
        return $this->write($request, $params, 'tags', fn (PhotoViewer $v, int $id): array => $this->service->updateTag($v, $id, (int) ($params['tid'] ?? 0), $request->all()));
    }

    public function tagRemove(Request $request, array $params = []): Response
    {
        return $this->write($request, $params, 'tags', fn (PhotoViewer $v, int $id): array => $this->service->removeTag($v, $id, (int) ($params['tid'] ?? 0)));
    }

    public function maskAdd(Request $request, array $params = []): Response
    {
        return $this->write($request, $params, 'masks', fn (PhotoViewer $v, int $id): array => $this->service->addMask($v, $id, $request->all()));
    }

    public function maskRemove(Request $request, array $params = []): Response
    {
        return $this->write($request, $params, 'masks', fn (PhotoViewer $v, int $id): array => $this->service->removeMask($v, $id, (int) ($params['mid'] ?? 0)));
    }

    public function commentAdd(Request $request, array $params = []): Response
    {
        return $this->write($request, $params, 'comments', fn (PhotoViewer $v, int $id): array => $this->service->addComment($v, $id, $request->all()));
    }

    public function commentEdit(Request $request, array $params = []): Response
    {
        return $this->write($request, $params, 'comments', fn (PhotoViewer $v, int $id): array => $this->service->editComment($v, $id, (int) ($params['cid'] ?? 0), $request->all()));
    }

    public function commentDelete(Request $request, array $params = []): Response
    {
        return $this->write($request, $params, 'comments', fn (PhotoViewer $v, int $id): array => $this->service->deleteComment($v, $id, (int) ($params['cid'] ?? 0)));
    }

    public function commentReport(Request $request, array $params = []): Response
    {
        return $this->write($request, $params, 'reports', fn (PhotoViewer $v, int $id): array => $this->service->reportComment(
            $v,
            $id,
            (int) ($params['cid'] ?? 0),
            $request->inputString('reason', 'other'),
            $request->inputString('details')
        ));
    }

    public function labelAdd(Request $request, array $params = []): Response
    {
        return $this->write($request, $params, 'tags', fn (PhotoViewer $v, int $id): array => $this->service->addLabel($v, $id, $request->inputString('label')));
    }

    public function labelRemove(Request $request, array $params = []): Response
    {
        return $this->write($request, $params, 'tags', fn (PhotoViewer $v, int $id): array => $this->service->removeLabel($v, $id, $request->inputString('label')));
    }

    public function sseTransfer(Request $request, array $params = []): Response
    {
        $identity = preg_match('/^(?:IDT-)?0*(\d{1,10})$/', strtoupper(trim($request->inputString('identity'))), $m) === 1 ? (int) $m[1] : null;

        return $this->write($request, $params, 'sse', fn (PhotoViewer $v, int $id): array => $this->service->transferToSse($v, $id, $identity, $this->actorLabel()));
    }

    public function sseWithdraw(Request $request, array $params = []): Response
    {
        return $this->write($request, $params, 'sse', fn (PhotoViewer $v, int $id): array => $this->service->withdrawFromSse($v, $id, $this->actorLabel()));
    }

    /**
     * Cadre commun des écritures : méthode POST, compte de la communauté, jeton CSRF, plafond d'actions par heure, puis le
     * service (qui refait les droits). Réponse JSON {ok, error?, …}.
     *
     * @param callable(PhotoViewer, int): array<string, mixed> $do
     */
    private function write(Request $request, array $params, string $bucket, callable $do): Response
    {
        $v = $this->viewer();
        if ($v === null) {
            return Response::json(['ok' => false, 'error' => 'Connectez-vous pour continuer.'], 401)->header('Cache-Control', 'no-store');
        }
        if (!Csrf::verify($request)) {
            return Response::json(['ok' => false, 'error' => 'Session expirée : rechargez la page.'], 419)->header('Cache-Control', 'no-store');
        }
        $limits = ['tags' => 240, 'masks' => 120, 'comments' => 120, 'reports' => 20, 'sse' => 60];
        if ((new FileRateLimiter())->tooManyAttempts('photo_library|' . $bucket . '|' . $v->tenantId . '|' . $v->userId, $limits[$bucket] ?? 120, 3600)) {
            return Response::json(['ok' => false, 'error' => 'Trop d’actions en peu de temps : réessayez plus tard.'], 429)->header('Cache-Control', 'no-store');
        }
        $id = (int) ($params['id'] ?? 0);
        $res = $id > 0 ? $do($v, $id) : ['ok' => false, 'error' => 'Photo introuvable.', 'status' => 404];
        $status = (int) ($res['status'] ?? (!empty($res['ok']) ? 200 : 400));
        unset($res['row'], $res['f'], $res['status']);

        return Response::json($res, $status)->header('Cache-Control', 'no-store');
    }

    // ------------------------------------------------------------------ Outils

    /** Le lecteur courant avec ses portes ; null sans compte ou sans communauté. */
    protected function viewer(): ?PhotoViewer
    {
        Session::start();
        $user = (int) Session::get('user_id');
        $tenant = (int) Session::get('tenant_id');
        if ($user < 1 || $tenant < 1) {
            return null;
        }
        $manager = false;
        $door = false;
        $manage = false;
        $transfer = null;
        $clear = false;
        try {
            $manager = function_exists('moderation_user_can_moderate') && moderation_user_can_moderate();
        } catch (\Throwable) {
        }
        try {
            // Mêmes portes que JNET (JnetDashboardService::mayReadIntelligence) : le droit d'entrer au bureau SSE, puis
            // l'engagement de confidentialité de la session, dans cette communauté, hors session invitée.
            $sse = new SseAccessCodeService();
            $door = !$sse->isGuest() && $sse->canEnterWithoutCode() && $sse->hasAcceptedConfidentiality() && $sse->tenantId() === $tenant;
            if ($door) {
                // Droits de la section Photothèque (SseAccessPolicy) : sans règle, ce sont les anciennes portes (lire et verser :
                // la porte SSE ; retirer un versement ou un visage SSE : la gestion des fichiers ou des dossiers).
                $ctx = ['guest' => false, 'tenant_id' => $tenant];
                $policy = SseAccessPolicy::forSession();
                $section = 'photos';
                $door = $policy->allows($user, $section, SseAccessCatalog::LEVEL_READ, $ctx);
                $transfer = $door && $policy->allows($user, $section, SseAccessCatalog::LEVEL_ADD, $ctx);
                $manage = $door && $policy->allows($user, $section, SseAccessCatalog::LEVEL_DELETE, $ctx);
            }
            $clear = (new SseClearanceService($sse))->allows(SseCaseRepository::CLASS_CONFIDENTIAL);
        } catch (\Throwable) {
        }

        return new PhotoViewer($user, $tenant, $manager, $door, $manage, $clear, $transfer);
    }

    private function actorLabel(): string
    {
        $n = trim((string) (Session::get('callsign') ?: Session::get('display_name') ?: ''));

        return $n !== '' ? $n : 'Membre';
    }

    private function notFoundJson(): Response
    {
        return Response::json(['ok' => false, 'error' => 'Photo introuvable.'], 404)->header('Cache-Control', 'no-store');
    }

    /** Filtres lus de la requête (valeurs courtes, nettoyées ; le dépôt refait la validation). @return array<string, string> */
    public static function filters(Request $request): array
    {
        $out = [];
        foreach (['q', 'author', 'unit', 'album', 'from', 'to', 'grid', 'label', 'person', 'sse'] as $k) {
            $raw = $request->query($k, '');
            $out[$k] = mb_substr(trim(is_scalar($raw) ? (string) $raw : ''), 0, 120);
        }

        return $out;
    }
}
