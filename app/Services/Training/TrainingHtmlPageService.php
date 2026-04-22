<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Repositories\TrainingFormationCustomPageRepository;

final class TrainingHtmlPageService
{
    public function __construct(private TrainingFormationCustomPageRepository $repository) {}

    /** @return array{status:string,is_published:int,published_at:?string,scheduled_publish_at:?string,archived_at:?string} */
    public function resolveWorkflowState(string $requested, bool $publishNow, ?string $scheduledAt): array
    {
        $status = $requested;
        $isPublished = 0;
        $publishedAt = null;
        $archivedAt = null;
        $scheduledAt = $scheduledAt !== null && trim($scheduledAt) !== '' ? trim($scheduledAt) : null;

        if ($publishNow || $status === 'published') {
            $status = 'published';
            $isPublished = 1;
            $publishedAt = date('Y-m-d H:i:s');
            $scheduledAt = null;
        } elseif ($status === 'scheduled' && $scheduledAt !== null) {
            $isPublished = 0;
        } elseif ($status === 'archived') {
            $isPublished = 0;
            $archivedAt = date('Y-m-d H:i:s');
        } elseif (!in_array($status, ['draft', 'review', 'scheduled', 'published', 'archived'], true)) {
            $status = 'draft';
        }

        return [
            'status' => $status,
            'is_published' => $isPublished,
            'published_at' => $publishedAt,
            'scheduled_publish_at' => $scheduledAt,
            'archived_at' => $archivedAt,
        ];
    }

    public function estimateReadTimeMinutes(string $html, ?string $sectionsJson): int
    {
        $text = trim(strip_tags($html));
        if ($sectionsJson) {
            $items = json_decode($sectionsJson, true);
            if (is_array($items)) {
                foreach ($items as $item) {
                    if (is_array($item)) {
                        $text .= ' ' . trim(strip_tags((string) ($item['html'] ?? '')));
                    }
                }
            }
        }
        $words = preg_split('/\s+/u', $text) ?: [];
        $count = count(array_filter($words, static fn ($w) => $w !== ''));

        return max(1, (int) ceil($count / 220));
    }

    public function buildDiffSummary(array $before, array $after): string
    {
        $changes = [];
        foreach (['title', 'slug', 'status', 'visibility_level'] as $key) {
            if ((string) ($before[$key] ?? '') !== (string) ($after[$key] ?? '')) {
                $changes[] = $key;
            }
        }

        return $changes === [] ? 'Contenu mis à jour.' : ('Champs modifiés: ' . implode(', ', $changes));
    }

    public function createRevision(int $pageId, int $tenantId, int $userId, string $type, array $pageSnapshot, ?array $previous = null): void
    {
        $diff = $previous !== null ? $this->buildDiffSummary($previous, $pageSnapshot) : 'Révision initiale.';
        $this->repository->createRevision($pageId, $tenantId, $userId, $type, $pageSnapshot, $diff);
    }

    public function applyScheduledPublicationIfDue(int $tenantId): void
    {
        $rows = $this->repository->listByTenant($tenantId, 200, ['status' => 'scheduled']);
        $now = date('Y-m-d H:i:s');
        foreach ($rows as $row) {
            $scheduled = (string) ($row['scheduled_publish_at'] ?? '');
            if ($scheduled === '' || $scheduled > $now) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $this->repository->update($id, $tenantId, [
                'status' => 'published',
                'is_published' => 1,
                'published_at' => $now,
                'scheduled_publish_at' => null,
            ]);
            $this->repository->addActivity($id, $tenantId, null, 'scheduled_publish_triggered', ['scheduled_publish_at' => $scheduled]);
        }
    }
}
