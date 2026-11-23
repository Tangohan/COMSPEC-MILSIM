<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\MilitarySpecialtyRepository;
use App\Services\Personnel\MilitarySpecialtyService;
use InvalidArgumentException;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class MilitarySpecialtyServiceTest extends TestCase
{
    private PDO $pdo;
    private MilitarySpecialtyService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $tables = [
            'tenants (id INTEGER PRIMARY KEY)',
            'users (id INTEGER PRIMARY KEY, tenant_id INT)',
            'user_community_memberships (tenant_id INT, user_id INT, status TEXT, PRIMARY KEY (tenant_id, user_id))',
            'countries (id INTEGER PRIMARY KEY, iso2 TEXT, name_fr TEXT, name_en TEXT)',
            'military_services (id INTEGER PRIMARY KEY, country_id INT, code TEXT, name TEXT)',
            'military_sources (id INTEGER PRIMARY KEY, name TEXT, url TEXT, publisher TEXT, published_at TEXT, checked_at TEXT)',
            'military_specialty_systems (id INTEGER PRIMARY KEY, service_id INT, system_key TEXT, name TEXT, personnel_category TEXT, source_id INT, archived_at TEXT)',
            'military_specialty_codes (id INTEGER PRIMARY KEY, system_id INT, reference_key TEXT, code TEXT, official_title TEXT, label_fr TEXT, label_en TEXT, kind TEXT, is_pattern INT, description TEXT, source_id INT, source_locator TEXT, verification_status TEXT, verified_at TEXT, valid_from TEXT, valid_until TEXT, archived_at TEXT)',
            'military_specialty_levels (id INTEGER PRIMARY KEY, specialty_code_id INT, level_code TEXT, official_title TEXT, label_fr TEXT, label_en TEXT, awarded_code TEXT, sort_order INT, source_id INT, source_locator TEXT, verification_status TEXT, verified_at TEXT, archived_at TEXT)',
            'personnel_job_roles (id INTEGER PRIMARY KEY, tenant_id INT, name TEXT)',
            'personnel_qualification_definitions (id INTEGER PRIMARY KEY, tenant_id INT, name TEXT)',
            'personnel_qualifications (id INTEGER PRIMARY KEY, tenant_id INT, user_id INT, status TEXT, admin_status TEXT)',
            'personnel_job_role_specialty_codes (tenant_id INT, job_role_id INT, specialty_code_id INT, created_by INT, created_at TEXT, updated_at TEXT, PRIMARY KEY (tenant_id,job_role_id,specialty_code_id))',
            'personnel_profile_specialty_codes (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INT, user_id INT, specialty_code_id INT, specialty_level_id INT, status TEXT, obtained_at TEXT, evidence_qualification_id INT, validated_by INT, validated_at TEXT, created_at TEXT, updated_at TEXT, UNIQUE (tenant_id,user_id,specialty_code_id))',
            'qualification_specialty_targets (tenant_id INT, definition_id INT, specialty_code_id INT, specialty_level_id INT, created_by INT, created_at TEXT, updated_at TEXT, PRIMARY KEY (tenant_id,definition_id,specialty_code_id))',
            'audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INT, user_id INT, action TEXT, entity_type TEXT, entity_id INT, old_value TEXT, new_value TEXT, created_at TEXT)',
        ];
        foreach ($tables as $table) {
            $this->pdo->exec('CREATE TABLE ' . $table);
        }
        $this->pdo->exec('INSERT INTO tenants VALUES (7),(8)');
        $this->pdo->exec('INSERT INTO users VALUES (10,7),(11,7),(12,8),(13,7),(99,8)');
        $this->pdo->exec("INSERT INTO user_community_memberships VALUES (7,10,'active'),(7,11,'active'),(7,12,'active'),(7,13,'left'),(8,99,'active')");
        $this->pdo->exec("INSERT INTO countries VALUES (1,'US','États-Unis','United States'),(2,'GB','Royaume-Uni','United Kingdom')");
        $this->pdo->exec("INSERT INTO military_services VALUES (1,1,'USAF','US Air Force'),(2,2,'RAF','Royal Air Force')");
        $this->pdo->exec("INSERT INTO military_sources VALUES (1,'Publication officielle','https://www.af.mil/reference','USAF','2025-01-01','2026-01-01')");
        $this->pdo->exec("INSERT INTO military_specialty_systems VALUES (1,1,'us-usaf-afsc-enlisted','AFSC','enlisted',1,NULL),(2,2,'gb-raf-trade','RAF trades','mixed',1,NULL)");
        $codes = [
            [1,1,'us-cct','1Z2X1','Combat Control','Contrôle aérien','Combat Control','specialty',1,'Un métier ; le X n’est pas remplacé sans source.',1,'verified'],
            [2,1,'us-pj','1Z1X1','Pararescue','Sauveteur','Pararescue','specialty',1,'Sauvetage.',1,'verified'],
            [3,2,'gb-group-8','08','RAF Regiment','RAF Regiment','RAF Regiment','trade_group',0,'Groupe historique.',1,'historical'],
            [4,2,'gb-no-code',null,'Regiment Gunner','Fusilier RAF','Regiment Gunner','trade',0,'Aucun code public confirmé.',null,'unverified'],
        ];
        $st = $this->pdo->prepare('INSERT INTO military_specialty_codes (id,system_id,reference_key,code,official_title,label_fr,label_en,kind,is_pattern,description,source_id,verification_status,verified_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
        foreach ($codes as $code) {
            $st->execute([...$code, '2026-01-01']);
        }
        $this->pdo->exec("INSERT INTO military_specialty_levels VALUES (10,1,'3','Apprentice','Apprenti','Apprentice',NULL,3,1,'CFETP','verified','2026-01-01',NULL),(11,1,'9','Superintendent','Supervision','Superintendent',NULL,9,NULL,NULL,'unverified',NULL,NULL),(20,2,'3','Apprentice','Apprenti','Apprentice',NULL,3,1,'CFETP','verified','2026-01-01',NULL)");
        $this->pdo->exec("INSERT INTO personnel_job_roles VALUES (1,7,'Opérateur'),(2,8,'Autre emploi')");
        $this->pdo->exec("INSERT INTO personnel_qualification_definitions VALUES (1,7,'Cours'),(2,8,'Autre cours')");
        $this->pdo->exec("INSERT INTO personnel_qualifications VALUES (1,7,10,'valid','obtained'),(2,7,11,'valid','obtained'),(3,8,10,'valid','obtained'),(4,7,10,'valid','in_training'),(5,7,10,'expired','obtained')");
        $this->service = new MilitarySpecialtyService($this->pdo);
    }

    private function input(array $overrides = []): array
    {
        return array_replace(['specialty_code_id' => 1, 'specialty_level_id' => 10, 'status' => 'confirmed', 'obtained_at' => '2025-11-01', 'evidence_qualification_id' => 1], $overrides);
    }

    public function testCatalogPreservesNumericStringsUnknownCodesAndExplicitLevelMeaning(): void
    {
        self::assertTrue($this->service->ready());
        $uk = $this->service->catalog('gb');
        self::assertSame([null, '08'], array_column($uk, 'code'));
        $cct = $this->service->findCode(1);
        self::assertSame('US', $cct['country_code']);
        self::assertSame('https://www.af.mil/reference', $cct['source_url']);
        self::assertSame(['3', '9'], array_column($cct['levels'], 'level_code'));
        self::assertNull($cct['levels'][0]['awarded_code']);
        $this->pdo->exec("UPDATE military_specialty_codes SET archived_at = '2026-01-01' WHERE id = 1");
        self::assertCount(1, $this->service->catalog('US'));
        self::assertNotNull($this->service->findCode(1));
    }

    public function testConfirmedSaveIsIdempotentAuditedAndDoesNotAwardAnythingElse(): void
    {
        $this->service->saveJobCodes(7, 1, [1,2,1], 99);
        $this->service->saveQualificationTargets(7, 1, [['specialty_code_id' => 1, 'specialty_level_id' => 10]], 99);
        self::assertSame([], $this->service->listForMember(7, 10));
        $id = $this->service->saveMember(7, 10, $this->input(), 99);
        self::assertSame($id, $this->service->saveMember(7, 10, $this->input(), 99));
        $this->service->saveJobCodes(7, 1, [2,1], 99);
        $this->service->saveQualificationTargets(7, 1, [['specialty_code_id' => 1, 'specialty_level_id' => 10]], 99);
        self::assertSame(3, $this->rowCount('audit_logs'));
        self::assertSame(5, $this->rowCount('personnel_qualifications'));
        self::assertSame(2, $this->rowCount('personnel_job_roles'));
        $member = $this->service->listForMember(7, 10)[0];
        self::assertSame('confirmed', $member['status']);
        self::assertSame(10, (int) $member['specialty_level_id']);
        self::assertSame(99, (int) $member['validated_by']);
        self::assertSame('3', $member['level_code']);
        self::assertNull($member['awarded_code']);
    }

    public function testCodeWithoutKnownLevelsOrPublicCodeCanBeDeclared(): void
    {
        $this->service->saveMember(7, 10, $this->input(['specialty_code_id' => 4, 'specialty_level_id' => null, 'status' => 'declared', 'evidence_qualification_id' => null]), 99);
        $member = $this->service->listForMember(7, 10)[0];
        self::assertNull($member['code']);
        self::assertNull($member['specialty_level_id']);
        self::assertNull($member['validated_by']);
        self::assertSame('declared', $member['status']);
    }

    public function testEmptyFormFieldsAndNullsHaveTheSameOptionalMeaning(): void
    {
        $id = $this->service->saveMember(7, 10, $this->input(['specialty_level_id' => '', 'obtained_at' => '', 'evidence_qualification_id' => '']), 99);
        self::assertSame($id, $this->service->saveMember(7, 10, $this->input(['specialty_level_id' => null, 'obtained_at' => null, 'evidence_qualification_id' => null]), 99));
        $row = $this->service->listForMember(7, 10)[0];
        foreach (['specialty_level_id', 'obtained_at', 'evidence_qualification_id'] as $field) {
            self::assertNull($row[$field]);
        }
        self::assertSame(1, $this->rowCount('audit_logs'));
    }

    public function testCrossSpecialtyLevelIsRejectedBeforeChangingAnExistingRecord(): void
    {
        $this->service->saveMember(7, 10, $this->input(), 99);
        $this->reject(fn () => $this->service->saveMember(7, 10, $this->input(['specialty_level_id' => 20]), 99));
        self::assertSame(10, (int) $this->service->listForMember(7, 10)[0]['specialty_level_id']);
        self::assertSame(1, $this->rowCount('audit_logs'));
        $this->reject(fn () => $this->service->saveQualificationTargets(7, 1, [['specialty_code_id' => 1, 'specialty_level_id' => 20]], 99));
        self::assertSame([], $this->service->qualificationTargets(7, 1));
    }

    public function testUnverifiedLevelIsDeclarableButCannotBeConfirmed(): void
    {
        $this->service->saveMember(7, 10, $this->input(['specialty_level_id' => 11, 'status' => 'declared']), 99);
        $this->reject(fn () => $this->service->saveMember(7, 10, $this->input(['specialty_level_id' => 11]), 99));
        self::assertSame('declared', $this->service->listForMember(7, 10)[0]['status']);
    }

    public function testConfirmedCodeRequiresExistingSourceAndVerificationDate(): void
    {
        foreach (["source_id = NULL", "source_id = 404", "verified_at = NULL", "verification_status = 'historical'", "verified_at = '9999-01-01'", "valid_until = '2000-01-01'"] as $mutation) {
            $this->pdo->exec("UPDATE military_specialty_codes SET source_id = 1, verified_at = '2026-01-01', verification_status = 'verified', valid_until = NULL WHERE id = 1");
            $this->pdo->exec('UPDATE military_specialty_codes SET ' . $mutation . ' WHERE id = 1');
            $this->reject(fn () => $this->service->saveMember(7, 10, $this->input(), 99));
        }
        self::assertSame(0, $this->rowCount('personnel_profile_specialty_codes'));
    }

    public function testProofMustBelongToSameMemberAndTenantAndBeObtainedForConfirmation(): void
    {
        foreach ([2,3,4,404] as $proofId) {
            $this->reject(fn () => $this->service->saveMember(7, 10, $this->input(['evidence_qualification_id' => $proofId]), 99));
        }
        self::assertSame(0, $this->rowCount('personnel_profile_specialty_codes'));
        $this->service->saveMember(7, 10, $this->input(['evidence_qualification_id' => 5]), 99);
        self::assertSame('confirmed', $this->service->listForMember(7, 10)[0]['status']);
    }

    public function testMembershipAcceptsActiveOtherHomeTenantButRejectsDepartedMember(): void
    {
        $this->service->saveMember(7, 12, $this->input(['evidence_qualification_id' => null]), 99);
        self::assertCount(1, $this->service->listForMember(7, 12));
        $this->reject(fn () => $this->service->saveMember(7, 13, $this->input(['evidence_qualification_id' => null]), 99));
        $this->reject(fn () => $this->service->saveMember(8, 10, $this->input(['evidence_qualification_id' => null]), 99));
        self::assertSame([], $this->service->listForMember(8, 12));
    }

    public function testCrossTenantJobQualificationAndArchiveAreRejected(): void
    {
        $this->reject(fn () => $this->service->saveJobCodes(7, 2, [1], 99));
        $this->reject(fn () => $this->service->saveQualificationTargets(7, 2, [['specialty_code_id' => 1]], 99));
        $id = $this->service->saveMember(7, 10, $this->input(), 99);
        $this->reject(fn () => $this->service->archiveMember(7, 11, $id, 99));
        self::assertSame([], $this->service->jobCodeIds(7, 2));
        self::assertSame([], $this->service->qualificationTargets(7, 2));
        self::assertSame('confirmed', $this->service->listForMember(7, 10)[0]['status']);
    }

    public function testInvalidDateStatusAndIdsNeverReachStorage(): void
    {
        foreach ([['obtained_at' => '2025-02-29'], ['obtained_at' => '9999-01-01'], ['obtained_at' => ['2025-01-01']],
            ['status' => 'admin'], ['status' => 'archived'], ['specialty_code_id' => '1x'], ['specialty_level_id' => '-1'],
            ['evidence_qualification_id' => '999999999999999999999999999999']] as $input) {
            $this->reject(fn () => $this->service->saveMember(7, 10, $this->input($input), 99));
        }
        $this->pdo->exec("UPDATE military_specialty_codes SET valid_from = '2025-01-01', valid_until = '2024-01-01' WHERE id = 1");
        $this->reject(fn () => $this->service->saveJobCodes(7, 1, [1], 99));
        self::assertSame(0, $this->rowCount('audit_logs'));
    }

    public function testArchivingHistoricalReferencesKeepsReadableDossierAndIsIdempotent(): void
    {
        $id = $this->service->saveMember(7, 10, $this->input(), 99);
        $this->pdo->exec("UPDATE military_specialty_codes SET archived_at = '2026-01-01' WHERE id = 1");
        $this->pdo->exec("UPDATE military_specialty_levels SET archived_at = '2026-01-01' WHERE id = 10");
        $this->service->archiveMember(7, 10, $id, 99);
        $this->service->archiveMember(7, 10, $id, 99);
        $member = $this->service->listForMember(7, 10)[0];
        self::assertSame('archived', $member['status']);
        self::assertSame('1Z2X1', $member['code']);
        self::assertSame('3', $member['level_code']);
        self::assertSame(2, $this->rowCount('audit_logs'));
        $this->reject(fn () => $this->service->saveMember(7, 10, $this->input(), 99));
    }

    public function testJobAndQualificationCanKeepOrDetachArchivedTargets(): void
    {
        $targets = [['specialty_code_id' => 1, 'specialty_level_id' => 10]];
        $this->service->saveJobCodes(7, 1, [1], 99);
        $this->service->saveQualificationTargets(7, 1, $targets, 99);
        $this->pdo->exec("UPDATE military_specialty_codes SET archived_at = '2026-01-01' WHERE id = 1");
        $this->service->saveJobCodes(7, 1, [1], 99);
        $this->service->saveQualificationTargets(7, 1, $targets, 99);
        self::assertSame(2, $this->rowCount('audit_logs'));
        $this->service->saveJobCodes(7, 1, [], 99);
        $this->service->saveQualificationTargets(7, 1, [], 99);
        self::assertSame([], $this->service->jobCodeIds(7, 1));
        self::assertSame([], $this->service->qualificationTargets(7, 1));
    }

    public function testInvalidBatchDoesNotPartiallyReplaceTargets(): void
    {
        $targets = [['specialty_code_id' => 1, 'specialty_level_id' => 10]];
        $this->service->saveQualificationTargets(7, 1, $targets, 99);
        $this->reject(fn () => $this->service->saveQualificationTargets(7, 1, [['specialty_code_id' => 2, 'specialty_level_id' => 20], ['specialty_code_id' => 1, 'specialty_level_id' => 20]], 99));
        self::assertSame([1], array_map('intval', array_column($this->service->qualificationTargets(7, 1), 'specialty_code_id')));
    }

    public function testCallerTransactionIsNeverCommittedAndFailureRollsBackOnlyServiceChanges(): void
    {
        $this->pdo->beginTransaction();
        $this->pdo->exec("UPDATE personnel_job_roles SET name = 'Modification appelante' WHERE id = 1");
        $this->service->saveJobCodes(7, 1, [1], 99);
        self::assertTrue($this->pdo->inTransaction());
        $this->pdo->exec("CREATE TRIGGER fail_specialty_audit BEFORE INSERT ON audit_logs BEGIN SELECT RAISE(ABORT, 'Journal indisponible'); END");
        try {
            $this->service->saveMember(7, 10, $this->input(), 99);
            self::fail('A write without its audit entry succeeded.');
        } catch (PDOException) {
            self::assertTrue($this->pdo->inTransaction());
        }
        self::assertSame(0, $this->rowCount('personnel_profile_specialty_codes'));
        self::assertSame('Modification appelante', $this->pdo->query('SELECT name FROM personnel_job_roles WHERE id=1')->fetchColumn());
        self::assertSame([1], $this->service->jobCodeIds(7, 1));
        $this->pdo->rollBack();
        self::assertSame([], $this->service->jobCodeIds(7, 1));
    }

    public function testMissingMigrationIsReadOnlyAndExplainedBeforeSaving(): void
    {
        $this->pdo->exec('DROP TABLE military_specialty_levels');
        self::assertFalse($this->service->ready());
        self::assertSame([], $this->service->catalog());
        self::assertSame([], (new MilitarySpecialtyRepository($this->pdo))->listForMember(7, 10));
        $this->reject(fn () => $this->service->saveJobCodes(7, 1, [1], 99));
        self::assertSame(2, $this->rowCount('personnel_job_roles'));
    }

    public function testSingleTargetEditsKeepOtherObjectivesAndStayIdempotent(): void
    {
        $this->service->changeQualificationTarget(7, 1, 1, 10, 99);
        $this->service->changeQualificationTarget(7, 1, 2, 20, 99);
        $this->service->changeQualificationTarget(7, 1, 2, 20, 99);
        self::assertSame([1,2], array_map('intval', array_column($this->service->qualificationTargets(7, 1), 'specialty_code_id')));
        self::assertSame(2, $this->rowCount('audit_logs'));
        $this->reject(fn () => $this->service->changeQualificationTarget(7, 1, 2, 10, 99));
        $this->reject(fn () => $this->service->changeQualificationTarget(7, 2, 1, 10, 99));
        self::assertSame([10,20], array_map('intval', array_column($this->service->qualificationTargets(7, 1), 'specialty_level_id')));
        $this->service->changeQualificationTarget(7, 1, 1, null, 99, true);
        self::assertSame([2], array_map('intval', array_column($this->service->qualificationTargets(7, 1), 'specialty_code_id')));
        self::assertSame([], $this->service->listForMember(7, 10));
        self::assertSame(5, $this->rowCount('personnel_qualifications'));
    }

    public function testPublicBatchIncludesOnlyRequestedConfirmedMembersInTheSameCommunityWithoutPrivateEvidence(): void
    {
        $this->service->saveMember(7, 10, $this->input(), 99);
        $this->service->saveMember(7, 11, $this->input(['specialty_code_id' => 2, 'specialty_level_id' => 20, 'evidence_qualification_id' => null]), 99);
        $this->service->saveMember(7, 10, $this->input(['specialty_code_id' => 2, 'specialty_level_id' => null, 'status' => 'declared', 'evidence_qualification_id' => null]), 99);
        // Même identifiant de personne dans une autre communauté : cette ligne ne doit pas être mêlée au résumé.
        $this->pdo->exec("INSERT INTO personnel_profile_specialty_codes (tenant_id,user_id,specialty_code_id,status) VALUES (8,10,2,'confirmed')");
        $public = $this->service->confirmedForMembers(7, [10,10,0,'incorrect']);
        self::assertSame([10], array_keys($public));
        self::assertSame([1], array_map('intval', array_column($public[10], 'specialty_code_id')));
        foreach (['evidence_qualification_id', 'validated_by', 'validated_at', 'obtained_at', 'tenant_id'] as $private) {
            self::assertArrayNotHasKey($private, $public[10][0]);
        }
        self::assertSame([], $this->service->confirmedForMembers(7, []));
        self::assertSame([], $this->service->confirmedForMembers(9, [10]));
    }

    public function testPublicBatchHidesWithdrawnUnverifiedAndArchivedReferences(): void
    {
        $this->service->saveMember(7, 10, $this->input(), 99);
        foreach ([
            "UPDATE personnel_profile_specialty_codes SET status='archived'",
            "UPDATE military_specialty_codes SET verification_status='historical' WHERE id=1",
            "UPDATE military_specialty_codes SET archived_at='2026-01-01' WHERE id=1",
            "UPDATE military_specialty_systems SET archived_at='2026-01-01' WHERE id=1",
            "UPDATE military_specialty_levels SET verification_status='unverified' WHERE id=10",
            "UPDATE military_specialty_levels SET archived_at='2026-01-01' WHERE id=10",
        ] as $mutation) {
            $this->pdo->beginTransaction();
            $this->pdo->exec($mutation);
            self::assertSame([], $this->service->confirmedForMembers(7, [10]), $mutation);
            $this->pdo->rollBack();
        }
        self::assertCount(1, $this->service->confirmedForMembers(7, [10])[10]);
    }

    public function testPublicBatchPreservesLevelZeroAndWorksBeforeTheMigration(): void
    {
        $this->pdo->exec("INSERT INTO military_specialty_levels VALUES (12,1,'0','Chief Enlisted Manager','Gestion de carrière','Chief Enlisted Manager',NULL,10,1,'AFECD','verified','2026-01-01',NULL)");
        $this->service->saveMember(7, 10, $this->input(['specialty_level_id' => 12]), 99);
        $public = $this->service->confirmedForMembers(7, [10]);
        self::assertSame('0', $public[10][0]['level_code']);
        self::assertStringContainsString('niveau 0', \App\Support\MilitarySpecialtyLabels::summary($public[10]));
        $this->pdo->exec('DROP TABLE military_specialty_levels');
        self::assertSame([], (new MilitarySpecialtyService($this->pdo))->confirmedForMembers(7, [10]));
    }

    private function reject(callable $work): void
    {
        try {
            $work();
            self::fail('An invalid specialty relationship was accepted.');
        } catch (InvalidArgumentException $e) {
            self::assertNotSame('', $e->getMessage());
        }
    }

    private function rowCount(string $table): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }
}
