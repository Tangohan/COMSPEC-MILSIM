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
        private DocumentVariablesCatalogRepository $variablesCatalog
    ) {
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
        $defaults = $this->autoFillService->getDefaults(['user_id' => $userId, 'tenant_id' => $tenantId]);

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
        $previewHtml = $this->builderService->buildPreviewHtml($document, $context);
        $alerts = $this->validationService->validate($document, $context, []);
        $completenessScore = $this->validationService->completenessScore($document, $alerts);
        $defaultPreset = $this->presetRepository->getDefault($tenantId);
        $defaults = $this->autoFillService->getDefaults(['user_id' => $userId, 'tenant_id' => $tenantId]);
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
        $bodyRendered = $request->input('body_rendered') ?? '';
        $classificationRaw = trim((string) ($request->input('classification_level') ?? ''));
        $classificationLevel = in_array($classificationRaw, CourrierClassification::codes(), true)
            ? $classificationRaw
            : 'interne';

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
            ]);
            Session::flash('success', 'Brouillon enregistré.');
            return Response::redirect(url('courrier/editor/' . $id));
        }

        $defaults = $this->autoFillService->getDefaults(['user_id' => $userId, 'tenant_id' => $tenantId]);
        if ($referenceNumber === null || $referenceNumber === '') {
            $referenceNumber = $defaults['reference_number'] ?? null;
        }
        if ($issuerLabel === null || $issuerLabel === '') {
            $issuerLabel = $defaults['issuer_label'] ?? null;
        }

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
        ]);
        Session::flash('success', 'Brouillon créé.');
        return Response::redirect(url('courrier/editor/' . $newId));
    }
}
