<?php

declare(strict_types=1);

namespace App\Services\Intel;

use App\Repositories\SseCaseRepository;
use App\Repositories\SseEntityLinkRepository;
use App\Repositories\SseFieldNoteRepository;
use App\Repositories\SseIntelCycleRepository;
use App\Repositories\SseInterestCaseRepository;
use App\Support\SseCerbere;

/**
 * « Convertir » une remontée : en fiche de renseignement (depuis un compte rendu), en dossier, en dossier d'intérêt,
 * en demande de collecte. La remontée d'origine n'est jamais modifiée (sauf le rattachement d'une fiche à son dossier,
 * comme le fait déjà le bureau SSE). Chaque conversion garde sa provenance :
 *   - un événement « convert » dans le fil de la remontée (cible, référence, auteur) : c'est lui qui interdit le doublon ;
 *   - un lien « issu de » dans sse_entity_links quand les deux bouts y ont un type (fiche → dossier, fiche → dossier
 *     d'intérêt), posé comme lien manuel avec son auteur.
 * Les textes repris sont ceux que l'auteur de la conversion lit : un passage caviardé pour lui reste barré.
 */
class IntelIntakeConversion
{
    /** Cibles possibles, par source. Il n'existe pas de création de compte rendu ni de tâche RH depuis le site. */
    public const TARGETS = [
        'fiche' => ['label' => 'Fiche de renseignement', 'hint' => 'Reprend le compte rendu dans une fiche du bureau SSE.', 'sources' => ['cr']],
        'dossier' => ['label' => 'Dossier', 'hint' => 'Ouvre un dossier SSE à partir de cette remontée.', 'sources' => ['cr', 'fiche']],
        'interet' => ['label' => 'Dossier d’intérêt', 'hint' => 'Signale une personne ou un fait à suivre.', 'sources' => ['cr', 'fiche']],
        'collecte' => ['label' => 'Demande de collecte', 'hint' => 'Demande au terrain de confirmer ou de compléter.', 'sources' => ['cr', 'fiche']],
    ];

    /** @var array<string, \Closure> créateurs remplaçables : fn (int $tenantId, array $data): int */
    private array $creators;

    /**
     * @param array<string, \Closure> $creators
     */
    public function __construct(private ?IntelIntakeService $intake = null, array $creators = [], private ?\Closure $linker = null)
    {
        $this->intake ??= new IntelIntakeService();
        $this->creators = $creators;
    }

    /**
     * Cibles offertes pour cette remontée, avec ce qui existe déjà.
     *
     * @param array<string, mixed> $item
     * @return list<array{kind: string, label: string, hint: string, existing: ?array{ref: string, url: string}}>
     */
    public function options(array $item): array
    {
        $done = $this->done($item);
        $out = [];
        foreach (self::TARGETS as $kind => $t) {
            if (!in_array((string) $item['source'], $t['sources'], true)) {
                continue;
            }
            $out[] = ['kind' => $kind, 'label' => $t['label'], 'hint' => $t['hint'], 'existing' => $done[$kind] ?? null];
        }

        return $out;
    }

    /**
     * Conversions déjà faites, lues dans le fil.
     *
     * @param array<string, mixed> $item
     * @return array<string, array{ref: string, url: string}>
     */
    public function done(array $item): array
    {
        $out = [];
        foreach ((array) ($item['events'] ?? []) as $e) {
            $kind = (string) ($e['meta']['kind'] ?? '');
            if (($e['kind'] ?? '') === 'convert' && isset(self::TARGETS[$kind]) && (int) ($e['meta']['target_id'] ?? 0) > 0) {
                $out[$kind] = ['ref' => (string) ($e['meta']['ref'] ?? ''), 'url' => self::targetPath($kind, (int) $e['meta']['target_id'])];
            }
        }

        return $out;
    }

    public static function targetPath(string $kind, int $id): string
    {
        return match ($kind) {
            'fiche' => 'atak/sse/fiches/' . $id,
            'dossier' => 'atak/sse/dossiers/' . $id,
            'interet' => 'atak/sse/interet/' . $id,
            default => 'atak/sse/collecte',
        };
    }

