<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Database;
use App\Repositories\SseInterestCaseHistoryRepository;
use App\Repositories\SseInterestCaseLinkRepository;
use App\Repositories\SseInterestCaseRepository;
use App\Services\Sse\SseInterestActor;
use App\Services\Sse\SseInterestCaseService;
use App\Services\Sse\SseInterestLinkResolver;
use PDO;

/**
 * Base SQLite en mémoire des dossiers d'intérêt (schéma complet : colonnes de l'étape sse_interest_case_suite et trois tables),
 * branchée sur Database le temps d'un test, avec le vrai service, les vrais dépôts et un résolveur de liens réduit.
 * Communauté 1 : 10 gestionnaire, 11 analyste, 12 lecteur, 13 second analyste ; communauté 2 : 20.
 */
final class SseInterestFixture
{
    public PDO $pdo;
    public SseInterestCaseService $service;
    public SseInterestCaseRepository $repo;
    /** @var list<array<string, mixed>> */
    public array $audit = [];
    /** @var list<array{int, int, array<string, mixed>}> */
    public array $notices = [];
    /** @var list<string> */
    public array $blockers = [];
    public string $now = '2026-11-15 12:00:00';
    private ?PDO $saved = null;

    public function __construct(bool $withSuite = true, ?SseInterestLinkResolver $resolver = null)
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        foreach ($this->ddl($withSuite) as $sql) {
            $this->pdo->exec($sql);
        }
        $this->pdo->exec("INSERT INTO tenants (id) VALUES (1), (2)");
        $this->pdo->exec("INSERT INTO users (id, tenant_id, display_name, callsign) VALUES (10, 1, 'Gestionnaire', 'G1'), (11, 1, 'Analyste', 'A1'), (12, 1, 'Lecteur', 'L1'), (13, 1, 'Second analyste', 'A2'), (20, 2, 'Étranger', 'X1')");

        $prop = new \ReflectionProperty(Database::class, 'pdo');
        $current = $prop->getValue();
        $this->saved = $current instanceof PDO ? $current : null;
        $prop->setValue(null, $this->pdo);

