<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Repositories\PlatformBoMessageRepository;
use App\Support\PlatformBoMessage;

/**
 * Messages de la plateforme tels que le back-office d'une communauté les voit : ciblage, langue, état par personne.
 * Un message n'arrive ici que si PlatformBoMessage::targets() retient la communauté : l'isolation est faite une seule fois.
 */
final class PlatformBoMessageService
{
    public function __construct(private ?PlatformBoMessageRepository $repo = null)
    {
        $this->repo ??= new PlatformBoMessageRepository();
    }

    /**
     * @param array<string, mixed> $tenant ligne tenants (id, plan_slug, tenant_type)
     * @return list<array<string, mixed>>
     */
    public function forTenant(
        array $tenant,
        int $userId,
        string $locale,
        bool $includeDismissed = false,
        bool $markSeen = false,
        bool $opened = false,
        ?int $now = null
    ): array {
        $tenantId = (int) ($tenant['id'] ?? 0);
        if ($tenantId < 1 || $userId < 1) {
            return [];
        }
        $plan = (string) ($tenant['plan_slug'] ?? 'free');
        $type = (string) ($tenant['tenant_type'] ?? 'full');
        $rows = array_values(array_filter(
            $this->repo->listLive($now),
            static fn (array $r): bool => PlatformBoMessage::targets($r, $tenantId, $plan, $type)
        ));
        if ($rows === []) {
            return [];
        }
        $states = $this->repo->states($tenantId, $userId, array_map(static fn (array $r): int => (int) $r['id'], $rows));

        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            $st = $states[$id];
            $level = PlatformBoMessage::level((string) $r['level']);
            $dismissible = $level !== 'urgent';
            $title = PlatformBoMessage::localized($r, 'title', $locale);
            if ($title === '' || $st['acked']) {
                continue;
            }
            $hidden = $dismissible && $st['dismissed'];
            if ($hidden && !$includeDismissed) {
                continue;
            }
            $url = PlatformBoMessage::safeUrl($r['cta_url'] ?? null);
            $label = PlatformBoMessage::localized($r, 'cta_label', $locale);
            $body = PlatformBoMessage::localized($r, 'body', $locale);
            if ($markSeen && !$hidden) {
                $this->repo->markSeen($id, $tenantId, $userId, $opened);
            }
            $out[] = [
                'id' => $id,
                'level' => $level,
                'title' => $title,
                'body' => $body,
                'body_html' => PlatformBoMessage::renderBody($body),
                'cta_label' => $url !== null && $label !== '' ? $label : '',
                'cta_url' => $url !== null && $label !== '' ? $url : null,
                'dismissible' => $dismissible,
                'ackable' => !$dismissible,
                'dismissed' => $hidden,
                'is_new' => !$st['opened'] && !$hidden,
                'ends_at' => $r['ends_at'] ?? null,
            ];
        }
        usort($out, static fn (array $a, array $b): int => [self::rank($b['level']), $b['id']] <=> [self::rank($a['level']), $a['id']]);

        return $out;
    }

    /** Pastille du menu : messages visibles que la personne n'a pas encore ouverts. */
    public function unreadCount(array $tenant, int $userId, ?int $now = null): array
    {
        $items = $this->forTenant($tenant, $userId, 'fr', false, false, false, $now);
        $new = array_filter($items, static fn (array $i): bool => !empty($i['is_new']));
        $urgent = array_filter($new, static fn (array $i): bool => $i['level'] === 'urgent');

        return ['count' => count($new), 'important' => $urgent !== []];
    }

    /** Un message vise-t-il cette communauté (lien du bouton, fermeture, acquittement) ? */
    public function findForTenant(int $messageId, array $tenant, ?int $now = null): ?array
    {
        $row = $this->repo->find($messageId);
        if ($row === null || !PlatformBoMessage::isLive($row, $now)) {
            return null;
        }
        $ok = PlatformBoMessage::targets($row, (int) ($tenant['id'] ?? 0), (string) ($tenant['plan_slug'] ?? 'free'), (string) ($tenant['tenant_type'] ?? 'full'));

        return $ok ? $row : null;
    }

    private static function rank(string $level): int
    {
        return match ($level) {
            'urgent' => 2,
            'important' => 1,
            default => 0,
        };
    }
}
