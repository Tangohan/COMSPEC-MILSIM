<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Auth\AuthService;
use App\Services\OrganizationCatalog\OrganizationCatalogService;

final class OrganizationCatalogController
{
    public function __construct(
        private ?AuthService $authService = null,
        private ?OrganizationCatalogService $catalog = null,
        private ?Gate $gate = null,
    ) {
        $this->authService ??= Container::get(AuthService::class);
        $this->catalog ??= Container::get(OrganizationCatalogService::class);
        $this->gate ??= Gate::getInstance();
    }

    public function index(Request $request, array $params = []): Response
    {
        $denied = $this->guard();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');

        return $this->page([
            'title' => 'Catalogue de l’organisation',
            'boPageSubtitle' => 'Administrez l’organigramme, les grades, les fonctions et les rôles, ou copiez un modèle.',
            'content' => 'admin.organization.catalog.index',
            'catalogItems' => $this->catalog->listForTenant($tenantId),
            'catalogArchived' => $this->catalog->listArchivedForTenant($tenantId),
            'catalogInstalls' => $this->catalog->recentInstalls($tenantId),
            'catalogInventory' => $this->catalog->currentInventory($tenantId),
            'catalogPreview' => null,
            'flashOk' => Session::getFlash('success'),
            'flashErr' => Session::getFlash('error'),
        ]);
    }

    public function show(Request $request, array $params = []): Response
    {
        $denied = $this->guard();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $code = $this->modelCode($request);
        $item = $this->catalog->getVisibleItem($tenantId, $code, true);
        if ($item === null) {
            Session::flash('error', 'Ce modèle n’est pas disponible pour votre communauté.');

            return Response::redirect(url('back-office/organisation/catalogue'));
        }

        return $this->page([
            'title' => (string) ($item['title'] ?? 'Modèle'),
            'boPageSubtitle' => 'Contenu complet du modèle, et actions d’administration.',
            'content' => 'admin.organization.catalog.show',
            'catalogItem' => $item,
            'flashOk' => Session::getFlash('success'),
            'flashErr' => Session::getFlash('error'),
        ]);
    }

    public function history(Request $request, array $params = []): Response
    {
        $denied = $this->guard();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');

        return $this->page([
            'title' => 'Journal du catalogue',
            'boPageSubtitle' => 'Toutes les applications de modèles : qui, quand, et ce qui a été copié.',
            'content' => 'admin.organization.catalog.history',
            'catalogInstalls' => $this->catalog->installHistory($tenantId, 200),
            'flashOk' => Session::getFlash('success'),
            'flashErr' => Session::getFlash('error'),
        ]);
    }

    public function preview(Request $request, array $params = []): Response
    {
        $denied = $this->guard();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $code = $this->modelCode($request);
        $parts = $this->partsFromRequest($request);
        $preview = $this->catalog->preview($tenantId, $code, $parts);
        if (empty($preview['ok'])) {
            Session::flash('error', (string) ($preview['error'] ?? 'Aperçu indisponible.'));

            return Response::redirect(url('back-office/organisation/catalogue'));
        }

        return $this->page([
            'title' => 'Aperçu du modèle',
            'boPageSubtitle' => 'Vérifiez ce qui sera ajouté, puis appliquez à cette communauté.',
            'content' => 'admin.organization.catalog.preview',
            'catalogPreview' => $preview,
            'catalogParts' => $parts,
            'flashOk' => null,
            'flashErr' => null,
        ]);
    }

