<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseIdDocumentRepository;
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
    }

    protected function tearDown(): void
    {
        SseIdentitySqlite::close();
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