    /**
     * @param array{id: int, label: string} $actor
     * @return array{ok: bool, existing: bool, error: string, kind: string, id: int, ref: string, url: string}
     */
    public function convert(int $tenantId, string $source, int $id, string $kind, array $actor, string $viewerLevel): array
    {
        $fail = static fn (string $error): array => ['ok' => false, 'existing' => false, 'error' => $error, 'kind' => $kind, 'id' => 0, 'ref' => '', 'url' => ''];
        $item = $this->intake->find($tenantId, $source, $id);
        if ($item === null) {
            return $fail('Remontée introuvable.');
        }
        if (!isset(self::TARGETS[$kind]) || !in_array($source, self::TARGETS[$kind]['sources'], true)) {
            return $fail('Cette conversion n’existe pas pour ce type de remontée.');
        }
        $done = $this->done($item);
        if (isset($done[$kind])) {
            return ['ok' => true, 'existing' => true, 'error' => '', 'kind' => $kind, 'id' => 0, 'ref' => $done[$kind]['ref'], 'url' => $done[$kind]['url']];
        }
        // Ce que l'auteur de la conversion lit, pas davantage.
        $row = IntelRedaction::applyRow($source, $item['row'], $item['redactions'], $viewerLevel, (int) $actor['id']);
        $ref = (string) $item['ref'];
        $title = trim((string) ($source === 'cr' ? ($row['summary'] ?? '') : ($row['title'] ?? '')));
        $title = $title !== '' ? $title : (string) $item['type_label'] . ' ' . $ref;
        $text = $source === 'cr'
            ? trim(implode("\n", array_filter([(string) ($row['details'] ?? ''), (string) ($row['remarks'] ?? '')])))
            : trim((string) ($row['body'] ?? ''));
        $place = (string) ($source === 'cr' ? ($row['location_description'] ?? '') : ($row['place_label'] ?? ''));
        $classification = $source === 'fiche' ? (string) ($row['classification'] ?? 'interne') : 'interne';
        $from = 'Issu de la remontée ' . $ref . ' (' . ($item['author'] !== '' ? $item['author'] : 'terrain') . ', ' . IntelIntakePresenter::when((string) $item['at']) . ').';
        try {
            [$newId, $newRef] = match ($kind) {
                'fiche' => $this->createNote($tenantId, $item, $row, $title, $text, $place),
                'dossier' => $this->createCase($tenantId, $item, $actor, $title, $from, $classification),
                'interet' => $this->createInterest($tenantId, $actor, $title, $text, $from, $classification),
                default => $this->createRequirement($tenantId, $item, $row, $actor, $title, $text, $ref),
            };
        } catch (\Throwable $e) {
            error_log('[remontees] conversion ' . $kind . ' : ' . mb_substr($e->getMessage(), 0, 300));
            $newId = 0;
            $newRef = '';
        }
        if ($newId < 1) {
            return $fail('La conversion n’a pas pu être enregistrée.');
        }
        $this->intake->journal($tenantId, $source, $id, 'convert', $actor, null, [
            'kind' => $kind, 'label' => self::TARGETS[$kind]['label'], 'target_id' => $newId, 'ref' => $newRef,
        ]);
        if ($kind === 'fiche') {
            // La fiche créée porte, dans son propre fil, d'où elle vient.
            $this->intake->journal($tenantId, 'fiche', $newId, 'convert', $actor, null, ['origin' => ['source' => $source, 'id' => $id, 'ref' => $ref]]);
        }
        $linkType = ['dossier' => 'case', 'interet' => 'interest'][$kind] ?? null;
        if ($source === 'fiche' && $linkType !== null) {
            $this->link($tenantId, $linkType, $newId, $id, $from, (int) $actor['id']);
        }

        return ['ok' => true, 'existing' => false, 'error' => '', 'kind' => $kind, 'id' => $newId, 'ref' => $newRef, 'url' => self::targetPath($kind, $newId)];
    }

