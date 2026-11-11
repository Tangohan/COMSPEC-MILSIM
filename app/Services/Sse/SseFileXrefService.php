<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Database;
use App\Repositories\SseFileRecordRepository;
use App\Support\SseFileText;

/**
 * Fichiers SSE — recoupements automatiques : « ce prénom est cité dans 2 comptes rendus », « même téléphone que… ».
 *
 * Index pré-calculé à l'écriture (sse_file_xref : mots, téléphones et numéros de chaque source) : aucune lecture de
 * toutes les tables à chaque recherche. Les sources (fiches de renseignement, SPOTREP / SALUTE / SITREP du jeu, comptes
 * rendus, contrôles d'identité, extractions de téléphones, annotations des fiches) sont indexées au fil de l'eau, par
 * lots bornés, grâce à un curseur par source (sse_file_xref_cursor) ; une source modifiée après coup se réindexe par
 * reindex(). Chaque entrée garde son niveau de diffusion (scope) : un membre ne voit que ce que son habilitation lit.
 *
 * Décisions mémorisées (sse_file_xref_decisions) : « Rattacher à cette fiche » ou « Ce n'est pas lui » (ne revient plus).
 * Surveillances (sse_file_watches) : un nouveau mot indexé qui correspond prévient le membre (cloche) une fois par source.
 */
final class SseFileXrefService
{
    /** Sources indexées : table, colonnes de texte, niveau de diffusion, libellé. */
    public const SOURCES = [
        'field_note' => ['table' => 'sse_field_notes', 'label' => 'Fiche de renseignement'],
        'tactical' => ['table' => 'atak_tactical_reports', 'label' => 'Compte rendu ATAK'],
        'aar' => ['table' => 'aar_reports', 'label' => 'Compte rendu post-opération'],
        'check' => ['table' => 'sse_id_checks', 'label' => 'Contrôle d’identité'],
        'ext_item' => ['table' => 'sse_phone_extraction_items', 'label' => 'Extraction de téléphone'],
        'note' => ['table' => 'sse_file_notes', 'label' => 'Annotation d’une fiche'],
    ];

    /** Classification des comptes rendus ATAK → niveau de diffusion du Bureau SSE. */
    private const TACTICAL_SCOPE = ['UNCLASSIFIED' => 'interne', 'RESTRICTED' => 'encadrement', 'CONFIDENTIAL' => 'confidentiel', 'SECRET' => 'tres_restreint'];

    private const MAX_TOKENS = 400;

    private Database $db;

    /** @var \Closure(int, int, array<string, mixed>): void */
    private \Closure $notify;

    /**
     * @param (\Closure(int, int, array<string, mixed>): void)|null $notify prévenir un membre (communauté, compte, contenu)
     */
    public function __construct(private ?SseFileRecordRepository $records = null, ?\Closure $notify = null)
    {
        $this->records ??= new SseFileRecordRepository();
        $this->db = $this->records->db();
        $this->notify = $notify ?? static function (int $tenantId, int $userId, array $payload): void {
            try {
                (new \App\Repositories\MemberNotificationRepository())->create($tenantId, $userId, 'sse_file_watch', $payload);
            } catch (\Throwable $e) {
                error_log('Fichiers SSE : notification de surveillance non envoyée : ' . $e->getMessage());
            }
        };
    }

    // ------------------------------------------------------------ Indexation

    /**
     * Indexe les sources nouvelles depuis le dernier passage (au plus $budget lignes en tout). Appelé à l'ouverture des
     * fichiers et par la tâche planifiée ; sans effet si tout est à jour.
     *
     * @return int lignes indexées
     */
    public function catchUp(int $tenantId, int $budget = 300): int
    {
        $done = 0;
        foreach (array_keys(self::SOURCES) as $type) {
            if ($done >= $budget) {
                break;
            }
            try {
                $done += $this->catchUpSource($tenantId, $type, $budget - $done);
            } catch (\PDOException $e) {
                // Source absente sur cette installation (table jamais créée) : ignorée.
                if (!str_contains($e->getMessage(), 'no such table') && !str_contains($e->getMessage(), "doesn't exist")) {
                    error_log('Fichiers SSE : indexation ' . $type . ' : ' . $e->getMessage());
                }
            }
        }

        return $done;
    }

