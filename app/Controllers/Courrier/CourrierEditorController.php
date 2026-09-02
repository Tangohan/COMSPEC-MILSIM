<?php

declare(strict_types=1);

namespace App\Controllers\Courrier;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\Courrier\CourrierDocumentRepository;
use App\Repositories\Courrier\DocumentPresetRepository;
use App\Repositories\Courrier\DocumentTemplateRepository;
use App\Repositories\Courrier\DocumentVariablesCatalogRepository;
use App\Services\Courrier\DocumentAutoFillService;
use App\Services\Courrier\DocumentBuilderService;
use App\Services\Courrier\DocumentValidationService;
use App\Services\Courrier\DocumentWorkflowService;
use App\Services\Courrier\CourrierClassification;
use App\Services\Courrier\TemplateVariableService;
use App\Repositories\ModerationArtifactRepository;
use App\Services\Moderation\ContentModerationConfig;
use App\Services\Moderation\ContentModerationOrchestrator;
use App\Services\Moderation\ModerationArtifactState;
use App\Services\Moderation\ModerationScanResult;
use App\Services\Moderation\ModerationSourceType;

class CourrierEditorController
{
    public function __construct(
        private CourrierDocumentRepository $documentRepository,
        private DocumentTemplateRepository $templateRepository,
        private DocumentPresetRepository $presetRepository,
        private DocumentBuilderService $builderService,
        private DocumentValidationService $validationService,
        private TemplateVariableService $variableService,
        private DocumentAutoFillService $autoFillService,
        private DocumentWorkflowService $workflowService,
        private DocumentVariablesCatalogRepository $variablesCatalog,
        private ContentModerationOrchestrator $moderationOrchestrator,
        private ModerationArtifactRepository $moderationArtifactRepository,
        private ContentModerationConfig $moderationConfig
    ) {
    }

