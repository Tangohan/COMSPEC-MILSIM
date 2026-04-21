<?php

declare(strict_types=1);

namespace App\Services\TrainingPublication;

class DocumentBuildService
{
    public function compile(array $normalizedLms, array $publication, array $annexes = [], array $reusableBlocks = []): array
    {
        $toc = $this->buildToc($normalizedLms['chapters'] ?? [], $annexes);
        $pages = $this->composePages($normalizedLms, $toc, $annexes, $reusableBlocks);

        $payload = [
            'cover' => $this->renderCover($normalizedLms['metadata'] ?? [], $publication),
            'table_of_contents' => $toc,
            'pages' => $pages,
            'annexes' => $annexes,
            'qr' => $this->injectQr($publication),
            'watermark' => $this->applyWatermark($publication),
        ];

        $checksum = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
        $basePath = '/storage/training-publications/' . ($publication['id'] ?? 'draft') . '-' . substr($checksum, 0, 12);

        return [
            'formats' => [
                'pdf_official' => $basePath . '.pdf',
                'web' => $basePath . '.json',
                'mobile' => $basePath . '.mobile.json',
                'print' => $basePath . '.print.pdf',
                'lms_package' => $basePath . '.zip',
            ],
            'checksum' => $checksum,
            'pages' => count($pages),
            'compiled_payload' => $payload,
            'qr_hash' => hash('sha256', json_encode($payload['qr']) ?: ''),
            'watermark_hash' => hash('sha256', json_encode($payload['watermark']) ?: ''),
        ];
    }

    private function renderCover(array $metadata, array $publication): array
    {
        return [
            'title' => $metadata['title'] ?? '',
            'code' => $metadata['code'] ?? '',
            'theme' => $publication['overlay_payload_json'] ?? '{}',
            'institutional_stamp' => $publication['institutional_signature_json'] ?? '{}',
        ];
    }

    private function buildToc(array $chapters, array $annexes): array
    {
        $toc = [];
        $page = 2;
        foreach ($chapters as $i => $chapter) {
            $chapterIndex = $i + 1;
            $toc[] = ['index' => (string) $chapterIndex, 'title' => (string) ($chapter['title'] ?? ''), 'page' => $page, 'type' => 'chapter'];
            $page++;
        }
        foreach ($annexes as $annex) {
            $toc[] = [
                'index' => 'A' . (string) ($annex['id'] ?? ''),
                'title' => (string) ($annex['title'] ?? 'Annexe'),
                'page' => $page,
                'type' => 'annex',
            ];
            $page++;
        }

        return $toc;
    }

    private function composePages(array $normalizedLms, array $toc, array $annexes, array $reusableBlocks): array
    {
        $pages = [];
        $pages[] = ['type' => 'cover'];
        $pages[] = ['type' => 'toc', 'entries' => $toc];

        foreach (($normalizedLms['chapters'] ?? []) as $chapter) {
            $pages[] = [
                'type' => 'chapter',
                'title' => (string) ($chapter['title'] ?? ''),
                'content_blocks' => array_merge([
                    ['component' => 'text_block'],
                    ['component' => 'operational_box'],
                ], $reusableBlocks),
            ];
        }

        foreach ($annexes as $annex) {
            $pages[] = ['type' => 'annex', 'annex' => $annex];
        }

        return $pages;
    }

    private function injectQr(array $publication): array
    {
        return [
            'payload' => [
                'document_id' => (int) ($publication['document_id'] ?? 0),
                'version' => (string) ($publication['version_label'] ?? 'v1'),
                'hash' => (string) ($publication['hash_integrity'] ?? ''),
            ],
        ];
    }

    private function applyWatermark(array $publication): array
    {
        return [
            'text' => (string) ($publication['status'] ?? 'draft'),
            'policy' => $publication['watermark_payload_json'] ?? '{}',
            'classification' => (string) ($publication['diffusion_classification'] ?? 'interne'),
        ];
    }
}