    private function catchUpSource(int $tenantId, string $type, int $limit): int
    {
        $cursor = $this->db->fetchOne('SELECT last_id FROM sse_file_xref_cursor WHERE tenant_id = :t AND source_type = :s', ['t' => $tenantId, 's' => $type]);
        $last = (int) ($cursor['last_id'] ?? 0);
        $rows = $this->db->fetchAll(
            'SELECT * FROM ' . self::SOURCES[$type]['table'] . ' WHERE tenant_id = :t AND id > :id ORDER BY id ASC LIMIT ' . max(1, min(500, $limit)),
            ['t' => $tenantId, 'id' => $last]
        );
        foreach ($rows as $row) {
            $doc = self::documentOf($type, $row);
            if ($doc !== null) {
                $this->index($tenantId, $type, (int) $row['id'], $doc['text'], $doc['scope'], $doc['op']);
            }
            $last = (int) $row['id'];
        }
        if ($rows !== []) {
            $now = gmdate('Y-m-d H:i:s');
            if ($cursor === null) {
                $this->db->execute('INSERT INTO sse_file_xref_cursor (tenant_id, source_type, last_id, updated_at) VALUES (:t, :s, :l, :now)', ['t' => $tenantId, 's' => $type, 'l' => $last, 'now' => $now]);
            } else {
                $this->db->execute('UPDATE sse_file_xref_cursor SET last_id = :l, updated_at = :now WHERE tenant_id = :t AND source_type = :s', ['t' => $tenantId, 's' => $type, 'l' => $last, 'now' => $now]);
            }
        }

        return count($rows);
    }

    /**
     * Texte indexable d'une ligne de source, son niveau de diffusion et son opération. null : ligne à ne pas indexer
     * (brouillon, supprimée).
     *
     * @param array<string, mixed> $row
     * @return array{text: string, scope: string, op: ?string, title: string, at: string, identity_id: ?int, extraction_id: ?int}|null
     */
    public static function documentOf(string $type, array $row): ?array
    {
        $s = static fn (string ...$keys): string => trim(implode("\n", array_filter(array_map(static fn (string $k): string => trim((string) ($row[$k] ?? '')), $keys))));
        $at = (string) ($row['created_at'] ?? '');

        return match ($type) {
            'field_note' => in_array((string) ($row['status'] ?? ''), ['brouillon', 'draft', 'supprimee', 'deleted'], true) ? null : [
                'text' => $s('title', 'body', 'place_label', 'grid_reference'),
                'scope' => (string) ($row['classification'] ?? 'interne'),
                'op' => isset($row['op_key']) ? (string) $row['op_key'] : null,
                'title' => trim((string) ($row['reference_code'] ?? '') . ' ' . (string) ($row['title'] ?? '')) ?: 'Fiche de renseignement',
                'at' => $at, 'identity_id' => null, 'extraction_id' => null,
            ],
            'tactical' => [
                'text' => $s('report_number', 'summary', 'details', 'remarks', 'location_description', 'grid_reference'),
                'scope' => self::TACTICAL_SCOPE[strtoupper((string) ($row['classification'] ?? 'UNCLASSIFIED'))] ?? 'interne',
                'op' => null,
                'title' => trim((string) ($row['report_type'] ?? 'Compte rendu') . ' ' . (string) ($row['report_number'] ?? '')),
                'at' => (string) ($row['report_timestamp'] ?? $at), 'identity_id' => null, 'extraction_id' => null,
            ],
            'aar' => [
                'text' => $s('title', 'operation_label', 'summary_text'),
                'scope' => 'interne',
                'op' => null,
                'title' => (string) ($row['title'] ?? 'Compte rendu post-opération'),
                'at' => $at, 'identity_id' => null, 'extraction_id' => null,
            ],
            'check' => [
                'text' => $s('presented_last', 'presented_first', 'doc_number', 'grid'),
                'scope' => 'interne',
                'op' => isset($row['op_key']) ? (string) $row['op_key'] : null,
                'title' => 'Contrôle CTRL-' . (int) ($row['id'] ?? 0) . (($row['controller_callsign'] ?? '') !== '' ? ' par ' . $row['controller_callsign'] : ''),
                'at' => $at,
                'identity_id' => ($row['identity_id'] ?? null) !== null ? (int) $row['identity_id'] : null, 'extraction_id' => null,
            ],
            'ext_item' => [
                'text' => $s('title', 'body', 'number', 'grid'),
                'scope' => 'interne',
                'op' => null,
                'title' => 'Extraction EXT-' . str_pad((string) (int) ($row['extraction_id'] ?? 0), 6, '0', STR_PAD_LEFT),
                'at' => (string) ($row['at'] ?? ''), 'identity_id' => null,
                'extraction_id' => (int) ($row['extraction_id'] ?? 0),
            ],
            'note' => ($row['deleted_at'] ?? null) !== null ? null : [
                'text' => $s('body'),
                'scope' => 'interne',
                'op' => null,
                'title' => 'Annotation' . (($row['author_label'] ?? '') !== '' ? ' de ' . $row['author_label'] : ''),
                'at' => $at,
                'identity_id' => (int) ($row['identity_id'] ?? 0), 'extraction_id' => null,
            ],
            default => null,
        };
    }