    /** Lien « issu de » : l'élément créé → la fiche d'origine. Lien manuel, avec son auteur. Ne lève jamais. */
    private function link(int $tenantId, string $type, int $newId, int $noteId, string $reason, int $userId): void
    {
        try {
            if ($this->linker !== null) {
                ($this->linker)($tenantId, $type, $newId, $noteId, $reason, $userId);

                return;
            }
            if (SseEntityLinkRepository::ready()) {
                (new SseEntityLinkRepository())->add($tenantId, $type, (string) $newId, 'note', (string) $noteId, 'created_from', $reason, SseCerbere::RULE_MANUAL, SseCerbere::ACTOR_USER, $userId > 0 ? $userId : null);
            }
        } catch (\Throwable) {
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function create(string $kind, int $tenantId, array $data): int
    {
        if (isset($this->creators[$kind])) {
            return (int) ($this->creators[$kind])($tenantId, $data);
        }

        return match ($kind) {
            'fiche' => (new SseFieldNoteRepository())->create($tenantId, $data),
            'dossier' => (new SseCaseRepository())->create(['tenant_id' => $tenantId] + $data),
            'interet' => (new SseInterestCaseRepository())->create($tenantId, $data),
            default => (int) ((new SseIntelCycleRepository())->createRequirement($tenantId, $data)['id'] ?? 0),
        };
    }

    private function lookup(string $table, int $tenantId, int $id): string
    {
        try {
            return (string) (\App\Core\Database::getInstance()->fetchOne("SELECT reference_code FROM {$table} WHERE id = :id AND tenant_id = :t", ['id' => $id, 't' => $tenantId])['reference_code'] ?? '');
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, mixed> $row
     * @return array{0: int, 1: string}
     */
    private function createNote(int $tenantId, array $item, array $row, string $title, string $text, string $place): array
    {
        $urgency = IntelIntakePresenter::URGENCY_LEVELS[IntelIntakePresenter::urgencyLevel((string) $item['priority'])][0];
        $id = $this->create('fiche', $tenantId, [
            'note_kind' => 'FRO',
            'title' => $title,
            'body' => $text !== '' ? $text : $title,
            'observed_at' => (string) ($row['event_timestamp'] ?? '') ?: (string) $item['at'],
            'place_label' => $place,
            'grid_reference' => (string) ($row['grid_reference'] ?? ''),
            'pos_x' => $row['pos_x'] ?? null,
            'pos_y' => $row['pos_y'] ?? null,
            'urgency' => $urgency,
            'classification' => 'interne',
            'status' => 'transmise',
            'origin' => 'atak',
            'author_label' => (string) ($row['submitter_callsign'] ?? ''),
            'author_user_id' => (int) ($row['submitter_user_id'] ?? 0),
            'author_steam_id' => (string) ($row['submitter_steam_id'] ?? ''),
            'author_unit' => (string) ($row['submitter_unit'] ?? ''),
            'idempotency_key' => 'remontee-cr-' . (int) $item['id'],
        ]);

        return [$id, $id > 0 ? ($this->lookup('sse_field_notes', $tenantId, $id) ?: 'Fiche #' . $id) : ''];
    }

    /**
     * @param array<string, mixed> $item
     * @param array{id: int, label: string} $actor
     * @return array{0: int, 1: string}
     */
    private function createCase(int $tenantId, array $item, array $actor, string $title, string $from, string $classification): array
    {
        $id = $this->create('dossier', $tenantId, [
            'title' => mb_substr($title, 0, 180),
            'summary' => $from,
            'classification' => $classification,
            'status' => 'ouvert',
            'created_by' => (int) $actor['id'],
        ]);
        if ($id > 0 && $item['source'] === 'fiche' && empty($item['row']['case_id'])) {
            try {
                (new SseFieldNoteRepository())->attachToCase($tenantId, (int) $item['id'], $id, ((int) ($item['row']['interest_case_id'] ?? 0)) ?: null);
            } catch (\Throwable) {
            }
        }

        return [$id, $id > 0 ? ($this->lookup('sse_cases', $tenantId, $id) ?: 'Dossier #' . $id) : ''];
    }

    /**
     * @param array{id: int, label: string} $actor
     * @return array{0: int, 1: string}
     */
    private function createInterest(int $tenantId, array $actor, string $title, string $text, string $from, string $classification): array
    {
        $id = $this->create('interet', $tenantId, [
            'temporary_designation' => mb_substr($title, 0, 120),
            'opening_reason' => $from,
            'description' => $text,
            'origin_operator' => (string) $actor['label'],
            'classification' => $classification,
            'created_by' => (int) $actor['id'],
        ]);

        return [$id, $id > 0 ? ($this->lookup('sse_interest_cases', $tenantId, $id) ?: 'Dossier d’intérêt #' . $id) : ''];
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, mixed> $row
     * @param array{id: int, label: string} $actor
     * @return array{0: int, 1: string}
     */
    private function createRequirement(int $tenantId, array $item, array $row, array $actor, string $title, string $text, string $ref): array
    {
        $level = IntelIntakePresenter::urgencyLevel((string) $item['priority']);
        $id = $this->create('collecte', $tenantId, [
            'title' => mb_substr('Recoupement : ' . $title, 0, 200),
            'question' => 'Confirmer ou compléter la remontée ' . $ref . ($text !== '' ? ' : ' . mb_substr($text, 0, 600) : '.'),
            'req_type' => 'PIR',
            'priority' => ['critical' => 'critique', 'high' => 'haute', 'medium' => 'normale', 'low' => 'normale'][$level],
            'case_id' => $item['case_id'] ?? null,
            'author_label' => (string) $actor['label'],
            'created_by' => (int) $actor['id'],
            'pos_x' => $row['pos_x'] ?? null,
            'pos_y' => $row['pos_y'] ?? null,
        ]);

        return [$id, $id > 0 ? 'Demande de collecte n° ' . $id : ''];
    }
}
