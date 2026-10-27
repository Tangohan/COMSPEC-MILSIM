<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\Courrier\CourrierDocumentNotificationRepository;
use App\Repositories\MemberNotificationRepository;
use App\Repositories\TenantMessageRepository;
use App\Services\Alerts\AlertPresentationService;
use App\Services\Moderation\ModerationRestrictionResolver;
use App\Services\Moderation\ModerationRestrictionsCatalog;
use App\Services\Notifications\ActivityHubPresentationService;

/**
 * Boîte de réception unifiée : messages internes, notifications, courrier et annonces
 * dans un seul fil, avec filtres, recherche, lecture élément par élément, sélection et « tout lire ».
 */
final class InboxController
{
    public const SOURCES = [
        'messages' => 'Messages',
        'notifications' => 'Notifications',
        'courrier' => 'Courrier',
        'annonces' => 'Annonces',
    ];

    private const LIMIT_PER_SOURCE = 80;

    public function index(Request $request, array $params = []): Response
    {
        [$tenantId, $userId] = $this->ctx();
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }
        $available = $this->availableSources($tenantId, $userId);
        $presentation = Container::get(ActivityHubPresentationService::class);
        $items = [];

        if ($available['messages']) {
            foreach ($this->messages()->listActivityThreadsForUser($tenantId, $userId, self::LIMIT_PER_SOURCE) as $row) {
                $f = $presentation->formatTenantMessageThreadRow($row, $userId);
                $subject = trim((string) ($row['subject'] ?? ''));
                $title = $subject !== '' ? $subject : 'Échange avec l’encadrement';
                // Le sujet sert de titre : on ne le répète pas en tête de l’aperçu.
                $prefix = $title . ' — ';
                if (str_starts_with((string) $f['detail'], $prefix)) {
                    $f['detail'] = substr((string) $f['detail'], strlen($prefix));
                }
                $items[] = $this->item('messages', (int) $row['id'], $title, $f);
            }
        }
        if ($available['notifications']) {
            foreach ($this->notifications()->listRecentForUser($tenantId, $userId, self::LIMIT_PER_SOURCE) as $row) {
                $f = $presentation->formatNotificationRow($row);
                $items[] = $this->item('notifications', (int) $row['id'], (string) $f['title'], $f);
            }
        }
        if ($available['courrier']) {
            foreach ($this->courrier()->listRecentForUser($tenantId, $userId, self::LIMIT_PER_SOURCE) as $row) {
                $f = $presentation->formatCourrierRow($row);
                $items[] = $this->item('courrier', (int) ($row['document_id'] ?? 0), (string) $f['title'], $f);
            }
        }
        try {
            foreach (Container::get(AlertPresentationService::class)->activityFeedForCurrentRequest() as $i => $alert) {
                $alertId = (int) ($alert['id'] ?? 0);
                $items[] = [
                    'key' => 'annonces:' . ($alertId > 0 ? $alertId : 'x' . $i),
                    'source' => 'annonces',
                    'id' => $alertId,
                    // Une annonce « masquable » peut être retirée de la boîte (comme la croix du bandeau).
                    'deletable' => $alertId > 0 && !empty($alert['dismissible']),
                    'title' => (string) ($alert['title'] ?? 'Annonce'),
                    'detail' => trim((string) ($alert['body'] ?? '')),
                    'href' => isset($alert['cta_url']) && $alert['cta_url'] !== '' ? (string) $alert['cta_url'] : url('alertes'),
                    // Les annonces n’ont pas d’état de lecture : on ne les compte pas comme non lues.
                    'unread' => false,
                    'at' => (string) ($alert['starts_at'] ?? $alert['created_at'] ?? ''),
                    'kind' => (string) ($alert['kind'] ?? 'info'),
                ];
            }
        } catch (\Throwable) {
        }

        usort($items, static fn (array $a, array $b): int => strcmp((string) $b['at'], (string) $a['at']));

        $counts = ['all' => count($items), 'unread' => 0];
        foreach (array_keys(self::SOURCES) as $source) {
            $counts[$source] = 0;
            $counts[$source . '_unread'] = 0;
        }
        foreach ($items as $it) {
            $counts[$it['source']]++;
            if ($it['unread']) {
                $counts['unread']++;
                $counts[$it['source'] . '_unread']++;
            }
        }
        $filter = (string) $request->query('filtre', '');
        if (!in_array($filter, ['', 'nonlus', ...array_keys(self::SOURCES)], true)) {
            $filter = '';
        }