    /** (Ré)indexe une source et prévient les membres qui surveillent un de ses mots. */
    public function index(int $tenantId, string $type, int $sourceId, string $text, string $scope = 'interne', ?string $op = null): void
    {
        if (!isset(self::SOURCES[$type]) || $sourceId < 1) {
            return;
        }
        $this->db->execute('DELETE FROM sse_file_xref WHERE tenant_id = :t AND source_type = :s AND source_id = :i', ['t' => $tenantId, 's' => $type, 'i' => $sourceId]);
        $tokens = SseFileText::tokens($text, self::MAX_TOKENS);
        if ($tokens === []) {
            return;
        }
        $now = gmdate('Y-m-d H:i:s');
        foreach (array_chunk($tokens, 80) as $chunk) {
            $values = [];
            $params = ['t' => $tenantId, 's' => $type, 'i' => $sourceId, 'sc' => mb_substr($scope, 0, 24), 'op' => $op !== null ? mb_substr($op, 0, 64) : null, 'now' => $now];
            foreach ($chunk as $n => $tok) {
                $values[] = "(:t, :s, :i, :k{$n}, :v{$n}, :p{$n}, :sc, :op, :now)";
                $params['k' . $n] = $tok['kind'];
                $params['v' . $n] = $tok['token'];
                $params['p' . $n] = $tok['kind'] === 'word' ? substr(SseFileText::phonetic($tok['token']), 0, 64) : null;
            }
            $this->db->execute(
                'INSERT INTO sse_file_xref (tenant_id, source_type, source_id, token_kind, token, phonetic, scope, op_key, created_at) VALUES ' . implode(', ', $values),
                $params
            );
        }
        $this->fireWatches($tenantId, $type, $sourceId, array_column($tokens, 'token'), $text);
    }

    public function reindex(int $tenantId, string $type, int $sourceId): void
    {
        if (!isset(self::SOURCES[$type])) {
            return;
        }
        $row = $this->db->fetchOne('SELECT * FROM ' . self::SOURCES[$type]['table'] . ' WHERE id = :id AND tenant_id = :t', ['id' => $sourceId, 't' => $tenantId]);
        $doc = $row !== null ? self::documentOf($type, $row) : null;
        if ($doc === null) {
            $this->db->execute('DELETE FROM sse_file_xref WHERE tenant_id = :t AND source_type = :s AND source_id = :i', ['t' => $tenantId, 's' => $type, 'i' => $sourceId]);

            return;
        }
        $this->index($tenantId, $type, $sourceId, $doc['text'], $doc['scope'], $doc['op']);
    }

    // ------------------------------------------------------------ Lecture

