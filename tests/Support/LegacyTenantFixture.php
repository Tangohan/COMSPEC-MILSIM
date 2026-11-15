<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Jeu d'essai « très vieille communauté » : une base SQLite en mémoire aux tables d'avant les migrations d'octobre 2026
 * (ni adhésions de communauté, ni unités de membre, ni journal des reprises) et des lignes aux champs NULL, vides ou
 * anciens, comme on en trouve dans une communauté créée il y a des années.
 *
 * Communauté 7 (la vieille) et communauté 8 (une autre, qui ne doit jamais être touchée) :
 *  1  complet                  : prénom + nom, grade, unité, portrait, matricule
 *  2  « Viper »                : aucun profil de personnel, aucune unité, aucun grade, aucun prénom ni nom
 *  3  « Hawk »                 : profil avec nom de personnage « Jean Dupont », sans unité, sans portrait, sans fiche d'état civil
 *  4  « Ghost »                : nom de personnage d'un seul mot, fiche d'état civil existante mais vide, ancien matricule « OLD-17 »
 *  5  « Echo »                 : prénom seul « Marie » (nom vide) et nom de personnage « Marie Curie » : ne doit JAMAIS être écrasé
 *  6  compte technique, 7 compte supprimé, 8 compte suspendu : hors annuaire
 *  9  membre de la communauté 8
 * Dossiers de recrutement : accepté il y a 2 ans sans bilan (ancien), il y a 100 jours (récent, en retard),
 * il y a 20 jours (pas encore dû), il y a 400 jours avec bilan (rien à signaler).
 * Niveau d'accès de base encore nommé « Opérateur ».
 */
final class LegacyTenantFixture
{
    public const TENANT = 7;

    public const OTHER_TENANT = 8;

    /** 15 novembre 2026, midi : horloge fixe des tests. */
    public static function now(): int
    {
        return (int) strtotime('2026-11-15 12:00:00');
    }

    public static function daysAgo(int $days): string
    {
        return date('Y-m-d H:i:s', strtotime('-' . $days . ' days', self::now()) ?: self::now());
    }

