<?php

declare(strict_types=1);

namespace App\Services\Courrier;

/**
 * Remplacement des placeholders dans le body_template à partir du contexte et variables_json.
 */
class TemplateRenderService
{
    public function __construct(
        private TemplateVariableService $variableService
    ) {
    }

    /**
     * Rend le corps du document : remplace {{var}} par les valeurs du contexte + variables_json.
     * @param array{user_id?: int, tenant_id?: int, unit_id?: int, document?: array} $context
     * @param array<string, string> $variablesOverrides clés type document.reference_number, user.first_name, etc.
     */
    public function renderBody(string $bodyTemplate, array $context, array $variablesOverrides = []): string
    {
        $resolved = $this->variableService->buildContext($context);
        foreach ($variablesOverrides as $k => $v) {
            $resolved[$k] = $v;
        }
        $out = $bodyTemplate;
        foreach ($resolved as $code => $value) {
            $out = str_replace('{{' . $code . '}}', (string) $value, $out);
        }
        return $out;
    }

    /**
     * Détecte les placeholders restants (non résolus) dans le texte.
     * @return list<string>
     */
    public function findUnresolvedPlaceholders(string $text): array
    {
        if (preg_match_all('/\{\{([a-zA-Z0-9_.]+)\}\}/', $text, $m)) {
            return array_unique($m[1]);
        }
        return [];
    }
}