    /**
     * Recoupements d'un sujet. $terms vient de la vue opérateur de la fiche (jamais une donnée cachée) :
     *   last, first : noms ; aliases : alias (un ou plusieurs mots) ; phones : téléphones ; numbers : papiers et plaques.
     * $canSee décide d'un niveau de diffusion (habilitation du membre).
     *
     * @param array{last?: string, first?: string, aliases?: list<string>, phones?: list<string>, numbers?: list<string>} $terms
     * @param \Closure(string): bool $canSee
     * @param array{skip_identity?: int, skip_extractions?: list<int>} $exclude sources propres au sujet (déjà dans sa fiche)
     * @return array{items: list<array<string, mixed>>, attached: list<array<string, mixed>>}
     */
    public function findFor(int $tenantId, int $identityId, array $terms, \Closure $canSee, array $exclude = []): array
    {
        $want = [];
        $last = SseFileText::norm((string) ($terms['last'] ?? ''));
        $first = SseFileText::norm((string) ($terms['first'] ?? ''));
        foreach (explode(' ', $last) as $w) {
            if (strlen($w) >= 3 && !SseFileText::isStopWord($w)) {
                $want[$w] = 'last';
            }
        }
        foreach (explode(' ', $first) as $w) {
            if (strlen($w) >= 3 && !SseFileText::isStopWord($w)) {
                $want[$w] ??= 'first';
            }
        }
        $aliasWords = [];
        foreach ($terms['aliases'] ?? [] as $alias) {
            $words = array_values(array_filter(explode(' ', SseFileText::norm($alias)), static fn (string $w): bool => strlen($w) >= 3 && !SseFileText::isStopWord($w)));
            if ($words !== []) {
                $aliasWords[$alias] = $words;
                foreach ($words as $w) {
                    $want[$w] ??= 'alias';
                }
            }
        }
        foreach ($terms['phones'] ?? [] as $ph) {
            $k = SseFileText::phoneKey($ph);
            if ($k !== '') {
                $want[$k] = 'phone';
            }
        }
        foreach ($terms['numbers'] ?? [] as $num) {
            $k = SseFileText::compact($num);
            if (strlen($k) >= 5) {
                $want[$k] = 'number';
            }
        }
        if ($want === []) {
            return ['items' => [], 'attached' => []];
        }
        $tokens = array_slice(array_keys($want), 0, 60);
        [$in, $params] = SseFileRecordRepository::in('k', array_map('strval', $tokens));
        $params['t'] = $tenantId;
        $rows = $this->db->fetchAll(
            "SELECT source_type, source_id, token, scope FROM sse_file_xref WHERE tenant_id = :t AND token IN ({$in}) LIMIT 3000",
            $params
        );
        // Orthographe approchante du nom (« Hadad » écrit dans un compte rendu pour HADDAD) : confiance « possible ».
        $phonLast = $last !== '' ? substr(SseFileText::phonetic(explode(' ', $last)[0]), 0, 64) : '';
        if (strlen($phonLast) >= 3) {
            foreach ($this->db->fetchAll(
                "SELECT source_type, source_id, token, scope FROM sse_file_xref WHERE tenant_id = :t AND phonetic = :p AND token_kind = 'word' LIMIT 500",
                ['t' => $tenantId, 'p' => $phonLast]
            ) as $r) {
                if (!isset($want[(string) $r['token']])) {
                    $r['token'] = '~' . $r['token'];
                    $rows[] = $r;
                }
            }
        }

        /** @var array<string, array{type: string, id: int, scope: string, tokens: array<string, true>}> $bySource */
        $bySource = [];
        foreach ($rows as $r) {
            $key = $r['source_type'] . ':' . $r['source_id'];
            $bySource[$key] ??= ['type' => (string) $r['source_type'], 'id' => (int) $r['source_id'], 'scope' => (string) ($r['scope'] ?? 'interne'), 'tokens' => []];
            $bySource[$key]['tokens'][(string) $r['token']] = true;
        }
        $decisions = [];
        foreach ($this->db->fetchAll('SELECT source_type, source_id, decision FROM sse_file_xref_decisions WHERE tenant_id = :t AND identity_id = :i', ['t' => $tenantId, 'i' => $identityId]) as $d) {
            $decisions[$d['source_type'] . ':' . $d['source_id']] = (string) $d['decision'];
        }

        $items = [];
        $attached = [];
        foreach ($bySource as $key => $src) {
            if (($decisions[$key] ?? '') === 'reject' || !$canSee($src['scope'])) {
                continue;
            }
            [$confidence, $reason, $needle] = self::grade($src['tokens'], $want, $aliasWords, $first, $last);
            if ($confidence === null) {
                continue;
            }
            $item = ['key' => $key, 'type' => $src['type'], 'id' => $src['id'], 'confidence' => $confidence, 'reason' => $reason, 'needle' => $needle];
            if (($decisions[$key] ?? '') === 'attach') {
                $attached[] = $item;
            } else {
                $items[] = $item;
            }
        }
        $rank = ['exact' => 0, 'probable' => 1, 'possible' => 2];
        usort($items, static fn (array $a, array $b): int => [$rank[$a['confidence']], -$a['id']] <=> [$rank[$b['confidence']], -$b['id']]);

        return [
            'items' => $this->resolve($tenantId, array_slice($items, 0, 40), $identityId, $exclude),
            'attached' => $this->resolve($tenantId, array_slice($attached, 0, 40), $identityId, []),
        ];
    }

