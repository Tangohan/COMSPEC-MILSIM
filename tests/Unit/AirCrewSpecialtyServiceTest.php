<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tactical\AirCrewSpecialtyService as S;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Page /9-line : pilotes et JTAC lus dans les dossiers (qualifications, fonctions), sans sortir de la communauté.
 */
final class AirCrewSpecialtyServiceTest extends TestCase
{
    private const OWN = 7;

    private const FOREIGN = 3;

    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE personnel_qualifications (id INTEGER PRIMARY KEY, tenant_id INTEGER, user_id INTEGER, qualification_name TEXT, level TEXT,
            status TEXT, expires_at TEXT, admin_status TEXT, definition_id INTEGER, visibility_level TEXT)');
        $this->pdo->exec('CREATE TABLE personnel_qualification_definitions (id INTEGER PRIMARY KEY, tenant_id INTEGER, name TEXT, code TEXT, short_name TEXT)');
        $this->pdo->exec('CREATE TABLE personnel_job_roles (id INTEGER PRIMARY KEY, tenant_id INTEGER, name TEXT, slug TEXT)');
        $this->pdo->exec('CREATE TABLE personnel_profile_job_roles (id INTEGER PRIMARY KEY, tenant_id INTEGER, user_id INTEGER, personnel_job_role_id INTEGER)');
        $this->pdo->exec('CREATE TABLE pilot_qualification_rules (id INTEGER PRIMARY KEY, tenant_id INTEGER, definition_id INTEGER)');
        $this->pdo->exec('CREATE TABLE atak_pilot_time (tenant_id INTEGER, user_id INTEGER, seconds INTEGER)');
    }

    private function qual(int $tenant, int $user, string $name, string $status = 'valid', ?string $expires = null, string $admin = 'obtained', ?int $def = null, string $vis = 'normal'): void
    {
        $st = $this->pdo->prepare('INSERT INTO personnel_qualifications (tenant_id, user_id, qualification_name, status, expires_at, admin_status, definition_id, visibility_level) VALUES (?,?,?,?,?,?,?,?)');
        $st->execute([$tenant, $user, $name, $status, $expires, $admin, $def, $vis]);
    }

    private function role(int $tenant, int $user, string $name, string $slug = ''): void
    {
        $this->pdo->prepare('INSERT INTO personnel_job_roles (tenant_id, name, slug) VALUES (?,?,?)')->execute([$tenant, $name, $slug]);
        $this->pdo->prepare('INSERT INTO personnel_profile_job_roles (tenant_id, user_id, personnel_job_role_id) VALUES (?,?,?)')->execute([$tenant, $user, (int) $this->pdo->lastInsertId()]);
    }

    public function testLabelsAreClassifiedWithoutFalseFriends(): void
    {
        self::assertSame([S::KIND_PILOT], S::classify('Pilote hélicoptère'));
        self::assertSame([S::KIND_PILOT], S::classify('PIL-H'));
        self::assertSame([S::KIND_PILOT], S::classify('Chef de bord'));
        self::assertSame([S::KIND_JTAC], S::classify('Contrôleur aérien avancé (JTAC)'));
        self::assertSame([S::KIND_JTAC], S::classify('fires_jtac'));
        self::assertSame([S::KIND_JTAC, S::KIND_PILOT], S::classify('FAC(A) pilot'));
        self::assertSame([], S::classify('Télépilote de drone'));
        self::assertSame([], S::classify('Pilote de drone'));
        self::assertSame([], S::classify('Pilote de char Leclerc'));
        self::assertSame([], S::classify('Pilote de VAB'));
        self::assertSame([], S::classify('Infirmier'));
        self::assertSame([], S::classify(''));
    }

    public function testQualificationBeatsRoleAndTheBestOneIsKept(): void
    {
        $this->role(self::OWN, 5, 'Pilote');
        $this->qual(self::OWN, 5, 'Pilote hélicoptère', 'valid', '2099-01-01');
        $this->qual(self::OWN, 5, 'Pilote avion', 'expired', '2020-01-01');
        $this->pdo->exec("INSERT INTO atak_pilot_time VALUES (7, 5, 7200), (7, 5, 1800), (3, 5, 99999)");

        $spec = (new S($this->pdo))->forMembers(self::OWN, [5])[5][S::KIND_PILOT];
        self::assertSame(S::STATUS_QUALIFIED, $spec['status']);
        self::assertSame('Pilote hélicoptère', $spec['label']);
        self::assertSame('2099-01-01', $spec['expires_at']);
        self::assertSame(2.5, $spec['hours'], 'heures de cette communauté seulement');
    }

    public function testStatesOfAQualification(): void
    {
        $today = '2026-11-08';
        self::assertSame(S::STATUS_QUALIFIED, S::qualificationStatus('valid', 'obtained', '', $today));
        self::assertSame(S::STATUS_QUALIFIED, S::qualificationStatus('expiring', '', '2026-12-01', $today));
        self::assertSame(S::STATUS_EXPIRED, S::qualificationStatus('valid', 'obtained', '2026-01-01', $today));
        self::assertSame(S::STATUS_TRAINING, S::qualificationStatus('valid', 'in_training', '', $today));
        self::assertSame(S::STATUS_TRAINING, S::qualificationStatus('in_progress', '', '', $today));
        self::assertSame(S::STATUS_TRAINING, S::qualificationStatus('valid', 'obtained', '', $today, 'Élève pilote'));
        self::assertNull(S::qualificationStatus('valid', 'revoked', '', $today));
        self::assertNull(S::qualificationStatus('valid', 'suspended', '', $today));
    }

    public function testAnotherCommunityDoesNotLeak(): void
    {
        $this->qual(self::FOREIGN, 5, 'JTAC');
        $this->role(self::FOREIGN, 5, 'Pilote');
        self::assertSame([], (new S($this->pdo))->forMembers(self::OWN, [5]));
        self::assertSame([], (new S($this->pdo))->forMembers(0, [5]));
    }

    public function testMaskedQualificationIsIgnored(): void
    {
        $this->qual(self::OWN, 5, 'JTAC', 'valid', null, 'obtained', null, 'hidden');
        self::assertSame([], (new S($this->pdo))->forMembers(self::OWN, [5]));
    }

    public function testPilotCurriculumQualificationCountsWhateverItsName(): void
    {
        $this->pdo->exec("INSERT INTO personnel_qualification_definitions (id, tenant_id, name, code, short_name) VALUES (11, 7, 'Qualification UH-60', 'UH60', 'UH60')");
        $this->pdo->exec('INSERT INTO pilot_qualification_rules (tenant_id, definition_id) VALUES (7, 11)');
        $this->qual(self::OWN, 5, 'Qualification UH-60', 'valid', null, 'obtained', 11);
        $this->qual(self::OWN, 6, 'Qualification UH-60', 'valid', null, 'obtained', 12);
        $out = (new S($this->pdo))->forMembers(self::OWN, [5, 6]);
        self::assertSame('Qualification UH-60', $out[5][S::KIND_PILOT]['label']);
        self::assertArrayNotHasKey(6, $out, 'fiche hors cursus, nom sans rapport');
    }

    public function testMissingTablesGiveNothingInsteadOfAnError(): void
    {
        $empty = new PDO('sqlite::memory:');
        $empty->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::assertSame([], (new S($empty))->forMembers(self::OWN, [5]));
    }

    public function testRouteAndPageAreWired(): void
    {
        $root = dirname(__DIR__, 2);
        self::assertStringContainsString("'/9-line/equipages'", (string) file_get_contents($root . '/routes/web.php'));
        $js = (string) file_get_contents($root . '/public/assets/js/atak-nine-line.js');
        self::assertStringContainsString('/9-line/equipages', $js);
        self::assertStringContainsString('spec_pilot', $js);
        self::assertStringContainsString('author_check', $js);
        self::assertStringContainsString('id="nl-roster"', (string) file_get_contents($root . '/views/atak/nine_line_view.php'));
    }
}
