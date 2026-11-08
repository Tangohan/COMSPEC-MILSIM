<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseCaseRepository;
use App\Repositories\SseEditorItemRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseClearanceService;
use App\Services\Sse\SseEditorLibraryService;

/**
 * Bureau SSE › Bibliothèque de l'éditeur : les contenus enregistrés par le mod (ICD-SSE-EDITOR-001), en lecture seule.
 *
 * Mêmes règles que l'API pour une session du portail : compte identifié et habilitation Confidentiel. La communauté est
 * celle de la session, comme dans l'API : c'est dans celle-là que l'habilitation est calculée.
 */
final class SseEditorLibraryController
{
    private const PER_PAGE = 50;

    public function __construct(
        private ?SseAccessCodeService $access = null,
        private ?SseClearanceService $clearance = null,
        private ?SseEditorItemRepository $items = null,
    ) {
        $this->access ??= new SseAccessCodeService();
        $this->clearance ??= new SseClearanceService($this->access);
    }

    /** La session peut-elle lire la bibliothèque ? Aussi utilisé par le menu du Bureau SSE. */
    public static function allowed(?SseClearanceService $clearance = null): bool
    {
        if ((int) Session::get('user_id') < 1 || (int) Session::get('tenant_id') < 1) {
            return false;
        }
        try {
            return ($clearance ?? new SseClearanceService())->allows(SseCaseRepository::CLASS_CONFIDENTIAL);
        } catch (\Throwable) {
            return false;
        }
    }

    public function index(Request $request, array $params = []): Response
    {
        if (!self::allowed($this->clearance)) {
            Session::flash('error', 'Compte identifié et habilitation Confidentiel requis pour la bibliothèque de l’éditeur.');

            return Response::redirect(url('atak/sse/operations'));
        }
        $type = $request->queryString('type');
        $state = $request->queryString('state');
        $filters = [
            'type' => isset(SseEditorLibraryService::TYPE_LABELS[$type]) ? $type : '',
            'state' => in_array($state, SseEditorLibraryService::STATES, true) ? $state : '',
            'q' => mb_substr(trim($request->queryString('q')), 0, 120),
        ];
        $tenantId = (int) Session::get('tenant_id');
        $rows = [];
        $total = 0;
        $pages = 1;
        $page = 1;
        $unavailable = false;
        try {
            $this->items ??= new SseEditorItemRepository();
            $total = $this->items->searchCount($tenantId, $filters);
            $pages = max(1, (int) ceil($total / self::PER_PAGE));
            $page = max(1, min($pages, (int) $request->queryString('page', '1')));
            $rows = $this->items->search($tenantId, $filters, ($page - 1) * self::PER_PAGE, self::PER_PAGE);
        } catch (\PDOException) {
            // Étape de migration 4070 pas encore passée, ou base indisponible : même lecture que l'API (503).
            $unavailable = true;
        }

        return (new SseIdentityRegistryController($this->access, $this->clearance))->page('atak.sse.editor_library', [
            'title' => 'Bibliothèque de l’éditeur',
            'editorItems' => array_map(SseEditorLibraryService::forScreen(...), $rows),
            'editorFilters' => $filters,
            'editorTotal' => $total,
            'editorPage' => $page,
            'editorPages' => $pages,
            'editorUnavailable' => $unavailable,
        ]);
    }
}
