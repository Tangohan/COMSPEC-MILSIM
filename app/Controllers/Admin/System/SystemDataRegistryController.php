<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Admin\DataRegistryService;
use App\Services\Audit\AuditService;
use App\Support\HttpJsonBody;

/**
 * Administration plateforme — Registre des données.
 *
 *   GET  /admin/system/donnees                 page (tableaux façon tableur)
 *   GET  /admin/system/donnees/api             lignes d'un jeu de données (JSON, ou CSV avec format=csv)
 *   GET  /admin/system/donnees/correlations    tout ce qui est rattaché à une entité (user, steam, terminal, tenant)
 *   POST /admin/system/donnees/action          action groupée ou purge d'une entité (JSON, en-tête X-CSRF-Token)
 */
final class SystemDataRegistryController
{
    public function __construct(private ?DataRegistryService $registry = null)
    {
        $this->registry ??= new DataRegistryService();
    }

    public function index(Request $request, array $params = []): Response
    {
        return Response::view('layout.main', [
            'title' => 'Registre des données',
            'content' => 'admin.system.data_registry',
            'isPlatformAdminShell' => true,
            'backOfficePageCss' => ['admin-data-registry.css'],
            'boPageKicker' => 'EXPLOITATION · DONNÉES',
            'boPageTitle' => 'Registre des données',
            'boPageSubtitle' => 'Communautés, comptes, joueurs, ATAK, unités et sessions en tableaux : corrélations, export Excel et purge. Recherche par adresse de profil Steam. Mods proches du pack officiel.',
            'registryDatasets' => DataRegistryService::DATASETS,
        ]);
    }

    /** Résolution d’outils (Steam depuis une URL de profil, etc.). */
    public function tools(Request $request, array $params = []): Response
    {
        $op = (string) $request->query('op', '');
        if ($op === 'steam') {
            $raw = mb_substr(trim((string) $request->query('q', '')), 0, 240);
            $res = $this->registry->resolveSteamQuery($raw);

            return Response::json([
                'ok' => $res['steam'] !== null,
                'steam' => $res['steam'],
                'message' => $res['steam'] !== null
                    ? ($res['note'] !== '' ? $res['note'] : 'Identifiant Steam trouvé.')
                    : ($res['note'] !== '' ? $res['note'] : 'Impossible de reconnaître un profil Steam dans cette saisie.'),
            ])->header('Cache-Control', 'private, no-store');
        }

        return Response::json(['ok' => false, 'message' => 'Outil inconnu.'], 400);
    }

    public function api(Request $request, array $params = []): Response
    {
        $dataset = (string) $request->query('dataset', 'users');
        if (!isset(DataRegistryService::DATASETS[$dataset])) {
            return Response::json(['ok' => false, 'error' => 'unknown_dataset'], 400);
        }
        $filters = $request->query('f', []);
        $csv = $request->query('format', '') === 'csv';
        try {
            $data = $this->registry->query(
                $dataset,
                mb_substr(trim((string) $request->query('q', '')), 0, 120),
                is_array($filters) ? array_map(static fn ($v) => mb_substr(trim((string) $v), 0, 120), $filters) : [],
                (string) $request->query('flag', ''),
                (string) $request->query('sort', ''),
                (string) $request->query('dir', 'desc'),
                (int) $request->query('page', 1),
                $csv ? 100000 : (int) $request->query('per', 100)
            );
        } catch (\Throwable $e) {
            error_log('[data-registry] ' . $e->getMessage());

            return Response::json(['ok' => false, 'message' => 'Lecture impossible.'], 500);
        }
        if ($csv) {
            return (new Response())
                ->header('Content-Type', 'text/csv; charset=utf-8')
                ->header('Content-Disposition', 'attachment; filename="athena-' . $dataset . '-' . date('Ymd-Hi') . '.csv"')
                ->header('Cache-Control', 'private, no-store')
                ->setBody(DataRegistryService::csv($data));
        }

        return Response::json(['ok' => true] + $data)->header('Cache-Control', 'private, no-store');
    }

    public function correlations(Request $request, array $params = []): Response
    {
        $type = (string) $request->query('type', '');
        $id = mb_substr(trim((string) $request->query('id', '')), 0, 64);
        $c = in_array($type, ['user', 'steam', 'terminal', 'tenant'], true) && $id !== ''
            ? $this->registry->correlations($type, $id)
            : null;
        if ($c === null) {
            return Response::json(['ok' => false, 'message' => 'Entité introuvable.'], 404);
        }

        return Response::json(['ok' => true] + $c)->header('Cache-Control', 'private, no-store');
    }

    public function action(Request $request, array $params = []): Response
    {
        if (!Csrf::validate((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
            return Response::json(['ok' => false, 'message' => 'Session expirée : rechargez la page.'], 419);
        }
        $body = json_decode(HttpJsonBody::rawJson(), true);
        $body = is_array($body) ? $body : [];
        $actorId = (int) Session::get('user_id');
        $actorLabel = (string) (Session::get('display_name') ?? ('Admin #' . $actorId));

        if (is_array($body['purge'] ?? null)) {
            $p = $body['purge'];
            $res = $this->registry->purgeEntity((string) ($p['type'] ?? ''), (string) ($p['id'] ?? ''), (string) ($p['confirm'] ?? ''), $actorId, $actorLabel);
            $this->audit('admin.data_registry.purge.' . ($p['type'] ?? ''), (string) ($p['id'] ?? ''), $res, $actorId);

            return Response::json($res, !empty($res['ok']) ? 200 : 422);
        }

        $dataset = (string) ($body['dataset'] ?? '');
        $action = (string) ($body['action'] ?? '');
        $ids = is_array($body['ids'] ?? null) ? $body['ids'] : [];
        $res = $this->registry->bulk($dataset, $action, $ids, $actorId, $actorLabel);
        $this->audit('admin.data_registry.' . $dataset . '.' . $action, count($ids) . ' ligne(s) : ' . implode(', ', array_slice(array_map('strval', $ids), 0, 20)), $res, $actorId);

        return Response::json($res, !empty($res['ok']) ? 200 : 422);
    }

    /** @param array{ok: bool, message: string} $res */
    private function audit(string $action, string $target, array $res, int $actorId): void
    {
        if (empty($res['ok'])) {
            return;
        }
        try {
            (new AuditService())->log($action, null, $actorId ?: null, 'data_registry', null, $target, (string) $res['message'], (string) ($_SERVER['REMOTE_ADDR'] ?? ''), (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        } catch (\Throwable) {
        }
    }
}