    /**
     * Confiance d'une source : téléphone ou numéro → exact ; nom et prénom → exact ; alias complet ou nom seul → probable ;
     * prénom seul ou orthographe approchante → possible.
     *
     * @param array<string, true> $found
     * @param array<string, string> $want
     * @param array<string, list<string>> $aliasWords
     * @return array{0: ?string, 1: string, 2: string}
     */
    public static function grade(array $found, array $want, array $aliasWords, string $first, string $last): array
    {
        foreach ($found as $tok => $_) {
            $kind = $want[$tok] ?? null;
            if ($kind === 'phone') {
                return ['exact', 'Même numéro de téléphone', $tok];
            }
            if ($kind === 'number') {
                return ['exact', 'Même numéro (papier ou plaque)', $tok];
            }
        }
        $lastWords = array_values(array_filter(explode(' ', $last), static fn (string $w): bool => strlen($w) >= 3));
        $firstWords = array_values(array_filter(explode(' ', $first), static fn (string $w): bool => strlen($w) >= 3));
        $hasLast = $lastWords !== [] && array_diff($lastWords, array_keys($found)) === [];
        $hasFirst = $firstWords !== [] && array_diff($firstWords, array_keys($found)) === [];
        if ($hasLast && $hasFirst) {
            return ['exact', 'Nom et prénom cités', $lastWords[0]];
        }
        foreach ($aliasWords as $alias => $words) {
            if (array_diff($words, array_keys($found)) === []) {
                return [count($words) > 1 || strlen($words[0]) >= 5 ? 'probable' : 'possible', 'Alias « ' . $alias . ' » cité', $words[0]];
            }
        }
        if ($hasLast) {
            return ['probable', 'Nom « ' . mb_strtoupper($last) . ' » cité', $lastWords[0]];
        }
        if ($hasFirst) {
            return ['possible', 'Prénom « ' . ucfirst($first) . ' » cité', $firstWords[0]];
        }
        foreach ($found as $tok => $_) {
            if (str_starts_with($tok, '~')) {
                return ['possible', 'Nom écrit « ' . substr($tok, 1) . ' » (orthographe approchante)', substr($tok, 1)];
            }
        }

        return [null, '', ''];
    }

