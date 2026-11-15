<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\Effectifs\Dossier\DossierPorts;
use PDO;

/**
 * Base SQLite réaliste pour le suivi de dossier : les tables des parcours existants (schéma réduit aux colonnes lues),
 * les tables du suivi (créées depuis la vraie migration) et un jeu de données proche d'une communauté vivante.
 * Sert aux tests et au harnais de rendu des captures.
 */
final class DossierFixture
{
    public const TENANT = 1;

    public static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /** Base avec les tables sources et, si demandé, les tables du suivi (la migration). */
    public static function pdo(bool $withMigration = true): MysqlDdlSqlitePdo
    {
        $_SERVER['HTTP_HOST'] ??= 'athena-comspec.eu';
        $pdo = new MysqlDdlSqlitePdo();
        foreach (self::sourceSchema() as $sql) {
            $pdo->exec($sql);
        }
        if ($withMigration) {
            self::migrate($pdo);
        }

        return $pdo;
    }

    /** Applique la vraie migration SQL du suivi de dossier. */
    public static function migrate(PDO $pdo): void
    {
        $sql = (string) file_get_contents(self::root() . '/migrations/2026_11_15_020_member_dossier_steps.sql');
        $sql = preg_replace('/^--.*$/m', '', $sql) ?? '';
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            $pdo->exec($statement);
        }
    }

    /** @return list<string> */
    public static function sourceSchema(): array
    {
        return [
            'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, display_name TEXT, status TEXT)',
            'CREATE TABLE member_integrations (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, user_id INTEGER, status TEXT)',
            'CREATE TABLE member_integration_steps (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, integration_id INTEGER, step_key TEXT, position INTEGER, title TEXT, description TEXT, step_type TEXT, responsible_kind TEXT, responsible_user_id INTEGER, status TEXT, due_at TEXT, is_required INTEGER, started_at TEXT, completed_at TEXT, validated_by INTEGER, force_reason TEXT, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE enlistments (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, submitter_user_id INTEGER, status TEXT, reviewed_at TEXT, created_at TEXT)',
            'CREATE TABLE enlistment_retro_feedbacks (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, enlistment_id INTEGER, feedback_scope TEXT, created_at TEXT, updated_at TEXT, auto_closed_at TEXT)',
            'CREATE TABLE personnel_qualifications (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, user_id INTEGER, qualification_name TEXT, admin_status TEXT, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE training_courses (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, title TEXT)',
            'CREATE TABLE training_enrollments (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, course_id INTEGER, user_id INTEGER, status TEXT, assigned_at TEXT, started_at TEXT, completed_at TEXT, expires_at TEXT)',
            'CREATE TABLE data_campaigns (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, title TEXT, status TEXT, due_on TEXT, opens_on TEXT, opened_at TEXT, closed_at TEXT)',
            'CREATE TABLE data_campaign_members (id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_id INTEGER, tenant_id INTEGER, user_id INTEGER, confirmed_at TEXT, first_seen_at TEXT, updated_at TEXT)',
            'CREATE TABLE data_campaign_flags (id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_id INTEGER, tenant_id INTEGER, user_id INTEGER, block_key TEXT, message TEXT, status TEXT, created_at TEXT)',
            'CREATE TABLE elevation_requests (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, target_user_id INTEGER, kind TEXT, status TEXT, created_at TEXT, resolved_at TEXT, updated_at TEXT)',
            'CREATE TABLE personnel_mobility_requests (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, user_id INTEGER, request_type TEXT, target_label TEXT, status TEXT, created_at TEXT, reviewed_at TEXT)',
            'CREATE TABLE member_departures (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, user_id INTEGER, departed_at TEXT, access_revoked INTEGER, access_revoked_at TEXT, created_at TEXT, dossier_archived_at TEXT, reinstated_at TEXT)',
        ];
    }

    public static function user(PDO $pdo, int $id, string $name, int $tenant = self::TENANT, string $status = 'active'): void
    {
        $pdo->prepare('INSERT INTO users (id, tenant_id, display_name, status) VALUES (?, ?, ?, ?)')->execute([$id, $tenant, $name, $status]);
    }

    /** @param array<string, mixed> $f */
    public static function integrationStep(PDO $pdo, int $integrationId, array $f): int
    {
        $d = $f + [
            'tenant_id' => self::TENANT, 'step_key' => 'k' . random_int(1, 999999), 'position' => 1, 'description' => '', 'step_type' => 'task',
            'responsible_kind' => 'member', 'responsible_user_id' => null, 'status' => 'pending', 'due_at' => null, 'is_required' => 1,
            'started_at' => null, 'completed_at' => null, 'created_at' => date('Y-m-d H:i:s', strtotime('-40 days')), 'updated_at' => null,
        ];
        $pdo->prepare('INSERT INTO member_integration_steps (tenant_id, integration_id, step_key, position, title, description, step_type, responsible_kind, responsible_user_id, status, due_at, is_required, started_at, completed_at, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$d['tenant_id'], $integrationId, $d['step_key'], $d['position'], $d['title'], $d['description'], $d['step_type'], $d['responsible_kind'], $d['responsible_user_id'], $d['status'], $d['due_at'], $d['is_required'], $d['started_at'], $d['completed_at'], $d['created_at'], $d['updated_at']]);

        return (int) $pdo->lastInsertId();
    }

    public static function integration(PDO $pdo, int $userId, string $status = 'in_progress'): int
    {
        $pdo->prepare('INSERT INTO member_integrations (tenant_id, user_id, status) VALUES (?, ?, ?)')->execute([self::TENANT, $userId, $status]);

        return (int) $pdo->lastInsertId();
    }

    private static function ago(int $days, string $time = '10:00:00'): string
    {
        return date('Y-m-d', strtotime(($days >= 0 ? '-' : '+') . abs($days) . ' days')) . ' ' . $time;
    }

    /**
     * Dossier d'un membre bien rempli : 18 étapes sur 6 parcours (recrutement, intégration, documents, formation,
     * fiabilisation, carrière) et deux étapes personnalisées, avec retards, un blocage, une étape facultative et des notes longues.
     *
     * @return array{user: int, integration: int, custom_late: int, custom_done: int, step_doc_late: int, step_blocked: int, step_optional: int, step_member: int}
     */
    public static function richMember(PDO $pdo, int $userId = 7): array
    {
        $pdo->prepare('INSERT INTO enlistments (tenant_id, submitter_user_id, status, reviewed_at, created_at) VALUES (?,?,?,?,?)')
            ->execute([self::TENANT, $userId, 'reviewed', self::ago(70), self::ago(82)]);
        $eid = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO enlistment_retro_feedbacks (tenant_id, enlistment_id, feedback_scope, created_at, updated_at) VALUES (?,?,?,?,?)')
            ->execute([self::TENANT, $eid, 'staff_one_month', self::ago(36), self::ago(36)]);

        $iid = self::integration($pdo, $userId);
        $s = [];
        $s['fiche'] = self::integrationStep($pdo, $iid, ['title' => 'Compléter sa fiche personnelle', 'step_type' => 'personnel_dossier', 'position' => 1, 'status' => 'completed', 'completed_at' => self::ago(60), 'started_at' => self::ago(68)]);
        $s['accueil'] = self::integrationStep($pdo, $iid, ['title' => 'Entretien d’accueil avec son référent', 'step_type' => 'appointment', 'responsible_kind' => 'referent', 'responsible_user_id' => 4, 'position' => 2, 'status' => 'completed', 'completed_at' => self::ago(55)]);
        $s['charte'] = self::integrationStep($pdo, $iid, ['title' => 'Lire et accepter la charte de la communauté', 'step_type' => 'document_read', 'position' => 3, 'status' => 'waiting_member', 'due_at' => self::ago(-3)]);
        $s['doc_late'] = self::integrationStep($pdo, $iid, ['title' => 'Lire le règlement des opérations', 'step_type' => 'document_read', 'position' => 4, 'status' => 'pending', 'due_at' => self::ago(5)]);
        $s['discord'] = self::integrationStep($pdo, $iid, ['title' => 'Rejoindre le serveur Discord', 'step_type' => 'task', 'position' => 5, 'status' => 'completed', 'completed_at' => self::ago(64)]);
        $s['optional'] = self::integrationStep($pdo, $iid, ['title' => 'Suivre la formation « Radio de base » (facultatif)', 'step_type' => 'lms_optional', 'responsible_kind' => 'member', 'position' => 6, 'status' => 'pending', 'is_required' => 0, 'due_at' => self::ago(-20)]);
        $s['blocked'] = self::integrationStep($pdo, $iid, ['title' => 'Validation par le chef d’unité', 'step_type' => 'manual_validation', 'responsible_kind' => 'rh', 'position' => 7, 'status' => 'blocked', 'due_at' => self::ago(2), 'description' => 'En attente du retour du chef de section.']);
        $s['bilan'] = self::integrationStep($pdo, $iid, ['title' => 'Bilan d’étape à 30 jours', 'step_type' => 'stage_bilan', 'responsible_kind' => 'instructor', 'position' => 8, 'status' => 'pending', 'due_at' => self::ago(-10)]);

        $pdo->prepare('INSERT INTO personnel_qualifications (tenant_id, user_id, qualification_name, admin_status, created_at, updated_at) VALUES (?,?,?,?,?,?)')
            ->execute([self::TENANT, $userId, 'Pilote d’hélicoptère léger', 'in_training', self::ago(30), self::ago(4)]);
        $pdo->prepare('INSERT INTO training_courses (tenant_id, title) VALUES (?, ?)')->execute([self::TENANT, 'Secourisme de combat']);
        $cid = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO training_enrollments (tenant_id, course_id, user_id, status, assigned_at, started_at, expires_at) VALUES (?,?,?,?,?,?,?)')
            ->execute([self::TENANT, $cid, $userId, 'in_progress', self::ago(20), self::ago(12), self::ago(-15)]);

        $pdo->prepare('INSERT INTO data_campaigns (tenant_id, title, status, due_on, opens_on, opened_at) VALUES (?,?,?,?,?,?)')
            ->execute([self::TENANT, 'Fiabilisation des données 2026', 'open', self::ago(-20), self::ago(10), self::ago(10)]);
        $camp = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO data_campaign_members (campaign_id, tenant_id, user_id, first_seen_at, updated_at) VALUES (?,?,?,?,?)')
            ->execute([$camp, self::TENANT, $userId, self::ago(3), self::ago(3)]);

        $pdo->prepare('INSERT INTO elevation_requests (tenant_id, target_user_id, kind, status, created_at, updated_at) VALUES (?,?,?,?,?,?)')
            ->execute([self::TENANT, $userId, 'grade', 'in_review', self::ago(9), self::ago(2)]);

        return [
            'user' => $userId, 'integration' => $iid, 'custom_late' => 0, 'custom_done' => 0,
            'step_doc_late' => $s['doc_late'], 'step_blocked' => $s['blocked'], 'step_optional' => $s['optional'], 'step_member' => $s['charte'],
        ];
    }
}
