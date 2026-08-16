<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SilentSchemaMigration;
use App\Support\SseTextLibraryCatalog;

/**
 * Bibliothèque rédactionnelle : mentions officielles réutilisables dans les documents SSE.
 *
 * Le catalogue livré n'est qu'une semence : une fois posé pour une unité, il devient
 * modifiable et n'est plus jamais réappliqué. Le texte réellement porté à un dossier
 * est copié à l'insertion, jamais lu depuis le modèle — modifier une mention centrale
 * ne réécrit donc aucun document déjà rédigé.
 */
final class SseTextTemplateRepository
{
    public function __construct(private ?Database $db = null)
    {
        $this->db ??= Database::getInstance();
        SilentSchemaMigration::run(base_path('bootstrap/atak_sse_text_library_migration.php'));
    }

    /** @return array<string,string> */
    public static function categories(): array
    {
        return SseTextLibraryCatalog::CATEGORIES;
    }

    /** @return array<string,string> */
    public static function contexts(): array
    {
        return SseTextLibraryCatalog::CONTEXTS;
    }

    /** @return array<string,string> */
    public static function variables(): array
    {
        return SseTextLibraryCatalog::VARIABLES;
    }

    public static function categoryLabel(string $key): string
    {
        return SseTextLibraryCatalog::CATEGORIES[$key] ?? 'Divers';
    }

    public static function contextLabel(string $key): string
    {
        return SseTextLibraryCatalog::CONTEXTS[$key] ?? 'Dossier';
    }

    /**
     * Pose le catalogue d'origine pour une unité qui n'en a encore aucun.
     * Ne touche jamais aux mentions existantes.
     */
    public function ensureSeed(int $tenantId): void
    {
        if ($tenantId < 1) {
            return;
        }
        static $done = [];
        if (isset($done[$tenantId])) {
            return;
        }
        $done[$tenantId] = true;

        try {
            $count = (int) ($this->db->fetchOne(
                'SELECT COUNT(*) AS n FROM sse_text_templates WHERE tenant_id = :t',
                ['t' => $tenantId]
            )['n'] ?? 0);
            if ($count > 0) {
                $this->ensureMissingEntries($tenantId);

                return;
            }

            $order = [];
            foreach (SseTextLibraryCatalog::entries() as $entry) {
                $cat = $entry['category'];
                $order[$cat] = ($order[$cat] ?? 0) + 10;
                $vars = SseTextLibraryCatalog::variablesUsedIn($entry['content']);
                $this->db->execute(
                    'INSERT IGNORE INTO sse_text_templates
                        (tenant_id, code, category, title, content, classification_min, context,
                         doctrine, fragment_kind, variables, version, is_default, is_active, is_seeded, sort_order)
                     VALUES (:t, :code, :cat, :title, :content, :cmin, :ctx, :doc, :frag, :vars, 1, :def, 1, 1, :ord)',
                    [
                        't' => $tenantId,
                        'code' => $entry['code'],
                        'cat' => $cat,
                        'title' => $entry['title'],
                        'content' => $entry['content'],
                        'cmin' => $entry['classification_min'],
                        'ctx' => $entry['context'],
                        'doc' => $entry['doctrine'] ?? 'neutre',
                        'frag' => $entry['fragment_kind'] ?? 'bloc',
                        'vars' => $vars === [] ? null : implode(',', $vars),
                        'def' => $entry['is_default'] ? 1 : 0,
                        'ord' => $order[$cat],
                    ]
                );
            }
        } catch (\Throwable) {
            // Bibliothèque indisponible : l'éditeur reste utilisable sans mentions.
        }
    }

    /**
     * Ajoute les nouvelles mentions du catalogue sans toucher aux mentions déjà présentes.
     */
    public function ensureMissingEntries(int $tenantId): void
    {
        if ($tenantId < 1) {
            return;
        }
        try {
            $existing = $this->db->fetchAll(
                'SELECT code FROM sse_text_templates WHERE tenant_id = :t',
                ['t' => $tenantId]
            );
            $have = [];
            foreach ($existing as $row) {
                $have[(string) ($row['code'] ?? '')] = true;
            }
            $orderBase = 5000;
            foreach (SseTextLibraryCatalog::entries() as $i => $entry) {
                if (isset($have[$entry['code']])) {
                    continue;
                }
                $vars = SseTextLibraryCatalog::variablesUsedIn($entry['content']);
                $this->db->execute(
                    'INSERT IGNORE INTO sse_text_templates
                        (tenant_id, code, category, title, content, classification_min, context,
                         doctrine, fragment_kind, variables, version, is_default, is_active, is_seeded, sort_order)
                     VALUES (:t, :code, :cat, :title, :content, :cmin, :ctx, :doc, :frag, :vars, 1, :def, 1, 1, :ord)',
                    [
                        't' => $tenantId,
                        'code' => $entry['code'],
                        'cat' => $entry['category'],
                        'title' => $entry['title'],
                        'content' => $entry['content'],
                        'cmin' => $entry['classification_min'],
                        'ctx' => $entry['context'],
                        'doc' => $entry['doctrine'] ?? 'neutre',
                        'frag' => $entry['fragment_kind'] ?? 'bloc',
                        'vars' => $vars === [] ? null : implode(',', $vars),
                        'def' => $entry['is_default'] ? 1 : 0,
                        'ord' => $orderBase + ($i * 10),
                    ]
                );
            }
        } catch (\Throwable) {
            // Colonnes doctrine/fragment absentes tant que la migration n’a pas tourné.
        }
    }

