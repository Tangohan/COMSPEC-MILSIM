<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Repositories\SseAccessRuleRepository;
use App\Services\Sse\SseAccessDirectory;
use App\Services\Sse\SseAccessPolicy;
use App\Services\Sse\SseAccessRuleService;
use App\Services\Sse\SseAccessSubjects;
use PDO;

/**
 * Base SQLite en mémoire et services des droits d'accès SSE, avec des comptes types.
 *
 * Communauté 1 : 10 gestionnaire (attribution des accès), 11 enquêteur (gestion des dossiers), 12 membre habilité,
 * 13 sans droit d'entrer ; communauté 2 : 20. Niveaux d'accès 1 « Enquêteurs » et 2 « Analystes » (communauté 1), 9 (autre
 * communauté). Droits d'origine, porte d'entrée et horloge sont injectés : aucune session ni base réelle.
 */
final class SseAccessFixture
{
    public const NOW = '2026-11-15 12:00:00';

    public PDO $pdo;
    public SseAccessRuleRepository $rules;
    public SseAccessPolicy $policy;
    public SseAccessRuleService $service;

    /** @var array<int, list<string>> */
    public array $perms = [
        10 => ['atak.sse.grant', 'atak.sse.access', 'atak.sse.case.manage', 'atak.sse.export'],
        11 => ['atak.sse.access', 'atak.sse.case.manage'],
        12 => ['atak.sse.access'],
        13 => [],
        20 => ['atak.sse.access'],
    ];

    /** @var list<array<string, mixed>> */
    public array $audit = [];

    public function __construct()
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        foreach ([
            'CREATE TABLE tenants (id INTEGER PRIMARY KEY)',
            'CREATE TABLE roles (id INTEGER PRIMARY KEY, tenant_id INT, name TEXT, slug TEXT)',
            'CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INT, role_id INT NULL, display_name TEXT, status TEXT)',
            'CREATE TABLE units (id INTEGER PRIMARY KEY, tenant_id INT, parent_id INT NULL, name TEXT)',
            'CREATE TABLE user_units (user_id INT, unit_id INT, ended_at TEXT NULL)',
            'CREATE TABLE tenant_user_roles (user_id INT, tenant_id INT, role_id INT, org_unit_id INT NULL, valid_from TEXT NULL, valid_until TEXT NULL)',
            'CREATE TABLE permissions (id INTEGER PRIMARY KEY, slug TEXT)',
            'CREATE TABLE role_permissions (role_id INT, permission_id INT)',
            'CREATE TABLE sse_access_rules (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INT, section TEXT, level TEXT, subject_type TEXT, subject_id INT, effect TEXT,
                starts_at TEXT NULL, ends_at TEXT NULL, reason TEXT, source TEXT, created_by INT, created_at TEXT, revoked_at TEXT NULL, revoked_by INT NULL)',
            'CREATE TABLE sse_access_rule_history (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INT, rule_id INT NULL, action TEXT, actor_user_id INT, actor_label TEXT,
                section TEXT, level TEXT, subject_type TEXT, subject_id INT, subject_label TEXT, effect TEXT, starts_at TEXT NULL, ends_at TEXT NULL, reason TEXT, created_at TEXT)',
            'INSERT INTO tenants (id) VALUES (1), (2)',
            "INSERT INTO roles (id, tenant_id, name, slug) VALUES (1, 1, 'Enquêteurs', 'enq'), (2, 1, 'Analystes', 'ana'), (9, 2, 'Autre', 'x')",
            "INSERT INTO units (id, tenant_id, parent_id, name) VALUES (100, 1, NULL, 'Section'), (101, 1, 100, 'Équipe'), (900, 2, NULL, 'Étrangère')",
            "INSERT INTO users (id, tenant_id, role_id, display_name, status) VALUES (10, 1, NULL, 'Gestionnaire', 'active'), (11, 1, NULL, 'Enquêteur', 'active'), (12, 1, NULL, 'Membre', 'active'), (13, 1, NULL, 'Sans droit', 'active'), (20, 2, 9, 'Étranger', 'active')",
            'INSERT INTO tenant_user_roles (user_id, tenant_id, role_id) VALUES (11, 1, 1), (12, 1, 2)',
            'INSERT INTO user_units (user_id, unit_id) VALUES (11, 101)',
            "INSERT INTO permissions (id, slug) VALUES (1, 'atak.sse.grant'), (2, 'admin.access')",
        ] as $sql) {
            $this->pdo->exec($sql);
        }
        $this->rules = new SseAccessRuleRepository($this->pdo);
        $this->policy = new SseAccessPolicy(
            $this->rules,
            new SseAccessSubjects($this->pdo),
            fn (int $u, int $t, string $slug): bool => in_array($slug, $this->perms[$u] ?? [], true),
            fn (?int $u, int $t, string $mode): bool => $u !== null && $u > 0 && $this->enters($u),
            fn (): string => self::NOW,
            fn (int $u, int $t): bool => false,
        );
        $directory = new class extends SseAccessDirectory {
            public function __construct()
            {
            }

            public function member(int $tenantId, int $userId): ?array
            {
                return $tenantId === 1 && $userId >= 10 && $userId <= 13 ? ['user_id' => $userId, 'label' => 'm' . $userId, 'unit' => ''] : null;
            }
        };
        $this->service = new SseAccessRuleService($this->rules, new SseAccessSubjects($this->pdo), $this->policy, $directory, function (array $row): void {
            $this->audit[] = $row;
        });
    }

    public function enters(int $userId): bool
    {
        foreach (['atak.sse.access', 'atak.sse.grant', 'admin.access'] as $slug) {
            if (in_array($slug, $this->perms[$userId] ?? [], true)) {
                return true;
            }
        }

        return false;
    }

    /** Règle posée directement en base (sans passer par les garde-fous). */
    public function addRule(string $section, string $level, string $type, int $id, string $effect, int $tenant = 1): int
    {
        $rid = $this->rules->insert($tenant, [
            'section' => $section, 'level' => $level, 'subject_type' => $type, 'subject_id' => $id, 'effect' => $effect,
            'starts_at' => null, 'ends_at' => null, 'reason' => 'test', 'source' => '',
        ], 10, self::NOW);
        $this->policy->reset();

        return $rid;
    }
}
