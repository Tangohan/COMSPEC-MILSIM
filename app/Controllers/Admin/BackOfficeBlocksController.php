<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantRepository;
use App\Services\BackOffice\Home\HomeBlocks;
use App\Services\BackOffice\Home\HomeBlocksStore;
use App\Services\Community\PageImageUploader;

/**
 * Éditeur du bloc personnalisé de l'accueil du back-office : même éditeur en place que la vitrine publique
 * (public/assets/js/community-page-editor.js), avec la bibliothèque de blocs de l'accueil (HomeBlocks).
 *
 * Routes sous /back-office/accueil/bloc (AuthMiddleware + OrganizationAdminMiddleware). Chaque appel vérifie en plus
 * que la personne est GESTIONNAIRE de sa communauté (l'assistance peut seulement lire le bloc), la communauté est celle
 * de la session (jamais celle de la requête) et, pour les écritures, le jeton CSRF (en-tête X-CSRF-Token).
 */
final class BackOfficeBlocksController
{
    private const MAX_PAYLOAD = 400_000;

    public function __construct(
        private HomeBlocksStore $store,
        private TenantRepository $tenants,
    ) {
    }

    /** GET /back-office/accueil/bloc */
    public function edit(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || !BackOfficeHomeController::canShare()) {
            Session::flash('error', 'Seuls les gestionnaires de la communauté peuvent modifier le bloc personnalisé.');

            return Response::redirect(url('back-office'));
        }
        $layout = $this->store->load($tenantId);
        if ($layout['blocks'] === []) {
            // Premier passage : un bloc d'exemple pour que la page ne soit jamais vide.
            $starter = HomeBlocks::starterBlock('article');
            $layout['blocks'] = $starter !== null ? [$starter] : [];
        }
        $styleKeys = HomeBlocks::allStyleKeys();
        $config = [
            'base' => url('back-office'),
            'siteBase' => rtrim(url(''), '/'),
            'csrf' => Csrf::token(),
            'root' => '.bo-blocks[data-cl-editing]',
            'mode' => 'direct',
            'ext' => 'home',
            'endpoints' => [
                'draft' => url('back-office/accueil/bloc/enregistrer'),
                'publish' => url('back-office/accueil/bloc/enregistrer'),
                'block' => url('back-office/accueil/bloc/rendu'),
                'image' => url('back-office/accueil/bloc/image'),
            ],
            'hasDraft' => true,
            'layout' => ['theme' => 'light', 'header' => [], 'blocks' => $layout['blocks']],
            'fields' => new \stdClass(),
            'sections' => new \stdClass(),
            'types' => HomeBlocks::TYPES,
            'styleOptions' => HomeBlocks::styleOptions(),
            'styleKeys' => $styleKeys,
            'media' => [],
            'sheets' => $this->store->choices($tenantId, $userId),
        ];

        return Response::view('layout.main', [
            'content' => 'admin.organization.bloc_editor',
            'title' => 'Bloc personnalisé',
            'isBackOfficeShell' => true,
            'boPageGroup' => 'Pilotage',
            'boPageTitle' => 'Bloc personnalisé',
            'boPageKicker' => 'PILOTAGE · ACCUEIL',
            'boPageSubtitle' => 'Même éditeur que la page publique de la communauté. Les modifications sont enregistrées automatiquement.',
            'backOfficePageCss' => ['pilotage-dashboard.css', 'back-office-home.css', 'community-page-builder.css', 'back-office-blocks.css'],
            'blocksLayout' => $layout,
            'blocksConfig' => $config,
            'blocksSheets' => $this->store->sheetsFor($layout, $tenantId, $userId),
        ]);
    }

    /** POST /back-office/accueil/bloc/enregistrer — contenu complet, revalidé côté serveur. */
    public function save(Request $request, array $params = []): Response
    {
        $err = $this->guard($request);
        if ($err !== null) {
            return $err;
        }
        $payload = $this->jsonBody();
        if ($payload === null) {
            return Response::json(['ok' => false, 'error' => 'Contenu illisible ou trop volumineux : rechargez la page et réessayez.'], 422);
        }
        $saved = $this->store->save((int) Session::get('tenant_id'), $payload, (int) Session::get('user_id'));
        if ($saved === null) {
            return Response::json(['ok' => false, 'error' => 'Contenu trop volumineux : retirez du texte ou des blocs.'], 413);
        }

        return Response::json(['ok' => true, 'saved_at' => gmdate('c'), 'message' => 'Bloc enregistré', 'count' => count($saved['blocks'])]);
    }

    /** POST /back-office/accueil/bloc/rendu — HTML d'un bloc (ajout ou réglage sans recharger la page). */
    public function renderBlock(Request $request, array $params = []): Response
    {
        $err = $this->guard($request);
        if ($err !== null) {
            return $err;
        }
        $payload = $this->jsonBody() ?? [];
        $block = null;
        if (isset($payload['new_type']) && is_string($payload['new_type'])) {
            $block = HomeBlocks::starterBlock($payload['new_type']);
        } elseif (is_array($payload['block'] ?? null)) {
            $block = HomeBlocks::normalizeBlock($payload['block']);
        }
        if ($block === null) {
            return Response::json(['ok' => false, 'error' => 'Type de bloc inconnu.'], 422);
        }
        $tenantId = (int) Session::get('tenant_id');
        $html = Response::view('community.partials.page_block', [
            'pbBlock' => $block,
            'pbEditing' => true,
            'pbSheets' => $this->store->sheetsFor(['blocks' => [$block]], $tenantId, (int) Session::get('user_id')),
        ])->body();
        if (class_exists(\App\Support\HtmlTranslator::class) && function_exists('html_lang') && html_lang() !== 'fr') {
            $html = \App\Support\HtmlTranslator::translate($html);
        }

        return Response::json(['ok' => true, 'block' => $block, 'html' => $html]);
    }

    /** POST /back-office/accueil/bloc/image — même envoi d'image que la vitrine publique. */
    public function uploadImage(Request $request, array $params = []): Response
    {
        $err = $this->guard($request);
        if ($err !== null) {
            return $err;
        }
        $tenant = $this->tenants->findById((int) Session::get('tenant_id'));
        $res = PageImageUploader::store($_FILES['image'] ?? null, (string) ($tenant['slug'] ?? ''));

        return Response::json($res['body'], $res['status']);
    }

    private function guard(Request $request): ?Response
    {
        $token = $request->header('X-CSRF-Token') ?? (string) $request->input('_csrf_token', '');
        if (!Csrf::validate($token)) {
            return Response::json(['ok' => false, 'error' => 'Session expirée : rechargez la page.'], 419);
        }
        if ((int) Session::get('tenant_id') < 1 || !BackOfficeHomeController::canShare()) {
            return Response::json(['ok' => false, 'error' => 'Vous n’avez pas les droits pour modifier ce bloc.'], 403);
        }

        return null;
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