    public static function create(bool $withImportLog = true): MysqlDdlSqlitePdo
    {
        $pdo = new MysqlDdlSqlitePdo();
        foreach ([
            'CREATE TABLE tenants (id int NOT NULL PRIMARY KEY, name varchar(100), created_at datetime, settings text)',
            'CREATE TABLE roles (id int NOT NULL PRIMARY KEY, tenant_id int, slug varchar(60), name varchar(100))',
            'CREATE TABLE users (id int NOT NULL PRIMARY KEY, tenant_id int, email varchar(190), display_name varchar(120), callsign varchar(60), status varchar(20), role_id int, grade_id int, deleted_at datetime, is_service_account int, created_at datetime)',
            'CREATE TABLE user_legal_identities (id int NOT NULL AUTO_INCREMENT PRIMARY KEY, tenant_id int NOT NULL, user_id int NOT NULL, first_name varchar(100), last_name varchar(100), phone varchar(50), birth_date date, nationality varchar(100), created_at datetime, updated_at datetime)',
            'CREATE TABLE personnel_profiles (id int NOT NULL AUTO_INCREMENT PRIMARY KEY, user_id int NOT NULL, character_name varchar(150), callsign varchar(100), primary_unit_id int, character_portrait_path varchar(255), matricule_internal varchar(100))',
            'CREATE TABLE personnel_extras (id int NOT NULL AUTO_INCREMENT PRIMARY KEY, user_id int NOT NULL, service_number varchar(50))',
            'CREATE TABLE personnel_assignments (id int NOT NULL AUTO_INCREMENT PRIMARY KEY, user_id int NOT NULL, unit_id int, status varchar(20), is_primary int, ended_at date)',
            'CREATE TABLE enlistments (id int NOT NULL AUTO_INCREMENT PRIMARY KEY, tenant_id int NOT NULL, first_name varchar(100), last_name varchar(100), status varchar(20), created_at datetime, reviewed_at datetime, reviewed_by int)',
            'CREATE TABLE enlistment_retro_feedbacks (id int NOT NULL AUTO_INCREMENT PRIMARY KEY, tenant_id int NOT NULL, enlistment_id int NOT NULL, feedback_scope varchar(40), author_user_id int, comment text, created_at datetime, auto_closed_at datetime)',
        ] as $ddl) {
            $pdo->exec($ddl);
        }
        if ($withImportLog) {
            // La table du journal vient de la vraie migration (et non d'un DDL écrit à la main).
            (new \App\Support\Migrations\SchemaInspector($pdo))->runSqlFile(dirname(__DIR__, 2) . '/migrations/2026_11_15_100_legacy_identity_import_log.sql');
        }
        $old = self::daysAgo(800);
        $ins = static function (string $sql, array $rows) use ($pdo): void {
            $st = $pdo->prepare($sql);
            foreach ($rows as $row) {
                $st->execute($row);
            }
        };
        $ins('INSERT INTO tenants (id, name, created_at, settings) VALUES (?, ?, ?, ?)', [
            [self::TENANT, 'Ancienne communauté', self::daysAgo(1500), null],
            [self::OTHER_TENANT, 'Autre communauté', self::daysAgo(30), null],
        ]);
        $ins('INSERT INTO roles (id, tenant_id, slug, name) VALUES (?, ?, ?, ?)', [
            [1, self::TENANT, 'member', 'Opérateur'],
            [2, self::OTHER_TENANT, 'member', 'Membre'],
        ]);
        $ins('INSERT INTO users (id, tenant_id, email, display_name, callsign, status, role_id, grade_id, deleted_at, is_service_account, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            [1, self::TENANT, 'alice@example.test', 'Alice Martin', 'Alpha', 'active', 1, 3, null, 0, $old],
            [2, self::TENANT, 'viper@example.test', 'viper_fr', 'Viper', 'active', 1, null, null, null, $old],
            [3, self::TENANT, 'hawk@example.test', 'hawk', 'Hawk', 'active', 1, 0, null, 0, $old],
            [4, self::TENANT, 'ghost@example.test', 'ghost', 'Ghost', 'active', 1, 2, null, 0, $old],
            [5, self::TENANT, 'echo@example.test', 'echo', 'Echo', 'active', 1, 2, null, 0, $old],
            [6, self::TENANT, 'bot@example.test', 'Compte technique', '', 'active', 1, null, null, 1, $old],
            [7, self::TENANT, 'gone@example.test', 'Supprimé', '', 'active', 1, null, self::daysAgo(10), 0, $old],
            [8, self::TENANT, 'sus@example.test', 'Suspendu', '', 'suspended', 1, null, null, 0, $old],
            [9, self::OTHER_TENANT, 'other@example.test', 'Autre', 'Zulu', 'active', 2, null, null, 0, $old],
        ]);
        $ins('INSERT INTO user_legal_identities (tenant_id, user_id, first_name, last_name, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)', [
            [self::TENANT, 1, 'Alice', 'Martin', $old, $old],
            [self::TENANT, 4, null, null, $old, $old],
            [self::TENANT, 5, 'Marie', '', $old, $old],
        ]);
        $ins('INSERT INTO personnel_profiles (user_id, character_name, callsign, primary_unit_id, character_portrait_path, matricule_internal) VALUES (?, ?, ?, ?, ?, ?)', [
            [1, 'Alice Martin', 'Alpha', 10, 'uploads/alice.jpg', 'AAA-001'],
            [3, 'Jean Dupont', 'Hawk', null, null, null],
            [4, 'Ghost', 'Ghost', null, '', null],
            [5, 'Marie Curie', 'Echo', null, '', null],
        ]);
        $ins('INSERT INTO personnel_extras (user_id, service_number) VALUES (?, ?)', [[4, 'OLD-17']]);
        $ins('INSERT INTO personnel_assignments (user_id, unit_id, status, is_primary, ended_at) VALUES (?, ?, ?, ?, ?)', [[1, 10, 'active', 1, null]]);
        $ins('INSERT INTO enlistments (id, tenant_id, first_name, last_name, status, created_at, reviewed_at, reviewed_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [
            [1, self::TENANT, 'Ancien', 'Candidat', 'reviewed', self::daysAgo(735), self::daysAgo(730), 1],
            [2, self::TENANT, 'Récent', 'Candidat', 'reviewed', self::daysAgo(102), self::daysAgo(100), 1],
            [3, self::TENANT, 'Nouveau', 'Candidat', 'reviewed', self::daysAgo(22), self::daysAgo(20), 1],
            [4, self::TENANT, 'Bilanté', 'Candidat', 'reviewed', self::daysAgo(405), self::daysAgo(400), 1],
            [5, self::OTHER_TENANT, 'Ailleurs', 'Candidat', 'reviewed', self::daysAgo(735), self::daysAgo(730), 1],
        ]);
        $ins('INSERT INTO enlistment_retro_feedbacks (tenant_id, enlistment_id, feedback_scope, author_user_id, comment, created_at, auto_closed_at) VALUES (?, ?, ?, ?, ?, ?, ?)', [
            [self::TENANT, 4, 'staff_one_month', 1, 'Très bien.', self::daysAgo(360), null],
        ]);

        return $pdo;
    }

    /**
     * Lignes d'annuaire des membres de la vieille communauté, telles que les écrans les reçoivent (colonnes NULL comprises).
     *
     * @return list<array<string, mixed>>
     */
    public static function directoryRows(): array
    {
        return [
            ['id' => 1, 'display_name' => 'Alice Martin', 'callsign' => 'Alpha', 'character_name' => 'Alice Martin', 'first_name' => 'Alice', 'last_name' => 'Martin', 'grade_short' => 'CPT', 'unit_name' => '1re Compagnie', 'status' => 'active'],
            ['id' => 2, 'display_name' => 'viper_fr', 'callsign' => 'Viper', 'character_name' => null, 'first_name' => null, 'last_name' => null, 'grade_short' => null, 'unit_name' => null, 'status' => 'active'],
            ['id' => 3, 'display_name' => 'hawk', 'callsign' => 'Hawk', 'character_name' => 'Jean Dupont', 'first_name' => null, 'last_name' => null, 'grade_short' => null, 'unit_name' => null, 'status' => 'active'],
            ['id' => 4, 'display_name' => 'ghost', 'callsign' => '', 'character_name' => 'Ghost', 'first_name' => '', 'last_name' => '', 'grade_short' => null, 'unit_name' => null, 'status' => null],
            ['id' => 5, 'display_name' => '', 'callsign' => null, 'character_name' => null, 'first_name' => 'Marie', 'last_name' => '', 'grade_short' => null, 'unit_name' => null, 'status' => 'active'],
            ['id' => 6, 'display_name' => null, 'callsign' => null, 'character_name' => null, 'first_name' => null, 'last_name' => null, 'grade_short' => null, 'unit_name' => null, 'status' => 'active'],
        ];
    }
}