    /**
     * Titre, date, extrait surligné et lien de chaque source retenue ; sources propres au sujet écartées.
     *
     * @param list<array<string, mixed>> $items
     * @param array{skip_identity?: int, skip_extractions?: list<int>} $exclude
     * @return list<array<string, mixed>>
     */
    private function resolve(int $tenantId, array $items, int $identityId, array $exclude): array
    {
        $out = [];
        foreach ($items as $it) {
            try {
                $row = $this->db->fetchOne('SELECT * FROM ' . self::SOURCES[$it['type']]['table'] . ' WHERE id = :id AND tenant_id = :t', ['id' => $it['id'], 't' => $tenantId]);
            } catch (\PDOException) {
                $row = null;
            }
            $doc = $row !== null ? self::documentOf($it['type'], $row) : null;
            if ($doc === null) {
                continue;
            }
            if (isset($exclude['skip_identity']) && $doc['identity_id'] === $exclude['skip_identity']) {
                continue;
            }
            if ($doc['extraction_id'] !== null && in_array($doc['extraction_id'], $exclude['skip_extractions'] ?? [], true)) {
                continue;
            }
            $out[] = $it + [
                'label' => self::SOURCES[$it['type']]['label'],
                'title' => $doc['title'],
                'at' => $doc['at'],
                'snippet' => SseFileText::snippet($doc['text'], (string) $it['needle']),
                'path' => 'atak/sse/fichiers/source/' . $it['type'] . '/' . $it['id'] . '?sujet=' . $identityId,
            ];
        }

        return $out;
    }

    /** Source lisible en entier (page « source » des fichiers) ; null si absente ou hors habilitation. */
    public function source(int $tenantId, string $type, int $id, \Closure $canSee): ?array
    {
        if (!isset(self::SOURCES[$type])) {
            return null;
        }
        try {
            $row = $this->db->fetchOne('SELECT * FROM ' . self::SOURCES[$type]['table'] . ' WHERE id = :id AND tenant_id = :t', ['id' => $id, 't' => $tenantId]);
        } catch (\PDOException) {
            return null;
        }
        $doc = $row !== null ? self::documentOf($type, $row) : null;
        if ($doc === null || !$canSee($doc['scope'])) {
            return null;
        }

        return $doc + ['type' => $type, 'id' => $id, 'label' => self::SOURCES[$type]['label']];
    }

    public function decide(int $tenantId, int $identityId, string $type, int $sourceId, string $decision, ?int $userId): bool
    {
        if (!isset(self::SOURCES[$type]) || !in_array($decision, ['attach', 'reject', 'undo'], true)) {
            return false;
        }
        $this->db->execute(
            'DELETE FROM sse_file_xref_decisions WHERE tenant_id = :t AND identity_id = :i AND source_type = :s AND source_id = :id',
            ['t' => $tenantId, 'i' => $identityId, 's' => $type, 'id' => $sourceId]
        );
        if ($decision === 'undo') {
            return true;
        }
        $this->db->insert(
            'INSERT INTO sse_file_xref_decisions (tenant_id, identity_id, source_type, source_id, decision, user_id, created_at) VALUES (:t, :i, :s, :id, :d, :u, :now)',
            ['t' => $tenantId, 'i' => $identityId, 's' => $type, 'id' => $sourceId, 'd' => $decision, 'u' => $userId, 'now' => gmdate('Y-m-d H:i:s')]
        );

        return true;
    }

    // -------------------------------------------------------- Surveillances

    /**
     * Surveiller un nom, un prénom, un numéro ou un sujet (ses noms, téléphones et numéros connus).
     *
     * @param list<string> $values
     */
    public function watch(int $tenantId, int $userId, string $kind, array $values, ?int $identityId = null): int
    {
        $kind = in_array($kind, ['subject', 'name', 'number'], true) ? $kind : 'name';
        $added = 0;
        foreach (array_slice($values, 0, 12) as $value) {
            $value = trim($value);
            $token = match (true) {
                SseFileText::phoneKey($value) !== '' && preg_match('/^\+?[\d\s().\-\/]+$/', $value) === 1 => SseFileText::phoneKey($value),
                preg_match('/\d/', $value) === 1 => SseFileText::compact($value),
                default => explode(' ', SseFileText::norm($value))[0] ?? '',
            };
            if (strlen($token) < 3 || SseFileText::isStopWord($token)) {
                continue;
            }
            $exists = $this->db->fetchOne(
                'SELECT id FROM sse_file_watches WHERE tenant_id = :t AND user_id = :u AND token = :k AND COALESCE(identity_id, 0) = :i',
                ['t' => $tenantId, 'u' => $userId, 'k' => $token, 'i' => (int) $identityId]
            );
            if ($exists !== null) {
                continue;
            }
            $this->db->insert(
                'INSERT INTO sse_file_watches (tenant_id, user_id, kind, value, token, identity_id, created_at) VALUES (:t, :u, :kind, :v, :k, :i, :now)',
                ['t' => $tenantId, 'u' => $userId, 'kind' => $kind, 'v' => mb_substr($value, 0, 120), 'k' => substr($token, 0, 64), 'i' => $identityId, 'now' => gmdate('Y-m-d H:i:s')]
            );
            $added++;
        }

        return $added;
    }

