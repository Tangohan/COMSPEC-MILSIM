<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\GameOperatorIdentity;
use App\Support\OperatorPortraits;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Profil du membre envoyé au jeu (téléphone ATAK, dossier de l'écran des rôles du mod COMSPEC Lobby) : la fonction est
 * l'emploi du membre dans la communauté de la session, jamais le poste de la mission ; le grade est celui de cette
 * communauté ; le portrait n'est jamais une image SSE privée. Base SQLite réelle, aucune doublure.
 */
final class GameOperatorIdentityTest extends TestCase
{
    private const SOAR = 7;
    private const OTHER = 9;
    private const JAKE = 41;
    private const AKIO = 42;

    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('CREATE TABLE grades (id INTEGER PRIMARY KEY, tenant_id INTEGER, label_short TEXT, label_long TEXT)');
        $this->pdo->exec('CREATE TABLE roles (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)');
        $this->pdo->exec('CREATE TABLE personnel_job_roles (id INTEGER PRIMARY KEY, tenant_id INTEGER, name TEXT)');
        $this->pdo->exec('CREATE TABLE personnel_profile_job_roles (id INTEGER PRIMARY KEY, tenant_id INTEGER, user_id INTEGER,
            personnel_job_role_id INTEGER, role_detail TEXT, is_primary INTEGER, sort_order INTEGER)');
        $this->pdo->exec("INSERT INTO grades VALUES (3, 7, 'A1C', 'Airman First Class'), (4, 9, 'Sgt', 'Sergent'), (5, 7, 'SrA', '')");
        $this->pdo->exec("INSERT INTO roles VALUES (2, 'Membre', 'member')");
        $this->pdo->exec("INSERT INTO personnel_job_roles VALUES (10, 7, 'TACP'), (11, 7, 'Instructeur'), (12, 9, 'Pilote'), (13, 7, 'Médecin')");
        // Jake (SOAR) : emploi principal TACP, emploi secondaire Instructeur ; dans l'autre communauté : Pilote.
        $this->pdo->exec("INSERT INTO personnel_profile_job_roles VALUES
            (1, 7, 41, 11, '', 0, 0),
            (2, 7, 41, 10, 'Gold Team', 1, 5),
            (3, 9, 41, 12, '', 1, 0),
            (4, 7, 42, 13, '', 1, 0)");
    }

    public function testFunctionIsTheMembersPrimaryJobOfTheSessionCommunity(): void
    {
        self::assertSame('TACP — Gold Team', GameOperatorIdentity::jobFunction($this->pdo, self::SOAR, self::JAKE));
    }

    public function testFunctionNeverComesFromAnotherCommunityNorFromAnotherMember(): void
    {
        self::assertSame('Pilote', GameOperatorIdentity::jobFunction($this->pdo, self::OTHER, self::JAKE));
        self::assertSame('Médecin', GameOperatorIdentity::jobFunction($this->pdo, self::SOAR, self::AKIO));
        // Akio n'a aucun emploi dans l'autre communauté : rien, surtout pas celui de Jake.
        self::assertSame('', GameOperatorIdentity::jobFunction($this->pdo, self::OTHER, self::AKIO));
    }

    public function testMemberWithoutJobHasAnEmptyFunctionSoTheGameHidesTheLine(): void
    {
        self::assertSame('', GameOperatorIdentity::jobFunction($this->pdo, self::SOAR, 99));
        self::assertSame('', GameOperatorIdentity::jobFunction($this->pdo, 0, self::JAKE));
        self::assertSame('', GameOperatorIdentity::jobFunction($this->pdo, self::SOAR, 0));
    }

    public function testJobRoleOfAnotherCommunityIsNotJoined(): void
    {
        // Ligne incohérente : l'emploi 12 appartient à l'autre communauté. Elle ne donne rien.
        $this->pdo->exec("INSERT INTO personnel_profile_job_roles VALUES (5, 7, 50, 12, '', 1, 0)");
        self::assertSame('', GameOperatorIdentity::jobFunction($this->pdo, self::SOAR, 50));
    }

    public function testGradeIsTheOneOfTheMemberRowForTheSessionCommunity(): void
    {
        // Ligne rendue par UserRepository::findById($id, $tenantId) : grade_id du profil de communauté.
        $inSoar = GameOperatorIdentity::gradeAndRole($this->pdo, ['id' => self::JAKE, 'tenant_id' => self::SOAR, 'grade_id' => 3, 'role_id' => 2]);
        self::assertSame('A1C', $inSoar['grade_short']);
        self::assertSame('Airman First Class', $inSoar['grade_long']);
        self::assertSame('Membre', $inSoar['role']);
        self::assertSame('member', $inSoar['role_slug']);

        $inOther = GameOperatorIdentity::gradeAndRole($this->pdo, ['id' => self::JAKE, 'tenant_id' => self::OTHER, 'grade_id' => 4]);
        self::assertSame('Sgt', $inOther['grade_short']);
        self::assertSame('', $inOther['role']);
    }

    public function testMemberWithoutGradeHasNoGrade(): void
    {
        foreach ([[], ['grade_id' => null], ['grade_id' => 0], ['grade_id' => ''], ['grade_id' => 999]] as $row) {
            $r = GameOperatorIdentity::gradeAndRole($this->pdo, $row);
            self::assertSame('', $r['grade_short']);
            self::assertSame('', $r['grade_long']);
        }
    }

    public function testMissingTablesGiveEmptyValuesNotAnError(): void
    {
        $empty = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        self::assertSame('', GameOperatorIdentity::jobFunction($empty, self::SOAR, self::JAKE));
        self::assertSame('', GameOperatorIdentity::gradeAndRole($empty, ['grade_id' => 3, 'role_id' => 2])['grade_short']);
    }

    public function testGameAuthProfileUsesTheCommunityRowAndNoLongerTheHomeAccountGrade(): void
    {
        $svc = str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Game/GameAuthService.php'));
        self::assertStringContainsString('GameOperatorIdentity::gradeAndRole(\App\Core\Database::getPdo(), $user)', $svc);
        self::assertStringContainsString('GameOperatorIdentity::jobFunction(\App\Core\Database::getPdo(), $tenantId, $userId)', $svc);
        self::assertStringContainsString('$extras = $this->loadOperatorExtras($user, $userId, $tenantId);', $svc);
        self::assertStringNotContainsString('LEFT JOIN grades g ON g.id = u.grade_id', $svc);
        self::assertStringContainsString("'function' => \$function,", $svc);
        self::assertStringContainsString("'grade' => \$grade,", $svc);
    }

    public function testOperatorPortraitIsNeverAPrivateSseImage(): void
    {
        foreach ([
            'uploads/portraits/41_1700000000.jpg',
            '/uploads/portraits/41_1700000000.png',
            'https://athena.example/public/uploads/portraits/41_1700000000.webp',
        ] as $ok) {
            self::assertTrue(OperatorPortraits::isPublicPortraitPath($ok), $ok);
        }
        foreach ([
            '',
            'sse-private/sse-id-photos/7_0123456789abcdef.jpg',
            'uploads/sse-id-photos/7_0123456789abcdef.jpg',
            '/uploads/sse-id-signatures/7_0123456789abcdef.png',
            'uploads/sse-id-backgrounds/7_0123456789abcdef.jpg',
            'uploads/sse-id-checks/7_0123456789abcdef.jpg',
            'uploads/sse-wanted/7_0123456789abcdef.jpg',
            'https://athena.example/public/uploads/sse-id-photos/7_0123456789abcdef.jpg',
            'storage/private/sse-images/sse-id-photos/7_0123456789abcdef.jpg',
            'uploads/portraits/../sse-id-photos/x.jpg',
            'UPLOADS\\SSE-ID-PHOTOS\\7_0123456789abcdef.jpg',
        ] as $ko) {
            self::assertFalse(OperatorPortraits::isPublicPortraitPath($ko), $ko);
        }
        $src = str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/app/Support/OperatorPortraits.php'));
        self::assertStringContainsString("if (!self::isPublicPortraitPath((string) (\$pick['row']['character_portrait_path'] ?? ''))) {", $src);
    }

    public function testEverySensitiveSseFolderIsCoveredByThePortraitGuard(): void
    {
        foreach (\App\Support\SseImageStorage::FOLDERS as $folder) {
            self::assertFalse(OperatorPortraits::isPublicPortraitPath('uploads/' . $folder . '/7_0123456789abcdef.jpg'), $folder);
            self::assertFalse(OperatorPortraits::isPublicPortraitPath(\App\Support\SseImageStorage::privatePath($folder, '7_0123456789abcdef.jpg')), $folder);
        }
    }
}
