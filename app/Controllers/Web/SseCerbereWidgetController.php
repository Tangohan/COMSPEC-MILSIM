<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Services\Security\FileRateLimiter;
use App\Services\Sse\Cerbere\SseCerbereContext;
use App\Services\Sse\Cerbere\SseCerbereWidget;
use App\Services\Sse\Links\SseLinkSessionViewer;
use App\Services\Sse\Links\SseLinkViewer;
use App\Support\SsePortalNav;

/**
 * Widget Cerbère du portail SSE : contenu du tiroir, chargé à l'ouverture (lecture seule, aucune écriture).
 *
 *   GET /atak/sse/cerbere/panneau?page=/atak/sse/dossiers/12
 *
 * « page » ne sert qu'à reconnaître la page où se trouve le lecteur (SseCerbereContext) ; la communauté, le compte et les droits
 * viennent de la SESSION. Un élément illisible, d'une autre communauté ou inexistant répond de la même façon (404, même texte).
 * Débit borné par session. Les décisions (accepter, écarter) passent par les routes existantes des propositions, qui rejugent le
 * droit et le jeton CSRF.
 */
final class SseCerbereWidgetController
{
    /** Ouvertures de tiroir autorisées par minute et par session. */
    private const MAX_PER_MINUTE = 40;

    /**
     * @param (\Closure(string, int, int): bool)|null $tooMany plafond de débit (clé, maximum, fenêtre en secondes)
     * @param (\Closure(): list<array<string, mixed>>)|null $nav navigation visible (tests)
     */
    public function __construct(
        private ?SseLinkViewer $viewer = null,
        private ?SseCerbereWidget $widget = null,
        private ?\Closure $tooMany = null,
        private ?\Closure $nav = null,
    ) {
    }

    public function panel(Request $request, array $params = []): Response
    {
        $viewer = $this->viewer ??= new SseLinkSessionViewer();
        $limit = $this->tooMany ?? static fn (string $key, int $max, int $window): bool => (new FileRateLimiter())->tooManyAttempts($key, $max, $window);
        $who = $viewer->tenantId() . '|' . ((int) $viewer->userId() ?: substr(sha1((string) session_id()), 0, 16));
        if ($limit('sse_cerbere_panel|' . $who, self::MAX_PER_MINUTE, 60)) {
            return $this->fragment('atak.sse._cerbere_panel', ['w' => null, 'message' => 'Trop de demandes en peu de temps : patientez une minute.'], 429);
        }
        $page = SseCerbereContext::sanitizePage($request->queryString('page'));
        $ctx = SseCerbereContext::fromPath($page ?? 'atak/sse/accueil');
        $widget = $this->widget ?? new SseCerbereWidget(
            $viewer,
            $this->nav !== null ? ($this->nav)() : $this->visibleNav($viewer),
            null,
            static fn (): \App\Services\Sse\Links\SseLinkGraph => new \App\Services\Sse\Links\SseLinkGraph($viewer, null, null, null, new \App\Services\Jnet\SseLinkJnetCitations($viewer->tenantId(), (int) $viewer->userId())),
        );
        $w = $widget->panel($ctx);
        if ($w === null) {
            return $this->fragment('atak.sse._cerbere_panel', ['w' => null, 'message' => 'Cerbère n’a rien à montrer ici : cet élément n’existe pas ou vous n’avez pas le droit de le voir.'], 404);
        }

        return $this->fragment('atak.sse._cerbere_panel', ['w' => $w, 'message' => '', 'csrf' => \App\Core\Csrf::token()], 200);
    }

    /** @return list<array<string, mixed>> */
    private function visibleNav(SseLinkViewer $viewer): array
    {
        try {
            return SsePortalNav::forSession(false, false, $viewer->isGuest())['sections'];
        } catch (\Throwable) {
            return [];
        }
    }

    /** @param array<string, mixed> $data */
    private function fragment(string $view, array $data, int $status): Response
    {
        return Response::view($view, $data)->setStatusCode($status)->header('Cache-Control', 'private, no-store');
    }
}