    public function unwatch(int $tenantId, int $userId, int $watchId): bool
    {
        return $this->db->execute('DELETE FROM sse_file_watches WHERE id = :id AND tenant_id = :t AND user_id = :u', ['id' => $watchId, 't' => $tenantId, 'u' => $userId]) > 0;
    }

    public function unwatchSubject(int $tenantId, int $userId, int $identityId): int
    {
        return $this->db->execute('DELETE FROM sse_file_watches WHERE tenant_id = :t AND user_id = :u AND identity_id = :i', ['t' => $tenantId, 'u' => $userId, 'i' => $identityId]);
    }

    /** @return list<array<string, mixed>> */
    public function watches(int $tenantId, int $userId): array
    {
        return $this->db->fetchAll('SELECT * FROM sse_file_watches WHERE tenant_id = :t AND user_id = :u ORDER BY id DESC LIMIT 100', ['t' => $tenantId, 'u' => $userId]);
    }

    public function watchesSubject(int $tenantId, int $userId, int $identityId): bool
    {
        return $this->db->fetchOne('SELECT id FROM sse_file_watches WHERE tenant_id = :t AND user_id = :u AND identity_id = :i', ['t' => $tenantId, 'u' => $userId, 'i' => $identityId]) !== null;
    }

    /** @param list<string> $tokens */
    private function fireWatches(int $tenantId, string $type, int $sourceId, array $tokens, string $text): void
    {
        if ($tokens === []) {
            return;
        }
        [$in, $params] = SseFileRecordRepository::in('w', array_slice($tokens, 0, self::MAX_TOKENS));
        $params['t'] = $tenantId;
        $watches = $this->db->fetchAll("SELECT * FROM sse_file_watches WHERE tenant_id = :t AND token IN ({$in}) LIMIT 200", $params);
        $notified = [];
        foreach ($watches as $w) {
            $uid = (int) $w['user_id'];
            if (isset($notified[$uid])) {
                continue;
            }
            $notified[$uid] = true;
            $this->db->execute('UPDATE sse_file_watches SET hits = hits + 1, last_hit_at = :now WHERE id = :id AND tenant_id = :t', ['now' => gmdate('Y-m-d H:i:s'), 'id' => (int) $w['id'], 't' => $tenantId]);
            $snippet = str_replace(["\x01", "\x02"], ['« ', ' »'], SseFileText::snippet($text, (string) $w['token'], 60));
            try {
                (new \App\Repositories\SseFileRepository($this->db))->logQuery($tenantId, [
                    'query_type' => 'QW',
                    'criteria' => ['watch' => (string) $w['value'], 'source' => $type . ':' . $sourceId],
                    'source' => 'watch',
                    'operator_user_id' => $uid,
                    'operator_label' => 'Surveillance',
                    'hit' => true,
                    'verdict' => 'signale',
                    'codes' => ['watch:' . $type . ':' . $sourceId],
                ]);
            } catch (\Throwable $e) {
                error_log('Fichiers SSE : surveillance non journalisée : ' . $e->getMessage());
            }
            ($this->notify)($tenantId, $uid, [
                'label' => (string) $w['value'],
                'detail' => self::SOURCES[$type]['label'] . ' : ' . $snippet,
                'path' => (int) ($w['identity_id'] ?? 0) > 0 ? 'atak/sse/fichiers/sujet/' . (int) $w['identity_id'] : 'atak/sse/fichiers/source/' . $type . '/' . $sourceId,
                'source_type' => $type,
                'source_id' => $sourceId,
            ]);
        }
    }
}
