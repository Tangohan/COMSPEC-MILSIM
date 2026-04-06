<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Container;
use App\Repositories\ForumBannedWordRepository;
use App\Repositories\ForumBlacklistedDomainRepository;
use App\Services\Forum\ForumModerationEngine;

class ForumModerationAdminApiController
{
    public function __construct(
        private ForumBannedWordRepository $bannedWordRepository,
        private ForumBlacklistedDomainRepository $blacklistedDomainRepository
    ) {}

    public function handle(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::json(['success' => false, 'message' => 'Non authentifié'], 401);
        }
        if (!Gate::getInstance()->allows('admin.access') && !(function_exists('forum_user_can_moderate') && forum_user_can_moderate())) {
            return Response::json(['success' => false, 'message' => 'Non autorisé'], 403);
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            return Response::json(['success' => false, 'message' => 'Jeton CSRF invalide'], 403);
        }

        $action = $request->input('action', '');
        $tenantId = (int) $tenantId;

        return match ($action) {
            'add_banned_word' => $this->addBannedWord($request, $tenantId),
            'delete_banned_word' => $this->deleteBannedWord($request, $tenantId),
            'add_blacklisted_domain' => $this->addBlacklistedDomain($request, $tenantId),
            'delete_blacklisted_domain' => $this->deleteBlacklistedDomain($request, $tenantId),
            'bot_self_test' => $this->botSelfTest($tenantId),
            'bot_preview' => $this->botPreview($tenantId),
            default => Response::json(['success' => false, 'message' => 'Action inconnue'], 400),
        };
    }

    private function addBannedWord(Request $request, int $tenantId): Response
    {
        $word = trim((string) $request->input('word', ''));
        if ($word === '') {
            return Response::json(['success' => false, 'message' => 'Mot requis'], 400);
        }
        $id = $this->bannedWordRepository->add($tenantId, $word, (string) $request->input('severity', 'block'));
        return Response::json(['success' => true, 'id' => $id]);
    }

    private function deleteBannedWord(Request $request, int $tenantId): Response
    {
        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            return Response::json(['success' => false, 'message' => 'ID requis'], 400);
        }
        $ok = $this->bannedWordRepository->delete($id, $tenantId);
        return $ok ? Response::json(['success' => true]) : Response::json(['success' => false, 'message' => 'Entrée introuvable'], 404);
    }

    private function addBlacklistedDomain(Request $request, int $tenantId): Response
    {
        $domain = trim((string) $request->input('domain', ''));
        if ($domain === '') {
            return Response::json(['success' => false, 'message' => 'Domaine requis'], 400);
        }
        $id = $this->blacklistedDomainRepository->add($tenantId, $domain);
        return Response::json(['success' => true, 'id' => $id]);
    }

    private function deleteBlacklistedDomain(Request $request, int $tenantId): Response
    {
        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            return Response::json(['success' => false, 'message' => 'ID requis'], 400);
        }
        $ok = $this->blacklistedDomainRepository->delete($id, $tenantId);
        return $ok ? Response::json(['success' => true]) : Response::json(['success' => false, 'message' => 'Entrée introuvable'], 404);
    }

    private function botSelfTest(int $tenantId): Response
    {
        $uid = (int) Session::get('user_id');
        if ($uid <= 0) {
            return Response::json(['success' => false, 'message' => 'Session invalide'], 400);
        }
        try {
            $engine = Container::get(ForumModerationEngine::class);
            $sample = 'Vérification interne : message de test avec lien https://example.com et termes neutres.';
            $r = $engine->analyze($tenantId, $uid, null, $sample);
            $action = (string) ($r['action'] ?? '?');
            $score = round((float) ($r['score'] ?? 0), 2);

            return Response::json([
                'success' => true,
                'message' => 'Analyse de test exécutée. Résultat : ' . $action . ' (score ' . $score . '). Les filtres mots et domaines de votre unité s’appliquent normalement.',
            ]);
        } catch (\Throwable) {
            return Response::json(['success' => false, 'message' => 'Impossible d’exécuter le test pour le moment.'], 500);
        }
    }

    private function botPreview(int $tenantId): Response
    {
        $words = $this->bannedWordRepository->listForTenant($tenantId);
        $domains = $this->blacklistedDomainRepository->listForTenant($tenantId);
        $wc = is_array($words) ? count($words) : 0;
        $dc = is_array($domains) ? count($domains) : 0;

        return Response::json([
            'success' => true,
            'message' => 'Aperçu : ' . $wc . ' mot(s) ou expression(s) filtrée(s), ' . $dc . ' site(s) interdit(s) dans les messages.',
        ]);
    }
}