        $db = Database::getInstance();
        $this->repo = new SseInterestCaseRepository($db);
        $this->service = new SseInterestCaseService(
            $this->repo,
            new SseInterestCaseHistoryRepository($db),
            new SseInterestCaseLinkRepository($db),
            $resolver ?? $this->resolver(),
            function (array $row): void {
                $this->audit[] = $row;
            },
            function (int $tenant, int $user, array $payload): void {
                $this->notices[] = [$tenant, $user, $payload];
            },
            fn (int $tenantId, array $case): array => $this->blockers,
            ['personne' => 'Personne d’intérêt', 'vehicule' => 'Véhicule', 'autre' => 'Autre'],
            fn (): string => $this->now,
        );
    }

    public function close(): void
    {
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->saved);
    }

    public function actor(int $userId, ?array $over = null): SseInterestActor
    {
        $tenant = $userId === 20 ? 2 : 1;
        $profile = match ($userId) {
            10 => ['read' => true, 'write' => true, 'delete' => true, 'export' => true, 'manage' => true, 'level' => 'tres_restreint'],
            11 => ['read' => true, 'write' => true, 'delete' => false, 'export' => false, 'manage' => false, 'level' => 'confidentiel'],
            13 => ['read' => true, 'write' => true, 'delete' => true, 'export' => true, 'manage' => false, 'level' => 'encadrement'],
            12 => ['read' => true, 'write' => false, 'delete' => false, 'export' => false, 'manage' => false, 'level' => 'interne'],
            default => ['read' => true, 'write' => true, 'delete' => true, 'export' => true, 'manage' => true, 'level' => 'tres_restreint'],
        };
        $profile = array_merge($profile, $over ?? []);

        return new SseInterestActor(
            $tenant, $userId, 'Opérateur ' . $userId,
            $profile['read'], $profile['write'], $profile['delete'], $profile['export'], $profile['manage'],
            $profile['level'], false,
            ['dossiers' => $profile['read'], 'fichiers' => $profile['read'], 'terrain' => $profile['read']]
        );
    }

    /** @return int identifiant du dossier ouvert */
    public function open(int $userId, array $over = []): int
    {
        $input = array_merge([
            'temporary_designation' => 'Homme au manteau gris',
            'opening_reason' => 'Observé deux fois près du dépôt nord.',
            'interest_level' => 'prioritaire',
            'digital_signature' => '1',
        ], $over);

        return $this->service->create($this->actor($userId), $input)['id'];
    }

    public function row(int $id): array
    {
        return $this->pdo->query('SELECT * FROM sse_interest_cases WHERE id = ' . $id)->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function resolver(): SseInterestLinkResolver
    {
        return new class implements SseInterestLinkResolver {
            /** @var array<string, array{label: string, masked?: bool}> */
            private array $known = [
                '1:person:5' => ['label' => 'DUPONT Jean'],
                '1:site:3' => ['label' => 'Dépôt nord'],
                '1:identity:8' => ['label' => 'Identité protégée', 'masked' => true],
                '1:fiche:2' => ['label' => 'Fiche FR-0002'],
                '2:person:5' => ['label' => 'AUTRE COMMUNAUTÉ'],
            ];

            public function search(SseInterestActor $actor, string $type, string $query, int $limit = 8): array
            {
                $out = [];
                foreach ($this->known as $key => $row) {
                    [$tenant, $t, $id] = explode(':', $key);
                    if ((int) $tenant === $actor->tenantId && $t === $type && empty($row['masked']) && stripos($row['label'], $query) !== false) {
                        $out[] = ['id' => $id, 'label' => $row['label'], 'hint' => ''];
                    }
                }

                return $out;
            }

            public function describe(SseInterestActor $actor, string $type, string $id): ?array
            {
                $row = $this->known[$actor->tenantId . ':' . $type . ':' . $id] ?? null;

                return $row === null ? null : ['label' => $row['label'], 'hint' => '', 'href' => empty($row['masked']) ? '/x/' . $id : null, 'masked' => !empty($row['masked'])];
            }
        };
    }

    /** @return list<string> */
    private function ddl(bool $withSuite): array
    {
        $ext = $withSuite
            ? ", version INTEGER NOT NULL DEFAULT 1, classification TEXT NOT NULL DEFAULT 'interne', case_type TEXT, assignee_user_id INTEGER, due_at TEXT, tags TEXT,
               archive_from_status TEXT, deleted_at TEXT, deleted_by INTEGER, delete_reason TEXT"
            : '';
        $out = [
            'CREATE TABLE tenants (id INTEGER PRIMARY KEY)',
            'CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER, display_name TEXT, callsign TEXT)',
            "CREATE TABLE sse_interest_cases (
                id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, reference_code TEXT NOT NULL, temporary_designation TEXT NOT NULL,
                suspected_alias TEXT, apparent_sex TEXT, estimated_age_range TEXT, suspected_nationality TEXT, suspected_affiliation TEXT,
                status TEXT NOT NULL DEFAULT 'signalement_recu', confidence_level TEXT NOT NULL DEFAULT 'non_evalue', interest_level TEXT NOT NULL DEFAULT 'courant',
                opening_reason TEXT NOT NULL, description TEXT, origin_operator TEXT, observed_elements TEXT, analysis_facts TEXT, analysis_assumptions TEXT,
                analysis_contradictions TEXT, analysis_questions TEXT, collection_needs TEXT, operational_risk TEXT, recommendations TEXT, source_label TEXT,
                source_reliability TEXT, signed_by_label TEXT, signed_at TEXT, acquisition_at TEXT, mission_label TEXT, created_by INTEGER, validated_by INTEGER,
                validated_at TEXT, archived_at TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP" . $ext . ',
                UNIQUE (tenant_id, reference_code))',
            'CREATE TABLE sse_interest_case_acl (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, interest_case_id INTEGER, user_id INTEGER, access_mode TEXT, created_by INTEGER, created_at TEXT DEFAULT CURRENT_TIMESTAMP)',
            'CREATE TABLE sse_interest_case_updates (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, interest_case_id INTEGER, body TEXT, author_label TEXT, author_user_id INTEGER, created_at TEXT DEFAULT CURRENT_TIMESTAMP)',
            'CREATE TABLE sse_interest_case_cooldowns (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, interest_case_id INTEGER, action_key TEXT, last_at TEXT, last_by INTEGER)',
        ];
        if ($withSuite) {
            $out[] = 'CREATE TABLE sse_interest_case_history (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, interest_case_id INTEGER NOT NULL, action TEXT NOT NULL, field_key TEXT, old_value TEXT, new_value TEXT, reason TEXT, actor_user_id INTEGER, actor_label TEXT, version_after INTEGER, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)';
            $out[] = 'CREATE TABLE sse_interest_case_links (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, interest_case_id INTEGER NOT NULL, link_type TEXT NOT NULL, target_id TEXT NOT NULL DEFAULT \'\', label TEXT NOT NULL, note TEXT, created_by INTEGER, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE (interest_case_id, link_type, target_id))';
            $out[] = 'CREATE TABLE sse_interest_case_watchers (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, interest_case_id INTEGER NOT NULL, user_id INTEGER NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE (interest_case_id, user_id))';
        }

        return $out;
    }
}
