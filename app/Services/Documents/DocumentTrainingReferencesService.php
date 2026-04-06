<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Repositories\TrainingRepository;
use App\Repositories\TrainingResourceRepository;

final class DocumentTrainingReferencesService
{
    public function __construct(
        private TrainingResourceRepository $trainingResourceRepository,
        private TrainingRepository $trainingRepository,
    ) {}

    /**
     * @param list<array<string, mixed>> $documents Lignes document (id, formation_id optionnel).
     * @return array<int, list<array{label: string, href: string}>>
     */
    public function mapByDocumentId(int $tenantId, array $documents): array
    {
        $ids = [];
        foreach ($documents as $d) {
            $id = (int) ($d['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        if ($ids === []) {
            return [];
        }
        $byDoc = [];
        foreach ($ids as $id) {
            $byDoc[$id] = [];
        }

        /** @var array<int, array<string, true>> */
        $seenHref = [];

        $addRef = function (int $docId, string $label, string $href) use (&$byDoc, &$seenHref): void {
            if (!isset($byDoc[$docId])) {
                return;
            }
            if ($href === '') {
                return;
            }
            if (isset($seenHref[$docId][$href])) {
                return;
            }
            $seenHref[$docId][$href] = true;
            $byDoc[$docId][] = ['label' => $label !== '' ? $label : 'Formation', 'href' => $href];
        };

        foreach ($this->trainingResourceRepository->listPublishedLmsCourseRefsForDocumentIds($tenantId, $ids) as $row) {
            $docId = (int) ($row['document_id'] ?? 0);
            $slug = trim((string) ($row['course_slug'] ?? ''));
            if ($docId <= 0 || $slug === '') {
                continue;
            }
            $title = trim((string) ($row['course_title'] ?? ''));
            $addRef($docId, $title !== '' ? $title : 'Parcours de formation', url('formations/' . rawurlencode($slug)));
        }

        foreach ($this->trainingRepository->listDocumentLinkedLegacyModules($tenantId, $ids) as $row) {
            $docId = (int) ($row['document_id'] ?? 0);
            $slug = trim((string) ($row['slug'] ?? ''));
            if ($docId <= 0 || $slug === '') {
                continue;
            }
            $title = trim((string) ($row['title'] ?? ''));
            $addRef($docId, $title !== '' ? $title : 'Module de formation', url('formations/' . rawurlencode($slug)));
        }

        $formationModuleIds = [];
        foreach ($documents as $d) {
            $fid = isset($d['formation_id']) ? (int) $d['formation_id'] : 0;
            if ($fid > 0) {
                $formationModuleIds[$fid] = true;
            }
        }
        if ($formationModuleIds !== []) {
            $modMap = $this->trainingRepository->batchPublishedModulesById($tenantId, array_keys($formationModuleIds));
            foreach ($documents as $d) {
                $docId = (int) ($d['id'] ?? 0);
                $fid = isset($d['formation_id']) ? (int) $d['formation_id'] : 0;
                if ($docId <= 0 || $fid <= 0 || !isset($modMap[$fid])) {
                    continue;
                }
                $m = $modMap[$fid];
                $slug = trim((string) ($m['slug'] ?? ''));
                if ($slug === '') {
                    continue;
                }
                $title = trim((string) ($m['title'] ?? ''));
                $addRef($docId, $title !== '' ? $title : 'Module de formation', url('formations/' . rawurlencode($slug)));
            }
        }

        return $byDoc;
    }
}
