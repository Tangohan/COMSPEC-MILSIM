<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Core\Gate;
use App\Repositories\Courrier\CourrierDocumentRepository;
use App\Repositories\DocumentCategoryRepository;
use App\Repositories\DocumentRepository;
use App\Repositories\TenantDashboardPinRepository;
use App\Services\Documents\DocumentAccessService;

/**
 * Résolution et filtrage ACL des épingles dashboard (affichage membre).
 */
final class TenantDashboardPinService
{
    public function __construct(
        private TenantDashboardPinRepository $pinRepository,
        private DocumentRepository $documentRepository,
        private DocumentCategoryRepository $categoryRepository,
        private CourrierDocumentRepository $courrierDocumentRepository,
        private DocumentAccessService $documentAccessService,
    ) {}

    /**
     * @return list<array{id: int, kind: string, label: string, href: ?string, notice_text: ?string}>
     */
    public function listResolvedPinsForViewer(int $tenantId, int $userId): array
    {
        $gate = Gate::getInstance();
        $rows = $this->pinRepository->listOrderedForTenant($tenantId);
        $out = [];
        foreach ($rows as $row) {
            $resolved = $this->resolveRow($row, $tenantId, $userId, $gate);
            if ($resolved !== null) {
                $out[] = $resolved;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id: int, kind: string, label: string, href: ?string, notice_text: ?string}|null
     */
    private function resolveRow(array $row, int $tenantId, int $userId, Gate $gate): ?array
    {
        $id = (int) ($row['id'] ?? 0);
        $type = (string) ($row['pin_type'] ?? '');
        $titleOverride = trim((string) ($row['title'] ?? ''));

        if ($type === 'external_url') {
            $url = trim((string) ($row['external_url'] ?? ''));
            if ($url === '' || !$this->isAllowedExternalUrl($url)) {
                return null;
            }
            $label = $titleOverride !== '' ? $titleOverride : $url;

            return [
                'id' => $id,
                'kind' => 'external_url',
                'label' => $label,
                'href' => $url,
                'notice_text' => null,
            ];
        }

        if ($type === 'notice') {
            $body = (string) ($row['notice_body'] ?? '');
            if (trim($body) === '') {
                return null;
            }
            $label = $titleOverride !== '' ? $titleOverride : 'Consigne';

            return [
                'id' => $id,
                'kind' => 'notice',
                'label' => $label,
                'href' => null,
                'notice_text' => $body,
            ];
        }

        if ($type === 'document_category') {
            if ($gate->deny('documents.view')) {
                return null;
            }
            $catId = (int) ($row['document_category_id'] ?? 0);
            if ($catId <= 0) {
                return null;
            }
            $cat = $this->categoryRepository->findById($catId, $tenantId);
            if (!$cat) {
                return null;
            }
            $docs = $this->documentRepository->listForTenant($tenantId, $catId, 'published', null, null, null, null, null, null);
            $readable = array_values(array_filter(
                $docs,
                fn (array $d): bool => $this->documentAccessService->canRead($d, $userId, $tenantId)
            ));
            if ($readable === []) {
                return null;
            }
            $label = $titleOverride !== '' ? $titleOverride : (string) ($cat['name'] ?? 'Dossier');
            $href = url('documents') . '?category=' . $catId;

            return [
                'id' => $id,
                'kind' => 'document_category',
                'label' => $label,
                'href' => $href,
                'notice_text' => null,
            ];
        }

        if ($type === 'document') {
            if ($gate->deny('documents.view')) {
                return null;
            }
            $docId = (int) ($row['document_id'] ?? 0);
            if ($docId <= 0) {
                return null;
            }
            $doc = $this->documentRepository->findById($docId, $tenantId);
            if (!$doc || ($doc['status'] ?? '') !== 'published') {
                return null;
            }
            if (!$this->documentAccessService->canRead($doc, $userId, $tenantId)) {
                return null;
            }
            $slug = (string) ($doc['slug'] ?? '');
            if ($slug === '') {
                return null;
            }
            $label = $titleOverride !== '' ? $titleOverride : (string) ($doc['title'] ?? 'Document');

            return [
                'id' => $id,
                'kind' => 'document',
                'label' => $label,
                'href' => url('documents/' . rawurlencode($slug)),
                'notice_text' => null,
            ];
        }

        if ($type === 'courrier_document') {
            $cdId = (int) ($row['courrier_document_id'] ?? 0);
            if ($cdId <= 0) {
                return null;
            }
            $cd = $this->courrierDocumentRepository->findById($cdId, $tenantId);
            if (!$cd) {
                return null;
            }
            if (!$this->canViewCourrierForPin($cd, $gate)) {
                return null;
            }
            $label = $titleOverride !== '' ? $titleOverride : trim((string) ($cd['title'] ?? $cd['reference_number'] ?? 'Courrier'));
            if ($label === '') {
                $label = 'Courrier #' . $cdId;
            }

            return [
                'id' => $id,
                'kind' => 'courrier_document',
                'label' => $label,
                'href' => url('courrier/read/' . $cdId),
                'notice_text' => null,
            ];
        }

        return null;
    }

    /** @param array<string, mixed> $cd */
    private function canViewCourrierForPin(array $cd, Gate $gate): bool
    {
        $st = (string) ($cd['status'] ?? '');
        if (in_array($st, ['sent', 'validated', 'signed'], true)) {
            return true;
        }
        if ($st === 'archived') {
            return $gate->allows('courrier.archive') || $gate->allows('courrier.view')
                || $gate->allows('admin.organization') || $gate->allows('admin.access');
        }
        if (in_array($st, ['draft', 'pending_validation', 'rejected'], true)) {
            return $gate->allows('courrier.create') || $gate->allows('courrier.validate')
                || $gate->allows('admin.organization') || $gate->allows('admin.access');
        }

        return $gate->allows('courrier.create') || $gate->allows('admin.organization');
    }

    private function isAllowedExternalUrl(string $url): bool
    {
        if (!preg_match('#^https?://#i', $url)) {
            return false;
        }
        if (strlen($url) > 2000) {
            return false;
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}
