<?php

declare(strict_types=1);

namespace App\Services\Communications;

use App\Repositories\UnitRepository;
use App\Services\Courrier\TemplateVariableService;

/**
 * Rendu des gabarits e-mail (placeholders type Courrier).
 */
final class TenantEmailRenderService
{
    public function __construct(
        private TemplateVariableService $templateVariableService,
        private UnitRepository $unitRepository
    ) {}

    /**
     * @return array{subject: string, html: string, text: string}
     */
    public function renderForUser(
        int $tenantId,
        int $userId,
        string $subjectTemplate,
        string $htmlTemplate,
        ?string $textTemplate
    ): array {
        $unitIds = $this->unitRepository->unitIdsForUser($tenantId, $userId);
        $primaryUnitId = $unitIds[0] ?? null;
        $ctx = [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'unit_id' => $primaryUnitId,
        ];
        $subject = $this->templateVariableService->replaceInString($subjectTemplate, $ctx);
        $html = $this->templateVariableService->replaceInString($htmlTemplate, $ctx);
        $text = $textTemplate !== null && trim($textTemplate) !== ''
            ? $this->templateVariableService->replaceInString($textTemplate, $ctx)
            : strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html));

        return [
            'subject' => $subject,
            'html' => $html,
            'text' => $text,
        ];
    }
}