    /**
     * @return array{header_line1: string, header_unit: string, header_section: string}
     */
    private function parseHeaderMetaFromDocument(array $document): array
    {
        $raw = $document['metadata_json'] ?? null;
        $meta = [];
        if ($raw !== null && $raw !== '') {
            $meta = is_string($raw) ? (json_decode($raw, true) ?: []) : (is_array($raw) ? $raw : []);
        }

        return [
            'header_line1' => trim((string) ($meta['header_line1'] ?? '')),
            'header_unit' => trim((string) ($meta['header_unit'] ?? '')),
            'header_section' => trim((string) ($meta['header_section'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed>|null $existingDoc
     * @return array<string, mixed>
     */
    private function mergeMetadataFromRequest(Request $request, ?array $existingDoc): array
    {
        $prev = [];
        if ($existingDoc !== null && !empty($existingDoc['metadata_json'])) {
            $raw = $existingDoc['metadata_json'];
            $prev = is_string($raw) ? (json_decode($raw, true) ?: []) : (is_array($raw) ? $raw : []);
        }
        $prev['header_line1'] = trim((string) $request->input('header_line1', ''));
        $prev['header_unit'] = trim((string) $request->input('header_unit', ''));
        $prev['header_section'] = trim((string) $request->input('header_section', ''));

        return $prev;
    }

    /**
     * @param array<string, mixed> $document
     * @param array{header_line1: string, header_unit: string, header_section: string} $header
     * @return array<string, mixed>
     */
    private function documentWithHeaderMeta(array $document, array $header): array
    {
        $raw = $document['metadata_json'] ?? null;
        $meta = [];
        if ($raw !== null && $raw !== '') {
            $meta = is_string($raw) ? (json_decode($raw, true) ?: []) : (is_array($raw) ? $raw : []);
        }
        $document['metadata_json'] = array_merge($meta, $header);

        return $document;
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }

        $templates = $this->templateRepository->listForTenant($tenantId, true);
        $presets = $this->presetRepository->listForTenant($tenantId);
        $variablesByCategory = $this->variableService->getAvailableVariables($tenantId);
        $defaultPreset = $this->presetRepository->getDefault($tenantId);
        $orgContext = ['user_id' => $userId, 'tenant_id' => $tenantId];
        $defaults = $this->autoFillService->getDefaults($orgContext);

        return Response::view('layout.main', [
            'title' => 'Nouveau document — Bureau Courrier',
            'content' => 'courrier/editor',
            'courrier' => [
                'document' => null,
                'templates' => $templates,
                'presets' => $presets,
                'variables_by_category' => $variablesByCategory,
                'default_preset' => $defaultPreset,
                'defaults' => $defaults,
                'alerts' => [],
                'completeness_score' => 0,
                'preview_html' => '',
                'classification_labels' => CourrierClassification::labels(),
                'header_meta' => [
                    'header_line1' => (string) ($defaults['header_line1'] ?? ''),
                    'header_unit' => (string) ($defaults['header_unit'] ?? ''),
                    'header_section' => (string) ($defaults['header_section'] ?? ''),
                ],
            ],
        ]);
    }

    public function edit(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }

        $document = $this->documentRepository->findById($id, $tenantId);
        if (!$document) {
            Session::flash('error', 'Document introuvable.');
            return Response::redirect(url('courrier'));
        }

        $templates = $this->templateRepository->listForTenant($tenantId, true);
        $presets = $this->presetRepository->listForTenant($tenantId);
        $variablesByCategory = $this->variableService->getAvailableVariables($tenantId);
        $context = ['user_id' => $userId, 'tenant_id' => $tenantId, 'document' => $document];

        // Auto-guérison : résoudre les {{variables}} encore présentes dans le corps.
        $rawBody = (string) ($document['body_rendered'] ?? '');
        if ($this->builderService->findUnresolvedPlaceholders($rawBody) !== []) {
            $resolvedBody = $this->builderService->resolveBodyPlaceholders($rawBody, $context);
            if ($resolvedBody !== $rawBody) {
                $this->documentRepository->update($id, ['body_rendered' => $resolvedBody]);
                $document['body_rendered'] = $resolvedBody;
                $context['document'] = $document;
            }
        }

        $orgContext = ['user_id' => $userId, 'tenant_id' => $tenantId];
        $headerMeta = $this->autoFillService->mergeLetterhead(
            $this->parseHeaderMetaFromDocument($document),
            $orgContext
        );
        $document = $this->documentWithHeaderMeta($document, $headerMeta);
        $context['document'] = $document;
        $previewHtml = $this->builderService->buildPreviewHtml($document, $context);
        $alerts = $this->validationService->validate($document, $context, []);
        $completenessScore = $this->validationService->completenessScore($document, $alerts);
        $defaultPreset = $this->presetRepository->getDefault($tenantId);
        $defaults = $this->autoFillService->getDefaults($orgContext);
        $versions = $this->documentRepository->getVersions($id);
        $versions = array_slice($versions, 0, 10);

        return Response::view('layout.main', [
            'title' => ($document['title'] ?: 'Sans titre') . ' — Bureau Courrier',
            'content' => 'courrier/editor',
            'courrier' => [
                'document' => $document,
                'templates' => $templates,
                'presets' => $presets,
                'variables_by_category' => $variablesByCategory,
                'default_preset' => $defaultPreset,
                'defaults' => $defaults,
                'preview_html' => $previewHtml,
                'alerts' => $alerts,
                'completeness_score' => $completenessScore,
                'versions' => $versions,
                'classification_labels' => CourrierClassification::labels(),
                'header_meta' => $headerMeta,
            ],
        ]);
    }

    public function save(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }

        $id = (int) ($request->input('id') ?? 0);
        $templateId = $request->input('template_id') ? (int) $request->input('template_id') : null;
        $presetId = $request->input('preset_id') ? (int) $request->input('preset_id') : null;
        $title = $request->input('title') ? trim((string) $request->input('title')) : null;
        $subject = $request->input('subject') ? trim((string) $request->input('subject')) : null;
        $referenceNumber = $request->input('reference_number') ? trim((string) $request->input('reference_number')) : null;
        $destinationLabel = $request->input('destination_label') ? trim((string) $request->input('destination_label')) : null;
        $issuerLabel = $request->input('issuer_label') ? trim((string) $request->input('issuer_label')) : null;
        $bodyRendered = (string) ($request->input('body_rendered') ?? '');
        $classificationRaw = trim((string) ($request->input('classification_level') ?? ''));
        $classificationLevel = in_array($classificationRaw, CourrierClassification::codes(), true)
            ? $classificationRaw
            : 'interne';

        // Résoudre {{variables}} avant enregistrement / modération.
        $resolveContext = [
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'document' => [
                'uuid' => null,
                'reference_number' => $referenceNumber,
            ],
        ];
        if ($id > 0) {
            $existingForResolve = $this->documentRepository->findById($id, $tenantId);
            if ($existingForResolve) {
                $resolveContext['document'] = array_merge($existingForResolve, [
                    'reference_number' => $referenceNumber ?? ($existingForResolve['reference_number'] ?? null),
                ]);
            }
        }
        $bodyRendered = $this->builderService->resolveBodyPlaceholders($bodyRendered, $resolveContext);

        $scan = $this->moderationOrchestrator->scanTextContent((string) $bodyRendered, array_values(array_filter([
            (string) ($title ?? ''),
            (string) ($subject ?? ''),
            (string) ($referenceNumber ?? ''),
            (string) ($destinationLabel ?? ''),
            (string) ($issuerLabel ?? ''),
        ], static fn ($s) => $s !== '')));
        if ($scan->state === ModerationArtifactState::REJECTED) {
            Session::flash('error', 'Le contenu est refusé par la modération automatique. Modifiez le texte et réessayez.');

            return $id > 0
                ? Response::redirect(url('courrier/editor/' . $id))
                : Response::redirect(url('courrier'));
        }
        $moderationState = null;
        if ($scan->state === ModerationArtifactState::QUARANTINED) {
            $moderationState = 'pending_review';
        }

        if ($id > 0) {
            $document = $this->documentRepository->findById($id, $tenantId);
            if (!$document) {
                Session::flash('error', 'Document introuvable.');
                return Response::redirect(url('courrier'));
            }
            if (!empty($document['signed_at']) || ($document['status'] ?? '') === 'signed') {
                Session::flash('error', 'Document signé : modification impossible. Utilisez une nouvelle version ou contactez un administrateur.');
                return Response::redirect(url('courrier/editor/' . $id));
            }
            $snapshot = [
                'title' => $document['title'] ?? null,
                'subject' => $document['subject'] ?? null,
                'reference_number' => $document['reference_number'] ?? null,
                'body_rendered' => $document['body_rendered'] ?? null,
                'destination_label' => $document['destination_label'] ?? null,
                'issuer_label' => $document['issuer_label'] ?? null,
                'updated_at' => $document['updated_at'] ?? null,
            ];
            $this->documentRepository->createVersion($id, $snapshot, $userId);
            $metadataMerged = $this->autoFillService->mergeLetterhead(
                $this->mergeMetadataFromRequest($request, $document),
                ['user_id' => $userId, 'tenant_id' => $tenantId]
            );
            $this->documentRepository->update($id, [
                'template_id' => $templateId,
                'preset_id' => $presetId,
                'title' => $title,
                'subject' => $subject,
                'reference_number' => $referenceNumber,
                'destination_label' => $destinationLabel,
                'issuer_label' => $issuerLabel,
                'body_rendered' => $bodyRendered,
                'classification_level' => $classificationLevel,
                'metadata_json' => $metadataMerged,
                'moderation_state' => $moderationState,
            ]);
            $this->syncCourrierModerationArtifact($tenantId, $id, $userId, $scan, (string) $bodyRendered);
            Session::flash('success', 'Brouillon enregistré.');
            if ($moderationState === 'pending_review') {
                Session::flash('warning', 'Ce brouillon est signalé pour revue modération (contenu sensible détecté).');
            }
            return Response::redirect(url('courrier/editor/' . $id));
        }

        $defaults = $this->autoFillService->getDefaults(['user_id' => $userId, 'tenant_id' => $tenantId]);
        if ($referenceNumber === null || $referenceNumber === '') {
            $referenceNumber = $defaults['reference_number'] ?? null;
        }
        if ($issuerLabel === null || $issuerLabel === '') {
            $issuerLabel = $defaults['issuer_label'] ?? null;
        }

        // Re-résoudre avec la référence définitive (nouvelle création).
        $bodyRendered = $this->builderService->resolveBodyPlaceholders($bodyRendered, [
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'document' => [
                'uuid' => null,
                'reference_number' => $referenceNumber,
            ],
        ]);

        $metadataMerged = $this->autoFillService->mergeLetterhead(
            $this->mergeMetadataFromRequest($request, null),
            ['user_id' => $userId, 'tenant_id' => $tenantId]
        );
        $newId = $this->documentRepository->create([
            'tenant_id' => $tenantId,
            'template_id' => $templateId,
            'preset_id' => $presetId,
            'type' => $request->input('type'),
            'status' => 'draft',
            'title' => $title,
            'reference_number' => $referenceNumber,
            'subject' => $subject,
            'destination_label' => $destinationLabel,
            'issuer_label' => $issuerLabel,
            'body_rendered' => $bodyRendered,
            'created_by' => $userId,
            'classification_level' => $classificationLevel,
            'metadata_json' => $metadataMerged,
            'moderation_state' => $moderationState,
        ]);
        // Second passage après création (uuid réel disponible).
        $created = $this->documentRepository->findById($newId, $tenantId);
        if ($created) {
            $finalBody = $this->builderService->resolveBodyPlaceholders((string) ($created['body_rendered'] ?? ''), [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'document' => $created,
            ]);
            if ($finalBody !== (string) ($created['body_rendered'] ?? '')) {
                $this->documentRepository->update($newId, ['body_rendered' => $finalBody]);
                $bodyRendered = $finalBody;
            }
        }
        $this->syncCourrierModerationArtifact($tenantId, $newId, $userId, $scan, (string) $bodyRendered);
        Session::flash('success', 'Brouillon créé.');
        if ($moderationState === 'pending_review') {
            Session::flash('warning', 'Ce brouillon est signalé pour revue modération (contenu sensible détecté).');
        }
        return Response::redirect(url('courrier/editor/' . $newId));
    }

    private function syncCourrierModerationArtifact(int $tenantId, int $courrierDocId, int $userId, ModerationScanResult $scan, string $bodyRendered): void
    {
        if (!$this->moderationArtifactRepository->tableExists()) {
            return;
        }
        $this->moderationArtifactRepository->deleteBySource($tenantId, ModerationSourceType::COURRIER_DOCUMENT, $courrierDocId);
        $hash = hash('sha256', $bodyRendered);
        $this->moderationArtifactRepository->insert($tenantId, [
            'user_id' => $userId,
            'source_type' => ModerationSourceType::COURRIER_DOCUMENT,
            'source_id' => $courrierDocId,
            'source_key' => null,
            'file_path' => null,
            'original_name' => null,
            'mime' => 'text/html',
            'sha256' => $hash,
            'state' => $scan->state === ModerationArtifactState::CLEAN ? ModerationArtifactState::CLEAN : ($scan->state === ModerationArtifactState::QUARANTINED ? ModerationArtifactState::QUARANTINED : ModerationArtifactState::CLEAN),
            'risk_score' => $scan->riskScore,
            'reason_codes' => $scan->reasonCodes,
            'scan_log' => $scan->scanLog,
            'ruleset_version' => $this->moderationConfig->rulesetVersion,
            'expires_at' => null,
        ]);
    }
}
