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
            $resolved[(string) $k] = (string) $v;
        }
        $out = $bodyTemplate;
        // Remplacer d'abord les clés les plus longues pour éviter les collisions partielles.
        $keys = array_keys($resolved);
        usort($keys, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));
        foreach ($keys as $code) {
            $out = str_replace('{{' . $code . '}}', (string) $resolved[$code], $out);
        }

        return $out;
    }

    /**
     * Détecte les placeholders restants (non résolus) dans le texte.
     * @return list<string>
     */
    public function findUnresolvedPlaceholders(string $text): array
    {
        if (preg_match_all('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', $text, $m)) {
            return array_values(array_unique($m[1]));
        }

        return [];
    }
}
