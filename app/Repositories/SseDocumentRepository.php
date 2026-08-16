<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Documents rédigés dans le bureau SSE (flash, comptes rendus, notes, synthèses).
 */
final class SseDocumentRepository
{
    /** @var array<string, string> */
    public const TYPE_LABELS = [
        'flash' => 'Flash renseignement',
        'compte_rendu' => 'Compte rendu d’exploitation',
        'note_analyse' => 'Note d’analyse',
        'synthese' => 'Synthèse de situation',
        'diffusion' => 'Version de diffusion',
    ];

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        'brouillon' => 'Brouillon',
        'en_relecture' => 'En relecture',
        'valide' => 'Validé',
        'archive' => 'Archivé',
    ];

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $path = base_path('bootstrap/atak_sse_portal_migration.php');
        if (is_file($path)) {
            $migrate = require $path;
            if (is_callable($migrate)) {
                try {
                    $migrate(Database::getPdo());
                } catch (\Throwable) {
                }
            }
        }
        $done = true;
    }

    public static function typeLabel(string $type): string
    {
        return self::TYPE_LABELS[$type] ?? 'Document';
    }

    public static function statusLabel(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? $status;
    }

    public static function normalizeType(string $raw): string
    {
        $s = strtolower(trim($raw));

        return isset(self::TYPE_LABELS[$s]) ? $s : 'note_analyse';
    }

    public static function normalizeStatus(string $raw): string
    {
        $s = strtolower(trim($raw));

        return isset(self::STATUS_LABELS[$s]) ? $s : 'brouillon';
    }

    /**
     * @param array{status?:string,q?:string,case_id?:int,document_type?:string} $filters
     * @return list<array<string, mixed>>
     */
    public function listForTenant(int $tenantId, array $filters = []): array
    {
        $where = ['d.tenant_id = :t'];
        $params = ['t' => $tenantId];
        if (!empty($filters['status'])) {
            $where[] = 'd.status = :status';
            $params['status'] = self::normalizeStatus((string) $filters['status']);
        }
        if (!empty($filters['document_type'])) {
            $where[] = 'd.document_type = :dtype';
            $params['dtype'] = self::normalizeType((string) $filters['document_type']);
        }
        if (!empty($filters['case_id'])) {
            $where[] = 'd.case_id = :case_id';
            $params['case_id'] = (int) $filters['case_id'];
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            $where[] = '(d.reference_code LIKE :q_ref OR d.title LIKE :q_title)';
            $params['q_ref'] = $like;
            $params['q_title'] = $like;
        }
        try {
            $rows = $this->db->fetchAll(
                'SELECT d.*, c.reference_code AS case_reference, c.title AS case_title
                 FROM sse_documents d
                 LEFT JOIN sse_cases c ON c.id = d.case_id AND c.tenant_id = d.tenant_id
                 WHERE ' . implode(' AND ', $where) . '
                 ORDER BY d.updated_at DESC
                 LIMIT 200',
                $params
            );
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->hydrate($row);
        }

        return $out;
    }

    /**
     * Répartition des documents du compartiment par état, tous filtres écartés.
     *
     * @return array<string, int>
     */
    public function countsByStatus(int $tenantId): array
    {
        $out = array_fill_keys(array_keys(self::STATUS_LABELS), 0);
        $out['total'] = 0;
        try {
            $rows = $this->db->fetchAll(
                'SELECT status, COUNT(*) AS n FROM sse_documents WHERE tenant_id = :t GROUP BY status',
                ['t' => $tenantId]
            );
        } catch (\Throwable) {
            return $out;
        }
        foreach ($rows as $row) {
            $status = self::normalizeStatus((string) ($row['status'] ?? ''));
            $n = (int) ($row['n'] ?? 0);
            $out[$status] = ($out[$status] ?? 0) + $n;
            $out['total'] += $n;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id, int $tenantId): ?array
    {
        try {
            $row = $this->db->fetchOne(
                'SELECT d.*, c.reference_code AS case_reference, c.title AS case_title
                 FROM sse_documents d
                 LEFT JOIN sse_cases c ON c.id = d.case_id AND c.tenant_id = d.tenant_id
                 WHERE d.id = :id AND d.tenant_id = :t
                 LIMIT 1',
                ['id' => $id, 't' => $tenantId]
            );
        } catch (\Throwable) {
            return null;
        }

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $tenantId = (int) ($data['tenant_id'] ?? 0);
        $ref = trim((string) ($data['reference_code'] ?? ''));
        if ($ref === '') {
            $ref = $this->nextReference($tenantId);
        }

        return (int) $this->db->insert(
            'INSERT INTO sse_documents (
                tenant_id, reference_code, case_id, document_type, title, body,
                classification, status, created_by, updated_by, author_label
            ) VALUES (
                :tenant_id, :reference_code, :case_id, :document_type, :title, :body,
                :classification, :status, :created_by, :updated_by, :author_label
            )',
            [
                'tenant_id' => $tenantId,
                'reference_code' => $ref,
                'case_id' => !empty($data['case_id']) ? (int) $data['case_id'] : null,
                'document_type' => self::normalizeType((string) ($data['document_type'] ?? 'note_analyse')),
                'title' => trim((string) ($data['title'] ?? 'Sans titre')),
                'body' => (string) ($data['body'] ?? ''),
                'classification' => SseCaseRepository::normalizeClassification((string) ($data['classification'] ?? 'confidentiel')),
                'status' => self::normalizeStatus((string) ($data['status'] ?? 'brouillon')),
                'created_by' => !empty($data['created_by']) ? (int) $data['created_by'] : null,
                'updated_by' => !empty($data['updated_by']) ? (int) $data['updated_by'] : null,
                'author_label' => $this->nullIfEmpty($data['author_label'] ?? null),
            ]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, int $tenantId, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id, 't' => $tenantId];
        foreach (['title', 'body', 'classification', 'status', 'document_type', 'case_id', 'author_label', 'updated_by', 'validated_by', 'validated_at'] as $k) {
            if (!array_key_exists($k, $data)) {
                continue;
            }
            if ($k === 'classification') {
                $fields[] = 'classification = :classification';
                $params['classification'] = SseCaseRepository::normalizeClassification((string) $data['classification']);
            } elseif ($k === 'status') {
                $fields[] = 'status = :status';
                $params['status'] = self::normalizeStatus((string) $data['status']);
            } elseif ($k === 'document_type') {
                $fields[] = 'document_type = :document_type';
                $params['document_type'] = self::normalizeType((string) $data['document_type']);
            } elseif ($k === 'case_id') {
                $fields[] = 'case_id = :case_id';
                $params['case_id'] = !empty($data['case_id']) ? (int) $data['case_id'] : null;
            } elseif ($k === 'title') {
                $fields[] = 'title = :title';
                $params['title'] = trim((string) $data['title']);
            } elseif ($k === 'body') {
                $fields[] = 'body = :body';
                $params['body'] = (string) $data['body'];
            } elseif ($k === 'author_label') {
                $fields[] = 'author_label = :author_label';
                $params['author_label'] = $this->nullIfEmpty($data['author_label']);
            } elseif ($k === 'updated_by') {
                $fields[] = 'updated_by = :updated_by';
                $params['updated_by'] = !empty($data['updated_by']) ? (int) $data['updated_by'] : null;
            } elseif ($k === 'validated_by') {
                $fields[] = 'validated_by = :validated_by';
                $params['validated_by'] = !empty($data['validated_by']) ? (int) $data['validated_by'] : null;
            } elseif ($k === 'validated_at') {
                $fields[] = 'validated_at = :validated_at';
                $params['validated_at'] = $this->nullIfEmpty($data['validated_at']);
            }
        }
        if ($fields === []) {
            return false;
        }

        return $this->db->execute(
            'UPDATE sse_documents SET ' . implode(', ', $fields) . ' WHERE id = :id AND tenant_id = :t',
            $params
        ) > 0;
    }

    private function nextReference(int $tenantId): string
    {
        $year = date('Y');
        $prefix = 'DOC-' . $year . '-';
        try {
            $row = $this->db->fetchOne(
                'SELECT reference_code FROM sse_documents
                 WHERE tenant_id = :t AND reference_code LIKE :p
                 ORDER BY id DESC LIMIT 1',
                ['t' => $tenantId, 'p' => $prefix . '%']
            );
        } catch (\Throwable) {
            $row = null;
        }
        $n = 1;
        if ($row && preg_match('/(\d+)$/', (string) $row['reference_code'], $m)) {
            $n = (int) $m[1] + 1;
        }

        return $prefix . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $type = self::normalizeType((string) ($row['document_type'] ?? 'note_analyse'));
        $status = self::normalizeStatus((string) ($row['status'] ?? 'brouillon'));
        $class = SseCaseRepository::normalizeClassification((string) ($row['classification'] ?? 'confidentiel'));

        return [
            'id' => (int) ($row['id'] ?? 0),
            'tenant_id' => (int) ($row['tenant_id'] ?? 0),
            'reference_code' => (string) ($row['reference_code'] ?? ''),
            'case_id' => isset($row['case_id']) && $row['case_id'] !== null ? (int) $row['case_id'] : null,
            'case_reference' => (string) ($row['case_reference'] ?? ''),
            'case_title' => (string) ($row['case_title'] ?? ''),
            'document_type' => $type,
            'document_type_label' => self::typeLabel($type),
            'title' => (string) ($row['title'] ?? ''),
            'body' => (string) ($row['body'] ?? ''),
            'classification' => $class,
            'classification_label' => SseCaseRepository::classificationLabel($class),
            'status' => $status,
            'status_label' => self::statusLabel($status),
            'author_label' => $row['author_label'] ?? null,
            'created_by' => isset($row['created_by']) ? (int) $row['created_by'] : null,
            'updated_by' => isset($row['updated_by']) ? (int) $row['updated_by'] : null,
            'validated_by' => isset($row['validated_by']) ? (int) $row['validated_by'] : null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'validated_at' => $row['validated_at'] ?? null,
        ];
    }

    /**
     * Corps type pour un nouveau document (rédaction guidée).
     */
    public static function bodyTemplate(string $type): string
    {
        $type = self::normalizeType($type);
        $zulu = gmdate('d/m/Y H:i') . ' Z';
        $local = date('d/m/Y H:i');

        return match ($type) {
            'flash' => <<<TXT
FLASH RENSEIGNEMENT
════════════════════════════════════════
Référence : (attribuée à l’enregistrement)
Date / heure (Zulu) : {$zulu}
Date / heure locale : {$local}
Classification : (celle du formulaire)
Priorité : IMMÉDIAT / PRIORITAIRE / ROUTINE

────────────────────────────────────────
1. SITUATION EN UNE PHRASE
────────────────────────────────────────
—

────────────────────────────────────────
2. SECTEUR / SITE / ZONE
────────────────────────────────────────
—
Repère / grille :
—

────────────────────────────────────────
3. FAITS ESSENTIELS
────────────────────────────────────────
• —
• —
• —

────────────────────────────────────────
4. SOURCE ET FIABILITÉ
────────────────────────────────────────
Nature de la source : observation / saisie / témoignage / autre
Fiabilité estimée : A / B / C / D / E — (justifier en une ligne)
—

────────────────────────────────────────
5. IMPACT IMMÉDIAT
────────────────────────────────────────
—

────────────────────────────────────────
6. ACTION DEMANDÉE
────────────────────────────────────────
• —
Délai souhaité :
Destinataire opérationnel :

────────────────────────────────────────
7. RÉSERVES
────────────────────────────────────────
Ce qui n’est pas établi :
—
TXT,
            'compte_rendu' => <<<TXT
COMPTE RENDU D’EXPLOITATION
════════════════════════════════════════
Référence : (attribuée à l’enregistrement)
Date / heure (Zulu) : {$zulu}
Date / heure locale : {$local}
Périmètre : site / identité / support numérique / mixte
Rédacteur : —

────────────────────────────────────────
1. OBJET ET CONTEXTE
────────────────────────────────────────
Objet de l’exploitation :
—
Autorité / ordre d’exécution :
—
Heure d’entrée / de sortie :

────────────────────────────────────────
2. SITUATION GÉNÉRALE
────────────────────────────────────────
—

────────────────────────────────────────
3. SITE / ENVIRONNEMENT
────────────────────────────────────────
Description du lieu :
—
Accès, obstacles, présence hostile / civile :
—
État d’exploitation (% ou pièces traitées) :

────────────────────────────────────────
4. PERSONNEL / IDENTITÉS
────────────────────────────────────────
Personnes présentes ou liées :
—
Signalements, aliases, documents d’identité :
—
Biométrie / photos : oui / non — détail :

────────────────────────────────────────
5. MATÉRIEL / SAISIES
────────────────────────────────────────
Armes / munitions :
—
Supports numériques (téléphones, PC, supports) :
—
Documents papier / plans :
—
Autres saisies :

────────────────────────────────────────
6. FAITS MARQUANTS
────────────────────────────────────────
• —
• —

────────────────────────────────────────
7. ANALYSE ET INCERTITUDES
────────────────────────────────────────
Faits consolidés :
—
Hypothèses (non confirmées) :
—
Contradictions / zones d’ombre :

────────────────────────────────────────
8. RECOMMANDATIONS
────────────────────────────────────────
• Collecte complémentaire :
• Mesures de protection / conservation des preuves :
• Suites pour le bureau SSE :

────────────────────────────────────────
9. ANNEXES (références)
────────────────────────────────────────
Preuves / photos / toiles liées :
—
TXT,
            'synthese' => <<<TXT
SYNTHÈSE DE SITUATION
════════════════════════════════════════
Référence : (attribuée à l’enregistrement)
Date / heure (Zulu) : {$zulu}
Périmètre temporel :
Périmètre géographique / thématique :
Rédacteur : —

────────────────────────────────────────
1. RÉSUMÉ EXÉCUTIF (5 lignes max)
────────────────────────────────────────
—

────────────────────────────────────────
2. CONTEXTE
────────────────────────────────────────
—

────────────────────────────────────────
3. ÉLÉMENTS CONSOLIDÉS
────────────────────────────────────────
• —
• —
• —

────────────────────────────────────────
4. POINTS ENCORE NON CONFIRMÉS
────────────────────────────────────────
• —
• —

────────────────────────────────────────
5. APPRECIATION
────────────────────────────────────────
Tendance : stable / dégradée / améliorée / indéterminée
Niveau de confiance de la synthèse : faible / moyen / élevé
—

────────────────────────────────────────
6. SUITE PROPOSÉE
────────────────────────────────────────
• —
Priorité :
Échéance :
TXT,
            'diffusion' => <<<TXT
VERSION DE DIFFUSION
════════════════════════════════════════
Référence source (document ou dossier) :
Date / heure (Zulu) : {$zulu}
Niveau de diffusion visé : Diffusion interne / Encadrement / Confidentiel / Très restreinte
Validé pour sortie de compartiment : oui / non — par :

────────────────────────────────────────
AVERTISSEMENT
────────────────────────────────────────
Ce texte est une version volontairement allégée.
Les éléments sensibles ont été omis ou généralisés.
Ne pas réintroduire de noms, grilles ou détails techniques non validés.

────────────────────────────────────────
1. OBJET (formulation large)
────────────────────────────────────────
—

────────────────────────────────────────
2. CONTENU EXPURGÉ
────────────────────────────────────────
—

────────────────────────────────────────
3. ÉLÉMENTS VOLONTAIREMENT OMIS
────────────────────────────────────────
(Ne pas recopier ici le détail classifié — lister seulement la nature)
• Identités nominatives
• —
• —

────────────────────────────────────────
4. CADRE D’EMPLOI
────────────────────────────────────────
Public autorisé :
Canal de diffusion :
Durée de validité / rappel de classification :
TXT,
            default => <<<TXT
NOTE D’ANALYSE
════════════════════════════════════════
Référence : (attribuée à l’enregistrement)
Date / heure (Zulu) : {$zulu}
Date / heure locale : {$local}
Analyste : —
Dossier / toile lié(e) : —

────────────────────────────────────────
1. OBJET
────────────────────────────────────────
—

────────────────────────────────────────
2. QUESTION POSÉE À L’ANALYSE
────────────────────────────────────────
—

────────────────────────────────────────
3. ÉLÉMENTS OBSERVÉS
────────────────────────────────────────
• —
• —
• —

────────────────────────────────────────
4. CROISEMENTS
────────────────────────────────────────
Sources mises en relation :
—
Concordances :
—
Discordances :

────────────────────────────────────────
5. HYPOTHÈSES
────────────────────────────────────────
H1 —
H2 —
H3 —

────────────────────────────────────────
6. LIMITES / CE QUI N’EST PAS ÉTABLI
────────────────────────────────────────
—
Ce que la note ne permet pas de conclure :

────────────────────────────────────────
7. CONCLUSION PROVISOIRE
────────────────────────────────────────
—

────────────────────────────────────────
8. BESOINS DE COLLECTE
────────────────────────────────────────
• —
Priorité :
TXT,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function bodyTemplatesByType(): array
    {
        $out = [];
        foreach (array_keys(self::TYPE_LABELS) as $type) {
            $out[$type] = self::bodyTemplate($type);
        }

        return $out;
    }

    /**
     * Corps texte → HTML pour rendu papier (aperçu / lecture).
     */
    /**
     * Échappe une ligne et convertit les marqueurs de caviardage [[texte]] ou [[#12]]
     * en barres noires dont la largeur reprend celle du passage masqué.
     */
    private static function inlineHtml(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        $converted = preg_replace_callback(
            '/\[\[(.*?)\]\]/u',
            static function (array $m): string {
                $raw = trim((string) $m[1]);
                if (preg_match('/^#(\d{1,3})$/', $raw, $sized) === 1) {
                    $width = (int) $sized[1];
                } else {
                    $width = mb_strlen(html_entity_decode($raw, ENT_QUOTES, 'UTF-8'), 'UTF-8');
                }
                $width = max(3, min(90, $width));

                // Styles en ligne : la barre reste visible hors du portail (export PDF, impression).
                return '<span class="sse-doc-paper__redact"'
                    . ' style="display:inline-block;width:' . $width . 'ch;height:0.95em;background:#0b1220"'
                    . ' role="img" aria-label="Passage caviardé" title="Passage caviardé"></span>';
            },
            $escaped
        );

        return is_string($converted) ? $converted : $escaped;
    }

    public static function bodyToHtml(string $body): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        $lines = explode("\n", $body);
        $html = [];
        foreach ($lines as $raw) {
            $line = rtrim($raw);
            $trim = trim($line);
            if ($trim === '') {
                $html[] = '<p class="sse-doc-paper__spacer">&nbsp;</p>';
                continue;
            }
            if (preg_match('/^[═─\-_=]{6,}$/u', $trim) === 1) {
                $html[] = '<hr class="sse-doc-paper__rule">';
                continue;
            }
            if ($trim === '—' || $trim === '--' || $trim === '-' || $trim === '• —') {
                $html[] = '<p class="sse-doc-paper__fill">………………………………………………………………</p>';
                continue;
            }
            $upper = mb_strtoupper($trim, 'UTF-8');
            $isTitle = in_array($upper, [
                'FLASH RENSEIGNEMENT',
                'COMPTE RENDU D’EXPLOITATION',
                "COMPTE RENDU D'EXPLOITATION",
                'NOTE D’ANALYSE',
                "NOTE D'ANALYSE",
                'SYNTHÈSE DE SITUATION',
                'SYNTHESE DE SITUATION',
                'VERSION DE DIFFUSION',
            ], true);
            if ($isTitle) {
                $html[] = '<h1 class="sse-doc-paper__doc-title">' . self::inlineHtml($trim) . '</h1>';
                continue;
            }
            if (preg_match('/^\d+\.\s+.+/u', $trim) === 1
                || preg_match('/^AVERTISSEMENT$/ui', $trim) === 1
                || (str_starts_with($trim, '─') === false && preg_match('/^[A-ZÀÂÄÉÈÊËÏÎÔÙÛÜŸÇ0-9].{0,70}$/u', $trim) === 1
                    && $upper === $trim && !str_contains($trim, ':'))) {
                $html[] = '<h2 class="sse-doc-paper__section">' . self::inlineHtml($trim) . '</h2>';
                continue;
            }
            if (preg_match('/^.+:\s*$/u', $trim) === 1 && mb_strlen($trim) < 90) {
                $html[] = '<p class="sse-doc-paper__label">' . self::inlineHtml($trim) . '</p>';
                continue;
            }
            if (str_starts_with($trim, '• ') || str_starts_with($trim, '- ') || preg_match('/^H\d+\s/u', $trim) === 1) {
                $html[] = '<p class="sse-doc-paper__bullet">' . self::inlineHtml($trim) . '</p>';
                continue;
            }
            $html[] = '<p class="sse-doc-paper__p">' . self::inlineHtml($trim) . '</p>';
        }

        return implode("\n", $html);
    }

    private function nullIfEmpty(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }
}
