<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\SseIdentityRegistryController;
use App\Controllers\Web\SseIdWorkshopController;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\SseIdDocumentRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseIdWorkshopService;
use App\Repositories\SseIdentityRepository;
use App\Services\Sse\SseIdentityHolderService;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseIdentitySqlite;

/**
 * Registre des papiers : le « titulaire » est le nom de l'identité qui porte le papier, pas celui d'un compte. Une identité civile
 * rattachée à un membre est une copie de son état civil qui peut se périmer ; une identité générée ou d'emprunt n'a pas à lui
 * ressembler. La liste dit de quelle nature est l'identité et signale, pour une identité civile, un nom différent de l'état civil.
 */
final class SseIdentityHolderTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = SseIdentitySqlite::open();
        $this->pdo->exec('CREATE TABLE user_profiles (user_id INTEGER PRIMARY KEY, first_name TEXT, last_name TEXT)');
        $this->pdo->exec("INSERT INTO users (id, tenant_id, steam_id, display_name) VALUES (10, 1, NULL, 'Joueur'), (20, 2, NULL, 'Autre communauté')");
        $this->pdo->exec("INSERT INTO user_profiles (user_id, first_name, last_name) VALUES (10, 'Jake', 'Gylenhall'), (20, 'Jake', 'Gylenhall')");
        $this->saved = ['session' => $_SESSION ?? null, 'post' => $_POST, 'get' => $_GET, 'server' => $_SERVER];
        $_SESSION = [
            'user_id' => 7, 'tenant_id' => 1, '_csrf_token' => 'jeton',
            'sse_tenant_id' => 1, 'sse_guest' => false, 'sse_clearance_until' => time() + 3600, 'sse_access_code_id' => 0,
            'sse_confidentiality_version' => SseAccessCodeService::CONFIDENTIALITY_VERSION, 'sse_confidentiality_tenant_id' => 1, 'sse_confidentiality_code_id' => 0,
        ];
        Gate::reset();
        Gate::getInstance()->setPermissions(['admin.access', 'atak.sse.case.manage', 'atak.sse.clearance.confidentiel', 'atak.sse.clearance.encadrement']);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['_csrf_token' => 'jeton'];
    }

    /** @var array<string, mixed> */
    private array $saved = [];

    protected function tearDown(): void
    {
        SseIdentitySqlite::close();
        $_POST = $this->saved['post'];
        $_GET = $this->saved['get'];
        $_SERVER = $this->saved['server'];
        if ($this->saved['session'] === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->saved['session'];
        }
        Gate::reset();
    }

    private function paper(string $last, string $first, string $kind, ?int $user, int $tenant = 1): int
    {
        $identity = (new SseIdentityRepository())->create($tenant, ['last_name' => $last, 'first_name' => $first, 'kind' => $kind, 'user_id' => $user]);

        return (new SseIdDocumentRepository())->create($tenant, [
            'identity_id' => $identity, 'doc_type' => 'id_card', 'country_code' => 'ALT', 'doc_number' => 'AB' . random_int(100000, 999999),
            'issued_on' => '2025-01-10', 'expires_on' => '2035-01-10',
        ]);
    }

    /** @return array<int, array<string, mixed>> le titulaire de chaque papier de la communauté 1, par identifiant de papier */
    private function holders(bool $truth = true): array
    {
        $docs = (new SseIdDocumentRepository())->list(1);
        $out = [];
        foreach ((new SseIdentityHolderService())->annotate(1, $docs, $truth) as $d) {
            $out[(int) $d['id']] = $d['holder'];
        }

        return $out;
    }

    public function testAStaleCivilNameIsFlaggedAgainstTheMemberCivilStatus(): void
    {
        $stale = $this->paper('Georgiou', 'Yannis', 'civil', 10);
        $same = $this->paper('GYLENHALL', 'jake', 'civil', 10);
        $swapped = $this->paper('Jake', 'Gylenhall', 'civil', 10);
        $holders = $this->holders();

        self::assertTrue($holders[$stale]['differs'], 'Georgiou n’est pas le nom d’état civil de Jake Gylenhall');
        self::assertSame(10, $holders[$stale]['member_id']);
        self::assertSame('Jake GYLENHALL', $holders[$stale]['member_name']);
        self::assertFalse($holders[$same]['differs'], 'Casse et accents ne comptent pas');
        self::assertFalse($holders[$swapped]['differs'], 'Prénom et nom intervertis : même personne');

        // Le membre change d'état civil : l'ancienne copie est aussitôt signalée, sans rien réécrire.
        $this->pdo->exec("UPDATE user_profiles SET first_name = 'Jacob', last_name = 'Gylen' WHERE user_id = 10");
        $holders = $this->holders();
        self::assertTrue($holders[$same]['differs']);
        self::assertSame('Jacob GYLEN', $holders[$same]['member_name']);
    }

    public function testGeneratedAndCoverIdentitiesAreNeverFlaggedAndSecretOnesStayHidden(): void
    {
        $generated = $this->paper('Georgiou', 'Yannis', 'generated', null);
        $linkedGenerated = $this->paper('Georgiou', 'Yannis', 'generated', 10);
        $cover = $this->paper('De-Aligeara', 'Oscar', 'cover', 10);
        $holders = $this->holders(true);

        self::assertFalse($holders[$generated]['differs']);
        self::assertNull($holders[$generated]['member_id'], 'Sans compte rattaché');
        self::assertSame('Identité générée', $holders[$generated]['kind_label']);
        self::assertFalse($holders[$linkedGenerated]['differs'], 'Une identité générée n’a pas à ressembler au membre');
        self::assertFalse($holders[$cover]['differs']);
        self::assertSame('Identité de couverture', $holders[$cover]['kind_label']);

        $blind = $this->holders(false);
        self::assertSame('', $blind[$cover]['kind_label'], 'Sans Confidentiel, la couverture n’est pas nommée');
        self::assertNull($blind[$cover]['member_id'], 'ni le membre qui la porte');
        self::assertSame('Identité générée', $blind[$generated]['kind_label']);
    }

    public function testAMemberOfAnotherCommunityOrWithoutCivilStatusIsNotCompared(): void
    {
        $foreign = $this->paper('Georgiou', 'Yannis', 'civil', 20, 1);
        $this->pdo->exec('DELETE FROM user_profiles WHERE user_id = 10');
        $blank = $this->paper('Georgiou', 'Yannis', 'civil', 10);
        $holders = $this->holders();

        self::assertNull($holders[$foreign]['member_id'], 'Cloison des communautés');
        self::assertFalse($holders[$blank]['differs'], 'Pas d’état civil renseigné : rien à comparer');
        self::assertSame('', $holders[$blank]['member_name']);
    }

    private function identity(string $kind, ?int $user, ?int $person): int
    {
        return (new SseIdentityRepository())->create(1, ['last_name' => 'Karimzadeh', 'first_name' => 'Élodie', 'kind' => $kind, 'user_id' => $user, 'person_id' => $person]);
    }

    private function docCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM sse_id_documents')->fetchColumn();
    }

    private function error(): string
    {
        return (string) Session::getFlash('error', '');
    }

    public function testTheHolderTypeSeparatesMembersTheServiceAndCheckedSubjects(): void
    {
        self::assertSame('subject', SseIdentityHolderService::holderType(['kind' => 'civil', 'person_id' => 5, 'user_id' => null]), 'sujet : identité civile d’une fiche personne, sans compte');
        self::assertSame('member', SseIdentityHolderService::holderType(['kind' => 'civil', 'person_id' => 5, 'user_id' => 10]), 'un compte rattaché prime');
        self::assertSame('service', SseIdentityHolderService::holderType(['kind' => 'cover', 'person_id' => 5, 'user_id' => null]));
        self::assertSame('service', SseIdentityHolderService::holderType(['kind' => 'forged', 'person_id' => 5, 'user_id' => null]));
        self::assertSame('service', SseIdentityHolderService::holderType(['kind' => 'generated', 'person_id' => 5, 'user_id' => null]), 'générée : préparée par le service');
        self::assertSame('service', SseIdentityHolderService::holderType(['kind' => 'civil', 'person_id' => null, 'user_id' => null]), 'ambiguë (ni compte ni fiche) : l’atelier reste ouvert');
    }

    public function testNoPaperIsGeneratedForACheckedSubjectEvenWithTheRight(): void
    {
        $subject = $this->identity('civil', null, 5);
        $controller = new SseIdentityRegistryController();

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['identity_id' => (string) $subject];
        $controller->documentCreateForm(new Request(), []);
        self::assertSame(SseIdentityHolderService::NO_ISSUE, $this->error());

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['_csrf_token' => 'jeton', 'identity_id' => (string) $subject, 'country_code' => 'ALT', 'doc_type' => 'id_card'];
        $controller->documentStore(new Request(), []);
        self::assertSame(SseIdentityHolderService::NO_ISSUE, $this->error());
        self::assertSame(0, $this->docCount());

        $res = (new SseIdWorkshopService())->save(1, ['country_code' => 'ALT', 'doc_type' => 'id_card'], (new SseIdentityRepository())->find($subject, 1), null, true, 7);
        self::assertFalse($res['ok']);
        self::assertSame(SseIdentityHolderService::NO_ISSUE, $res['error']);
        self::assertSame(0, $this->docCount());
    }

    public function testObservedPapersAreRecordedOnASubjectButNeverOnTheServiceIdentities(): void
    {
        $subject = $this->identity('civil', null, 5);
        $cover = $this->identity('cover', null, null);
        $controller = new SseIdentityRegistryController();
        $_POST = ['_csrf_token' => 'jeton', 'doc_type' => 'passport', 'country_code' => 'usa', 'doc_number' => 'us 47036925', 'authenticity' => 'fake', 'source' => 'seek', 'place' => 'Poste nord', 'seen_on' => '2026-11-10'];

        $controller->documentObserve(new Request(), ['id' => $subject]);
        $doc = (new SseIdDocumentRepository())->list(1)[0];
        self::assertSame('observed', $doc['workflow']);
        self::assertSame('Constaté', $doc['workflow_label']);
        self::assertSame('USA', $doc['country_code']);
        self::assertSame('US47036925', $doc['doc_number']);
        self::assertSame('fake', SseIdentityHolderService::authenticityOf($doc));
        self::assertStringContainsString('Poste nord', (string) $doc['notes']);
        self::assertStringContainsString('SEEK', (string) $doc['notes']);

        // Jamais d'émission ni de copie d'un papier constaté, même par l'atelier ; rien ne part en jeu.
        $_POST = ['_csrf_token' => 'jeton', 'to' => 'issued'];
        $controller->documentWorkflow(new Request(), ['id' => $doc['id']]);
        self::assertSame(SseIdentityHolderService::NO_ISSUE, $this->error());
        (new SseIdWorkshopController())->workshopCycle(new Request(), ['id' => $doc['id']]);
        self::assertSame(SseIdentityHolderService::NO_ISSUE, $this->error());
        self::assertNull((new SseIdWorkshopService())->duplicate(1, $doc, 7));
        self::assertSame('observed', (new SseIdDocumentRepository())->find((int) $doc['id'], 1)['workflow']);

        // Une identité préparée par le service ne reçoit pas de papier « constaté ».
        $_POST = ['_csrf_token' => 'jeton', 'doc_type' => 'id_card', 'country_code' => 'ALT', 'doc_number' => 'AB123456'];
        $controller->documentObserve(new Request(), ['id' => $cover]);
        self::assertStringContainsString('sujet contrôlé', $this->error());
        self::assertSame(1, $this->docCount());

        // Jeton périmé, droit manquant, numéro déjà pris : chaque refus se dit.
        $_POST = ['_csrf_token' => 'perime', 'country_code' => 'USA', 'doc_number' => 'ZZ1'];
        $controller->documentObserve(new Request(), ['id' => $subject]);
        self::assertStringContainsString('restée ouverte', $this->error());
        Gate::getInstance()->setPermissions(['atak.sse.view']);
        $_POST = ['_csrf_token' => 'jeton', 'country_code' => 'USA', 'doc_number' => 'ZZ1'];
        $controller->documentObserve(new Request(), ['id' => $subject]);
        self::assertStringContainsString('Droit manquant', $this->error());
        Gate::getInstance()->setPermissions(['admin.access', 'atak.sse.case.manage']);
        $_POST = ['_csrf_token' => 'jeton', 'doc_type' => 'passport', 'country_code' => 'USA', 'doc_number' => 'US47036925'];
        $controller->documentObserve(new Request(), ['id' => $subject]);
        self::assertStringContainsString('déjà enregistré', $this->error());
        self::assertSame(1, $this->docCount());
    }

    public function testPapersGeneratedBeforeOnASubjectStayVisibleButCannotAdvance(): void
    {
        $subject = $this->identity('civil', null, 5);
        $old = (new SseIdDocumentRepository())->create(1, ['identity_id' => $subject, 'doc_type' => 'id_card', 'country_code' => 'ALT', 'doc_number' => 'AB123456']);
        $controller = new SseIdentityRegistryController();
        $_POST = ['_csrf_token' => 'jeton', 'to' => 'validated'];
        $controller->documentWorkflow(new Request(), ['id' => $old]);

        self::assertSame(SseIdentityHolderService::NO_ISSUE, $this->error());
        $repo = new SseIdDocumentRepository();
        self::assertSame('draft', $repo->find($old, 1)['workflow'], 'Le papier n’est ni perdu ni avancé');
        self::assertCount(1, $repo->list(1, ['identity_id' => $subject]), 'Toujours affiché');
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/views/atak/sse/papers/identity_show.php');
        self::assertStringContainsString('Généré avant : lecture seule', $source);
        self::assertStringContainsString("'Papiers constatés'", $source);
    }

    public function testTheKeyIgnoresCaseAccentsOrderAndPunctuation(): void
    {
        self::assertSame(SseIdentityHolderService::key('Élodie De-Aligeara'), SseIdentityHolderService::key('DE ALIGEARA elodie'));
        self::assertNotSame(SseIdentityHolderService::key('Yannis Georgiou'), SseIdentityHolderService::key('Jake Gylenhall'));
    }

    public function testTheRegistryListShowsTheKindTheMemberAndTheCorrection(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/atak/sse/papers/documents.php');
        self::assertStringContainsString('Nom différent de l’état civil du membre', $view);
        self::assertStringContainsString('atak/sse/papiers/membres/', $view);
        $controller = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/SseIdentityRegistryController.php');
        self::assertStringContainsString('SseIdentityHolderService())->annotate(', $controller);
    }
}
