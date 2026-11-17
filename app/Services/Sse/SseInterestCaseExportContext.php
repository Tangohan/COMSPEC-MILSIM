<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseFieldNoteRepository;
use App\Services\Intel\IntelIntakeService;
use App\Services\Sse\Links\SseLinkGraph;
use App\Services\Sse\Links\SseLinkViewer;

/** Le graphe filtré et les fiches accessibles sont figés pour l'export, sans déduire d'identité. */
final class SseInterestCaseExportContext
{
    public function enrich(array $detail, SseInterestActor $actor, SseLinkViewer $viewer): array
    {
        if ($viewer->tenantId() !== $actor->tenantId) {
            throw new \InvalidArgumentException('Communauté incohérente');
        }
        $graph = (new SseLinkGraph($viewer))->resolve('interest', (string) $detail['case']['id'], 6);
        $notes = [];
        $persons = [];
        $drafts = [];
        $media = [];
        $seekRecords = [];
        $sheetAccess = SseSheetAccess::fromSession();
        $sheetRepo = new \App\Repositories\SseSheetRepository();
        foreach ((array) ($graph['nodes'] ?? []) as $node) {
            if (!empty($node['restricted'])) { continue; }
            if ($node['type'] === 'person') {
                $person = (new SsePersonWorkService())->detail(SsePersonViewer::fromSession(), (int) $node['id']);
                if ($person === null) { continue; }
                $persons[] = $person['person'] + ['export_samples' => $person['samples']];
                foreach ((array) ($person['person']['photos'] ?? []) as $photo) {
                    $path = (string) ($photo['image_path'] ?? '');
                    if ($path !== '') { $media[$path] = ['path' => $path, 'caption' => $photo['caption'] ?? $photo['angle_label'] ?? 'Photo', 'subject' => $node['title'], 'subject_key' => $node['key']]; }
                }
            }
            if ($node['type'] === 'identity' && $viewer->canRead('fichiers')) {
                foreach ((new \App\Repositories\SseFileRepository())->seekFor($actor->tenantId, ['identity_ids' => [(int) $node['id']], 'limit' => 100]) as $record) {
                    $seekRecords[(int) $record['id']] = $record + ['subject_key' => $node['key']];
                }
                foreach ((new SseSeekPhotoService())->forIdentity($actor->tenantId, (int) $node['id']) as $photo) {
                    $path = (string) ($photo['path'] ?? '');
                    if ($path !== '') { $media[$path] = ['path' => $path, 'caption' => $photo['caption'] ?? 'Photo SEEK II', 'subject' => $node['title'], 'subject_key' => $node['key']]; }
                }
            }
            if ($sheetAccess->canDraft() && \App\Repositories\SseSheetRepository::ready() && in_array($node['type'], ['person', 'identity'], true)) {
                $subject = (new SseSheetFactsService())->subject($actor->tenantId, $node['type'], (int) $node['id'], $sheetAccess);
                if ($subject !== null) {
                    $identityIds = array_map(static fn (array $i): int => (int) $i['id'], (array) ($subject['identities'] ?? []));
                    foreach ($sheetRepo->listDrafts($actor->tenantId, $subject['person_id'], $identityIds) as $draft) {
                        $classification = (string) ($draft['params']['classification'] ?? 'interne');
                        if (SseRedactionService::levelRank($classification) > $actor->maxRank()) { continue; }
                        $drafts[(int) $draft['id']] = $draft + ['subject_label' => $node['title'], 'subject_key' => $node['key']];
                    }
                }
            }
            if ($node['type'] === 'note') {
                $note = (new SseFieldNoteRepository())->findForTenant($actor->tenantId, (int) $node['id']);
                if ($note !== null) { $notes[] = $note; }
            }
        }
        $detail['field_notes'] = (new IntelIntakeService())->presentNotes($actor->tenantId, $notes, $actor->maxLevel, $actor->userId ?? 0);
        $detail['persons'] = $persons;
        $detail['drafts'] = array_values($drafts);
        $noteIds = array_fill_keys(array_column($detail['field_notes'], 'id'), true);
        $personIds = array_fill_keys(array_column($persons, 'id'), true);
        $graph['nodes'] = array_filter((array) ($graph['nodes'] ?? []), static fn (array $n): bool =>
            empty($n['restricted'])
            && ($n['type'] !== 'note' || isset($noteIds[$n['id']]))
            && ($n['type'] !== 'person' || isset($personIds[$n['id']]))
        );
        $reachable = [(string) ($graph['root'] ?? '') => true];
        do {
            $previous = count($reachable);
            foreach ((array) ($graph['edges'] ?? []) as $edge) {
                if (!isset($graph['nodes'][$edge['from']], $graph['nodes'][$edge['to']])) { continue; }
                if (isset($reachable[$edge['from']]) || isset($reachable[$edge['to']])) {
                    $reachable[$edge['from']] = $reachable[$edge['to']] = true;
                }
            }
        } while (count($reachable) > $previous);
        $graph['nodes'] = array_intersect_key($graph['nodes'], $reachable);
        $graph['edges'] = array_filter((array) ($graph['edges'] ?? []), static fn (array $e): bool => isset($reachable[$e['from']], $reachable[$e['to']]));
        $detail['graph'] = $graph;
        $detail['field_notes'] = array_values(array_filter($detail['field_notes'], static fn (array $n): bool => isset($reachable['note:' . $n['id']])));
        $detail['persons'] = array_values(array_filter($detail['persons'], static fn (array $p): bool => isset($reachable['person:' . $p['id']])));
        $detail['media'] = array_values(array_filter($media, static fn (array $m): bool => isset($reachable[$m['subject_key']])));
        $detail['seek_records'] = array_values(array_filter($seekRecords, static fn (array $r): bool => isset($reachable[$r['subject_key']])));
        $detail['drafts'] = array_values(array_filter($drafts, static fn (array $d): bool => isset($reachable[$d['subject_key']])));
        foreach ($detail['field_notes'] as $note) {
            $graph['nodes']['note:' . $note['id']]['title'] = (string) ($note['title'] ?? 'Fiche de renseignement');
        }
        foreach ($detail['persons'] as $person) {
            $graph['nodes']['person:' . $person['id']]['title'] = (string) ($person['display_name'] ?? 'Personne');
        }
        $detail['graph'] = $graph;
        $level = (string) ($detail['case']['classification'] ?? 'interne');
        foreach (array_merge(array_values((array) ($graph['nodes'] ?? [])), $notes, $persons, array_values($drafts)) as $row) {
            $candidate = (string) ($row['classification'] ?? $row['params']['classification'] ?? 'interne');
            if (SseRedactionService::levelRank($candidate) > SseRedactionService::levelRank($level)) {
                $level = $candidate;
            }
        }
        $detail['export_classification'] = $level;

        return $detail;
    }
}
