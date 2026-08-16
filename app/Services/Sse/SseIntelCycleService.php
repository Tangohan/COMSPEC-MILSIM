<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseCaseRepository;
use App\Repositories\SseIntelCycleRepository;
use App\Support\SseIntelCycleCatalog;

/**
 * LOT 4 — Cycle : exigences → tasking → produit → validation → sanitisation → diffusion.
 */
final class SseIntelCycleService
{
    public function __construct(
        private ?SseIntelCycleRepository $repo = null,
        private ?SseReportService $reports = null,
        private ?SseCaseRepository $cases = null,
        private ?SseIntelFoundationService $intel = null,
    ) {
        $this->repo ??= new SseIntelCycleRepository();
        $this->reports ??= new SseReportService();
        $this->cases ??= new SseCaseRepository();
        $this->intel ??= new SseIntelFoundationService();
    }

    /**
     * Tableau de bord cycle pour le workspace.
     *
     * @return array<string,mixed>
     */
    public function cycleBoard(int $tenantId, ?int $caseId = null): array
    {
        $filters = $caseId !== null ? ['case_id' => $caseId, 'limit' => 60] : ['limit' => 40];
        $requirements = $this->repo->listRequirements($tenantId, $filters);
        $taskings = $this->repo->listTaskings($tenantId, $filters);
        $products = $this->repo->listProducts($tenantId, $filters);
        $counts = $this->repo->countsForTenant($tenantId, $caseId);

        return [
            'counts' => $counts,
            'requirements' => $requirements,
            'taskings' => $taskings,
            'products' => $products,
            'catalog' => [
                'requirement_types' => SseIntelCycleCatalog::REQUIREMENT_TYPES,
                'requirement_statuses' => SseIntelCycleCatalog::REQUIREMENT_STATUSES,
                'priorities' => SseIntelCycleCatalog::PRIORITIES,
                'tasking_statuses' => SseIntelCycleCatalog::TASKING_STATUSES,
                'product_types' => SseIntelCycleCatalog::PRODUCT_TYPES,
                'product_statuses' => SseIntelCycleCatalog::PRODUCT_STATUSES,
                'release_levels' => SseIntelCycleCatalog::RELEASE_LEVELS,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $data
     * @return array{ok:bool,id?:int,message?:string,error?:string}
     */
    public function createRequirement(int $tenantId, array $data, string $author = '', ?int $userId = null): array
    {
        $data['author_label'] = $author !== '' ? $author : ($data['author_label'] ?? null);
        $data['created_by'] = $userId;
        if (empty($data['reference_code']) && !empty($data['case_id'])) {
            $case = $this->cases->findById((int) $data['case_id'], $tenantId);
            $type = strtoupper((string) ($data['req_type'] ?? 'PIR'));
            $data['reference_code'] = sprintf(
                '%s-%s-%03d',
                (string) ($case['reference_code'] ?? 'SSE'),
                $type,
                random_int(1, 999)
            );
        }
        $result = $this->repo->createRequirement($tenantId, $data);
        if (!($result['ok'] ?? false)) {
            return $result;
        }
        $this->emitEvent($tenantId, (int) ($data['case_id'] ?? 0), 'REQUIREMENT_CREATED', sprintf(
            'Exigence %s créée — %s',
            SseIntelCycleCatalog::requirementTypeLabel((string) ($data['req_type'] ?? 'PIR')),
            (string) ($data['title'] ?? '')
        ), $author, ['requirement_id' => $result['id']]);

        return ['ok' => true, 'id' => $result['id'], 'message' => 'Exigence enregistrée.'];
    }

    /**
     * @param array<string,mixed> $data
     * @return array{ok:bool,message?:string,error?:string}
     */
    public function updateRequirementStatus(
        int $tenantId,
        int $id,
        string $status,
        ?int $coveragePct = null,
        string $author = ''
    ): array {
        $req = $this->repo->findRequirement($tenantId, $id);
        if ($req === null) {
            return ['ok' => false, 'error' => 'Exigence introuvable.'];
        }
        $payload = ['status' => $status];
        if ($coveragePct !== null) {
            $payload['coverage_pct'] = $coveragePct;
            if ($coveragePct >= 100) {
                $payload['status'] = 'satisfait';
            } elseif ($coveragePct > 0 && $status === 'ouvert') {
                $payload['status'] = 'partiellement_couvert';
            }
        }
        if (!$this->repo->updateRequirement($tenantId, $id, $payload)) {
            return ['ok' => false, 'error' => 'Mise à jour impossible.'];
        }
        $this->emitEvent(
            $tenantId,
            (int) ($req['case_id'] ?? 0),
            'REQUIREMENT_UPDATED',
            sprintf(
                'Exigence « %s » — %s',
                (string) ($req['title'] ?? ''),
                SseIntelCycleCatalog::statusLabel('requirement', (string) ($payload['status'] ?? $status))
            ),
            $author,
            ['requirement_id' => $id, 'status' => $payload['status'] ?? $status]
        );

        return ['ok' => true, 'message' => 'Exigence mise à jour.'];
    }

    /**
     * @param array<string,mixed> $data
     * @return array{ok:bool,id?:int,message?:string,error?:string}
     */
    public function createTasking(int $tenantId, array $data, string $author = '', ?int $userId = null): array
    {
        $reqId = (int) ($data['requirement_id'] ?? 0);
        $req = $this->repo->findRequirement($tenantId, $reqId);
        if ($req === null) {
            return ['ok' => false, 'error' => 'Exigence introuvable pour cet ordre de collecte.'];
        }
        $data['case_id'] = $data['case_id'] ?? $req['case_id'];
        $data['author_label'] = $author !== '' ? $author : ($data['author_label'] ?? null);
        $data['created_by'] = $userId;
        if (empty($data['status']) || $data['status'] === 'brouillon') {
            $data['status'] = !empty($data['emit']) ? 'emis' : 'brouillon';
        }
        $result = $this->repo->createTasking($tenantId, $data);
        if (!($result['ok'] ?? false)) {
            return $result;
        }
        if (($req['status'] ?? '') === 'ouvert') {
            $this->repo->updateRequirement($tenantId, $reqId, ['status' => 'en_cours']);
        }
        $this->emitEvent(
            $tenantId,
            (int) ($req['case_id'] ?? 0),
            'TASKING_CREATED',
            sprintf('Ordre de collecte — %s', (string) ($data['title'] ?? '')),
            $author,
            ['tasking_id' => $result['id'], 'requirement_id' => $reqId]
        );

        return ['ok' => true, 'id' => $result['id'], 'message' => 'Ordre de collecte enregistré.'];
    }

    /**
     * @param array<string,mixed> $data
     * @return array{ok:bool,message?:string,error?:string}
     */
    public function advanceTasking(int $tenantId, int $id, array $data, string $author = ''): array
    {
        $task = $this->repo->findTasking($tenantId, $id);
        if ($task === null) {
            return ['ok' => false, 'error' => 'Ordre de collecte introuvable.'];
        }
        if (!$this->repo->updateTasking($tenantId, $id, $data)) {
            return ['ok' => false, 'error' => 'Mise à jour impossible.'];
        }
        $status = (string) ($data['status'] ?? $task['status']);
        if (in_array($status, ['remis', 'clos'], true) && !empty($task['requirement_id'])) {
            $this->bumpRequirementCoverage($tenantId, (int) $task['requirement_id'], 25, $author);
        }
        $this->emitEvent(
            $tenantId,
            (int) ($task['case_id'] ?? 0),
            'TASKING_UPDATED',
            sprintf(
                'Ordre « %s » — %s',
                (string) ($task['title'] ?? ''),
                SseIntelCycleCatalog::statusLabel('tasking', $status)
            ),
            $author,
            ['tasking_id' => $id, 'status' => $status]
        );

        return ['ok' => true, 'message' => 'Ordre de collecte mis à jour.'];
    }

    /**
     * Génère un produit à partir du moteur de comptes rendus + niveau de diffusion.
     *
     * @return array{ok:bool,id?:int,message?:string,error?:string}
     */
    public function generateProduct(
        int $tenantId,
        int $caseId,
        string $productType,
        string $releaseLevel,
        string $author = '',
        ?int $userId = null,
        ?int $requirementId = null
    ): array {
        $type = strtoupper($productType);
        if (!isset(SseIntelCycleCatalog::PRODUCT_TYPES[$type])) {
            $type = 'INITIAL';
        }
        if (!isset(SseIntelCycleCatalog::RELEASE_LEVELS[$releaseLevel])) {
            $releaseLevel = 'interne';
        }

        $body = $type === 'FLASH'
            ? $this->reports->buildFlashReport($caseId, $tenantId, $releaseLevel)
            : $this->reports->buildInitialReport($caseId, $tenantId, $releaseLevel);

        if (trim($body) === '') {
            // Fallback sans niveau si le dossier n’a pas encore de caviardage.
            $body = $type === 'FLASH'
                ? $this->reports->buildFlashReport($caseId, $tenantId)
                : $this->reports->buildInitialReport($caseId, $tenantId);
        }
        if (trim($body) === '') {
            return ['ok' => false, 'error' => 'Impossible de composer le compte rendu pour ce dossier.'];
        }

        $case = $this->cases->findById($caseId, $tenantId);
        $title = sprintf(
            '%s — %s',
            SseIntelCycleCatalog::PRODUCT_TYPES[$type],
            (string) ($case['reference_code'] ?? ('Dossier #' . $caseId))
        );

        $result = $this->repo->createProduct($tenantId, [
            'case_id' => $caseId,
            'requirement_id' => $requirementId,
            'product_type' => $type,
            'title' => $title,
            'body_text' => $body,
            'classification' => (string) ($case['classification'] ?? 'encadrement'),
            'release_level' => $releaseLevel,
            'status' => 'brouillon',
            'sanitised' => $releaseLevel !== 'interne',
            'author_label' => $author,
            'created_by' => $userId,
        ]);
        if (!($result['ok'] ?? false)) {
            return $result;
        }
        if ($releaseLevel !== 'interne') {
            $this->repo->updateProduct($tenantId, (int) $result['id'], [
                'sanitised' => true,
                'status' => 'en_relecture',
            ]);
        }
        $this->emitEvent(
            $tenantId,
            $caseId,
            'PRODUCT_CREATED',
            sprintf('Produit « %s » composé', $title),
            $author,
            ['product_id' => $result['id'], 'product_type' => $type, 'release_level' => $releaseLevel]
        );

        return ['ok' => true, 'id' => $result['id'], 'message' => 'Compte rendu composé.'];
    }

    /**
     * @return array{ok:bool,message?:string,error?:string}
     */
    public function validateProduct(int $tenantId, int $productId, string $author = ''): array
    {
        $product = $this->repo->findProduct($tenantId, $productId);
        if ($product === null) {
            return ['ok' => false, 'error' => 'Produit introuvable.'];
        }
        $ok = $this->repo->updateProduct($tenantId, $productId, [
            'status' => 'valide',
            'validated_by_label' => $author !== '' ? $author : 'Analyste',
            'mark_validated' => true,
        ]);
        if (!$ok) {
            return ['ok' => false, 'error' => 'Validation impossible.'];
        }
        $this->emitEvent(
            $tenantId,
            (int) $product['case_id'],
            'PRODUCT_VALIDATED',
            sprintf('Produit validé — %s', (string) ($product['title'] ?? '')),
            $author,
            ['product_id' => $productId]
        );

        return ['ok' => true, 'message' => 'Produit validé.'];
    }

    /**
     * Recompose le corps avec caviardage au niveau demandé.
     *
     * @return array{ok:bool,message?:string,error?:string}
     */
    public function sanitiseProduct(
        int $tenantId,
        int $productId,
        ?string $releaseLevel = null,
        string $author = ''
    ): array {
        $product = $this->repo->findProduct($tenantId, $productId);
        if ($product === null) {
            return ['ok' => false, 'error' => 'Produit introuvable.'];
        }
        $level = $releaseLevel ?? (string) ($product['release_level'] ?? 'interne');
        if (!isset(SseIntelCycleCatalog::RELEASE_LEVELS[$level])) {
            $level = 'interne';
        }
        $type = (string) ($product['product_type'] ?? 'INITIAL');
        $body = $type === 'FLASH'
            ? $this->reports->buildFlashReport((int) $product['case_id'], $tenantId, $level)
            : $this->reports->buildInitialReport((int) $product['case_id'], $tenantId, $level);
        if (trim($body) === '') {
            $body = (string) ($product['body_text'] ?? '');
        }
        $ok = $this->repo->updateProduct($tenantId, $productId, [
            'body_text' => $body,
            'release_level' => $level,
            'sanitised' => true,
            'status' => in_array($product['status'], ['valide', 'sanitise', 'diffuse'], true)
                ? 'sanitise'
                : 'en_relecture',
        ]);
        if (!$ok) {
            return ['ok' => false, 'error' => 'Sanitisation impossible.'];
        }
        $this->emitEvent(
            $tenantId,
            (int) $product['case_id'],
            'PRODUCT_SANITISED',
            sprintf(
                'Produit sanitisé (%s) — %s',
                SseIntelCycleCatalog::RELEASE_LEVELS[$level] ?? $level,
                (string) ($product['title'] ?? '')
            ),
            $author,
            ['product_id' => $productId, 'release_level' => $level]
        );

        return ['ok' => true, 'message' => 'Version sanitisée enregistrée.'];
    }

    /**
     * @param list<array{label:string,role?:string}> $recipients
     * @return array{ok:bool,message?:string,error?:string}
     */
    public function diffuseProduct(
        int $tenantId,
        int $productId,
        array $recipients,
        string $author = ''
    ): array {
        $product = $this->repo->findProduct($tenantId, $productId);
        if ($product === null) {
            return ['ok' => false, 'error' => 'Produit introuvable.'];
        }
        if (!in_array($product['status'], ['valide', 'sanitise', 'diffuse'], true)) {
            return ['ok' => false, 'error' => 'Validez le produit avant diffusion.'];
        }
        $clean = [];
        foreach ($recipients as $r) {
            if (!is_array($r)) {
                continue;
            }
            $label = trim((string) ($r['label'] ?? $r['recipient_label'] ?? ''));
            if ($label !== '') {
                $clean[] = [
                    'label' => $label,
                    'role' => (string) ($r['role'] ?? $r['recipient_role'] ?? ''),
                ];
            }
        }
        if ($clean === []) {
            return ['ok' => false, 'error' => 'Indiquez au moins un destinataire.'];
        }
        $this->repo->replaceRecipients($tenantId, $productId, $clean);
        $this->repo->updateProduct($tenantId, $productId, [
            'status' => 'diffuse',
            'mark_diffused' => true,
        ]);
        $this->emitEvent(
            $tenantId,
            (int) $product['case_id'],
            'PRODUCT_DIFFUSED',
            sprintf(
                'Diffusion — %s (%d destinataire%s)',
                (string) ($product['title'] ?? ''),
                count($clean),
                count($clean) > 1 ? 's' : ''
            ),
            $author,
            ['product_id' => $productId, 'recipients' => count($clean)]
        );

        return ['ok' => true, 'message' => 'Produit diffusé.'];
    }

    private function bumpRequirementCoverage(int $tenantId, int $reqId, int $delta, string $author): void
    {
        $req = $this->repo->findRequirement($tenantId, $reqId);
        if ($req === null) {
            return;
        }
        $pct = min(100, (int) ($req['coverage_pct'] ?? 0) + $delta);
        $status = $pct >= 100 ? 'satisfait' : ($pct > 0 ? 'partiellement_couvert' : (string) ($req['status'] ?? 'ouvert'));
        $this->repo->updateRequirement($tenantId, $reqId, [
            'coverage_pct' => $pct,
            'status' => $status,
        ]);
        $this->emitEvent(
            $tenantId,
            (int) ($req['case_id'] ?? 0),
            'REQUIREMENT_COVERAGE',
            sprintf('Couverture exigence « %s » : %d %%', (string) ($req['title'] ?? ''), $pct),
            $author,
            ['requirement_id' => $reqId, 'coverage_pct' => $pct]
        );
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function emitEvent(
        int $tenantId,
        int $caseId,
        string $type,
        string $summary,
        string $author,
        array $payload = []
    ): void {
        if ($tenantId < 1) {
            return;
        }
        try {
            $this->intel->recordEvent([
                'tenant_id' => $tenantId,
                'context_id' => 1,
                'case_id' => $caseId > 0 ? $caseId : null,
                'event_type' => $type,
                'source_system' => 'INTEL_CYCLE',
                'summary' => $summary,
                'identity_tier' => 'DOCUMENTARY',
                'source_reliability' => 'B',
                'info_credibility' => 2,
                'author_label' => $author !== '' ? $author : 'Cellule SSE',
                'payload' => $payload,
                'idempotency_key' => sprintf(
                    'cycle:%s:%d:%s',
                    $type,
                    $payload['requirement_id'] ?? $payload['tasking_id'] ?? $payload['product_id'] ?? 0,
                    date('YmdHis')
                ),
            ]);
        } catch (\Throwable) {
        }
    }
}
