<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\MemberIdentityPapersController;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SseIdentityHistoryRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\UserProfileRepository;
use App\Services\Sse\MemberIdentitySyncService;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseIdentityHolderService;
use App\Services\Sse\SseMemberCivilIdentityBatch;
use App\Services\Sse\SseMemberCivilIdentityService as Civil;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseIdentitySqlite;
use Tests\Support\SseWantedSqlite;

/**
 * L'identité OFFICIELLE (civile) d'un membre suit son état civil : dérivation, date de naissance stable, alignement (identité,
 * papiers officiels, jamais les couvertures), historique restaurable, nom volontairement différent, doublons, compte supprimé,
 * mise à jour automatique au changement de nom, outil en ligne de commande (simulation, application, reprise, échec sans perte),
 * droits, jeton CSRF et cloison des communautés.
 */
final class SseMemberCivilIdentityTest extends TestCase
{
    private PDO $pdo;

    /** @var list<array<string, mixed>> */
    private array $audit = [];

    /** @var array<string, mixed> */
    private array $saved = [];

    private string $tmp = '';

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = SseIdentitySqlite::open();
        $this->pdo->sqliteCreateFunction('NOW', static fn (): string => gmdate('Y-m-d H:i:s'), 0);
        foreach (['callsign TEXT', 'email TEXT', 'created_at TEXT'] as $col) {
            $this->pdo->exec('ALTER TABLE users ADD COLUMN ' . $col);
        }
        $this->pdo->exec('CREATE TABLE user_profiles (user_id INTEGER PRIMARY KEY, first_name TEXT, last_name TEXT, birth_date TEXT, phone TEXT, created_at TEXT, updated_at TEXT)');
        $this->pdo->exec('CREATE TABLE personnel_profiles (id INTEGER PRIMARY KEY, user_id INT NOT NULL, tenant_id INT, birth_place TEXT, nationality TEXT, sex TEXT, clearance_level TEXT)');
        $this->pdo->exec('CREATE TABLE sse_audit_log (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INT, actor_user_id INT, actor_label TEXT, action TEXT, object_type TEXT, object_id INT, object_uuid TEXT, reason TEXT, before_json TEXT, after_json TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        SseWantedSqlite::createWantedTables($this->pdo);
        \App\Repositories\SseIdentityEventRepository::forgetReady();
        SseIdentitySqlite::migrate($this->pdo);
        Civil::forgetCapabilities();

        $this->pdo->exec("INSERT INTO users (id, tenant_id, display_name, callsign, email, created_at) VALUES
            (10, 1, 'jake#1', 'TA1', 'jake@example.test', '2025-03-04 10:00:00'),
            (11, 1, 'solo#2', 'TB2', 'solo@example.test', '2024-01-02 10:00:00'),
            (12, 1, 'anon', NULL, 'deleted-12-1@deleted.invalid', '2024-01-02 10:00:00'),
            (13, 1, 'rien', NULL, 'rien@example.test', '2024-01-02 10:00:00'),
            (20, 2, 'autre', 'ZZ9', 'autre@example.test', '2024-01-02 10:00:00')");
        $this->pdo->exec("INSERT INTO user_profiles (user_id, first_name, last_name, birth_date) VALUES
            (10, 'Jake', 'Gylenhall', '1979-05-05'), (11, 'Solo', NULL, NULL), (12, NULL, NULL, NULL), (13, NULL, NULL, NULL), (20, 'Jake', 'Gylenhall', NULL)");

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
        $this->audit = [];
        $this->tmp = sys_get_temp_dir() . '/civil-identity-' . bin2hex(random_bytes(4));
    }

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
        Civil::forgetCapabilities();
        if ($this->tmp !== '' && is_dir($this->tmp)) {
            foreach (glob($this->tmp . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($this->tmp);
        }
    }

    private function service(): Civil
    {
        return new Civil(null, null, function (array $e): void {
            $this->audit[] = $e;
        });
    }

    /** @param array<string, mixed> $extra */
    private function identity(string $kind, string $last, string $first, ?int $user, array $extra = []): int
    {
        return (new SseIdentityRepository())->create(1, $extra + [
            'kind' => $kind, 'last_name' => $last, 'first_name' => $first, 'user_id' => $user,
            'birth_date' => '1985-02-02', 'birth_place' => 'Kavala', 'nationality' => 'USA', 'sex' => 'M',
        ]);
    }

    private function paper(int $identity, string $country, string $number, string $origin = 'official', string $type = 'id_card'): int
    {
        return (new SseIdDocumentRepository())->create(1, [
            'identity_id' => $identity, 'doc_type' => $type, 'country_code' => $country, 'doc_number' => $number,
            'issued_on' => '2025-01-10', 'expires_on' => '2035-01-10', 'authority' => 'Autorité ' . $country, 'st_origin' => $origin,
        ]);
    }

    /** @return array<string, mixed> */
    private function row(int $identityId): array
    {
        return (array) $this->pdo->query('SELECT * FROM sse_identities WHERE id = ' . $identityId)->fetch(PDO::FETCH_ASSOC);
    }

    /** @return array{0: int, 1: int, 2: int, 3: int} identité Georgiou, papier USA, papier AUT, papier de couverture */
    private function georgiou(): array
    {
        $id = $this->identity('civil', 'Georgiou', 'Yannis', 10);
        $usa = $this->paper($id, 'USA', 'US47036925', 'official', 'passport');
        $aut = $this->paper($id, 'AUT', 'AT1234567');
        $cover = $this->identity('cover', 'De-Aligeara', 'Oscar', 10);
        $coverDoc = $this->paper($cover, 'USA', 'US99999999', 'cover');

        return [$id, $usa, $aut, $coverDoc];
    }

    // ------------------------------------------------------------------ Dérivation

    public function testTheOfficialIdentityIsDerivedFromTheCivilStatusNotFromTheRealBirthDate(): void
    {
        [$id] = $this->georgiou();
        $info = $this->service()->inspect(1, 10);
        self::assertSame(Civil::MISALIGNED, $info['state']);
        self::assertSame($id, $info['identity']['id']);
        self::assertSame('Jake', $info['target']['first_name']);
        self::assertSame('Gylenhall', $info['target']['last_name']);
        self::assertNotSame('1979-05-05', $info['target']['birth_date'], 'user_profiles.birth_date est la date réelle du joueur : jamais reprise');
        self::assertSame('generated', $info['sources']['birth_date']);
        self::assertSame(['last_name', 'first_name', 'birth_date', 'birth_place'], array_column($info['changes'], 'field'), 'le nom, le prénom, la naissance et le lieu de l’autre personne sont remplacés ensemble');
    }

    public function testBirthPlaceAndBirthDateComeFromTheCharacterWhenTheyExist(): void
    {
        $this->identity('civil', 'Georgiou', 'Yannis', 10);
        $this->pdo->exec("INSERT INTO personnel_profiles (user_id, tenant_id, birth_place) VALUES (10, 1, 'Boston')");
        $this->pdo->exec("UPDATE user_profiles SET character_birth_date = '1991-07-14' WHERE user_id = 10");
        $info = $this->service()->inspect(1, 10);
        self::assertSame('1991-07-14', $info['target']['birth_date']);
        self::assertSame('profile', $info['sources']['birth_date']);
        self::assertSame('Boston', $info['target']['birth_place']);
        self::assertSame('profile', $info['sources']['birth_place']);
    }

    public function testNameRuleFallsBackLikeTheUnifiedRuleAndNeverOverwritesWithACallsign(): void
    {
        // Prénom seul : il devient le nom de l'identité (comme pour une fiche personne).
        $solo = $this->identity('civil', 'Autre', 'Quelqu’un', 11);
        $info = $this->service()->inspect(1, 11);
        self::assertSame(Civil::MISALIGNED, $info['state']);
        self::assertSame('Solo', $info['target']['last_name']);
        self::assertSame('', $info['target']['first_name']);

        // Aucun état civil : l'identité existante n'est pas remplacée par l'indicatif.
        $blank = $this->identity('civil', 'Georgiou', 'Yannis', 13);
        $info = $this->service()->inspect(1, 13);
        self::assertSame(Civil::NO_CIVIL_STATE, $info['state']);
        $res = $this->service()->align(1, 13, 7);
        self::assertFalse($res['ok']);
        self::assertSame('Georgiou', $this->row($blank)['last_name']);
        self::assertSame('Autre', $this->row($solo)['last_name'], 'inspecter ne modifie rien');
    }

    public function testBirthDateIsDeterministicStableAndPlausible(): void
    {
        $a = Civil::generateBirthDate(1, 10, '2025-03-04 10:00:00');
        self::assertSame($a, Civil::generateBirthDate(1, 10, '2025-03-04 10:00:00'));
        self::assertNotSame($a, Civil::generateBirthDate(2, 10, '2025-03-04 10:00:00'), 'la communauté entre dans la graine');
        self::assertNotSame($a, Civil::generateBirthDate(1, 11, '2025-03-04 10:00:00'), 'le membre aussi');
        for ($u = 1; $u <= 200; $u++) {
            $age = 2025 - (int) substr(Civil::generateBirthDate(1, $u, '2025-06-01'), 0, 4);
            self::assertGreaterThanOrEqual(24, $age);
            self::assertLessThanOrEqual(45, $age);
            self::assertNotSame('', Civil::validDate(Civil::generateBirthDate(1, $u, null)));
        }
        // Stable : posée une fois sur le profil, la même date revient, quoi que fasse le profil ensuite.
        [$id] = $this->georgiou();
        $before = $this->service()->inspect(1, 10)['target']['birth_date'];
        self::assertTrue($this->service()->align(1, 10, 7)['ok']);
        self::assertSame($before, (string) $this->pdo->query('SELECT character_birth_date FROM user_profiles WHERE user_id = 10')->fetchColumn());
        self::assertSame($before, substr((string) $this->row($id)['birth_date'], 0, 10));
        $this->pdo->exec("UPDATE user_profiles SET first_name = 'Jacob' WHERE user_id = 10");
        self::assertSame($before, $this->service()->inspect(1, 10)['target']['birth_date']);
    }

    // ------------------------------------------------------------------ Alignement

    public function testAligningRewritesTheOfficialIdentityAndOnlyTheOfficialPapers(): void
    {
        [$id, $usa, $aut, $coverDoc] = $this->georgiou();
        $coverIdentity = (int) $this->pdo->query("SELECT id FROM sse_identities WHERE kind = 'cover'")->fetchColumn();
        $coverBefore = $this->row($coverIdentity);

        $res = $this->service()->align(1, 10, 7, 'manual', $id);
        self::assertTrue($res['ok'], $res['message']);
        $row = $this->row($id);
        self::assertSame(['Gylenhall', 'Jake'], [$row['last_name'], $row['first_name']]);
        self::assertSame($row['birth_date'] !== '1985-02-02', true);
        self::assertSame(2, $res['documents'], 'papier USA et papier AUT');
        self::assertNotNull($row['civil_synced_at']);

        $docs = new SseIdDocumentRepository();
        $us = $docs->find($usa, 1);
        self::assertSame('US47036925', $us['doc_number'], 'numéro inchangé');
        self::assertSame('Autorité USA', $us['authority']);
        self::assertSame('2035-01-10', $us['expires_on']);
        self::assertStringContainsString('Identité mise à jour le ' . gmdate('Y-m-d'), (string) $us['notes']);
        self::assertStringContainsString('Identité mise à jour le', (string) $docs->find($aut, 1)['notes']);
        self::assertNull($docs->find($coverDoc, 1)['notes'], 'un papier de couverture ne bouge pas');
        self::assertSame($coverBefore, $this->row($coverIdentity), 'l’identité de couverture non plus');

        // Aperçu dans le registre : plus de nom différent.
        $holders = (new SseIdentityHolderService())->annotate(1, $docs->list(1, ['identity_id' => $id]), true);
        self::assertFalse($holders[0]['holder']['differs']);
        self::assertSame('aligned', $this->service()->inspect(1, 10)['state']);
        self::assertSame('id_member.civil_align', $this->audit[0]['action']);
    }

    public function testOldValuesGoToTheRestorableHistoryAndTheTimeline(): void
    {
        [$id] = $this->georgiou();
        self::assertTrue($this->service()->align(1, 10, 7)['ok']);
        $rows = (new SseIdentityHistoryRepository())->listForIdentities(1, 10, [$id]);
        $byField = [];
        foreach ($rows as $r) {
            $byField[$r['field']] = $r;
        }
        self::assertSame('Georgiou', $byField['last_name']['lost_value']);
        self::assertSame('Gylenhall', $byField['last_name']['kept_value']);
        self::assertSame('Yannis', $byField['first_name']['lost_value']);
        self::assertSame('1985-02-02', $byField['birth_date']['lost_value']);
        self::assertSame('Kavala', $byField['birth_place']['lost_value']);
        self::assertSame(['athena', 'civil'], [$byField['last_name']['lost_side'], $byField['last_name']['decision']]);

        $events = array_column((new \App\Repositories\SseIdentityEventRepository())->listForIdentity(1, $id, ['identity.civil_aligned']), 'summary');
        self::assertStringContainsString('Aligné sur l’état civil du membre', $events[0]);

        // Restaurable avec l'outil existant : le nom d'avant revient, et il est dès lors voulu (plus jamais réécrit d'office).
        $res = (new MemberIdentitySyncService())->restore(1, 10, (int) $byField['last_name']['id'], true, 7);
        self::assertTrue($res['ok'], $res['message']);
        self::assertSame('Georgiou', $this->row($id)['last_name']);
        self::assertSame(1, (int) $this->row($id)['name_override']);
        self::assertSame(Civil::OVERRIDE, $this->service()->inspect(1, 10)['state']);
    }

    public function testAligningTwiceChangesNothing(): void
    {
        [$id] = $this->georgiou();
        self::assertTrue($this->service()->align(1, 10, 7)['ok']);
        $history = (int) $this->pdo->query('SELECT COUNT(*) FROM sse_identity_history')->fetchColumn();
        $events = (int) $this->pdo->query('SELECT COUNT(*) FROM sse_identity_events')->fetchColumn();
        $after = $this->row($id);
        $again = $this->service()->align(1, 10, 7);
        self::assertTrue($again['ok']);
        self::assertSame('aligned', $again['status']);
        self::assertSame([$history, $events, $after], [(int) $this->pdo->query('SELECT COUNT(*) FROM sse_identity_history')->fetchColumn(), (int) $this->pdo->query('SELECT COUNT(*) FROM sse_identity_events')->fetchColumn(), $this->row($id)]);
    }

    public function testAnIdentityWhoseNameIsAlreadyRightKeepsItsOwnBirthData(): void
    {
        $id = $this->identity('civil', 'GYLENHALL', 'jake', 10, ['birth_date' => '1990-03-03', 'birth_place' => 'Denver']);
        $info = $this->service()->inspect(1, 10);
        self::assertSame(Civil::ALIGNED, $info['state'], 'la casse ne compte pas : rien n’est réécrit');
        // Ce que le responsable a saisi est repris sur le profil au premier alignement qui l'exige, jamais remplacé par un tirage.
        $this->pdo->exec("UPDATE user_profiles SET character_birth_date = '1991-01-01' WHERE user_id = 10");
        $info = $this->service()->inspect(1, 10);
        self::assertSame(['birth_date'], array_column($info['changes'], 'field'), 'le profil du personnage fait foi');
        self::assertTrue($this->service()->align(1, 10, 7)['ok']);
        self::assertSame(['GYLENHALL', 'jake', '1991-01-01', 'Denver'], [$this->row($id)['last_name'], $this->row($id)['first_name'], $this->row($id)['birth_date'], $this->row($id)['birth_place']]);
    }

    // ------------------------------------------------------------------ Cas limites

    public function testAVoluntarilyDifferentNameIsNeverOverwritten(): void
    {
        [$id] = $this->georgiou();
        self::assertTrue($this->service()->setOverride(1, 10, $id, true, 7)['ok']);
        self::assertSame(Civil::OVERRIDE, $this->service()->inspect(1, 10)['state']);
        $res = $this->service()->align(1, 10, 7);
        self::assertFalse($res['ok']);
        self::assertSame('Georgiou', $this->row($id)['last_name']);

        // Même un changement de nom du membre (voie automatique) respecte le réglage.
        $this->pdo->exec("UPDATE user_profiles SET last_name = 'Gylen' WHERE user_id = 10");
        Civil::onProfileChanged(10);
        self::assertSame('Georgiou', $this->row($id)['last_name']);

        // Le registre ne le signale plus comme écart, et le dit.
        $holder = (new SseIdentityHolderService())->annotate(1, (new SseIdDocumentRepository())->list(1, ['identity_id' => $id]), true)[0]['holder'];
        self::assertSame([false, true], [$holder['differs'], $holder['override']]);

        // Retiré, il se réaligne.
        self::assertTrue($this->service()->setOverride(1, 10, $id, false, 7)['ok']);
        self::assertTrue($this->service()->align(1, 10, 7)['ok']);
        self::assertSame('Gylen', $this->row($id)['last_name']);
        self::assertContains('id_member.civil_override_on', array_column($this->audit, 'action'), 'journalisé');
    }

    public function testSeveralCivilIdentitiesAreReportedNotMerged(): void
    {
        $old = $this->identity('civil', 'Georgiou', 'Yannis', 10);
        $young = $this->identity('civil', 'Papadakis', 'Nikos', 10);
        $info = $this->service()->inspect(1, 10);
        self::assertSame($old, $info['identity']['id'], 'la plus ancienne est l’identité officielle');
        self::assertSame([$young], $info['duplicates']);
        self::assertTrue($this->service()->align(1, 10, 7)['ok']);
        self::assertSame('Gylenhall', $this->row($old)['last_name']);
        self::assertSame('Papadakis', $this->row($young)['last_name'], 'l’autre n’est pas touchée ni fusionnée');
        self::assertSame(2, (int) $this->pdo->query("SELECT COUNT(*) FROM sse_identities WHERE kind = 'civil'")->fetchColumn());
    }

    public function testAMemberWithoutOfficialIdentityGetsADerivedOneOnlyOnRequest(): void
    {
        $info = $this->service()->inspect(1, 10);
        self::assertSame(Civil::MISSING, $info['state']);
        self::assertSame(['Jake', 'Gylenhall'], [$info['target']['first_name'], $info['target']['last_name']]);

        // Un changement de nom ne crée jamais d'identité d'office (AUTO_CREATE désactivé).
        $this->pdo->exec("UPDATE user_profiles SET last_name = 'Gylen' WHERE user_id = 10");
        Civil::onProfileChanged(10);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_identities')->fetchColumn());
        self::assertFalse($this->service()->align(1, 10, 7, 'manual', null, false)['ok']);

        $res = $this->service()->align(1, 10, 7);
        self::assertTrue($res['ok'], $res['message']);
        $row = $this->row($res['identity_id']);
        self::assertSame(['civil', 10, 'Gylen', 'Jake'], [$row['kind'], (int) $row['user_id'], $row['last_name'], $row['first_name']]);
        self::assertSame('Identité générée à partir de l’état civil du membre.', $row['notes']);
        self::assertNotSame('', (string) $row['birth_date']);
    }

    public function testADeletedAccountIsNeverNamedNorAligned(): void
    {
        $id = $this->identity('civil', 'Georgiou', 'Yannis', 12);
        $info = $this->service()->inspect(1, 12);
        self::assertSame(Civil::ANONYMIZED, $info['state']);
        self::assertSame([], $info['changes']);
        self::assertSame([], $info['target'], 'aucun nom proposé');
        self::assertFalse($this->service()->align(1, 12, 7)['ok']);
        self::assertSame('Georgiou', $this->row($id)['last_name']);
        $out = (new SseMemberCivilIdentityBatch(null, $this->service()))->run(false, 1);
        self::assertSame(1, $out['totals']['anonymized']);
        self::assertStringNotContainsString('Georgiou', json_encode($out['rows']));
    }

    public function testOnlyTheMembersOwnCommunityIsReached(): void
    {
        $foreign = (new SseIdentityRepository())->create(1, ['kind' => 'civil', 'last_name' => 'Georgiou', 'first_name' => 'Yannis', 'user_id' => 20]);
        self::assertSame(Civil::UNKNOWN, $this->service()->inspect(1, 20)['state'], 'membre d’une autre communauté');
        self::assertFalse($this->service()->align(1, 20, 7)['ok']);
        self::assertSame('Georgiou', $this->row($foreign)['last_name']);
    }

    public function testOnlyCivilIdentitiesOfMembersAreEverAligned(): void
    {
        $generated = $this->identity('generated', 'Georgiou', 'Yannis', 10);
        $alias = $this->identity('alias', 'Papadakis', 'Nikos', 10);
        $forged = $this->identity('forged', 'Makris', 'Petros', 10);
        $subject = (new SseIdentityRepository())->create(1, ['kind' => 'civil', 'last_name' => 'Karimzadeh', 'first_name' => 'Élodie', 'person_id' => 5]);
        $before = [$this->row($generated), $this->row($alias), $this->row($forged), $this->row($subject)];
        $out = (new SseMemberCivilIdentityBatch(null, $this->service()))->run(true, 1, null, false);
        self::assertSame($before, [$this->row($generated), $this->row($alias), $this->row($forged), $this->row($subject)]);
        self::assertSame(0, $out['totals']['applied']);
        self::assertSame(0, $out['totals']['scanned'], 'aucune identité civile de membre à examiner');
    }

    public function testNoHistoryNoWrite(): void
    {
        [$id] = $this->georgiou();
        $this->pdo->exec('DROP TABLE sse_identity_history');
        $this->pdo->exec('DROP TABLE sse_identity_events');
        Civil::forgetCapabilities();
        \App\Repositories\SseIdentityEventRepository::forgetReady();
        $res = $this->service()->align(1, 10, 7);
        self::assertFalse($res['ok']);
        self::assertStringContainsString('historique', $res['message']);
        self::assertSame('Georgiou', $this->row($id)['last_name'], 'rien n’est écrit sans pouvoir garder l’ancienne valeur');
    }

    public function testDegradedModeWithoutTheNewColumnsStillAlignsAndExplains(): void
    {
        [$id] = $this->georgiou();
        $this->pdo->exec('ALTER TABLE sse_identities DROP COLUMN name_override');
        $this->pdo->exec('ALTER TABLE sse_identities DROP COLUMN civil_synced_at');
        $this->pdo->exec('ALTER TABLE user_profiles DROP COLUMN character_birth_date');
        Civil::forgetCapabilities();
        $info = $this->service()->inspect(1, 10);
        self::assertSame(['name_override', 'character_birth_date'], $info['degraded']);
        self::assertFalse($info['can_override']);
        $a = $info['target']['birth_date'];
        self::assertTrue($this->service()->align(1, 10, 7)['ok']);
        self::assertSame($a, substr((string) $this->row($id)['birth_date'], 0, 10), 'la même date qu’avec la colonne : elle se recalcule à l’identique');
        $res = $this->service()->setOverride(1, 10, $id, true, 7);
        self::assertFalse($res['ok']);
        self::assertStringContainsString('4700', $res['message']);
    }

    public function testAFailureMidWayLeavesNothingBehind(): void
    {
        [$id, $usa] = $this->georgiou();
        $this->pdo->exec("CREATE TRIGGER civil_boom BEFORE UPDATE ON sse_id_documents WHEN NEW.id = {$usa} BEGIN SELECT RAISE(ABORT, 'disque plein'); END");
        $before = [$this->row($id), (int) $this->pdo->query('SELECT COUNT(*) FROM sse_identity_history')->fetchColumn(), (int) $this->pdo->query('SELECT COUNT(*) FROM sse_identity_events')->fetchColumn()];
        $res = $this->service()->align(1, 10, 7);
        self::assertFalse($res['ok']);
        self::assertSame('error', $res['status']);
        self::assertStringContainsString('rien n’a été modifié', $res['message']);
        self::assertSame($before, [$this->row($id), (int) $this->pdo->query('SELECT COUNT(*) FROM sse_identity_history')->fetchColumn(), (int) $this->pdo->query('SELECT COUNT(*) FROM sse_identity_events')->fetchColumn()]);
        self::assertFalse($this->pdo->inTransaction());
    }

    // ------------------------------------------------------------------ Mise à jour automatique

    public function testChangingTheNameUpdatesTheOfficialIdentityAndItsPapersButNotTheCovers(): void
    {
        [$id, $usa, , $coverDoc] = $this->georgiou();
        self::assertTrue($this->service()->align(1, 10, 7)['ok']);
        $coverIdentity = (int) $this->pdo->query("SELECT id FROM sse_identities WHERE kind = 'cover'")->fetchColumn();
        $coverBefore = $this->row($coverIdentity);
        $dob = substr((string) $this->row($id)['birth_date'], 0, 10);

        // Dialogue « État civil du personnage », fiche, fiabilisation : tout passe par UserProfileRepository::upsert.
        (new UserProfileRepository())->upsert(10, ['first_name' => 'Jacob', 'last_name' => 'Gylen']);
        $row = $this->row($id);
        self::assertSame(['Gylen', 'Jacob', $dob], [$row['last_name'], $row['first_name'], substr((string) $row['birth_date'], 0, 10)]);
        self::assertSame(substr_count((string) (new SseIdDocumentRepository())->find($usa, 1)['notes'], 'Identité mise à jour le'), 2, 'une mention par mise à jour');
        self::assertSame($coverBefore, $this->row($coverIdentity));
        self::assertNull((new SseIdDocumentRepository())->find($coverDoc, 1)['notes']);

        // Un autre champ du profil, ou le même nom, ne touche à rien.
        $stamp = $this->row($id)['updated_at'];
        (new UserProfileRepository())->upsert(10, ['first_name' => 'Jacob', 'last_name' => 'Gylen', 'phone' => '0102030405']);
        self::assertSame($stamp, $this->row($id)['updated_at']);
    }

    public function testASaveNeverFailsBecauseOfTheIdentityRegistry(): void
    {
        $this->pdo->exec('DROP TABLE sse_identities');
        (new UserProfileRepository())->upsert(10, ['first_name' => 'Jacob']);
        self::assertSame('Jacob', $this->pdo->query('SELECT first_name FROM user_profiles WHERE user_id = 10')->fetchColumn());
    }

    // ------------------------------------------------------------------ Outil en ligne de commande

    public function testTheBatchSimulatesByDefaultThenAppliesWithAJournalAndIsIdempotent(): void
    {
        [$id] = $this->georgiou();
        $log = $this->tmp . '/journal.jsonl';
        $dry = (new SseMemberCivilIdentityBatch(null, $this->service(), $log))->run(false, 1);
        self::assertSame('Georgiou', $this->row($id)['last_name']);
        self::assertFileDoesNotExist($log, 'la simulation n’écrit rien, pas même un journal');
        self::assertSame(1, $dry['totals']['misaligned']);
        $row = $dry['rows'][0];
        self::assertSame(['dry-run', 'Yannis GEORGIOU', 'Jake GYLENHALL'], [$row['result'], $row['registry'], $row['profile']]);
        self::assertCount(2, $row['documents'], 'les papiers officiels concernés sont listés');
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_identity_history')->fetchColumn());

        $run = (new SseMemberCivilIdentityBatch(null, $this->service(), $log))->run(true, 1);
        self::assertSame([1, 0], [$run['totals']['applied'], $run['totals']['errors']]);
        self::assertSame('Gylenhall', $this->row($id)['last_name']);
        $lines = array_filter(explode("\n", (string) file_get_contents($log)));
        self::assertCount(1, $lines);
        $entry = json_decode((string) reset($lines), true);
        self::assertSame(['applied', 10, 'Georgiou'], [$entry['result'], $entry['user_id'], $entry['changes'][0]['old']]);

        $again = (new SseMemberCivilIdentityBatch(null, $this->service(), $log))->run(true, 1);
        self::assertSame([0, 1], [$again['totals']['applied'], $again['totals']['aligned']]);
        self::assertCount(1, array_filter(explode("\n", (string) file_get_contents($log))), 'rien de nouveau au journal');
    }

    public function testTheBatchHonoursLimitTenantAndCreateMissing(): void
    {
        $a = $this->identity('civil', 'Georgiou', 'Yannis', 10);
        $b = $this->identity('civil', 'Autre', 'Quelqu’un', 11);
        $limited = (new SseMemberCivilIdentityBatch(null, $this->service(), $this->tmp . '/l.jsonl'))->run(true, 1, 1);
        self::assertSame(1, $limited['totals']['applied']);
        self::assertSame(2, $limited['totals']['misaligned']);
        self::assertSame(['Gylenhall', 'Autre'], [$this->row($a)['last_name'], $this->row($b)['last_name']]);
        (new SseMemberCivilIdentityBatch(null, $this->service(), $this->tmp . '/l.jsonl'))->run(true, 1, 1);
        self::assertSame('Solo', $this->row($b)['last_name']);

        // Sans identité : listée, jamais créée sans --create-missing.
        $none = (new SseMemberCivilIdentityBatch(null, $this->service()))->run(true, 1, null, false);
        self::assertSame(0, (int) $this->pdo->query("SELECT COUNT(*) FROM sse_identities WHERE user_id = 13")->fetchColumn());
        $created = (new SseMemberCivilIdentityBatch(null, $this->service(), $this->tmp . '/c.jsonl'))->run(true, 1, null, true);
        self::assertGreaterThanOrEqual(1, $created['totals']['created']);
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM sse_identities WHERE user_id = 13 AND kind = 'civil'")->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_identities WHERE user_id = 20')->fetchColumn(), 'une autre communauté n’est pas touchée');
        self::assertSame(0, $none['totals']['created']);
    }

    public function testOneFailingMemberDoesNotLoseTheOthers(): void
    {
        $a = $this->identity('civil', 'Georgiou', 'Yannis', 10);
        $b = $this->identity('civil', 'Autre', 'Quelqu’un', 11);
        $this->pdo->exec("CREATE TRIGGER civil_boom BEFORE UPDATE ON sse_identities WHEN NEW.id = {$a} AND NEW.last_name = 'Gylenhall' BEGIN SELECT RAISE(ABORT, 'verrou'); END");
        $log = $this->tmp . '/f.jsonl';
        $run = (new SseMemberCivilIdentityBatch(null, $this->service(), $log))->run(true, 1);
        self::assertSame([1, 1], [$run['totals']['applied'], $run['totals']['errors']]);
        self::assertSame(['Georgiou', 'Solo'], [$this->row($a)['last_name'], $this->row($b)['last_name']], 'le fautif est intact, l’autre est aligné');
        self::assertGreaterThan(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_identity_history WHERE identity_id = ' . $b . " AND decision = 'civil'")->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_identity_history WHERE identity_id = ' . $a)->fetchColumn());
        $results = array_map(static fn (string $l): string => (string) (json_decode($l, true)['result'] ?? ''), array_filter(explode("\n", (string) file_get_contents($log))));
        self::assertSame(['error', 'applied'], array_values($results));
    }

    public function testTheScriptIsASimulationByDefaultAndCliOnly(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/tools/sse-sync-member-identities.php');
        self::assertStringContainsString("PHP_SAPI !== 'cli'", $src);
        self::assertStringContainsString("\$apply = isset(\$opts['apply']);", $src);
        self::assertStringContainsString('SIMULATION (aucune écriture)', $src);
        self::assertStringContainsString("'create-missing'", $src);
        self::assertSame(0, preg_match('/--apply\s*=/', $src), '--apply est un simple interrupteur');
    }

    // ------------------------------------------------------------------ Droits, CSRF, IDOR

    private function flash(string $key): string
    {
        return (string) Session::getFlash($key, '');
    }

    public function testTheAlignButtonNeedsTheRightAValidTokenAndAMemberOfTheCommunity(): void
    {
        [$id] = $this->georgiou();
        $c = new MemberIdentityPapersController();
        $_SERVER['REQUEST_URI'] = '/atak/sse/papiers/membres/10/etat-civil';

        // Jeton périmé : rien.
        $_POST = ['_csrf_token' => 'perime', 'identity_id' => (string) $id];
        $c->civilAlign(new Request(), ['id' => 10]);
        self::assertStringContainsString('Session expirée', $this->flash('error'));
        self::assertSame('Georgiou', $this->row($id)['last_name']);

        // Droit insuffisant : rien.
        Gate::getInstance()->setPermissions(['atak.sse.view']);
        $_POST = ['_csrf_token' => 'jeton', 'identity_id' => (string) $id];
        $c->civilAlign(new Request(), ['id' => 10]);
        self::assertStringContainsString('pas habilité', $this->flash('error'));
        self::assertSame('Georgiou', $this->row($id)['last_name']);
        Gate::getInstance()->setPermissions(['admin.access', 'atak.sse.case.manage', 'atak.sse.clearance.confidentiel', 'atak.sse.clearance.encadrement']);

        // Membre d'une autre communauté (IDOR) : introuvable, rien.
        $foreign = (new SseIdentityRepository())->create(1, ['kind' => 'civil', 'last_name' => 'Georgiou', 'first_name' => 'Yannis', 'user_id' => 20]);
        $c->civilAlign(new Request(), ['id' => 20]);
        self::assertStringContainsString('Membre introuvable', $this->flash('error'));
        self::assertSame('Georgiou', $this->row($foreign)['last_name']);

        // Identité vue à l'écran périmée (une autre) : rien.
        $_POST = ['_csrf_token' => 'jeton', 'identity_id' => (string) ($id + 99)];
        $c->civilAlign(new Request(), ['id' => 10]);
        self::assertStringContainsString('a changé entre-temps', $this->flash('error'));
        self::assertSame('Georgiou', $this->row($id)['last_name']);

        // Tout est en règle.
        $_POST = ['_csrf_token' => 'jeton', 'identity_id' => (string) $id];
        $c->civilAlign(new Request(), ['id' => 10]);
        self::assertStringContainsString('alignée', $this->flash('success'));
        self::assertSame('Gylenhall', $this->row($id)['last_name']);
    }

    public function testTheOverrideButtonIsGuardedLikeTheAlignButton(): void
    {
        [$id] = $this->georgiou();
        $c = new MemberIdentityPapersController();
        $_SERVER['REQUEST_URI'] = '/atak/sse/papiers/membres/10/etat-civil/volontaire';
        $_POST = ['_csrf_token' => 'perime', 'identity_id' => (string) $id, 'on' => '1'];
        $c->civilOverride(new Request(), ['id' => 10]);
        self::assertSame(0, (int) $this->row($id)['name_override']);
        $_POST = ['_csrf_token' => 'jeton', 'identity_id' => (string) $id, 'on' => '1'];
        $c->civilOverride(new Request(), ['id' => 10]);
        self::assertSame(1, (int) $this->row($id)['name_override']);
        // Une identité qui n'est pas la civile de ce membre ne se marque pas.
        $cover = (int) $this->pdo->query("SELECT id FROM sse_identities WHERE kind = 'cover'")->fetchColumn();
        $_POST = ['_csrf_token' => 'jeton', 'identity_id' => (string) ($cover ?: 999), 'on' => '1'];
        $c->civilOverride(new Request(), ['id' => 10]);
        self::assertStringContainsString('Identité introuvable', $this->flash('error'));
    }

    public function testAMemberOnlyAlignsHisOwnIdentityAndNeverCreatesOne(): void
    {
        [$id] = $this->georgiou();
        $other = (new SseIdentityRepository())->create(1, ['kind' => 'civil', 'last_name' => 'Georgiou', 'first_name' => 'Yannis', 'user_id' => 11]);
        $_SESSION['user_id'] = 10;
        $c = new MemberIdentityPapersController();
        $_SERVER['REQUEST_URI'] = '/jnet/moi/mes-papiers/etat-civil';

        $_POST = ['_csrf_token' => 'perime', 'identity_id' => (string) $id, 'user_id' => '11'];
        $c->alignMine(new Request(), []);
        self::assertSame('Georgiou', $this->row($id)['last_name']);

        // Une autre identité nommée dans la requête (user_id, id d'un autre membre) n'a aucun effet.
        $_POST = ['_csrf_token' => 'jeton', 'identity_id' => (string) $other, 'user_id' => '11'];
        $c->alignMine(new Request(), []);
        self::assertSame('Georgiou', $this->row($other)['last_name']);
        self::assertSame('Georgiou', $this->row($id)['last_name']);
        self::assertStringContainsString('a changé entre-temps', $this->flash('error'));

        $_POST = ['_csrf_token' => 'jeton', 'identity_id' => (string) $id];
        $c->alignMine(new Request(), []);
        self::assertSame('Gylenhall', $this->row($id)['last_name']);
        self::assertSame('Georgiou', $this->row($other)['last_name']);

        // Sans identité officielle, le membre n'en crée pas lui-même.
        $_SESSION['user_id'] = 13;
        $_POST = ['_csrf_token' => 'jeton'];
        $c->alignMine(new Request(), []);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_identities WHERE user_id = 13')->fetchColumn());
    }

    // ------------------------------------------------------------------ Registre, vues, étape

    public function testTheRegistryListOffersAnAlignActionWithTheBeforeAndAfter(): void
    {
        [$id] = $this->georgiou();
        $docs = (new SseIdDocumentRepository())->list(1, ['identity_id' => $id]);
        $holder = (new SseIdentityHolderService())->annotate(1, $docs, true)[0]['holder'];
        self::assertTrue($holder['differs']);
        self::assertSame($id, $holder['align']['identity_id']);
        self::assertSame(['Georgiou', 'Gylenhall'], [$holder['align']['changes'][0]['old'], $holder['align']['changes'][0]['new']]);
        self::assertCount(2, $holder['align']['documents']);

        $root = dirname(__DIR__, 2);
        $list = (string) file_get_contents($root . '/views/atak/sse/papers/documents.php');
        self::assertStringContainsString('Nom différent de l’état civil du membre', $list);
        self::assertStringContainsString("'back' => 'documents'", $list);
        $dialog = (string) file_get_contents($root . '/views/partials/sse_civil_align.php');
        self::assertStringContainsString('Aligner sur l’état civil', $dialog);
        self::assertStringContainsString('Avant', $dialog);
        self::assertStringContainsString('Après', $dialog);
        self::assertStringNotContainsString('imulation', $dialog, 'aucune mention « simulation » dans cette interface');
        self::assertStringContainsString("name=\"_csrf_token\"", $dialog);
        self::assertSame(0, preg_match('/as \$(d|c|row|r|i)\)/', $dialog), 'la fenêtre ne réutilise pas les variables de boucle de la page qui l’inclut');
        $card = (string) file_get_contents($root . '/views/admin/effectifs_workspace/partials/member_papers_civil.php');
        self::assertStringContainsString('Nom volontairement différent', $card);
        self::assertStringContainsString("/etat-civil/volontaire", $card);
        self::assertStringContainsString('jnet/moi/mes-papiers/etat-civil', (string) file_get_contents($root . '/views/admin/member_situation/mes_papiers.php'));
        $routes = (string) file_get_contents($root . '/routes/web.php');
        foreach (['/atak/sse/papiers/membres/{id}/etat-civil\'', '/back-office/ressources/effectifs/membres/{id}/papiers/etat-civil\'', '/jnet/moi/mes-papiers/etat-civil\''] as $path) {
            self::assertStringContainsString("\$router->post('" . $path, $routes);
        }
    }

    public function testTheStepIsRegisteredWithAWayBackAndRunsTwiceWithoutChange(): void
    {
        $root = dirname(__DIR__, 2);
        $registry = (string) file_get_contents($root . '/database/steps/registry.php');
        self::assertStringContainsString("['id' => 'sse_member_civil_identity'", $registry);
        self::assertStringContainsString("'file' => '4700_sse_member_civil_identity.php', 'rerun' => true]", $registry);
        self::assertFileExists($root . '/database/steps/4700_sse_member_civil_identity.php');
        self::assertFileExists($root . '/migrations/rollback/sse_member_civil_identity_down.sql');
        self::assertSame(1, substr_count($registry, '4700_'));

        $second = SseIdentitySqlite::migrate($this->pdo);
        self::assertStringContainsString('sse_identities.name_override déjà présente', $second);
        self::assertStringContainsString('user_profiles.character_birth_date déjà présente', $second);
        $cols = array_column($this->pdo->query('PRAGMA table_info(user_profiles)')->fetchAll(), 'name');
        self::assertContains('character_birth_date', $cols);
        $down = (string) file_get_contents($root . '/migrations/rollback/sse_member_civil_identity_down.sql');
        foreach (['name_override', 'civil_synced_at', 'character_birth_date'] as $col) {
            self::assertStringContainsString($col, $down);
        }
    }

    public function testEveryMemberSaveGoesThroughTheProfileRepositoryHook(): void
    {
        $repo = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/UserProfileRepository.php');
        self::assertStringContainsString('SseMemberCivilIdentityService::onProfileChanged($userId)', $repo);
        self::assertFalse(Civil::AUTO_CREATE);
    }
}