    public function apply(Request $request, array $params = []): Response
    {
        $denied = $this->guard();
        if ($denied !== null) {
            return $denied;
        }
        if (!$this->csrfOk($request, 'La session a expiré. Recommencez l’application du modèle.')) {
            return Response::redirect(url('back-office/organisation/catalogue'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $code = $this->modelCode($request);
        $parts = $this->partsFromRequest($request);
        $result = $this->catalog->apply($tenantId, $code, $parts, (int) Session::get('user_id'));
        if (empty($result['ok'])) {
            Session::flash('error', (string) ($result['error'] ?? 'Le modèle n’a pas pu être appliqué.'));

            return Response::redirect(url('back-office/organisation/catalogue'));
        }
        $summary = (string) (($result['report']['summary'] ?? '') ?: 'Modèle appliqué.');
        Session::flash('success', $summary);

        return Response::redirect(url('back-office/organisation/catalogue/historique'));
    }

    public function snapshot(Request $request, array $params = []): Response
    {
        $denied = $this->guard();
        if ($denied !== null) {
            return $denied;
        }
        if (!$this->csrfOk($request, 'La session a expiré. Recommencez l’enregistrement.')) {
            return Response::redirect(url('back-office/organisation/catalogue'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $title = trim((string) $request->input('titre', ''));
        $result = $this->catalog->snapshot($tenantId, (int) Session::get('user_id'), $title);
        if (empty($result['ok'])) {
            Session::flash('error', (string) ($result['error'] ?? 'L’enregistrement n’a pas abouti.'));

            return Response::redirect(url('back-office/organisation/catalogue'));
        }
        Session::flash('success', 'Le modèle de votre organisation a été enregistré. Il n’est visible que pour vous.');
        $code = (string) (($result['item']['code'] ?? '') ?: '');
        if ($code !== '') {
            return Response::redirect(url('back-office/organisation/catalogue/modele?modele=' . rawurlencode($code)));
        }

        return Response::redirect(url('back-office/organisation/catalogue'));
    }

    public function rename(Request $request, array $params = []): Response
    {
        $denied = $this->guard();
        if ($denied !== null) {
            return $denied;
        }
        $code = $this->modelCode($request);
        if (!$this->csrfOk($request, 'La session a expiré. Recommencez le renommage.')) {
            return $this->backToModel($code);
        }
        $result = $this->catalog->renamePrivate(
            (int) Session::get('tenant_id'),
            $code,
            trim((string) $request->input('titre', '')),
            (int) Session::get('user_id')
        );
        if (empty($result['ok'])) {
            Session::flash('error', (string) ($result['error'] ?? 'Le modèle n’a pas pu être renommé.'));
        } else {
            Session::flash('success', 'Le modèle a été renommé.');
        }

        return $this->backToModel($code);
    }

    public function refresh(Request $request, array $params = []): Response
    {
        $denied = $this->guard();
        if ($denied !== null) {
            return $denied;
        }
        $code = $this->modelCode($request);
        if (!$this->csrfOk($request, 'La session a expiré. Recommencez l’actualisation.')) {
            return $this->backToModel($code);
        }
        $result = $this->catalog->refreshPrivate(
            (int) Session::get('tenant_id'),
            $code,
            (int) Session::get('user_id')
        );
        if (empty($result['ok'])) {
            Session::flash('error', (string) ($result['error'] ?? 'L’actualisation n’a pas abouti.'));
        } else {
            Session::flash('success', 'Le modèle a été actualisé avec l’organisation actuelle. Rien n’a été appliqué.');
        }

        return $this->backToModel($code);
    }

    public function archive(Request $request, array $params = []): Response
    {
        $denied = $this->guard();
        if ($denied !== null) {
            return $denied;
        }
        $code = $this->modelCode($request);
        if (!$this->csrfOk($request, 'La session a expiré. Recommencez le retrait.')) {
            return Response::redirect(url('back-office/organisation/catalogue'));
        }
        $result = $this->catalog->archivePrivate(
            (int) Session::get('tenant_id'),
            $code,
            (int) Session::get('user_id')
        );
        if (empty($result['ok'])) {
            Session::flash('error', (string) ($result['error'] ?? 'Le modèle n’a pas pu être retiré.'));

            return $this->backToModel($code);
        }
        Session::flash('success', 'Le modèle a été retiré du catalogue. Le journal des applications est conservé. Vous pouvez le restaurer plus tard.');

        return Response::redirect(url('back-office/organisation/catalogue'));
    }

    public function restore(Request $request, array $params = []): Response
    {
        $denied = $this->guard();
        if ($denied !== null) {
            return $denied;
        }
        $code = $this->modelCode($request);
        if (!$this->csrfOk($request, 'La session a expiré. Recommencez la restauration.')) {
            return Response::redirect(url('back-office/organisation/catalogue'));
        }
        $result = $this->catalog->restorePrivate(
            (int) Session::get('tenant_id'),
            $code,
            (int) Session::get('user_id')
        );
        if (empty($result['ok'])) {
            Session::flash('error', (string) ($result['error'] ?? 'Le modèle n’a pas pu être restauré.'));

            return Response::redirect(url('back-office/organisation/catalogue'));
        }
        Session::flash('success', 'Le modèle est de nouveau disponible.');

        return $this->backToModel($code);
    }

    private function guard(): ?Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        if (!$this->canManage()) {
            Session::flash('error', 'Vous n’avez pas l’habilitation pour administrer le catalogue de l’organisation.');

            return Response::redirect(url('back-office/organisation-effectifs'));
        }
        if ((int) Session::get('tenant_id') < 1) {
            return Response::redirect(url('dashboard'));
        }

        return null;
    }

    private function canManage(): bool
    {
        return $this->gate->allows('organization.catalog.manage')
            || $this->gate->allows('organization.orbat.manage')
            || $this->gate->allows('admin.organization')
            || $this->gate->allows('admin.access');
    }

    private function csrfOk(Request $request, string $error): bool
    {
        if (Csrf::validate((string) $request->input('_csrf_token'))) {
            return true;
        }
        Session::flash('error', $error);

        return false;
    }

    private function modelCode(Request $request): string
    {
        return trim((string) $request->query('modele', $request->input('modele', '')));
    }

    private function backToModel(string $code): Response
    {
        if ($code === '') {
            return Response::redirect(url('back-office/organisation/catalogue'));
        }

        return Response::redirect(url('back-office/organisation/catalogue/modele?modele=' . rawurlencode($code)));
    }

    /**
     * @return array{orbat: bool, grades: bool, functions: bool, roles: bool}
     */
    private function partsFromRequest(Request $request): array
    {
        $submitted = $request->input('inclure');
        if (!is_array($submitted)) {
            if ($request->isPost()) {
                return $this->catalog->normalizeParts([
                    'orbat' => false,
                    'grades' => false,
                    'functions' => false,
                    'roles' => false,
                ]);
            }

            return $this->catalog->normalizeParts([]);
        }

        return $this->catalog->normalizeParts([
            'orbat' => !empty($submitted['organigramme']),
            'grades' => !empty($submitted['grades']),
            'functions' => !empty($submitted['fonctions']),
            'roles' => !empty($submitted['roles']),
        ]);
    }

    /**
     * @param array<string, mixed> $vars
     */
    private function page(array $vars): Response
    {
        return Response::view('layout.main', array_merge([
            'isBackOfficeShell' => true,
            'boPageGroup' => 'Personnel',
            'boPageTitle' => (string) ($vars['title'] ?? 'Catalogue de l’organisation'),
            'boPageKicker' => 'PERSONNEL · STRUCTURE',
            'boPageSubtitle' => 'Copiez un modèle officiel dans votre communauté, ou enregistrez l’état actuel.',
            'backOfficePageCss' => ['back-office-catalog.css'],
            'showPortalFooter' => false,
        ], $vars));
    }
}
