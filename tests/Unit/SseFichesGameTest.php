<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Api\Game\GameSseFichesApiController;
use App\Core\Request;
use App\Repositories\SseFileRecordRepository;
use App\Repositories\SseIdentityRepository;
use App\Services\Sse\SseFichesGameService;
use App\Support\ComspecApiKeyAuth;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseSheetSqlite;

/**
 * Fiches du portail SSE lues par le téléphone ATAK (ICD-SSE-FICHES-001) : filtre par la classification et l'habilitation du
 * porteur, jamais l'identité d'un membre, caviardage des données cachées, pages de la taille d'un retour de COMSPEC Link,
 * révision stable (ETag, since_rev), communauté de la clé et jamais de la requête, débit borné, journal, partage par référence.
 */
final class SseFichesGameTest extends TestCase
{
    private PDO $pdo;

    /** @var array<string, mixed> */
    private array $saved = [];

    /** @var list<array<string, mixed>> lignes envoyées au journal d'audit */
    private array $audited = [];

    private int $seq = 0;

    protected function setUp(): void
    {
        $this->saved = ['session' => $_SESSION ?? null, 'post' => $_POST ?? [], 'get' => $_GET ?? [], 'server' => $_SERVER];
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = SseSheetSqlite::open();
        $this->pdo->exec("INSERT INTO users (id, tenant_id, steam_id, display_name) VALUES (7, 1, '76561198000000007', 'Vrai Membre')");
        $this->pdo->exec('CREATE TABLE game_sessions (id INTEGER PRIMARY KEY, tenant_id INTEGER, user_id INTEGER, steam_id TEXT, access_token_hash TEXT, expires_at TEXT, revoked_at TEXT)');
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['HTTP_X_COMSPEC_KEY'], $_SERVER['HTTP_X_ATAK_TOKEN'], $_SERVER['HTTP_IF_NONE_MATCH']);
        ComspecApiKeyAuth::resetForTests();
        $this->audited = [];
        $this->seq = 0;
    }

    protected function tearDown(): void
    {
        SseSheetSqlite::close();
        $_POST = $this->saved['post'];
        $_GET = $this->saved['get'];
        $_SERVER = $this->saved['server'];
        if ($this->saved['session'] === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->saved['session'];
        }
        ComspecApiKeyAuth::resetForTests();
    }

    // ------------------------------------------------------------------ Jeu de données

    /** Niveaux des comptes : 7 Encadrement, 8 Confidentiel, 9 Très restreint, les autres le plancher. */
    private function service(): SseFichesGameService
    {
        return new SseFichesGameService(null, static fn (int $tenant, int $user): string => match ($user) {
            7 => 'encadrement', 8 => 'confidentiel', 9 => 'tres_restreint', default => 'interne',
        });
    }

    private function controller(bool $tooMany = false, ?string $maintenance = null, ?string $sessionLevel = 'interne'): GameSseFichesApiController
    {
        return new GameSseFichesApiController(
            $this->service(),
            static fn (): bool => $tooMany,
            static fn (): ?string => $maintenance,
            static fn (): ?string => $sessionLevel,
            function (array $row): void {
                $this->audited[] = $row;
            },
        );
    }

    private function gameToken(int $tenant, int $user): void
    {
        $token = str_repeat('c', 60) . sprintf('%04d', $user);
        $st = $this->pdo->prepare('INSERT INTO game_sessions (tenant_id, user_id, access_token_hash, expires_at) VALUES (?, ?, ?, ?)');
        $st->execute([$tenant, $user, hash('sha256', $token), '2099-01-01 00:00:00']);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        ComspecApiKeyAuth::resetForTests();
    }

    private function identity(int $tenant, string $last, string $first, ?int $userId = null, string $alias = ''): int
    {
        return (new SseIdentityRepository())->create($tenant, [
            'last_name' => $last, 'first_name' => $first, 'sex' => 'M', 'birth_date' => '1984-03-12', 'nationality' => 'TAK',
            'birth_place' => 'Pyrgos', 'alias' => $alias !== '' ? $alias : null, 'user_id' => $userId,
        ]);
    }

    /** @param array<string, mixed> $o */
    private function notice(int $tenant, int $identity, array $o = []): string
    {
        $ref = $o['ref'] ?? sprintf('A-%04d/2026', ++$this->seq);
        $st = $this->pdo->prepare("INSERT INTO sse_wanted_notices (tenant_id, identity_id, ref, ref_year, ref_seq, notice_type, threat_level, armed, reason, instructions, zone, last_seen_place, last_seen_at,
            authority, status, active_key, issued_at, expires_at, created_at, updated_at)
            VALUES (:t, :i, :ref, 2026, :seq, :type, :threat, :armed, :reason, :instr, :zone, :place, :seen, :auth, :status, :ak, '2026-11-01 08:00:00', :exp, '2026-11-01 08:00:00', :upd)");
        $st->execute([
            't' => $tenant, 'i' => $identity, 'ref' => $ref, 'seq' => $this->seq, 'type' => $o['type'] ?? 'hvt', 'threat' => $o['threat'] ?? 'high', 'armed' => $o['armed'] ?? 'yes',
            'reason' => $o['reason'] ?? 'MOTIF : trafic d’armes sur le port', 'instr' => $o['instructions'] ?? 'MESURE : ne pas intervenir seul, signaler',
            'zone' => $o['zone'] ?? 'Pyrgos', 'place' => $o['place'] ?? 'Entrepôt quai 4', 'seen' => $o['seen'] ?? '2026-11-10 21:30:00',
            'auth' => $o['authority'] ?? 'Bureau de recherche', 'status' => $o['status'] ?? 'active', 'ak' => ($o['status'] ?? 'active') === 'active' ? 1 : null,
            'exp' => $o['expires'] ?? null, 'upd' => $o['updated'] ?? '2026-11-12 09:00:00',
        ]);

        return $ref;
    }

    /** @param array<string, mixed> $o */
    private function sheet(int $tenant, ?int $identity, string $model, string $classification, array $o = []): string
    {
        $ref = $o['ref'] ?? sprintf('%s-2026-%04d', strtoupper(substr($model, 0, 2)), ++$this->seq);
        $texts = $o['texts'] ?? ['reason' => 'Surveillance du port', 'instructions' => 'Observer et rendre compte', 'behaviour' => 'Passe chaque soir', 'mention' => 'Ne pas diffuser'];
        $facts = ['display' => $o['display'] ?? 'DUPONT Marc', 'aliases' => ['Le Rouquin'], 'threat' => 'medium', 'armed' => 'no', 'zone' => 'Kavala',
            'last_seen_place' => 'Marché', 'last_seen_at' => '2026-11-09 18:00',
            'links' => ['vehicle' => ['Fourgon blanc 12-AB-34'], 'address' => ['12 rue du Port'], 'associate' => ['Paul MARTIN (frère)']],
            'birth_date' => '1984-03-12', 'birth_place' => 'Pyrgos', 'id_document' => 'PASS-998877'];
        $snap = ['data' => ['facts' => $facts, 'valid_until' => $o['until'] ?? '2099-01-01', 'issued_at' => '2026-11-05', 'authority' => ['Bureau fictif', 'Fictional bureau']]];
        $st = $this->pdo->prepare('INSERT INTO sse_doc_issues (tenant_id, person_id, identity_id, model, ref, ref_year, ref_seq, lang, classification, faces, texts_json, snapshot_json, sensitive_json, issued_by_user_id, issued_by_label, issued_at)
            VALUES (:t, NULL, :i, :m, :ref, 2026, :seq, \'fr\', :cl, \'auto\', :texts, :snap, \'[]\', 7, \'ANALYSTE\', :at)');
        $st->execute(['t' => $tenant, 'i' => $identity, 'm' => $model, 'ref' => $ref, 'seq' => $this->seq, 'cl' => $classification,
            'texts' => json_encode($texts, JSON_UNESCAPED_UNICODE), 'snap' => json_encode($snap, JSON_UNESCAPED_UNICODE), 'at' => $o['at'] ?? '2026-11-05 10:00:00']);

        return $ref;
    }

    /** @return list<string> références, dans l'ordre servi */
    private static function refs(array $pkg): array
    {
        return array_column($pkg['fiches'], 'ref');
    }

    /** @param list<string> $refs @return list<string> */
    private static function sorted(array $refs): array
    {
        sort($refs);

        return $refs;
    }

    /** @return list<mixed> ce que le jeu relit : même forme que parseSimpleArray. */
    private static function parse(string $ref): array
    {
        self::assertStringNotContainsString('|', $ref);
        self::assertStringNotContainsString("\n", $ref);
        $json = preg_replace_callback('/"((?:[^"]|"")*)"/', static fn (array $m): string => (string) json_encode(str_replace('""', '"', $m[1]), JSON_UNESCAPED_UNICODE), $ref);
        $rows = json_decode((string) $json, true);
        self::assertIsArray($rows, $ref);

        return $rows;
    }

    private function seed(): void
    {
        $dupont = $this->identity(1, 'Karimzadeh', 'Élodie', null, 'La Chatte');
        $this->notice(1, $dupont, ['ref' => 'A-0001/2026', 'type' => 'hvt', 'threat' => 'critical']);
        $this->pdo->exec("INSERT INTO sse_file_links (tenant_id, identity_id, kind, value, value_norm, visibility, reveal_phase, created_at) VALUES
            (1, {$dupont}, 'vehicle', 'Pick-up gris 55-ZZ-01', 'x', 'known', 0, '2026-11-01 10:00:00'),
            (1, {$dupont}, 'address', '3 chemin des Oliviers', 'x', 'known', 0, '2026-11-01 10:00:00'),
            (1, {$dupont}, 'address', 'Adresse cachée', 'x', 'hidden', 0, '2026-11-01 10:00:00'),
            (1, {$dupont}, 'associate', 'Yanis BENALI', 'x', 'known', 0, '2026-11-01 10:00:00')");
        $other = $this->identity(2, 'Bravo', 'Cible');
        $this->notice(2, $other, ['ref' => 'A-0900/2026']);
        $this->sheet(1, $dupont, 'vigilance', 'interne', ['ref' => 'FV-2026-0001']);
        $this->sheet(1, $this->identity(1, 'Dupont', 'Marc'), 'identification', 'encadrement', ['ref' => 'FI-2026-0002']);
        $this->sheet(1, $this->identity(1, 'Secret', 'Dossier'), 'profile', 'confidentiel', ['ref' => 'FR-2026-0003', 'display' => 'SECRET Dossier',
            'texts' => ['summary' => 'Synthèse du renseignement', 'assessment' => 'Fiable', 'recommendations' => 'Poursuivre la surveillance', 'mention' => '']]);
        $this->sheet(1, $this->identity(1, 'Tressecret', 'Dossier'), 'control', 'tres_restreint', ['ref' => 'FC-2026-0004']);
    }

    // ------------------------------------------------------------------ Contenu et filtre par habilitation

    public function testFloorViewerGetsNoticesAndInternalSheetsOnly(): void
    {
        $this->seed();
        $pkg = $this->service()->build(1, 'interne');

        self::assertSame(['A-0001/2026', 'FV-2026-0001'], self::sorted(self::refs($pkg)));
        $avis = array_column($pkg['fiches'], null, 'ref')['A-0001/2026'];
        self::assertSame('avis', $avis['kind']);
        self::assertSame('hvt', $avis['type']);
        self::assertSame('KARIMZADEH Élodie', $avis['name']);
        self::assertSame('La Chatte', $avis['alias']);
        self::assertSame('critical', $avis['threat']);
        self::assertSame('yes', $avis['armed']);
        self::assertSame('MOTIF : trafic d’armes sur le port', $avis['reason']);
        self::assertSame('MESURE : ne pas intervenir seul, signaler', $avis['measures']);
        self::assertSame('Pyrgos', $avis['zone']);
        self::assertSame('Entrepôt quai 4', $avis['place']);
        self::assertSame('2026-11-10', $avis['seen']);
        self::assertSame('UNCLASSIFIED//FOUO', $avis['mark']);
        self::assertSame('interne', $avis['level']);
        self::assertSame('Bureau de recherche', $avis['auth']);
        self::assertSame('interne', $pkg['level']);
        // Plancher : véhicules oui, adresses (Encadrement) et personnes liées (Confidentiel) non.
        self::assertSame(['Pick-up gris 55-ZZ-01'], $avis['vehicles']);
        self::assertSame([], $avis['addresses']);
        self::assertSame([], $avis['people']);
        self::assertSame(['hvt', 'Cible de haute valeur (HVT)', 'High-value target (HVT)'], $pkg['types'][0]);
    }

    public function testClearanceOpensSheetsAndLinksStepByStepAndNeverBeyond(): void
    {
        $this->seed();
        $command = $this->service()->build(1, 'encadrement');
        self::assertSame(['A-0001/2026', 'FI-2026-0002', 'FV-2026-0001'], self::sorted(self::refs($command)));
        $byRef = array_column($command['fiches'], null, 'ref');
        self::assertSame(['3 chemin des Oliviers'], $byRef['A-0001/2026']['addresses'], 'Adresses à partir d’Encadrement, la cachée jamais');
        self::assertSame([], $byRef['A-0001/2026']['people']);
        self::assertSame('CONFIDENTIAL', $byRef['FI-2026-0002']['mark']);

        $confidential = $this->service()->build(1, 'confidentiel');
        self::assertSame(['A-0001/2026', 'FI-2026-0002', 'FR-2026-0003', 'FV-2026-0001'], self::sorted(self::refs($confidential)));
        $byRef = array_column($confidential['fiches'], null, 'ref');
        self::assertSame(['Yanis BENALI'], $byRef['A-0001/2026']['people']);
        self::assertSame('SECRET//NOFORN', $byRef['FR-2026-0003']['mark']);

        $all = $this->service()->build(1, 'tres_restreint');
        self::assertContains('FC-2026-0004', self::refs($all));
        self::assertSame('TOP SECRET//NOFORN', $all['mark']);
        // Niveau inconnu : le plancher.
        self::assertSame(self::refs($this->service()->build(1, 'interne')), self::refs($this->service()->build(1, 'inconnu')));
    }

    public function testFindDoesNotRevealAFicheAboveTheClearance(): void
    {
        $this->seed();
        self::assertNull($this->service()->find(1, 'interne', 'FR-2026-0003'), 'Au-dessus : même réponse qu’une référence inconnue');
        self::assertNull($this->service()->find(1, 'encadrement', 'FR-2026-0003'));
        self::assertNull($this->service()->find(1, 'interne', 'NOPE'));
        self::assertNull($this->service()->find(1, 'tres_restreint', 'A-0900/2026'), 'Une fiche d’une autre communauté n’existe pas');
        $f = $this->service()->find(1, 'confidentiel', 'FR-2026-0003');
        self::assertNotNull($f);
        self::assertSame('SECRET Dossier', $f['name']);
        self::assertSame('Synthèse du renseignement', $f['desc']);
        self::assertSame('Poursuivre la surveillance', $f['measures']);
    }

    public function testNeverAMemberIdentityNorWhatIsHidden(): void
    {
        $member = $this->identity(1, 'Membre', 'Vrai', 7);
        $this->notice(1, $member, ['ref' => 'A-0500/2026']);
        $this->sheet(1, $member, 'vigilance', 'interne', ['ref' => 'FV-2026-0500']);
        $normal = $this->identity(1, 'Normal', 'Jean');
        $this->notice(1, $normal, ['ref' => 'A-0501/2026']);
        $this->pdo->exec("INSERT INTO sse_file_links (tenant_id, identity_id, kind, value, value_norm, linked_identity_id, relation, visibility, reveal_phase, created_at)
            VALUES (1, {$normal}, 'associate', 'Vrai MEMBRE', 'x', {$member}, 'associate', 'known', 0, '2026-11-01 10:00:00'),
                   (1, {$normal}, 'associate', 'Cousin Quelconque', 'x', NULL, 'family', 'known', 0, '2026-11-01 10:00:00')");
        // Identité cachée par le MJ : une barre, ni alias ni lien.
        $hidden = $this->identity(1, 'Cachée', 'Agent', null, 'Alias caché');
        $this->notice(1, $hidden, ['ref' => 'A-0502/2026']);
        (new SseFileRecordRepository())->saveProfile(1, $hidden, ['visibility' => ['identity' => ['v' => 'hidden', 'phase' => 0]]], null);
        $this->pdo->exec("INSERT INTO sse_file_links (tenant_id, identity_id, kind, value, value_norm, visibility, reveal_phase, created_at) VALUES (1, {$hidden}, 'vehicle', 'Camion rouge', 'x', 'known', 0, '2026-11-01 10:00:00')");

        $pkg = $this->service()->build(1, 'tres_restreint');
        self::assertContains('A-0501/2026', self::refs($pkg));
        self::assertNotContains('A-0500/2026', self::refs($pkg));
        self::assertNotContains('FV-2026-0500', self::refs($pkg));
        self::assertNull($this->service()->find(1, 'tres_restreint', 'A-0500/2026'));
        self::assertNull($this->service()->find(1, 'tres_restreint', 'FV-2026-0500'));
        $byRef = array_column($pkg['fiches'], null, 'ref');
        self::assertSame(['Cousin Quelconque (Famille)'], array_map(static fn (string $p): string => str_replace(['(famille)', '(Famille)'], '(Famille)', $p), $byRef['A-0501/2026']['people']));
        self::assertStringNotContainsString('MEMBRE', (string) json_encode($pkg, JSON_UNESCAPED_UNICODE));
        self::assertStringNotContainsString('Cachée', (string) json_encode($byRef['A-0502/2026'], JSON_UNESCAPED_UNICODE));
        self::assertSame('', $byRef['A-0502/2026']['alias']);
        self::assertSame([], $byRef['A-0502/2026']['vehicles']);
        self::assertSame(['wanted' => false], $this->service()->wantedFor(1, $member), 'Pas d’avis relayé pour un membre');
    }

    public function testNoCivilStatusAndNoOtherCommunityEverLeaves(): void
    {
        $this->seed();
        $json = (string) json_encode([
            $this->service()->build(1, 'tres_restreint'),
            $this->service()->find(1, 'tres_restreint', 'FR-2026-0003'),
        ], JSON_UNESCAPED_UNICODE);
        foreach (['1984-03-12', 'PASS-998877', 'TAK', 'Bravo', 'A-0900/2026', 'photo', 'steam', '7656119', 'truth', 'arma_key'] as $leak) {
            self::assertStringNotContainsString($leak, $json, $leak);
        }
        self::assertStringNotContainsString('birth', $json);
        self::assertStringNotContainsString('1984', $json);
    }

    public function testOnlyActiveNoticesAndLatestValidSheetsAreServed(): void
    {
        $id = $this->identity(1, 'Actif', 'Alain');
        $this->notice(1, $id, ['ref' => 'A-0600/2026']);
        $this->notice(1, $this->identity(1, 'Levé', 'Luc'), ['ref' => 'A-0601/2026', 'status' => 'lifted']);
        $this->notice(1, $this->identity(1, 'Échu', 'Eric'), ['ref' => 'A-0602/2026', 'expires' => '2020-01-01 00:00:00']);
        $this->notice(1, $this->identity(1, 'Valable', 'Vic'), ['ref' => 'A-0603/2026', 'expires' => '2099-01-01 00:00:00']);
        $this->sheet(1, $id, 'vigilance', 'interne', ['ref' => 'FV-2026-0001', 'at' => '2026-11-01 10:00:00']);
        $this->sheet(1, $id, 'vigilance', 'interne', ['ref' => 'FV-2026-0002', 'at' => '2026-11-03 10:00:00']);
        $this->sheet(1, $this->identity(1, 'Périmée', 'Paul'), 'vigilance', 'interne', ['ref' => 'FV-2026-0003', 'until' => '2020-01-01']);
        $this->sheet(1, $id, 'notice', 'interne', ['ref' => 'AR-2026-0009']);

        $refs = self::refs($this->service()->build(1, 'interne'));
        self::assertContains('A-0600/2026', $refs);
        self::assertContains('A-0603/2026', $refs);
        foreach (['A-0601/2026', 'A-0602/2026', 'FV-2026-0003', 'AR-2026-0009'] as $absent) {
            self::assertNotContains($absent, $refs, $absent);
        }
        self::assertContains('FV-2026-0002', $refs, 'La dernière fiche émise du sujet');
        self::assertNotContains('FV-2026-0001', $refs, 'Les émissions plus anciennes du même modèle sont remplacées');
    }

    public function testMostSevereFirstThenMostRecent(): void
    {
        $a = $this->identity(1, 'A', 'a');
        $b = $this->identity(1, 'B', 'b');
        $c = $this->identity(1, 'C', 'c');
        $this->notice(1, $a, ['ref' => 'A-0701/2026', 'threat' => 'low', 'updated' => '2026-11-14 10:00:00']);
        $this->notice(1, $b, ['ref' => 'A-0702/2026', 'threat' => 'critical', 'updated' => '2026-11-01 10:00:00']);
        $this->notice(1, $c, ['ref' => 'A-0703/2026', 'threat' => 'critical', 'updated' => '2026-11-13 10:00:00']);
        self::assertSame(['A-0703/2026', 'A-0702/2026', 'A-0701/2026'], self::refs($this->service()->build(1, 'interne')));
    }

    public function testMissingTablesGiveAnEmptyListNotAnError(): void
    {
        $this->pdo->exec('DROP TABLE sse_doc_issues');
        $this->pdo->exec('DROP TABLE sse_wanted_notices');
        $pkg = $this->service()->build(1, 'interne');
        self::assertSame([], $pkg['fiches']);
        self::assertCount(1, SseFichesGameService::pages($pkg));
        self::assertSame(['wanted' => false], $this->service()->wantedFor(1, 3));
    }

    // ------------------------------------------------------------------ Pages et révision

    public function testPagesStayUnderTheDllLimitAndGiveBackEveryFiche(): void
    {
        $long = str_repeat('Motif « très » long, avec | barres et "guillemets" ', 30);
        for ($i = 0; $i < 90; $i++) {
            $id = $this->identity(1, 'Nom' . $i, 'Prénom');
            $this->notice(1, $id, ['reason' => $long, 'instructions' => $long, 'threat' => ['low', 'medium', 'high', 'critical'][$i % 4]]);
        }
        $pkg = $this->service()->build(1, 'interne');
        self::assertCount(SseFichesGameService::MAX_FICHES, $pkg['fiches']);
        self::assertTrue($pkg['truncated']);
        self::assertSame(90, $pkg['total']);
        $pages = SseFichesGameService::pages($pkg);
        self::assertGreaterThan(1, count($pages));
        self::assertLessThanOrEqual(SseFichesGameService::MAX_PAGES, count($pages));
        $seen = [];
        foreach ($pages as $i => $page) {
            self::assertLessThanOrEqual(SseFichesGameService::PAGE_BYTES, strlen($page), "page {$i}");
            $p = self::parse($page);
            self::assertSame([SseFichesGameService::VERSION, $pkg['revision']], [$p[0], $p[1]]);
            self::assertSame($i, $p[4]);
            self::assertSame($i + 1 < count($pages) ? $i + 1 : 0, $p[5]);
            self::assertSame('interne', $p[7][2]);
            self::assertSame('UNCLASSIFIED//FOUO', $p[7][3]);
            foreach ($p[6] as $row) {
                self::assertCount(count(SseFichesGameService::COLUMNS), $row);
                $seen[] = $row[0];
            }
        }
        $served = count($seen);
        self::assertGreaterThan(0, $served);
        self::assertSame(array_slice(self::refs($pkg), 0, $served), $seen, 'Mêmes fiches, même ordre, aucune perdue en route');
        self::assertSame(SseFichesGameService::COLUMNS, array_keys(array_combine(SseFichesGameService::COLUMNS, SseFichesGameService::cells($pkg['fiches'][0]))));
    }

    public function testShortListCutsLongTextsAndDetailGivesThemWhole(): void
    {
        $id = $this->identity(1, 'Long', 'Texte');
        $text = str_repeat('Une phrase de motif. ', 40);
        $ref = $this->notice(1, $id, ['reason' => $text]);
        $short = $this->service()->build(1, 'interne')['fiches'][0];
        self::assertTrue($short['cut']);
        self::assertLessThanOrEqual(SseFichesGameService::SHORT['reason'], mb_strlen($short['reason']));
        self::assertStringEndsWith('…', $short['reason']);
        $full = $this->service()->find(1, 'interne', $ref);
        self::assertGreaterThan(mb_strlen($short['reason']), mb_strlen((string) $full['reason']));
        $page = SseFichesGameService::detailPage($this->service()->build(1, 'interne'), $full);
        self::assertLessThanOrEqual(SseFichesGameService::PAGE_BYTES, strlen($page));
        self::assertSame($ref, self::parse($page)[6][0][0]);
    }

    public function testRevisionChangesWithTheContentAndWithTheLevel(): void
    {
        $this->seed();
        $a = $this->service()->build(1, 'interne');
        $b = $this->service()->build(1, 'interne');
        self::assertSame($a['revision'], $b['revision']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $a['revision']);
        self::assertGreaterThan(0, SseFichesGameService::revisionId($a['revision']));
        self::assertNotSame($a['revision'], $this->service()->build(1, 'encadrement')['revision']);
        $this->pdo->exec("UPDATE sse_wanted_notices SET instructions = 'Nouvelle consigne' WHERE ref = 'A-0001/2026'");
        self::assertNotSame($a['revision'], $this->service()->build(1, 'interne')['revision']);
    }

    // ------------------------------------------------------------------ Route

    public function testRouteServesTheCommunityOfTheKeyAtTheLevelOfTheAccount(): void
    {
        $this->seed();
        $this->gameToken(1, 7);
        $_GET = ['tenant_id' => '2', 'tenant_slug' => 'bravo'];
        $resp = $this->controller()->show(new Request());
        self::assertSame(200, $resp->statusCode());
        $data = json_decode($resp->body(), true);
        self::assertSame('encadrement', $data['level']);
        self::assertSame(['A-0001/2026', 'FI-2026-0002', 'FV-2026-0001'], self::sorted(array_column($data['fiches'], 'ref')));
        self::assertStringNotContainsString('A-0900/2026', $resp->body());
        self::assertStringStartsWith('W/"ssefiches-', (string) $resp->headerValue('ETag'));
        self::assertSame('private, no-cache', $resp->headerValue('Cache-Control'));
    }

    public function testCommunityKeyWithoutAnAccountGetsTheLeastSensitive(): void
    {
        $this->seed();
        // Compte inconnu du calcul (aucune habilitation) : plancher.
        $this->gameToken(1, 42);
        $data = json_decode($this->controller()->show(new Request())->body(), true);
        self::assertSame('interne', $data['level']);
        self::assertSame(['A-0001/2026', 'FV-2026-0001'], self::sorted(array_column($data['fiches'], 'ref')));
    }

    public function testRouteRefusesWithoutKeyOrWithAWrongOne(): void
    {
        $this->seed();
        self::assertSame(401, $this->controller()->show(new Request())->statusCode());
        $_GET = ['tenant_id' => '1'];
        self::assertSame(401, $this->controller()->show(new Request())->statusCode(), 'Une communauté en paramètre ne vaut pas une clé');
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . str_repeat('z', 64);
        ComspecApiKeyAuth::resetForTests();
        $resp = $this->controller()->show(new Request());
        self::assertSame(401, $resp->statusCode());
        self::assertStringNotContainsString('fiches', $resp->body());
        self::assertSame([], $this->audited, 'Un accès refusé ne lit rien');
    }

    public function testPortalSessionUsesItsOwnClearance(): void
    {
        $this->seed();
        $_SESSION = ['user_id' => 7, 'tenant_id' => 1];
        self::assertSame(403, $this->controller(false, null, null)->show(new Request())->statusCode());
        $data = json_decode($this->controller(false, null, 'confidentiel')->show(new Request())->body(), true);
        self::assertSame('confidentiel', $data['level']);
        self::assertContains('FR-2026-0003', array_column($data['fiches'], 'ref'));
        self::assertNotContains('FC-2026-0004', array_column($data['fiches'], 'ref'));
    }

    public function testKnownRevisionCostsNothingAndEtagGives304(): void
    {
        $this->seed();
        $this->gameToken(1, 7);
        $first = json_decode($this->controller()->show(new Request())->body(), true);
        $_GET = ['since_rev' => $first['revision']];
        $same = json_decode($this->controller()->show(new Request())->body(), true);
        self::assertTrue($same['unchanged']);
        self::assertArrayNotHasKey('fiches', $same);
        $_GET = [];
        $_SERVER['HTTP_IF_NONE_MATCH'] = 'W/"ssefiches-' . $first['revision'] . '"';
        $resp = $this->controller()->show(new Request());
        self::assertSame(304, $resp->statusCode());
        self::assertSame('', $resp->body());
    }

    public function testDetailByReferenceWithASlashAndAboveClearanceIsNotFound(): void
    {
        $this->seed();
        $this->gameToken(1, 7);
        $ok = $this->controller()->detail(new Request(), ['ref' => rawurlencode('A-0001/2026')]);
        self::assertSame(200, $ok->statusCode());
        self::assertSame('A-0001/2026', json_decode($ok->body(), true)['fiche']['ref']);
        self::assertSame(404, $this->controller()->detail(new Request(), ['ref' => 'FR-2026-0003'])->statusCode());
        $_GET = ['ref' => 'A-0001/2026'];
        self::assertSame(200, $this->controller()->show(new Request())->statusCode());
        $lines = array_column($this->audited, 'action');
        self::assertContains('fiches.detail', $lines);
    }

    public function testGameChannelReturnsPagesDetailAndTheUnchangedMark(): void
    {
        $this->seed();
        $this->gameToken(1, 8);
        $resp = $this->controller()->kindResponse(['kind' => SseFichesGameService::KIND, 'op' => 'list', 'tenant_id' => 2]);
        self::assertSame(200, $resp->statusCode());
        $data = json_decode($resp->body(), true);
        self::assertGreaterThan(0, $data['id']);
        self::assertLessThanOrEqual(SseFichesGameService::PAGE_BYTES, strlen($data['ref']));
        $page = self::parse($data['ref']);
        self::assertSame(SseFichesGameService::TTL, $page[3]);
        self::assertSame('A-0001/2026', $page[6][0][0]);
        self::assertSame('confidentiel', $page[7][2]);

        $again = json_decode($this->controller()->kindResponse(['kind' => SseFichesGameService::KIND, 'rev' => $page[1]])->body(), true);
        self::assertTrue($again['unchanged']);
        $mark = self::parse($again['ref']);
        self::assertSame([], $mark[6]);
        self::assertTrue($mark[9]);

        $detail = json_decode($this->controller()->kindResponse(['kind' => SseFichesGameService::KIND, 'op' => 'detail', 'ref' => 'FR-2026-0003'])->body(), true);
        self::assertSame('FR-2026-0003', self::parse($detail['ref'])[6][0][0]);
        self::assertSame(404, $this->controller()->kindResponse(['kind' => SseFichesGameService::KIND, 'op' => 'detail', 'ref' => 'FC-2026-0004'])->statusCode());
    }

    public function testRateLimitAndMaintenanceStopTheRead(): void
    {
        $this->seed();
        $this->gameToken(1, 7);
        $limited = $this->controller(true)->show(new Request());
        self::assertSame(429, $limited->statusCode());
        self::assertSame((string) GameSseFichesApiController::RATE_WINDOW, $limited->headerValue('Retry-After'));
        self::assertStringNotContainsString('A-0001', $limited->body());
        $paused = $this->controller(false, 'Maintenance en cours')->show(new Request());
        self::assertSame(503, $paused->statusCode());
        self::assertStringNotContainsString('A-0001', $paused->body());
    }

    public function testListReadIsJournalledWithTheLevelOfTheAccount(): void
    {
        $this->seed();
        $this->gameToken(1, 7);
        $this->controller()->show(new Request());
        $first = array_column($this->audited, 'action');
        self::assertContains('fiches.read', $first);
        $row = $this->audited[0];
        self::assertSame(1, $row['tenant_id']);
        self::assertSame(7, $row['actor_user_id']);
        self::assertSame('game_fiche', $row['object_type']);
        self::assertSame('encadrement', $row['after']['level']);
    }

    // ------------------------------------------------------------------ Partage

    public function testShareIsJournalledAndCarriesOnlyTheReference(): void
    {
        $this->seed();
        $this->gameToken(1, 7);
        $resp = $this->controller()->kindResponse(['kind' => SseFichesGameService::KIND_SHARE, 'ref' => 'FI-2026-0002', 'to' => 'Alpha 2-1', 'via' => 'bt']);
        self::assertSame(200, $resp->statusCode());
        $data = json_decode($resp->body(), true);
        self::assertSame([SseFichesGameService::VERSION, 'share', 'FI-2026-0002', 'encadrement', true], self::parse($data['ref']));
        // Ni le nom, ni le motif, ni une mesure dans la réponse : le serveur ne transporte rien.
        foreach (['DUPONT', 'Surveillance', 'Observer'] as $secret) {
            self::assertStringNotContainsString($secret, $resp->body());
        }
        $row = end($this->audited);
        self::assertSame('fiches.share', $row['action']);
        self::assertSame('FI-2026-0002', $row['object_uuid']);
        self::assertStringContainsString('Alpha 2-1', (string) $row['reason']);
        self::assertSame('encadrement', $row['after']['classification']);
    }

    public function testShareAboveTheSenderClearanceIsRefusedAndJournalled(): void
    {
        $this->seed();
        $this->gameToken(1, 7);
        $resp = $this->controller()->kindResponse(['kind' => SseFichesGameService::KIND_SHARE, 'ref' => 'FR-2026-0003', 'to' => 'Bravo 1-1', 'via' => 'sms']);
        self::assertSame(403, $resp->statusCode());
        self::assertSame('fiches.share_refused', end($this->audited)['action']);
        self::assertSame(422, $this->controller()->kindResponse(['kind' => SseFichesGameService::KIND_SHARE, 'ref' => '', 'to' => 'x'])->statusCode());
    }

    public function testShareIsRateLimited(): void
    {
        $this->seed();
        $this->gameToken(1, 7);
        $calls = 0;
        $controller = new GameSseFichesApiController(
            $this->service(),
            static function (string $key, int $max, int $window) use (&$calls): bool {
                if (str_starts_with($key, 'sse_fiche_share|')) {
                    return ++$calls > 2;
                }

                return false;
            },
            static fn (): ?string => null,
            static fn (): ?string => 'interne',
            function (array $row): void {
                $this->audited[] = $row;
            },
        );
        $body = ['kind' => SseFichesGameService::KIND_SHARE, 'ref' => 'A-0001/2026', 'to' => 'Équipier', 'via' => 'sms'];
        self::assertSame(200, $controller->kindResponse($body)->statusCode());
        self::assertSame(200, $controller->kindResponse($body)->statusCode());
        $third = $controller->kindResponse($body);
        self::assertSame(429, $third->statusCode());
        self::assertSame((string) GameSseFichesApiController::SHARE_WINDOW, $third->headerValue('Retry-After'));
    }

    // ------------------------------------------------------------------ Contrôle d'identité : avis

    public function testWantedForGivesRefTypeThreatAndAShortInstruction(): void
    {
        $id = $this->identity(1, 'Recherché', 'Roger');
        $this->notice(1, $id, ['ref' => 'A-0800/2026', 'type' => 'arrest', 'threat' => 'high', 'instructions' => str_repeat('Consigne. ', 40)]);
        $w = $this->service()->wantedFor(1, $id);
        self::assertTrue($w['wanted']);
        self::assertSame('A-0800/2026', $w['ref']);
        self::assertSame('arrest', $w['type']);
        self::assertSame('high', $w['threat']);
        self::assertSame('yes', $w['armed']);
        self::assertLessThanOrEqual(120, mb_strlen((string) $w['instruction']));
        self::assertSame(['wanted' => false], $this->service()->wantedFor(2, $id), 'Autre communauté');
        self::assertSame(['wanted' => false], $this->service()->wantedFor(1, null));
        self::assertSame(['wanted' => false], $this->service()->wantedFor(1, $this->identity(1, 'Sans', 'Avis')));
    }

    public function testWantedOpOfTheGameChannelAnswersForTheCheckOfTheCommunity(): void
    {
        $id = $this->identity(1, 'Recherché', 'Roger');
        $this->notice(1, $id, ['ref' => 'A-0800/2026', 'type' => 'arrest']);
        $this->gameToken(1, 7);
        $checks = static fn (int $tenantId, int $checkId): ?int => $checkId === 12 && $tenantId === 1 ? $id : null;
        $controller = new GameSseFichesApiController($this->service(), static fn (): bool => false, static fn (): ?string => null, static fn (): ?string => 'interne', function (array $row): void {
            $this->audited[] = $row;
        }, $checks);
        $hit = self::parse(json_decode($controller->kindResponse(['kind' => SseFichesGameService::KIND, 'op' => 'wanted', 'check' => 12])->body(), true)['ref']);
        self::assertSame([SseFichesGameService::VERSION, 'wanted', true, 'A-0800/2026', 'arrest', 'high', 'yes'], array_slice($hit, 0, 7));
        $miss = self::parse(json_decode($controller->kindResponse(['kind' => SseFichesGameService::KIND, 'op' => 'wanted', 'check' => 13])->body(), true)['ref']);
        self::assertFalse($miss[2]);
        self::assertSame(422, $controller->kindResponse(['kind' => SseFichesGameService::KIND, 'op' => 'wanted', 'check' => 0])->statusCode());
    }

    // ------------------------------------------------------------------ Câblage

    public function testRoutesAndTheKindChannelAreDeclared(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        self::assertStringContainsString("'/api/game/v1/sse/fiches', [\\App\\Controllers\\Api\\Game\\GameSseFichesApiController::class, 'show']", $routes);
        self::assertStringContainsString("'/api/game/v1/sse/fiches/{ref}', [\\App\\Controllers\\Api\\Game\\GameSseFichesApiController::class, 'detail']", $routes);
        $identity = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Api/SseIdentityApiController.php');
        self::assertStringContainsString('SseFichesGameService::KIND', $identity);
        self::assertStringContainsString('SseFichesGameService::KIND_SHARE', $identity);
        self::assertStringContainsString('GameSseFichesApiController', $identity);
    }
}