        return Response::view('layout.main', [
            'title' => 'Boîte de réception',
            'content' => 'portal.inbox',
            'inboxItems' => $items,
            'inboxCounts' => $counts,
            'inboxSources' => array_filter(self::SOURCES, static fn (string $k): bool => $k === 'annonces' || !empty($available[$k]), ARRAY_FILTER_USE_KEY),
            'inboxFilter' => $filter,
            'inboxQuery' => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            'inboxCanWrite' => $available['messages'],
            'csrfToken' => Csrf::token(),
        ]);
    }

    /**
     * Marque comme lu : un élément (source + id), une sélection (keys[] = « source:id »),
     * une source entière (source seule) ou tout (source = all).
     */
    public function markRead(Request $request, array $params = []): Response
    {
        $json = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
        $back = url('boite-reception') . $this->filterQuery($request);
        if (!Csrf::validate($request->input('_csrf_token'))) {
            return $json ? Response::json(['ok' => false], 419) : $this->flashBack($back, 'error', 'Session expirée. Rechargez la page.');
        }
        [$tenantId, $userId] = $this->ctx();
        if ($tenantId < 1 || $userId < 1) {
            return $json ? Response::json(['ok' => false], 401) : Response::redirect(url('login'));
        }
        $available = $this->availableSources($tenantId, $userId);

        $keys = $request->input('keys', []);
        $targets = [];
        if (is_array($keys) && $keys !== []) {
            foreach (array_slice($keys, 0, 300) as $key) {
                [$source, $id] = array_pad(explode(':', (string) $key, 2), 2, '0');
                $targets[] = [$source, (int) $id];
            }
        } else {
            $targets[] = [(string) $request->input('source', 'all'), (int) $request->input('id', 0)];
        }

        $done = 0;
        foreach ($targets as [$source, $id]) {
            foreach ($source === 'all' ? ['messages', 'notifications', 'courrier'] : [$source] as $src) {
                if (empty($available[$src])) {
                    continue;
                }
                $done += $this->markOne($src, $id, $tenantId, $userId) ? 1 : 0;
            }
        }

        if ($json) {
            return Response::json(['ok' => true, 'done' => $done]);
        }

        return $this->flashBack($back, 'success', $done > 0 ? 'Marqué comme lu.' : 'Rien à marquer.');
    }

    /**
     * Supprime de la boîte : une sélection (keys[] = « source:id »), un élément (source + id),
     * ou tous les éléments déjà lus (lus = 1). Notifications et avis de courrier sont effacés ;
     * une conversation est seulement retirée de la boîte du membre (elle revient si quelqu’un répond) ;
     * une annonce est masquée pour lui.
     */
    public function delete(Request $request, array $params = []): Response
    {
        $json = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
        $back = url('boite-reception') . $this->filterQuery($request);
        if (!Csrf::validate($request->input('_csrf_token'))) {
            return $json ? Response::json(['ok' => false], 419) : $this->flashBack($back, 'error', 'Session expirée. Rechargez la page.');
        }
        [$tenantId, $userId] = $this->ctx();
        if ($tenantId < 1 || $userId < 1) {
            return $json ? Response::json(['ok' => false], 401) : Response::redirect(url('login'));
        }
        $available = $this->availableSources($tenantId, $userId);
        $done = 0;

        if ((string) $request->input('lus', '') === '1') {
            if ($available['notifications']) {
                $done += $this->notifications()->deleteForUser($tenantId, $userId, [], true);
            }
            if ($available['courrier']) {
                $done += $this->courrier()->deleteForUser($tenantId, $userId, [], true);
            }
        } else {
            $bySource = ['messages' => [], 'notifications' => [], 'courrier' => [], 'annonces' => []];
            $keys = $request->input('keys', []);
            if (!is_array($keys) || $keys === []) {
                $keys = [(string) $request->input('source', '') . ':' . (int) $request->input('id', 0)];
            }
            foreach (array_slice($keys, 0, 300) as $key) {
                [$source, $id] = array_pad(explode(':', (string) $key, 2), 2, '0');
                if (isset($bySource[$source]) && (int) $id > 0) {
                    $bySource[$source][] = (int) $id;
                }
            }
            if ($bySource['messages'] !== []) {
                $done += $this->messages()->hideThreadsForUser($tenantId, $userId, $bySource['messages']);
            }
            if ($bySource['notifications'] !== []) {
                $done += $this->notifications()->deleteForUser($tenantId, $userId, $bySource['notifications']);
            }
            if ($bySource['courrier'] !== [] && $available['courrier']) {
                $done += $this->courrier()->deleteForUser($tenantId, $userId, $bySource['courrier']);
            }
            if ($bySource['annonces'] !== []) {
                try {
                    $dismissals = Container::get(\App\Repositories\UserAlertDismissalRepository::class);
                    foreach ($bySource['annonces'] as $alertId) {
                        $dismissals->dismiss($userId, 'tenant', $alertId);
                        $done++;
                    }
                } catch (\Throwable) {
                }
            }
        }

        if ($json) {
            return Response::json(['ok' => true, 'done' => $done]);
        }

        return $this->flashBack($back, 'success', $done > 0 ? ($done > 1 ? $done . ' éléments supprimés.' : 'Élément supprimé.') : 'Rien à supprimer.');
    }

    private function markOne(string $source, int $id, int $tenantId, int $userId): bool
    {
        switch ($source) {
            case 'messages':
                if ($id > 0) {
                    $this->messages()->markThreadRead($id, $userId);
                } else {
                    $this->messages()->markAllThreadsReadForUser($tenantId, $userId);
                }

                return true;
            case 'notifications':
                $id > 0 ? $this->notifications()->markRead($tenantId, $userId, $id) : $this->notifications()->markAllRead($tenantId, $userId);

                return true;
            case 'courrier':
                $id > 0 ? $this->courrier()->markReadForDocument($tenantId, $userId, $id) : $this->courrier()->markAllReadForUser($tenantId, $userId);

                return true;
            default:
                return false;
        }
    }

    /** @return array{messages: bool, notifications: bool, courrier: bool} */
    private function availableSources(int $tenantId, int $userId): array
    {
        $gate = Gate::getInstance();
        $courrier = false;
        try {
            $resolver = Container::get(ModerationRestrictionResolver::class);
            $courrier = $gate->allows('courrier.view')
                && $resolver->isModuleAllowed($tenantId, $userId, ModerationRestrictionsCatalog::KEY_COURRIER);
        } catch (\Throwable) {
        }

        return ['messages' => true, 'notifications' => true, 'courrier' => $courrier];
    }

    /**
     * @param array{title: string, detail: string, href: string, unread: bool, at: string} $formatted
     * @return array<string, mixed>
     */
    private function item(string $source, int $id, string $title, array $formatted): array
    {
        return [
            'key' => $source . ':' . $id,
            'source' => $source,
            'id' => $id,
            'title' => $title,
            'detail' => (string) $formatted['detail'],
            'href' => (string) $formatted['href'],
            'unread' => (bool) $formatted['unread'],
            'at' => (string) $formatted['at'],
            'kind' => $source,
            'deletable' => $id > 0,
        ];
    }

    /** @return array{0: int, 1: int} */
    private function ctx(): array
    {
        return [(int) (Session::get('tenant_id') ?? 0), (int) (Session::get('user_id') ?? 0)];
    }

    private function filterQuery(Request $request): string
    {
        $filter = (string) $request->input('filtre', '');

        return in_array($filter, ['nonlus', ...array_keys(self::SOURCES)], true) ? '?filtre=' . $filter : '';
    }

    private function flashBack(string $back, string $type, string $message): Response
    {
        Session::flash($type, $message);

        return Response::redirect($back);
    }

    private function messages(): TenantMessageRepository
    {
        return Container::get(TenantMessageRepository::class);
    }

    private function notifications(): MemberNotificationRepository
    {
        return Container::get(MemberNotificationRepository::class);
    }

    private function courrier(): CourrierDocumentNotificationRepository
    {
        return Container::get(CourrierDocumentNotificationRepository::class);
    }
}