    /**
     * @param array<string,mixed> $filters category|context|q|only_active
     * @return list<array<string,mixed>>
     */
    public function listForTenant(int $tenantId, array $filters = []): array
    {
        $this->ensureSeed($tenantId);

        $where = ['tenant_id = :t'];
        $params = ['t' => $tenantId];

        if (!empty($filters['only_active'])) {
            $where[] = 'is_active = 1';
        }
        $category = trim((string) ($filters['category'] ?? ''));
        if ($category !== '' && isset(SseTextLibraryCatalog::CATEGORIES[$category])) {
            $where[] = 'category = :cat';
            $params['cat'] = $category;
        }
        $context = trim((string) ($filters['context'] ?? ''));
        if ($context !== '' && isset(SseTextLibraryCatalog::CONTEXTS[$context])) {
            $where[] = 'context = :ctx';
            $params['ctx'] = $context;
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(code LIKE :q OR title LIKE :q OR content LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        try {
            $rows = $this->db->fetchAll(
                'SELECT * FROM sse_text_templates WHERE ' . implode(' AND ', $where)
                . ' ORDER BY category ASC, sort_order ASC, code ASC',
                $params
            );
        } catch (\Throwable) {
            return [];
        }

        return array_map([$this, 'hydrate'], $rows);
    }

    /** @return array<string,mixed>|null */
    public function findById(int $tenantId, int $id): ?array
    {
        try {
            $row = $this->db->fetchOne(
                'SELECT * FROM sse_text_templates WHERE tenant_id = :t AND id = :i LIMIT 1',
                ['t' => $tenantId, 'i' => $id]
            );
        } catch (\Throwable) {
            return null;
        }

        return $row === null ? null : $this->hydrate($row);
    }

    /** @return array<string,mixed>|null */
    public function findByCode(int $tenantId, string $code): ?array
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return null;
        }
        try {
            $row = $this->db->fetchOne(
                'SELECT * FROM sse_text_templates WHERE tenant_id = :t AND code = :c LIMIT 1',
                ['t' => $tenantId, 'c' => $code]
            );
        } catch (\Throwable) {
            return null;
        }

        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(int $tenantId, array $data): ?int
    {
        $code = $this->normalizeCode((string) ($data['code'] ?? ''));
        $title = trim((string) ($data['title'] ?? ''));
        $content = trim((string) ($data['content'] ?? ''));
        if ($code === '' || $title === '' || $content === '') {
            return null;
        }
        if ($this->findByCode($tenantId, $code) !== null) {
            return null;
        }

        try {
            return $this->db->insert(
                'INSERT INTO sse_text_templates
                    (tenant_id, code, category, title, content, classification_min, context,
                     doctrine, fragment_kind, variables, version, is_default, is_active, is_seeded, sort_order, created_by, updated_by)
                 VALUES (:t, :code, :cat, :title, :content, :cmin, :ctx, :doc, :frag, :vars, 1, :def, :act, 0, :ord, :by, :by)',
                [
                    't' => $tenantId,
                    'code' => $code,
                    'cat' => $this->normalizeCategory((string) ($data['category'] ?? '')),
                    'title' => mb_substr($title, 0, 180),
                    'content' => $content,
                    'cmin' => ($data['classification_min'] ?? null) ?: null,
                    'ctx' => $this->normalizeContext((string) ($data['context'] ?? '')),
                    'doc' => $this->normalizeDoctrine((string) ($data['doctrine'] ?? '')),
                    'frag' => $this->normalizeFragment((string) ($data['fragment_kind'] ?? '')),
                    'vars' => $this->variablesCsv($content),
                    'def' => !empty($data['is_default']) ? 1 : 0,
                    'act' => array_key_exists('is_active', $data) && !$data['is_active'] ? 0 : 1,
                    'ord' => (int) ($data['sort_order'] ?? 100),
                    'by' => ((int) ($data['user_id'] ?? 0)) ?: null,
                ]
            );
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Le numéro de version n'avance que si le texte change : c'est lui qui
     * permet de savoir quelle rédaction a été portée à un dossier donné.
     *
     * @param array<string,mixed> $data
     */
    public function update(int $tenantId, int $id, array $data): bool
    {
        $current = $this->findById($tenantId, $id);
        if ($current === null) {
            return false;
        }
        $title = trim((string) ($data['title'] ?? ''));
        $content = trim((string) ($data['content'] ?? ''));
        if ($title === '' || $content === '') {
            return false;
        }
        $version = (int) ($current['version'] ?? 1);
        if ($content !== (string) ($current['content'] ?? '')) {
            $version++;
        }

        try {
            $this->db->execute(
                'UPDATE sse_text_templates
                    SET category = :cat, title = :title, content = :content,
                        classification_min = :cmin, context = :ctx, variables = :vars,
                        version = :ver, is_default = :def, is_active = :act,
                        sort_order = :ord, updated_by = :by
                  WHERE tenant_id = :t AND id = :i',
                [
                    'cat' => $this->normalizeCategory((string) ($data['category'] ?? $current['category'])),
                    'title' => mb_substr($title, 0, 180),
                    'content' => $content,
                    'cmin' => ($data['classification_min'] ?? null) ?: null,
                    'ctx' => $this->normalizeContext((string) ($data['context'] ?? $current['context'])),
                    'vars' => $this->variablesCsv($content),
                    'ver' => $version,
                    'def' => !empty($data['is_default']) ? 1 : 0,
                    'act' => !empty($data['is_active']) ? 1 : 0,
                    'ord' => (int) ($data['sort_order'] ?? ($current['sort_order'] ?? 100)),
                    'by' => ((int) ($data['user_id'] ?? 0)) ?: null,
                    't' => $tenantId,
                    'i' => $id,
                ]
            );

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function toggleActive(int $tenantId, int $id): ?bool
    {
        $current = $this->findById($tenantId, $id);
        if ($current === null) {
            return null;
        }
        $next = empty($current['is_active']) ? 1 : 0;
        try {
            $this->db->execute(
                'UPDATE sse_text_templates SET is_active = :a WHERE tenant_id = :t AND id = :i',
                ['a' => $next, 't' => $tenantId, 'i' => $id]
            );
        } catch (\Throwable) {
            return null;
        }

        return (bool) $next;
    }

    /** Les mentions du catalogue d'origine ne sont pas supprimables : on les désactive. */
    public function delete(int $tenantId, int $id): bool
    {
        $current = $this->findById($tenantId, $id);
        if ($current === null) {
            return false;
        }
        if (!empty($current['is_seeded'])) {
            return false;
        }
        try {
            return $this->db->execute(
                'DELETE FROM sse_text_templates WHERE tenant_id = :t AND id = :i',
                ['t' => $tenantId, 'i' => $id]
            ) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Consigne ce qui a été inséré dans un document : modèle, version et texte porté.
     *
     * @param list<array<string,mixed>> $uses
     */
    public function recordUses(int $tenantId, int $documentId, int $caseId, array $uses, string $authorLabel, ?int $userId = null): int
    {
        $saved = 0;
        foreach ($uses as $use) {
            $code = $this->normalizeCode((string) ($use['code'] ?? ''));
            if ($code === '') {
                continue;
            }
            $template = $this->findByCode($tenantId, $code);
            $text = trim((string) ($use['text'] ?? ''));
            try {
                $this->db->execute(
                    'INSERT INTO sse_text_template_uses
                        (tenant_id, document_id, case_id, template_id, template_code, template_version,
                         template_content, inserted_text, author_label, created_by)
                     VALUES (:t, :d, :c, :ti, :code, :ver, :tpl, :txt, :a, :u)',
                    [
                        't' => $tenantId,
                        'd' => $documentId > 0 ? $documentId : null,
                        'c' => $caseId > 0 ? $caseId : null,
                        'ti' => $template !== null ? (int) $template['id'] : null,
                        'code' => $code,
                        'ver' => (int) ($use['version'] ?? ($template['version'] ?? 1)),
                        'tpl' => $template !== null ? (string) $template['content'] : null,
                        'txt' => $text !== '' ? $text : null,
                        'a' => mb_substr($authorLabel, 0, 160),
                        'u' => $userId ?: null,
                    ]
                );
                $saved++;
                if ($template !== null) {
                    $this->db->execute(
                        'UPDATE sse_text_templates SET usage_count = usage_count + 1 WHERE id = :i',
                        ['i' => (int) $template['id']]
                    );
                }
            } catch (\Throwable) {
                // Traçabilité best-effort : ne bloque jamais l'enregistrement du document.
            }
        }

        return $saved;
    }

    /** @return list<array<string,mixed>> */
    public function usesForDocument(int $tenantId, int $documentId): array
    {
        if ($documentId < 1) {
            return [];
        }
        try {
            return $this->db->fetchAll(
                'SELECT * FROM sse_text_template_uses
                  WHERE tenant_id = :t AND document_id = :d
                  ORDER BY id ASC',
                ['t' => $tenantId, 'd' => $documentId]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<string,int> */
    public function countsByCategory(int $tenantId): array
    {
        $this->ensureSeed($tenantId);
        try {
            $rows = $this->db->fetchAll(
                'SELECT category, COUNT(*) AS n FROM sse_text_templates WHERE tenant_id = :t GROUP BY category',
                ['t' => $tenantId]
            );
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['category']] = (int) $row['n'];
        }

        return $out;
    }

    /**
     * Remplace les variables par le contexte courant. Une variable inconnue est
     * laissée telle quelle : mieux vaut un marqueur visible qu'un trou silencieux.
     *
     * @param array<string,string> $context
     */
    public static function render(string $content, array $context): string
    {
        $content = \App\Services\Sse\SseContextualMentionService::resolveConditionalPhrase($content, $context);
        $result = preg_replace_callback(
            '/\{\{\s*([a-z0-9_.]+)\s*\}\}/i',
            static function (array $m) use ($context): string {
                $key = strtolower($m[1]);
                $value = trim((string) ($context[$key] ?? ''));

                return $value !== '' ? $value : $m[0];
            },
            $content
        );

        return is_string($result) ? $result : $content;
    }

    private function normalizeDoctrine(string $doctrine): string
    {
        return isset(\App\Support\SseAnalyticalCatalog::DOCTRINES[$doctrine]) ? $doctrine : 'neutre';
    }

    private function normalizeFragment(string $kind): string
    {
        return isset(\App\Support\SseAnalyticalCatalog::FRAGMENT_KINDS[$kind]) ? $kind : 'bloc';
    }

    private function normalizeCode(string $code): string
    {
        $code = strtoupper(trim($code));
        $code = preg_replace('/[^A-Z0-9\-_]/', '', $code) ?? '';

        return mb_substr($code, 0, 32);
    }

    private function normalizeCategory(string $category): string
    {
        return isset(SseTextLibraryCatalog::CATEGORIES[$category]) ? $category : 'mentions';
    }

    private function normalizeContext(string $context): string
    {
        return isset(SseTextLibraryCatalog::CONTEXTS[$context]) ? $context : 'dossier';
    }

    private function variablesCsv(string $content): ?string
    {
        $vars = SseTextLibraryCatalog::variablesUsedIn($content);

        return $vars === [] ? null : implode(',', $vars);
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function hydrate(array $row): array
    {
        $row['id'] = (int) ($row['id'] ?? 0);
        $row['version'] = (int) ($row['version'] ?? 1);
        $row['sort_order'] = (int) ($row['sort_order'] ?? 100);
        $row['usage_count'] = (int) ($row['usage_count'] ?? 0);
        $row['is_default'] = (bool) ($row['is_default'] ?? false);
        $row['is_active'] = (bool) ($row['is_active'] ?? true);
        $row['is_seeded'] = (bool) ($row['is_seeded'] ?? false);
        $row['category_label'] = self::categoryLabel((string) ($row['category'] ?? ''));
        $row['context_label'] = self::contextLabel((string) ($row['context'] ?? ''));
        $row['doctrine'] = (string) ($row['doctrine'] ?? 'neutre');
        $row['doctrine_label'] = \App\Support\SseAnalyticalCatalog::label(
            \App\Support\SseAnalyticalCatalog::DOCTRINES,
            $row['doctrine']
        );
        $row['fragment_kind'] = (string) ($row['fragment_kind'] ?? 'bloc');
        $row['fragment_kind_label'] = \App\Support\SseAnalyticalCatalog::label(
            \App\Support\SseAnalyticalCatalog::FRAGMENT_KINDS,
            $row['fragment_kind']
        );
        $row['variable_list'] = array_values(array_filter(
            array_map('trim', explode(',', (string) ($row['variables'] ?? '')))
        ));

        return $row;
    }
}
