<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UserRepository;
use App\Services\Auth\AuthService;
use App\Services\Jnet\JnetLinkAccess;
use App\Services\Jnet\JnetLinkRules;
use App\Services\Jnet\JnetLinkService;
use App\Services\Jnet\JnetSpaceService;
use App\Services\Rbac\RbacService;
use App\Support\PortalAccessChoice;

/**
 * Écritures des liaisons JNET : liens entre contenus (« Lier à… », retrait) et liaisons entre unités (créer, terminer),
 * plus la recherche qui alimente « Lier à… » et la saisie « @ » / « [[ » des pages. Les écritures sont en POST avec jeton
 * CSRF ; chaque contrôle de droit est refait ici, côté serveur (JnetLinkService et JnetLinkAccess, qui ne font que reprendre
 * les règles existantes de JNET). L'adresse de retour est toujours calculée par le serveur à partir du contenu relié, jamais
 * lue dans la requête. Une action envoyée sans rechargement (en-tête Accept: application/json) reçoit un JSON {ok, message}.
 */
final class JnetLinksController
{
    public function __construct(
        private ?AuthService $authService = null,
        private ?RbacService $rbacService = null,
        private ?UserRepository $userRepository = null,
        private ?JnetSpaceService $spaces = null,
        private ?JnetLinkService $links = null,
    ) {
        $this->authService ??= \App\Core\Container::get(AuthService::class);
        $this->rbacService ??= \App\Core\Container::get(RbacService::class);
        $this->userRepository ??= \App\Core\Container::get(UserRepository::class);
        $this->spaces ??= new JnetSpaceService();
        $this->links ??= new JnetLinkService();
    }

