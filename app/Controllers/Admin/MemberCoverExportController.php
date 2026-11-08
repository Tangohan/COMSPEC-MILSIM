<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UserRepository;
use App\Services\Sse\SseCoverExportService;

/**
 * Ma couverture générée sur Athena puis téléchargée pour le jeu (« Mes papiers » du joueur, fiche membre du Bureau SSE).
 *
 * Droits : un joueur ne génère que pour lui-même ; pour un autre membre il faut la gestion des papiers (même droit que
 * la fiche membre) et, depuis le Bureau SSE, un accès non invité. Toute action : POST + jeton CSRF, communauté de la
 * session, membre de cette communauté. Personnes fictives seulement.
 */
final class MemberCoverExportController
{
    public function __construct(
        private ?SseCoverExportService $service = null,
        private ?UserRepository $users = null,
    ) {
        $this->service ??= new SseCoverExportService();
        $this->users ??= new UserRepository();
    }

    /** POST /back-office/ma-situation/mes-papiers/couverture */
    public function generateMine(Request $request, array $params = []): Response
    {
        $viewer = (int) Session::get('user_id');
        $tenantId = (int) Session::get('tenant_id');
        if ($viewer < 1 || $tenantId < 1) {
            return Response::redirect(url('login'));
        }

        return $this->generate($request, $tenantId, $viewer, $viewer, false, url('back-office/ma-situation/mes-papiers') . '#couverture');
    }

    /** GET …/mes-papiers/couverture/{exportId}/telecharger */
    public function downloadMine(Request $request, array $params = []): Response
    {
        $viewer = (int) Session::get('user_id');
        $tenantId = (int) Session::get('tenant_id');
        if ($viewer < 1 || $tenantId < 1) {
            return Response::redirect(url('login'));
        }

        return $this->download($tenantId, (int) ($params['exportId'] ?? 0), $viewer, false, url('back-office/ma-situation/mes-papiers') . '#couverture');
    }

    /** POST /atak/sse/papiers/membres/{id}/couverture */
    public function generateFor(Request $request, array $params = []): Response
    {
        $ctx = $this->managerContext($params);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $viewer, $subject, $back] = $ctx;

        return $this->generate($request, $tenantId, $subject, $viewer, true, $back);
    }

    /** GET /atak/sse/papiers/membres/{id}/couverture/{exportId}/telecharger */
    public function downloadFor(Request $request, array $params = []): Response
    {
        $ctx = $this->managerContext($params);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $viewer, $subject, $back] = $ctx;

        // Le fichier doit être celui du membre que nomme l'adresse.
        return $this->download($tenantId, (int) ($params['exportId'] ?? 0), $viewer, true, $back, $subject);
    }

    /** @return array{0: int, 1: int, 2: int, 3: string}|Response */
    private function managerContext(array $params): array|Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $viewer = (int) Session::get('user_id');
        if ($viewer < 1 || $tenantId < 1) {
            return Response::redirect(url('login'));
        }
        $subject = (int) ($params['id'] ?? 0);
        $rights = MemberIdentityPapersController::currentRights();
        $guest = (new \App\Services\Sse\SseAccessCodeService())->isGuest();
        if (!$rights['manage'] || !$rights['sse'] || $guest) {
            Session::flash('error', 'Vous n’êtes pas habilité à gérer les couvertures des membres.');

            return Response::redirect(url('atak/sse'));
        }
        if ($subject < 1 || $this->users->findById($subject, $tenantId) === null) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(url('atak/sse/papiers/membres'));
        }

        return [$tenantId, $viewer, $subject, MemberIdentityPapersController::baseUrl($subject, true) . '#couverture'];
    }

    private function generate(Request $request, int $tenantId, int $subject, int $actor, bool $manager, string $back): Response
    {
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        $bytes = null;
        $file = $_FILES['photo'] ?? null;
        if (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $tmp = (string) ($file['tmp_name'] ?? '');
            if (($file['error'] ?? 1) !== UPLOAD_ERR_OK || $tmp === '' || !is_uploaded_file($tmp)) {
                Session::flash('error', 'Photo : envoi impossible (4 Mo au plus, PNG, JPG ou WebP).');

                return Response::redirect($back);
            }
            $bytes = (string) @file_get_contents($tmp);
        }
        $res = $this->service->generate(
            $tenantId, $subject, $actor, strtoupper(trim($request->inputString('country'))), strtoupper($request->inputString('sex')) ?: null,
            $request->inputString('lang', 'fr'), $bytes, $manager
        );
        if ($res['ok']) {
            Session::flash('success', 'Couverture générée. Téléchargez le fichier, puis importez-le dans le jeu.');
        } else {
            Session::flash('error', match ($res['error']) {
                'limit' => 'Limite de générations atteinte pour aujourd’hui. Réessayez dans 24 heures.',
                'country' => 'Pays inconnu ou archivé pour cette communauté.',
                'photo' => 'Photo refusée : PNG, JPG ou WebP lisible, 40 pixels au moins.',
                default => 'Aucune couverture libre n’a pu être tirée. Réessayez.',
            });
        }

        return Response::redirect($back);
    }

    private function download(int $tenantId, int $exportId, int $viewer, bool $manager, string $back, ?int $onlyUser = null): Response
    {
        $res = $exportId > 0 ? $this->service->download($tenantId, $exportId, $viewer, $manager, null, $onlyUser) : null;
        if ($res === null) {
            Session::flash('error', 'Fichier de couverture introuvable.');

            return Response::redirect($back);
        }
        $name = 'comspec-couverture-' . preg_replace('/[^A-Za-z0-9-]/', '', (string) $res['row']['ref']) . '.json';

        return (new Response())
            ->header('Content-Type', 'application/json; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="' . $name . '"')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Cache-Control', 'no-store')
            ->setBody($res['json']);
    }
}
