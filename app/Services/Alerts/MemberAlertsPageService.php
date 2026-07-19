<?php

declare(strict_types=1);

namespace App\Services\Alerts;

use App\Core\Gate;
use App\Repositories\ForumTopicRepository;
use App\Services\Dashboard\TenantDashboardPinService;
use App\Support\AlertDisplayStyle;

/**
 * Contenu de la page membre « Alertes & annonces » (messages en cours + historique).
 */
final class MemberAlertsPageService
{
    public function __construct(
        private AlertPresentationService $alerts,
        private TenantDashboardPinService $pins,
        private ForumTopicRepository $forumTopics,
    ) {}

    /**
     * @return array{
     *   active: list<array<string, mixed>>,
     *   history: list<array<string, mixed>>,
     *   manage_url: ?string
     * }
     */
    public function buildForViewer(int $tenantId, int $userId): array
    {
        $active = [];
        $activeKeys = [];

        try {
            foreach ($this->alerts->forCurrentRequest() as $alert) {
                $style = (string) ($alert['display_style'] ?? 'classic');
                if (AlertDisplayStyle::isNavbarStyle($style)) {
                    continue;
                }
                $item = $this->mapAlertRow($alert, false);
                $key = $this->itemKey($item);
                $activeKeys[$key] = true;
                $active[] = $item;
            }
        } catch (\Throwable) {
            // Page consultable même si le service d’alertes est indisponible.
        }

        if ($tenantId > 0 && $userId > 0) {
            try {
                foreach ($this->pins->listResolvedPinsForViewer($tenantId, $userId) as $pin) {
                    if ((string) ($pin['kind'] ?? '') !== 'notice') {
                        continue;
                    }
                    $noticeBody = trim((string) ($pin['notice_text'] ?? ''));
                    if ($noticeBody === '') {
                        continue;
                    }
                    $active[] = [
                        'scope' => 'tenant',
                        'kind' => 'notice',
                        'category' => 'Annonce',
                        'title' => (string) ($pin['label'] ?? 'Annonce'),
                        'body' => $noticeBody,
                        'cta_label' => null,
                        'cta_url' => null,
                        'status' => 'active',
                        'ended_at' => null,
                    ];
                }
            } catch (\Throwable) {
                // Optionnel.
            }

            try {
                foreach ($this->forumTopics->listPinnedOnDashboardForTenant($tenantId, 12) as $ftPin) {
                    $title = trim((string) ($ftPin['title'] ?? ''));
                    if ($title === '') {
                        continue;
                    }
                    $rawBody = trim((string) ($ftPin['first_post_body'] ?? ''));
                    $topicId = (int) ($ftPin['id'] ?? 0);
                    $active[] = [
                        'scope' => 'tenant',
                        'kind' => 'forum_pin',
                        'category' => 'Message épinglé',
                        'title' => $title,
                        'body' => $this->plainTextExcerpt($rawBody, 280),
                        'cta_label' => 'Ouvrir le message',
                        'cta_url' => $topicId > 0 ? url('forum/topic/' . $topicId) : null,
                        'status' => 'active',
                        'ended_at' => null,
                    ];
                }
            } catch (\Throwable) {
                // Optionnel si le schéma forum n’est pas prêt.
            }
        }

        $history = [];
        try {
            foreach ($this->alerts->recentlyEndedForCurrentRequest(40) as $alert) {
                $item = $this->mapAlertRow($alert, true);
                $key = $this->itemKey($item);
                if (isset($activeKeys[$key])) {
                    continue;
                }
                $history[] = $item;
            }
        } catch (\Throwable) {
            $history = [];
        }

        $gate = Gate::getInstance();
        $manageUrl = $gate->allows('dashboard.pins.manage')
            || $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            ? url('back-office/alerts')
            : null;

        return [
            'active' => $active,
            'history' => $history,
            'manage_url' => $manageUrl,
        ];
    }

    /**
     * @param array<string, mixed> $alert
     * @return array<string, mixed>
     */
    private function mapAlertRow(array $alert, bool $ended): array
    {
        return [
            'scope' => (string) ($alert['scope'] ?? 'tenant'),
            'id' => (int) ($alert['id'] ?? 0),
            'kind' => (string) ($alert['kind'] ?? 'info'),
            'category' => null,
            'title' => (string) ($alert['title'] ?? ''),
            'body' => (string) ($alert['body'] ?? ''),
            'cta_label' => $alert['cta_label'] ?? null,
            'cta_url' => $alert['cta_url'] ?? null,
            'status' => $ended ? 'ended' : 'active',
            'ended_at' => isset($alert['ended_at']) && is_string($alert['ended_at']) ? $alert['ended_at'] : null,
        ];
    }

    /** @param array<string, mixed> $item */
    private function itemKey(array $item): string
    {
        $scope = (string) ($item['scope'] ?? '');
        $id = (int) ($item['id'] ?? 0);
        if ($scope !== '' && $id > 0) {
            return $scope . ':' . $id;
        }

        return 't:' . md5((string) ($item['title'] ?? '') . '|' . (string) ($item['body'] ?? ''));
    }

    private function plainTextExcerpt(string $raw, int $maxLen = 280): string
    {
        $text = trim(strip_tags($raw));
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($text) > $maxLen) {
                return rtrim(mb_substr($text, 0, $maxLen - 1)) . '…';
            }

            return $text;
        }
        if (strlen($text) > $maxLen) {
            return rtrim(substr($text, 0, $maxLen - 1)) . '…';
        }

        return $text;
    }
}