    /**
     * Recherche de contenus à relier ou à citer : uniquement ce que le lecteur voit déjà (chaque résultat repasse par le
     * contrôle de droits). GET, lecture seule.
     */
    public function search(Request $request, array $params = []): Response
    {
        $ctx = $this->context();
        if ($ctx === null) {
            return Response::json(['results' => []], 401);
        }
        $types = array_values(array_filter(array_map('trim', explode(',', (string) $request->query('types', ''))), static fn (string $t): bool => $t !== ''));
        $rows = JnetLinkAccess::forViewer($ctx['tenant'], $ctx['user'], $this->spaces)->search((string) $request->query('q', ''), $types, (int) $request->query('space', 0));
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'ref' => JnetLinkRules::ref($r['type'], $r['id']),
                'type' => $r['type'],
                'typeLabel' => JnetLinkRules::TYPES[$r['type']] ?? '',
                'title' => $r['title'],
                'sub' => $r['sub'],
                'href' => $r['href'],
            ];
        }

        return Response::json(['results' => $out]);
    }

    /** Pose un lien entre deux contenus. */
    public function create(Request $request, array $params = []): Response
    {
        $g = $this->guard($request);
        if ($g instanceof Response) {
            return $g;
        }
        $src = (string) $request->input('src', '');
        $viewer = $g['viewer'];
        $res = $this->links->create($viewer, $src, (string) $request->input('dst', ''), (int) $request->input('space', 0));
        $back = $this->backTo($viewer, $src);

        return $this->done($request, $back, $res['error'], 'Lien ajouté.', $res['error'] === null ? ['id' => $res['id']] : []);
    }

    /** Retire un lien (son auteur, ou le chef de l'espace d'où il a été posé). */
    public function remove(Request $request, array $params = []): Response
    {
        $g = $this->guard($request);
        if ($g instanceof Response) {
            return $g;
        }
        $linkId = (int) ($params['id'] ?? 0);
        $row = $this->links->repo()->link($g['tenant'], $linkId);
        $back = $row !== null ? $this->backTo($g['viewer'], JnetLinkRules::ref((string) $row['src_type'], (string) $row['src_id'])) : url('jnet');
        $error = $this->links->remove($g['viewer'], $linkId);

        return $this->done($request, $back, $error, 'Lien retiré.');
    }

    /** Crée une liaison entre l'unité de l'espace et une autre unité : chef de l'espace seulement. */
    public function createUnitLink(Request $request, array $params = []): Response
    {
        $g = $this->guard($request);
        if ($g instanceof Response) {
            return $g;
        }
        $unit = (int) ($params['id'] ?? 0);
        $back = url('jnet/u/' . $unit . '?vue=liaisons&direct=1');
        if (!$this->spaces->tree($g['tenant'], $g['user'])->has($unit) || !$this->spaces->canRead($g['tenant'], $g['user'], $unit)) {
            return $this->done($request, url('jnet'), 'Cet espace n’existe pas ou ne vous est pas accessible.', null);
        }
        $error = $this->links->createUnitLink($g['viewer'], $unit, (int) $request->input('partner', 0), [
            'kind' => $request->input('kind', ''),
            'direction' => $request->input('direction', ''),
            'starts_on' => $request->input('starts_on', ''),
            'ends_on' => $request->input('ends_on', ''),
            'note' => $request->input('note', ''),
            'grant_read' => (string) $request->input('grant_read', '') !== '',
        ]);

        return $this->done($request, $back, $error, 'Liaison créée.');
    }

    /** Met fin à une liaison entre unités (le chef de l'une des deux unités). */
    public function endUnitLink(Request $request, array $params = []): Response
    {
        $g = $this->guard($request);
        if ($g instanceof Response) {
            return $g;
        }
        $unit = (int) ($params['id'] ?? 0);
        $back = url('jnet/u/' . $unit . '?vue=liaisons&direct=1');
        if (!$this->spaces->tree($g['tenant'], $g['user'])->has($unit) || !$this->spaces->canRead($g['tenant'], $g['user'], $unit)) {
            return $this->done($request, url('jnet'), 'Cet espace n’existe pas ou ne vous est pas accessible.', null);
        }
        $error = $this->links->endUnitLink($g['viewer'], (int) ($params['lid'] ?? 0));

        return $this->done($request, $back, $error, 'Liaison terminée.');
    }

    // ——— Outils ———

    /** @return array{tenant: int, user: int}|null */
    private function context(): ?array
    {
        if (!$this->authService->check()) {
            return null;
        }
        $tenant = (int) Session::get('tenant_id');
        $user = (int) Session::get('user_id');
        if ($tenant <= 0 || $user <= 0 || PortalAccessChoice::isNoOrganizationContext()) {
            return null;
        }
        $row = $this->authService->user();
        if ($row) {
            $this->rbacService->setPermissionsForGateFromUserRow($row, $this->userRepository);
        }

        return ['tenant' => $tenant, 'user' => $user];
    }

    /**
     * Contrôles communs d'une écriture : connexion, jeton CSRF, lecteur construit à partir de la session.
     *
     * @return array{tenant: int, user: int, viewer: JnetLinkAccess}|Response
     */
    private function guard(Request $request): array|Response
    {
        $ctx = $this->context();
        if ($ctx === null) {
            return $this->wantsJson($request) ? Response::json(['ok' => false, 'message' => 'Connexion requise.'], 401) : Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            return $this->deny($request, url('jnet'), 'Votre session a expiré, rien n’a été enregistré.', 419);
        }

        return $ctx + ['viewer' => JnetLinkAccess::forViewer($ctx['tenant'], $ctx['user'], $this->spaces)];
    }

    /** Page du contenu source, si le lecteur la voit ; sinon l'accueil de JNET. Jamais une adresse venue de la requête. */
    private function backTo(JnetLinkAccess $viewer, string $srcRef): string
    {
        $ref = JnetLinkRules::parseRef($srcRef);
        $seen = $ref !== null ? $viewer->see($ref['type'], $ref['id']) : null;

        return $seen !== null && $seen['href'] !== '' ? $seen['href'] : url('jnet');
    }

    private function wantsJson(Request $request): bool
    {
        return str_contains(strtolower((string) $request->header('Accept')), 'application/json');
    }

    private function deny(Request $request, string $to, string $message, int $status = 403): Response
    {
        if ($this->wantsJson($request)) {
            return Response::json(['ok' => false, 'message' => $message], $status);
        }
        Session::flash('error', $message);

        return Response::redirect($to);
    }

    /**
     * Fin d'une écriture : redirection avec message en ligne, ou JSON ({ok, message}) pour une action sans rechargement.
     *
     * @param array<string, mixed> $extra
     */
    private function done(Request $request, string $to, ?string $error, ?string $ok, array $extra = []): Response
    {
        if ($this->wantsJson($request)) {
            return Response::json(['ok' => $error === null && $ok !== null, 'message' => (string) ($error ?? $ok ?? '')] + $extra, $error === null && $ok !== null ? 200 : 422);
        }
        if ($error !== null) {
            Session::flash('error', $error);
        } elseif ($ok !== null) {
            Session::flash('success', $ok);
        }

        return Response::redirect($to);
    }
}
