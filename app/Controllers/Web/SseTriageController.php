<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseClearanceService;
use App\Services\Sse\SseTriageInbox;
use App\Support\SsePortalNav;
use App\Support\SseTriageBadges;

/**
 * Portail SSE : boîte « À trier » (tout ce qui attend une décision humaine, toutes files confondues) et compteurs légers
 * pour les pastilles de la barre latérale. Lecture seule : les décisions restent dans la page de chaque file, qui contrôle
 * ses propres droits ; les files non lisibles par la session ne sont ni comptées ni listées (SseTriageInbox).
 */
final class SseTriageController
{
    public function __construct(
        private ?SseAccessCodeService $access = null,
        private ?SseClearanceService $clearance = null,
        private ?SseTriageInbox $inbox = null,
    ) {
        $this->access ??= new SseAccessCodeService();
        $this->inbox ??= SseTriageInbox::forSession();
    }

    /** GET /atak/sse/a-trier : liste unifiée, onglets par source. */
    public function index(Request $request, array $params = []): Response
    {
        [$sections, $snap] = $this->load();
        $tab = $request->queryString('source');
        if ($tab !== '' && !isset($snap['sources'][$tab])) {
            $tab = '';
        }
        $items = $tab === '' ? $snap['items'] : array_values(array_filter($snap['items'], static fn (array $i): bool => $i['source'] === $tab));

        return (new SseIdentityRegistryController($this->access, $this->clearance ?? new SseClearanceService($this->access)))->page('atak.sse.triage', [
            'title' => 'À trier',
            'triage' => $snap,
            'triageItems' => $items,
            'triageTab' => $tab,
            'activeNav' => 'a-trier',
            'lang' => SseWantedNoticeController::readerLang(),
        ]);
    }

    /** GET /atak/sse/a-trier/compteurs : JSON léger, lecture seule (pas de jeton CSRF), pour l'actualisation des pastilles. */
    public function counters(Request $request, array $params = []): Response
    {
        [$sections, $snap] = $this->load();
        $lang = SseWantedNoticeController::readerLang();
        $data = SseTriageBadges::fromSnapshot($sections, $snap);
        $entries = [];
        foreach ($data['entries'] as $nav => $e) {
            $entries[$nav] = $e + ['label' => SseTriageBadges::label($e['count']), 'aria' => SseTriageBadges::aria($e['count'], $lang)];
        }
        $secs = [];
        foreach ($data['sections'] as $id => $e) {
            $secs[$id] = $e + ['label' => SseTriageBadges::label($e['count']), 'aria' => SseTriageBadges::aria($e['count'], $lang)];
        }

        return Response::json([
            'total' => $data['total'],
            'label' => SseTriageBadges::label($data['total']),
            'aria' => SseTriageBadges::aria($data['total'], $lang),
            'entries' => (object) $entries,
            'sections' => (object) $secs,
        ])->header('Cache-Control', 'private, no-store');
    }

    /** @return array{0: list<array<string, mixed>>, 1: array{total: int, sources: array<string, array<string, mixed>>, items: list<array<string, mixed>>}} */
    private function load(): array
    {
        $isGuest = $this->access->isGuest();
        $nav = SsePortalNav::forSession(false, false, $isGuest);
        $sections = $nav['sections'];
        $navIds = [];
        foreach ($sections as $s) {
            foreach ($s['items'] as $i) {
                $navIds[] = (string) $i['id'];
            }
        }
        $tenant = $this->access->tenantId() ?: (int) Session::get('tenant_id');
        $snap = $this->inbox->snapshot($tenant, (int) Session::get('user_id'), $navIds);

        return [$sections, $snap];
    }
}
