<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseLoreRepository;
use App\Services\Sse\SseKitContentService;
use App\Support\SseAccessCatalog;
use App\Support\SseKitCatalog;

/**
 * Portail SSE — Générer du contenu : le MJ ou le gestionnaire des fichiers peuple sa communauté avec un réseau fictif
 * tiré des kits (pays des papiers) et des thèmes de réseau. Aperçu sans écriture, création en un lot identifiable,
 * liste des lots et suppression en bloc.
 *
 * Accès : section Fichiers, niveau « gérer » (droit « gérer les fichiers SSE », SseAccessPolicy), jamais en session
 * invitée. Tout est borné à la communauté de la session ; chaque écriture passe par le jeton CSRF et le journal SSE.
 */
final class SseContentGeneratorController
{
    public const BASE = SseFilesContext::BASE . '/generer';

    private SseFilesContext $ctx;

    public function __construct(?SseFilesContext $ctx = null, private ?SseKitContentService $service = null)
    {
        $this->ctx = $ctx ?? new SseFilesContext();
    }

    public function index(Request $request, array $params = []): Response
    {
        if (!$this->allowed()) {
            return $this->ctx->denied($request);
        }

        return $this->page(SseKitContentService::normalizeParams($this->defaults()), 0, null);
    }

    /** Aperçu : rien n'est écrit. */
    public function preview(Request $request, array $params = []): Response
    {
        if (!$this->allowed() || !$this->ctx->csrfOk($request)) {
            return $this->ctx->denied($request, self::BASE);
        }
        $p = SseKitContentService::normalizeParams($this->input($request));
        $seed = SseKitContentService::normalizeSeed($request->input('seed'));

        return $this->page($p, $seed, $this->service()->preview($this->ctx->tenantId(), $p, $seed));
    }

    /** Création du lot affiché à l'aperçu (mêmes paramètres, même graine). */
    public function commit(Request $request, array $params = []): Response
    {
        if (!$this->allowed() || !$this->ctx->csrfOk($request)) {
            return $this->ctx->denied($request, self::BASE);
        }
        $p = SseKitContentService::normalizeParams($this->input($request));
        $seed = SseKitContentService::normalizeSeed($request->input('seed'));
        try {
            $res = $this->service()->commit($this->ctx->tenantId(), $p, $seed, $this->ctx->userId(), (string) ($this->ctx->operatorLabel() ?? ''));
        } catch (\Throwable $e) {
            error_log('Générer du contenu SSE : création impossible : ' . $e->getMessage());
            $res = ['ok' => false, 'error' => 'Le lot n’a pas pu être créé : rien n’a été enregistré. Réessayez, ou réduisez le nombre de personnes.'];
        }
        if (!$res['ok']) {
            Session::flash('error', (string) ($res['error'] ?? 'Le lot n’a pas pu être créé.'));

            return Response::redirect(url(self::BASE));
        }
        Session::flash('success', 'Lot créé : ' . (int) $res['identities'] . ' personnes, ' . (int) $res['documents'] . ' papiers, ' . (int) $res['links']
            . ' liens. Tout est marqué « fictif, généré » et se supprime en bloc depuis la liste des lots.');

        return Response::redirect(url(self::BASE) . '#lots');
    }

    /** Suppression en bloc d'un lot de la communauté. */
    public function delete(Request $request, array $params = []): Response
    {
        if (!$this->allowed() || !$this->ctx->csrfOk($request)) {
            return $this->ctx->denied($request, self::BASE);
        }
        try {
            $done = $this->service()->delete($this->ctx->tenantId(), (int) ($params['id'] ?? 0), $this->ctx->userId());
        } catch (\Throwable $e) {
            error_log('Générer du contenu SSE : suppression impossible : ' . $e->getMessage());
            $done = null;
        }
        if ($done === null) {
            Session::flash('error', 'Lot introuvable, ou suppression impossible : rien n’a été retiré.');
        } else {
            Session::flash('success', 'Lot supprimé : ' . $done['identities'] . ' personnes et ' . $done['documents'] . ' papiers retirés. Le reste du registre n’a pas été touché.');
        }

        return Response::redirect(url(self::BASE) . '#lots');
    }

    private function allowed(): bool
    {
        return !$this->ctx->isGuest() && $this->ctx->tenantId() > 0 && $this->ctx->allows('fichiers', SseAccessCatalog::LEVEL_MANAGE);
    }

    /**
     * @param array<string, mixed> $p paramètres normalisés
     * @param array<string, mixed>|null $preview
     */
    private function page(array $p, int $seed, ?array $preview): Response
    {
        $tenantId = $this->ctx->tenantId();
        try {
            $sessions = (new SseLoreRepository())->list($tenantId, 50);
        } catch (\Throwable) {
            $sessions = [];
        }
        $kits = [];
        foreach (SseKitCatalog::kits() as $code => $kit) {
            $kits[$code] = ['kit' => $kit, 'sizes' => SseKitCatalog::sizes($code)];
        }

        return $this->ctx->render('atak.sse.files.generate', [
            'title' => 'Générer du contenu',
            'kits' => $kits,
            'themes' => SseKitCatalog::themes(),
            'params' => $p,
            'seed' => $seed,
            'preview' => $preview,
            'batches' => $this->service()->listBatches($tenantId),
            'room' => $this->service()->room($tenantId, $p),
            'sessions' => $sessions,
            'limits' => ['people' => SseKitContentService::MAX_PEOPLE, 'community' => SseKitContentService::MAX_COMMUNITY],
        ]);
    }

    /** @return array<string, mixed> */
    private function defaults(): array
    {
        return ['kits' => [SseKitCatalog::codes()[0]], 'themes' => []] + SseKitContentService::DEFAULTS;
    }

    /** @return array<string, mixed> */
    private function input(Request $request): array
    {
        $out = [];
        foreach (['kits', 'themes'] as $k) {
            $v = $request->input($k, []);
            $out[$k] = is_array($v) ? $v : [];
        }
        foreach (['people', 'civil_pct', 'link_density', 'forged_pct', 'alias_pct', 'wanted_pct', 'vehicles_pp', 'docs_pp', 'lang', 'label', 'op_key', 'lore_session_id'] as $k) {
            $out[$k] = $request->input($k);
        }

        return $out;
    }

    private function service(): SseKitContentService
    {
        return $this->service ??= new SseKitContentService();
    }
}
