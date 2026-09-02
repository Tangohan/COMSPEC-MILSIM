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
        $list = $this->loadRows();
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

    /** @return list<array<string, mixed>> */
    private function loadRows(): array
    {
        $path = base_path(self::JSON_PATH);
        if (is_file($path)) {
            $raw = json_decode((string) file_get_contents($path), true);
            $list = is_array($raw) ? ($raw['snippets'] ?? $raw) : [];
            if (is_array($list) && $list !== []) {
                return $list;
            }
        }

        return self::defaultSnippets();
    }

    /** @return list<array{code: string, label: string, phase: string, body: string}> */
    private static function defaultSnippets(): array
    {
        return [
            [
                'code' => 'intro_objet',
                'label' => 'Objet de la directive',
                'phase' => 'intro',
                'body' => 'La présente directive fixe les règles d’emploi applicables à l’ensemble du personnel concerné.',
            ],
            [
                'code' => 'intro_champ',
                'label' => 'Champ d’application',
                'phase' => 'intro',
                'body' => 'Elle s’applique à tous les membres de la communauté, dès sa prise en compte.',
            ],
            [
                'code' => 'intro_refs',
                'label' => 'Références',
                'phase' => 'intro',
                'body' => 'Cette directive s’inscrit dans le référentiel doctrinal en vigueur de la communauté.',
            ],
            [
                'code' => 'trans_demande',
                'label' => 'Il est demandé',
                'phase' => 'transition',
                'body' => 'Il est demandé à chacun de prendre connaissance de ce document et de l’appliquer sans délai.',
            ],
            [
                'code' => 'trans_suite',
                'label' => 'En conséquence',
                'phase' => 'transition',
                'body' => 'En conséquence, les dispositions ci-dessous se substituent aux consignes antérieures portant sur le même objet.',
            ],
            [
                'code' => 'close_prise',
                'label' => 'Prise en compte',
                'phase' => 'closure',
                'body' => 'Chaque destinataire accuse réception et prend en compte cette version. Toute difficulté d’application est signalée à l’encadrement.',
            ],
            [
                'code' => 'close_diffusion',
                'label' => 'Diffusion',
                'phase' => 'closure',
                'body' => 'Diffusion : interne à la communauté. Reproduction limitée aux besoins du service.',
            ],
        ];
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
