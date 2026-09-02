<?php

declare(strict_types=1);

namespace App\Services\Courrier;

/**
 * Snippets réglementaires (fichier JSON versionné ; extensible vers table SQL).
 */
final class CourrierSnippetService
{
    private const JSON_PATH = 'ressources/courrier/snippets.json';

    /** @return list<array{code: string, label: string, phase: string, body: string}> */
    public function listForEditor(?string $phase = null): array
    {
        $path = base_path(self::JSON_PATH);
        if (!is_file($path)) {
            return [];
        }
        $raw = json_decode((string) file_get_contents($path), true);
        $list = $raw['snippets'] ?? [];
        if (!is_array($list)) {
            return [];
        }
        $out = [];
        foreach ($list as $row) {
            if (!is_array($row) || empty($row['code'])) {
                continue;
            }
            $p = (string) ($row['phase'] ?? 'intro');
            if ($phase !== null && $phase !== '' && $p !== $phase) {
                continue;
            }
            $out[] = [
                'code' => (string) $row['code'],
                'label' => (string) ($row['label'] ?? $row['code']),
                'phase' => $p,
                'body' => (string) ($row['body'] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * Rend un snippet avec le contexte courant (utilisateur, document).
     * @param array{user_id?: int, tenant_id?: int, document?: array} $context
     */
    public function renderSnippetBody(string $code, array $context, TemplateRenderService $renderService): string
    {
        foreach ($this->listForEditor() as $s) {
            if ($s['code'] === $code) {
                $doc = $context['document'] ?? [];
                $overrides = [
                    'subject' => $doc['subject'] ?? '',
                    'destination_label' => $doc['destination_label'] ?? '',
                    'issuer_label' => $doc['issuer_label'] ?? '',
                ];
                return $renderService->renderBody($s['body'], $context, $overrides);
            }
        }
        return '';
    }
}
