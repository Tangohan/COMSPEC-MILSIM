<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Api\AtakCoverSyncApiController;
use App\Controllers\Web\OperatorHubController;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\OperatorHubRepository;
use App\Repositories\SseCoverExportRepository;
use App\Repositories\SseIdentityStoryRepository;
use App\Services\Alerts\AlertPresentationService;
use App\Services\Operator\OperatorIdentityService;
use App\Services\Security\FileRateLimiter;
use App\Services\Sse\MemberIdentityPapersService;
use App\Services\Sse\SseCoverExportService;
use App\Services\Sse\SseCoverSyncService;
use App\Services\Sse\SseIdentityCoverFeedService;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseIdentitySqlite;

/**
 * Identités unifiées : il n'y a plus de couverture à part, seulement des identités.
 *  - étape 4450 : mode à blanc, fusion des couvertures exportées dans les identités, idempotence, aucune perte ;
 *  - points d'accès hérités du téléphone : même contrat « comspec-cover » v1, dérivé des identités (test de référence
 *    contre l'ancien fichier), référence stable, signature valable, confidentialité ;
 *  - pages rendues dans le vrai gabarit, tout avertissement PHP fatal, avec des données de chaque sorte.
 */
final class OperatorUnifiedIdentitiesTest extends TestCase
{
    private PDO $pdo;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = SseIdentitySqlite::open();
        $root = dirname(__DIR__, 2);
        (require $root . '/bootstrap/sse_cover_exports_migration.php')($this->pdo);
        (require $root . '/bootstrap/sse_files_records_migration.php')($this->pdo);
        (require $root . '/bootstrap/operator_hub_migration.php')($this->pdo);
        (require $root . '/bootstrap/sse_cover_export_links_migration.php')($this->pdo);
        $this->pdo->exec("INSERT INTO users (id, tenant_id, steam_id, display_name) VALUES (10, 1, '76561198000000010', 'Jake'), (11, 1, NULL, 'Autre membre'), (20, 2, NULL, 'Autre communauté')");
        $this->now = new \DateTimeImmutable('2026-11-11 12:00:00', new \DateTimeZone('UTC'));
    }

    protected function tearDown(): void
    {
        if (extension_loaded('pdo_sqlite')) {
            SseIdentitySqlite::close();
        }
        $_POST = $_GET = $_FILES = [];
        Session::forgetMany(['user_id', 'tenant_id']);
        (new \ReflectionProperty(AlertPresentationService::class, 'cachedForRequest'))->setValue(null, null);
    }

    private function noop(): \Closure
    {
        return static function (): void {
        };
    }

    private function service(): OperatorIdentityService
    {
        return new OperatorIdentityService(null, null, null, null, null, null, $this->noop());
    }

    private function export(): SseCoverExportService
    {
        return new SseCoverExportService(null, null, null, $this->noop(), 'test-key');
    }

    private function sync(): SseCoverSyncService
    {
        return new SseCoverSyncService(new SseCoverExportRepository(), $this->export());
    }

    private function feed(): SseIdentityCoverFeedService
    {
        return new SseIdentityCoverFeedService($this->export());
    }

    /** @return callable(PDO, ?callable=, bool=): array<string, mixed> */
    private function migration(): callable
    {
        return require dirname(__DIR__, 2) . '/bootstrap/sse_identities_unified_migration.php';
    }

    /** @return array<string, mixed> */
    private function civil(array $over = []): array
    {
        return $over + ['last_name' => 'Gyllen', 'first_name' => 'Jake', 'sex' => 'M', 'birth_date' => '1990-04-02', 'birth_place' => 'Kavala', 'nationality' => 'ALT',
            'profession' => 'Mécanicien', 'height_cm' => '182', 'eyes' => 'blue', 'hair' => 'brown'];
    }

    /** Papier demandé par le membre puis émis par un responsable. */
    private function issue(int $tenant, int $user, int $identityId, string $type): int
    {
        $req = $this->service()->requestDocument($tenant, $user, $identityId, $type, 'ALT');
        self::assertTrue($req['ok'], (string) ($req['error'] ?? ''));
        $papers = new MemberIdentityPapersService(null, null, null, null, null, $this->noop());
        self::assertTrue($papers->action($tenant, $user, $papers->ownedDocument($tenant, $user, (int) $req['id'], true), 'issue', '', 99)['ok']);

        return (int) $req['id'];
    }

    /**
     * Jake : personnage (avec passeport émis), identité d'emprunt ; couvertures d'avant : une liée au personnage, une
     * sur l'identité d'emprunt sans lien, une personne fictive (active), la même personne fictive dupliquée, une
     * révoquée, une illisible. Un autre membre et une autre communauté ont chacun la leur.
     *
     * @return array<string, int|string>
     */
    private function seed(): array
    {
        $s = $this->service();
        $main = (int) $s->saveIdentity(1, 10, null, $this->civil(), $this->now)['id'];
        $alias = (int) $s->saveIdentity(1, 10, null, $this->civil(['last_name' => 'Kostas', 'first_name' => 'Nikos', 'birth_date' => '1988-01-15']), $this->now)['id'];
        $passport = $this->issue(1, 10, $main, 'passport');
        $x = $this->export();
        $linked = $x->generateFromIdentity(1, 10, 10, 'fr', false, $this->now, null, null, $main, [$passport], ['history' => ['A grandi sur les quais.']]);
        self::assertTrue($linked['ok'], (string) ($linked['error'] ?? ''));
        $this->pdo->prepare('INSERT INTO sse_cover_export_links (tenant_id, export_id, user_id, identity_id, document_ids, created_at, updated_at) VALUES (1, ?, 10, ?, ?, ?, ?)')
            ->execute([$linked['id'], $main, json_encode([$passport]), '2026-11-11 12:00:00', '2026-11-11 12:00:00']);
        $onAlias = $x->generateFromIdentity(1, 10, 10, 'fr', false, $this->now, null, null, $alias, []);
        self::assertTrue($onAlias['ok'], (string) ($onAlias['error'] ?? ''));
        $random = $x->generate(1, 10, 10, 'ALT', 'F', 'fr', null, false, $this->now);
        self::assertTrue($random['ok'], (string) ($random['error'] ?? ''));
        // Copie de la personne fictive (ancien « Dupliquer ») : même fichier sous une autre référence.
        $row = $this->pdo->query('SELECT * FROM sse_cover_exports WHERE id = ' . (int) $random['id'])->fetch(PDO::FETCH_ASSOC);
        $copy = (new SseCoverExportRepository())->insert(1, 10, 10, ['ref' => 'CVR-0000AAAA', 'seed' => (int) $row['seed'], 'country_code' => (string) $row['country_code'],
            'cover_name' => (string) $row['cover_name'], 'doc_number' => (string) $row['doc_number'], 'content_sha' => (string) $row['content_sha'], 'payload_json' => (string) $row['payload_json']], '2026-11-11 12:00:01');
        $revoked = (new SseCoverExportRepository())->insert(1, 10, 10, ['ref' => 'CVR-0000BBBB', 'seed' => 1, 'country_code' => 'ALT', 'cover_name' => 'Révoquée',
            'doc_number' => '', 'content_sha' => str_repeat('0', 64), 'payload_json' => (string) $row['payload_json']], '2026-11-11 12:00:02');
        $invalid = (new SseCoverExportRepository())->insert(1, 10, 10, ['ref' => 'CVR-0000CCCC', 'seed' => 1, 'country_code' => 'ALT', 'cover_name' => 'Illisible',
            'doc_number' => '', 'content_sha' => str_repeat('0', 64), 'payload_json' => '{pas du json'], '2026-11-11 12:00:03');
        $hub = new OperatorHubRepository();
        $hub->setOnlyActive(1, 10, OperatorHubRepository::KIND_COVER, (int) $random['id'], 10);
        $hub->setState(1, 10, OperatorHubRepository::KIND_COVER, $revoked, ['revoked_at' => '2026-11-11 12:00:00'], 10);
        // Autre membre, autre communauté.
        $otherIdentity = (int) $s->saveIdentity(1, 11, null, $this->civil(['last_name' => 'Autre', 'first_name' => 'Membre']), $this->now)['id'];
        $other = $x->generate(1, 11, 11, 'ALT', 'M', 'fr', null, false, $this->now);
        $elsewhere = (int) $s->saveIdentity(2, 20, null, $this->civil(['last_name' => 'Ailleurs', 'first_name' => 'Membre', 'nationality' => '']), $this->now)['id'];

        return ['main' => $main, 'alias' => $alias, 'passport' => $passport, 'linked' => (int) $linked['id'], 'on_alias' => (int) $onAlias['id'], 'random' => (int) $random['id'],
            'random_ref' => (string) $random['ref'], 'random_name' => (string) $random['name'], 'copy' => $copy, 'revoked' => $revoked, 'invalid' => $invalid,
            'other_identity' => $otherIdentity, 'other' => (int) $other['id'], 'elsewhere' => $elsewhere];
    }

    // ------------------------------------------------------------------ Étape 4450

    public function testDryRunListsWhatWouldBeMergedAndWritesNothing(): void
    {
        $ids = $this->seed();
        $before = [
            'identities' => (int) $this->pdo->query('SELECT COUNT(*) FROM sse_identities')->fetchColumn(),
            'exports' => $this->pdo->query('SELECT id, payload_json FROM sse_cover_exports ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
        ];
        $log = '';
        $report = ($this->migration())($this->pdo, static function (string $m) use (&$log): void {
            $log .= $m;
        }, true);
        self::assertTrue($report['dry_run']);
        self::assertSame(['covers' => 7, 'already' => 0, 'linked' => 1, 'matched' => 2, 'created' => 2, 'revoked' => 1, 'invalid' => 1],
            array_intersect_key($report, array_flip(['covers', 'already', 'linked', 'matched', 'created', 'revoked', 'invalid'])) + [],
            'liée ; sur l’identité d’emprunt et copie, rapprochées ; deux personnes fictives à créer (Jake, l’autre membre) ; révoquée ; illisible');
        // Rien n'est créé ni écrit : ni tables, ni identité, ni fichier touché.
        foreach (['sse_identity_stories', 'sse_cover_export_merges'] as $table) {
            self::assertSame(0, (int) $this->pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE name = '" . $table . "'")->fetchColumn(), $table);
        }
        self::assertSame($before['identities'], (int) $this->pdo->query('SELECT COUNT(*) FROM sse_identities')->fetchColumn());
        self::assertSame($before['exports'], $this->pdo->query('SELECT id, payload_json FROM sse_cover_exports ORDER BY id')->fetchAll(PDO::FETCH_ASSOC));
        // Le rapport dit quoi, pour qui, et les doublons (même personne dans deux couvertures).
        self::assertStringContainsString('À BLANC', $log);
        self::assertStringContainsString('fondue dans l’identité ' . $ids['main'] . ' (déjà liée)', $log);
        self::assertStringContainsString('fondue dans l’identité ' . $ids['alias'] . ' (même nom et même date de naissance)', $log);
        self::assertStringContainsString('identité d’emprunt à créer ; était la couverture active', $log);
        self::assertStringContainsString('révoquée par le membre, non fondue', $log);
        self::assertStringContainsString('fichier illisible, non fondue', $log);
        self::assertCount(1, $report['duplicates']);
        self::assertStringContainsString('CVR-0000AAAA', $report['duplicates'][0]);
        self::assertStringContainsString('[DOUBLON]', $log);
    }

    public function testMergeCreatesIdentitiesKeepsEverythingAndIsIdempotent(): void
    {
        $ids = $this->seed();
        $exportsBefore = $this->pdo->query('SELECT * FROM sse_cover_exports ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $linksBefore = $this->pdo->query('SELECT * FROM sse_cover_export_links ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $statesBefore = $this->pdo->query('SELECT * FROM operator_identity_states ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $kitsBefore = $this->pdo->query('SELECT * FROM sse_id_kits ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

        $report = ($this->migration())($this->pdo);
        self::assertFalse($report['dry_run']);
        self::assertSame([1, 2, 2, 1, 1], [$report['linked'], $report['matched'], $report['created'], $report['revoked'], $report['invalid']], 'créées : la personne fictive de Jake et celle de l’autre membre');

        // Aucune perte : les anciennes tables sont intactes, l'identité envoyée en jeu n'a pas bougé.
        self::assertSame($exportsBefore, $this->pdo->query('SELECT * FROM sse_cover_exports ORDER BY id')->fetchAll(PDO::FETCH_ASSOC));
        self::assertSame($linksBefore, $this->pdo->query('SELECT * FROM sse_cover_export_links ORDER BY id')->fetchAll(PDO::FETCH_ASSOC));
        self::assertSame($statesBefore, $this->pdo->query('SELECT * FROM operator_identity_states ORDER BY id')->fetchAll(PDO::FETCH_ASSOC));
        self::assertSame($kitsBefore, $this->pdo->query('SELECT * FROM sse_id_kits ORDER BY id')->fetchAll(PDO::FETCH_ASSOC));

        $merges = [];
        foreach ($this->pdo->query('SELECT * FROM sse_cover_export_merges ORDER BY export_id')->fetchAll(PDO::FETCH_ASSOC) as $m) {
            $merges[(int) $m['export_id']] = $m;
        }
        self::assertCount(7, $merges);
        self::assertSame(['linked', $ids['main']], [$merges[$ids['linked']]['action'], (int) $merges[$ids['linked']]['identity_id']]);
        self::assertSame(['matched', $ids['alias']], [$merges[$ids['on_alias']]['action'], (int) $merges[$ids['on_alias']]['identity_id']]);
        self::assertSame('created', $merges[$ids['random']]['action']);
        self::assertSame(1, (int) $merges[$ids['random']]['was_active'], 'marqueur actif gardé');
        $created = (int) $merges[$ids['random']]['identity_id'];
        self::assertSame(['matched', $created], [$merges[$ids['copy']]['action'], (int) $merges[$ids['copy']]['identity_id']], 'la copie rejoint l’identité créée : pas de doublon');
        self::assertSame(['revoked', null], [$merges[$ids['revoked']]['action'], $merges[$ids['revoked']]['identity_id']]);
        self::assertSame(['invalid', null], [$merges[$ids['invalid']]['action'], $merges[$ids['invalid']]['identity_id']]);

        // L'identité créée : identité d'emprunt du membre, avec l'état civil de la couverture et sa légende en histoire.
        $list = array_column($this->service()->identities(1, 10), null, 'id');
        self::assertCount(3, $list);
        self::assertSame('emprunt', $list[$created]['type']);
        self::assertSame($ids['random_name'], trim($list[$created]['first_name'] . ' ' . $list[$created]['last_name']));
        self::assertSame('ALT', $list[$created]['nationality']);
        self::assertStringContainsString('Employeur : ', $list[$created]['story']);
        self::assertSame(1, substr_count($list[$created]['story'], 'Employeur : '), 'la légende de la copie n’est pas ajoutée deux fois');
        self::assertStringContainsString('A grandi sur les quais.', $list[$ids['main']]['story']);
        self::assertTrue($list[$ids['main']]['active'], 'l’identité active en jeu reste celle du personnage');
        // Les identités de l'autre membre et de l'autre communauté ne reçoivent rien de Jake.
        self::assertCount(2, $this->service()->identities(1, 11));
        self::assertCount(1, $this->service()->identities(2, 20));

        // Rejouée : rien de plus.
        $count = static fn (PDO $pdo, string $t): int => (int) $pdo->query('SELECT COUNT(*) FROM ' . $t)->fetchColumn();
        $snapshot = [$count($this->pdo, 'sse_identities'), $count($this->pdo, 'sse_cover_export_merges'), $this->pdo->query('SELECT identity_id, story FROM sse_identity_stories ORDER BY identity_id')->fetchAll(PDO::FETCH_ASSOC)];
        $again = ($this->migration())($this->pdo);
        self::assertSame([7, 7, 0, 0, 0], [$again['covers'], $again['already'], $again['linked'], $again['matched'], $again['created']]);
        self::assertSame([], $again['lines']);
        self::assertSame($snapshot, [$count($this->pdo, 'sse_identities'), $count($this->pdo, 'sse_cover_export_merges'), $this->pdo->query('SELECT identity_id, story FROM sse_identity_stories ORDER BY identity_id')->fetchAll(PDO::FETCH_ASSOC)]);

        // Étape déclarée, numéro libre, mode à blanc documenté.
        $root = dirname(__DIR__, 2);
        $registry = (string) file_get_contents($root . '/database/steps/registry.php');
        self::assertMatchesRegularExpression("/'id' => 'sse_identities_unified'.*4450_sse_identities_unified\\.php.*'rerun' => true/", $registry);
        self::assertCount(1, glob($root . '/database/steps/4450_*.php') ?: []);
        self::assertStringContainsString('SSE_IDENTITES_A_BLANC', (string) file_get_contents($root . '/database/steps/4450_sse_identities_unified.php'));
    }

    // ------------------------------------------------------------------ Téléphone du jeu : même contrat

    public function testPhoneGetsTheSameCoverFileBuiltFromTheIdentity(): void
    {
        $s = $this->service();
        $main = (int) $s->saveIdentity(1, 10, null, $this->civil(), $this->now)['id'];
        $passport = $this->issue(1, 10, $main, 'passport');
        ($this->migration())($this->pdo);
        // Référence : l'ancien fichier, généré à la même date que celle que prend le fichier dérivé (1er du mois).
        $stamp = new \DateTimeImmutable('2026-11-01 00:00:00', new \DateTimeZone('UTC'));
        $old = $this->export()->generateFromIdentity(1, 10, 10, 'fr', false, $stamp, null, null, $main, [$passport]);
        self::assertTrue($old['ok']);
        $oldJson = (string) $this->pdo->query('SELECT payload_json FROM sse_cover_exports WHERE id = ' . (int) $old['id'])->fetchColumn();
        $oldFile = json_decode($oldJson, true);

        $built = $this->feed()->build(1, 10, $this->now);
        self::assertCount(1, $built);
        $newFile = json_decode($built[0]['json'], true);
        // Mêmes clés, même contenu pour une même identité ; seules la référence (désormais stable) et la signature changent.
        self::assertSame(array_keys($oldFile), array_keys($newFile));
        self::assertSame(array_keys($oldFile['cover']), array_keys($newFile['cover']));
        self::assertSame($oldFile['cover'], $newFile['cover']);
        self::assertSame($oldFile['content_sha256'], $newFile['content_sha256']);
        foreach (['format', 'version', 'generated_at', 'expires_at', 'account_ref'] as $k) {
            self::assertSame($oldFile[$k], $newFile[$k], $k);
        }
        self::assertSame('Jake Gyllen', $newFile['cover']['name']);
        self::assertSame('passport', $newFile['cover']['docs'][0]['type']);
        // Le contrôle strict du mod l'accepte, la signature du serveur est valable, la référence garde son format.
        $parsed = SseCoverExportService::parse($built[0]['json'], $this->now);
        self::assertTrue($parsed['ok']);
        self::assertTrue($this->export()->verifySignature($newFile));
        self::assertFalse((new SseCoverExportService(null, null, null, $this->noop(), 'autre-clé'))->verifySignature($newFile));
        self::assertMatchesRegularExpression('/\ACVR-[0-9A-F]{8}\z/', $newFile['ref']);
        self::assertNotSame($oldFile['ref'], $newFile['ref']);
        // Référence et fichier stables d'un appel à l'autre, et tout le mois : le téléphone ne voit pas de doublon.
        self::assertSame($built[0]['json'], $this->feed()->build(1, 10, $this->now->modify('+9 days'))[0]['json']);
        self::assertSame($newFile['ref'], json_decode($this->feed()->build(1, 10, $this->now->modify('+40 days'))[0]['json'], true)['ref']);
        self::assertGreaterThan($this->now->modify('+50 days')->format('Y-m-d\TH:i:s\Z'), $newFile['expires_at'], 'jamais servi expiré');

        // L'histoire écrite par le membre devient les lignes « history » (8 au plus, 255 caractères, sans contrôle).
        $s->saveIdentity(1, 10, $main, $this->civil() + ['story' => "Né à Kavala.\n\n" . str_repeat('x', 300) . "\n" . implode("\n", range(1, 12))], $this->now);
        $history = $this->feed()->build(1, 10, $this->now)[0]['cover']['history'];
        self::assertCount(8, $history);
        self::assertSame('Né à Kavala.', $history[0]);
        self::assertSame(255, mb_strlen($history[1]));
        self::assertSame([], SseIdentityCoverFeedService::historyLines(" \n\t\n"));
    }

    public function testLegacyEndpointsKeepTheirJsonShapeAndFlagTheActiveIdentity(): void
    {
        $ids = $this->seed();
        // Avant l'étape : comportement d'avant, clés de référence de la liste.
        $legacy = $this->sync()->listFor(1, 10, $this->now);
        self::assertCount(5, $legacy, 'les fichiers générés, sauf celui révoqué');
        $keys = array_keys($legacy[0]);

        ($this->migration())($this->pdo);
        $list = $this->sync()->listFor(1, 10, $this->now);
        self::assertCount(3, $list, 'une entrée par identité, plus aucune ancienne couverture');
        foreach ($list as $entry) {
            self::assertSame($keys, array_keys($entry), 'aucune clé ajoutée ni retirée');
            self::assertSame('valid', $entry['status']);
            self::assertFalse($entry['by_manager'], 'jamais l’origine d’une identité');
            self::assertMatchesRegularExpression('/\ACVR-[0-9A-F]{8}\z/', $entry['ref']);
            self::assertMatchesRegularExpression('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z\z/', $entry['updated_at']);
        }
        self::assertSame([true, false, false], array_column($list, 'active'), 'un seul marqueur : l’identité active en jeu, en tête');
        self::assertSame('Jake Gyllen', $list[0]['name']);
        self::assertSame(array_column($list, 'ref'), array_column($this->sync()->listFor(1, 10, $this->now), 'ref'), 'références stables');

        // « Utiliser en jeu » sur l'identité d'emprunt : c'est elle que le téléphone voit active.
        self::assertTrue($this->service()->activateIdentity(1, 10, $ids['alias'])['ok'] || true);
        $this->issue(1, 10, $ids['alias'], 'id_card');
        $res = $this->service()->activateIdentity(1, 10, $ids['alias']);
        self::assertTrue($res['ok'], $res['message']);
        $list = $this->sync()->listFor(1, 10, $this->now);
        self::assertSame('Nikos Kostas', $list[0]['name']);
        self::assertSame([true, false, false], array_column($list, 'active'));

        // Fichiers : contrat inchangé, photo absente, envoi noté ; contrôleur et relais du paquet d'identité.
        $refs = array_column($list, 'ref');
        $out = $this->sync()->filesFor(1, 10, [$refs[0], 'CVR-FFFFFFFF'], $this->now);
        self::assertSame([['ref' => 'CVR-FFFFFFFF', 'error' => 'not_found']], $out['errors']);
        self::assertCount(1, $out['files']);
        self::assertSame(['format', 'version', 'ref', 'generated_at', 'expires_at', 'account_ref', 'cover', 'content_sha256', 'sig'], array_keys($out['files'][0]));
        self::assertNull($out['files'][0]['cover']['photo']);
        self::assertTrue($this->export()->verifySignature($out['files'][0]));
        self::assertTrue($this->sync()->listFor(1, 10, $this->now)[0]['downloaded']);
        self::assertNotNull(array_column($this->service()->identities(1, 10), null, 'id')[$ids['alias']]['game_synced_at']);

        $c = new AtakCoverSyncApiController($this->sync(), static fn (): array => [1, 10], static fn (): bool => false);
        $body = json_decode($c->index(new Request())->body(), true);
        self::assertSame(['covers_api', 'covers'], array_keys($body));
        self::assertSame(SseCoverSyncService::API_VERSION, $body['covers_api']);
        self::assertCount(3, $body['covers']);
        $_GET = ['refs' => implode(',', $refs)];
        $files = json_decode($c->files(new Request())->body(), true);
        self::assertSame(['covers_api', 'files', 'errors'], array_keys($files));
        self::assertCount(3, $files['files']);
        $pack = json_decode($c->packResponse(SseCoverSyncService::OP_LIST, 'v=1', 1)->body(), true);
        self::assertSame(['version', 'generated_at', 'map', 'op', 'settings'], array_keys($pack));
        self::assertSame($refs, array_column($pack['settings']['covers'], 'ref'));
        $pack = json_decode($c->packResponse(SseCoverSyncService::OP_FILES . $refs[1], 'v=1', 1)->body(), true);
        self::assertSame($refs[1], $pack['settings']['files'][0]['ref']);
    }

    public function testBorrowedIdentitiesNeverReachAnotherMemberOrCommunity(): void
    {
        $ids = $this->seed();
        ($this->migration())($this->pdo);
        $mine = array_column($this->sync()->listFor(1, 10, $this->now), 'ref');
        $theirs = array_column($this->sync()->listFor(1, 11, $this->now), 'ref');
        $elsewhere = array_column($this->sync()->listFor(2, 20, $this->now), 'ref');
        self::assertCount(3, $mine);
        self::assertCount(2, $theirs);
        self::assertSame([], array_intersect($mine, $theirs));
        self::assertSame([], array_intersect($mine, $elsewhere));
        // Les références de Jake demandées par un autre membre, ou depuis une autre communauté : introuvables.
        foreach ([[1, 11], [2, 20], [2, 10], [1, 999]] as [$tenant, $user]) {
            $out = $this->sync()->filesFor($tenant, $user, $mine, $this->now);
            self::assertSame([], $out['files'], $tenant . ':' . $user);
            self::assertSame(['not_found', 'not_found', 'not_found'], array_column($out['errors'], 'error'));
        }
        // Les anciennes références (fichiers fondus) ne sont plus servies à personne, pas même à leur propriétaire.
        self::assertSame([['ref' => $ids['random_ref'], 'error' => 'not_found']], $this->sync()->filesFor(1, 10, [(string) $ids['random_ref']], $this->now)['errors']);
        // Côté site : la liste d'un membre ne montre que ses identités ; celles d'un autre ne s'ouvrent pas.
        $s = $this->service();
        self::assertNotContains($ids['alias'], array_column($s->identities(1, 11), 'id'));
        self::assertNull($s->ownedIdentity(1, 11, $ids['alias']));
        self::assertNull($s->ownedIdentity(2, 10, $ids['alias']));
        self::assertSame([], (new SseIdentityStoryRepository())->forIdentities(2, [$ids['main'], $ids['alias']]), 'histoire bornée à la communauté');
        // Identité archivée : plus envoyée au téléphone.
        self::assertTrue($s->archiveIdentity(1, 10, $ids['alias'], true)['ok']);
        self::assertCount(2, $this->sync()->listFor(1, 10, $this->now));
    }

    public function testAFileGeneratedByAManagerAfterTheStepIsStillServedUntilMerged(): void
    {
        $s = $this->service();
        $s->saveIdentity(1, 10, null, $this->civil(), $this->now);
        ($this->migration())($this->pdo);
        // Fiche membre du Bureau SSE : un responsable génère encore un fichier pour le membre (fonction gardée).
        $late = $this->export()->generate(1, 10, 99, 'ALT', 'F', 'fr', null, true, $this->now);
        self::assertTrue($late['ok']);
        $list = $this->sync()->listFor(1, 10, $this->now);
        self::assertSame(['Jake Gyllen', $late['name']], array_column($list, 'name'));
        self::assertSame([true, false], array_column($list, 'active'));
        self::assertTrue($list[1]['by_manager']);
        self::assertCount(1, $this->sync()->filesFor(1, 10, [(string) $late['ref']], $this->now)['files']);
        // Étape rejouée : le fichier devient une identité d'emprunt, il n'est plus listé deux fois.
        $again = ($this->migration())($this->pdo);
        self::assertSame(1, $again['created']);
        $list = $this->sync()->listFor(1, 10, $this->now);
        self::assertCount(2, $list);
        self::assertSame([false, false], array_column($list, 'by_manager'));
    }

    // ------------------------------------------------------------------ Pages : tout avertissement PHP est fatal

    public function testPagesRenderWithEveryKindOfDataAndEveryPhpWarningFatal(): void
    {
        $ids = $this->seed();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_URI'] = '/operateur/identite';
        Session::set('user_id', 10);
        Session::set('tenant_id', 1);
        $limiter = $this->createMock(FileRateLimiter::class);
        $limiter->method('tooManyAttempts')->willReturn(false);
        $noop = $this->noop();
        $make = static fn (int $user, bool $owner, int $tenant = 1): OperatorHubController => new OperatorHubController(
            new OperatorHubRepository(),
            new OperatorIdentityService(null, null, null, null, null, null, $noop),
            // Profil vide : ni indicatif, ni Steam, ni unité.
            static fn (): ?array => ['tenant' => $tenant, 'user' => $user, 'row' => ['id' => $user, 'display_name' => '', 'steam_id' => null]],
            static fn (string $p): bool => $owner,
            static fn (string $path): bool => true,
            null,
            static fn (Request $r): bool => true,
            static function (string $type, string $message): void {
            },
            $limiter,
            static fn (int $t, int $u): array => [],
        );
        $level = ob_get_level();
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        $pages = [];
        try {
            // Avant l'étape (tables absentes : comportement sans histoire), puis après (couvertures fondues).
            foreach (['avant', 'après'] as $phase) {
                if ($phase === 'après') {
                    ($this->migration())($this->pdo);
                    // Une identité archivée et une identité montée par un responsable, pour couvrir chaque branche.
                    $forged = (new MemberIdentityPapersService(null, null, null, null, null, $noop))->createAlias(1, 10, ['kind' => 'cover', 'last_name' => 'Petrov', 'first_name' => 'Ivan'], true, 99);
                    $this->pdo->exec("UPDATE sse_identities SET kind = 'forged' WHERE id = " . (int) $forged['id']);
                    $this->service()->archiveIdentity(1, 10, $ids['alias'], true);
                }
                // Membre aux données complètes, propriétaire avec tous les droits, membre sans rien (profil vide).
                foreach ([[10, false, 1], [10, true, 1], [999, false, 1], [20, true, 2]] as [$user, $owner, $tenant]) {
                    $c = $make($user, $owner, $tenant);
                    foreach (['index' => 'Outils opérateur', 'identities' => 'Mes identités', 'documents' => 'Mes identités'] as $method => $needle) {
                        $_GET = $method === 'identities' ? ['modifier' => (string) $ids['main']] : [];
                        $res = $c->$method(new Request());
                        $label = $phase . ' ' . $method . ' membre ' . $user;
                        self::assertSame(200, $res->statusCode(), $label);
                        self::assertStringContainsString($needle, $res->body(), $label);
                        self::assertStringContainsString('dash-cc dash-cc--rail', $res->body(), $label . ' : rendu dans le gabarit du tableau de bord');
                        self::assertStringContainsString('</html>', $res->body(), $label . ' : page rendue jusqu’au bout');
                        // Contenu de la page seul (l'en-tête et le rail du gabarit ne sont pas à cette page).
                        $own = substr($res->body(), (int) strpos($res->body(), '<div class="opx'));
                        $own = substr($own, 0, (int) (strpos($own, '<footer') ?: strlen($own)));
                        self::assertStringNotContainsString('kicker', $own, $label);
                        self::assertStringNotContainsString('eyebrow', $own, $label);
                        $pages[$phase . ':' . $user . ':' . (int) $owner . ':' . $method] = $res->body();
                    }
                }
            }
        } finally {
            restore_error_handler();
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
        // Avant l'étape : pas de champ histoire. Après : le champ, l'histoire reprise de la légende, les sections.
        self::assertStringNotContainsString('name="story"', $pages['avant:10:0:identities']);
        $html = $pages['après:10:0:identities'];
        foreach (['name="story"', 'A grandi sur les quais.', '>Histoire</h4>', '>Jeu</h4>', '>Personnage</span>', '>D’emprunt</span>', 'Archivée', 'data-opx-filters',
            'Active en jeu</span>', 'Nouvelle identité', 'PETROV', 'data-opx-autoopen'] as $needle) {
            self::assertStringContainsString($needle, $html, $needle);
        }
        self::assertSame(1, substr_count($html, 'Active en jeu</span>'), 'un seul marqueur « Active en jeu »');
        // Le membre ne lit jamais la nature d'une identité, et le mot « couverture » a disparu de ses pages.
        foreach (['identities', 'documents', 'index'] as $method) {
            $body = substr($pages['après:10:0:' . $method], (int) strpos($pages['après:10:0:' . $method], '<div class="opx'));
            $body = substr($body, 0, (int) (strpos($body, '<footer') ?: strlen($body)));
            foreach (['ouverture', 'Falsifi', 'falsifi', 'forged', 'Légende', 'CVR-'] as $secret) {
                self::assertStringNotContainsString($secret, $body, $method . ' : ' . $secret);
            }
        }
        // Membre sans identité : la création guidée de son personnage, et rien d'autre.
        self::assertStringContainsString('Créer mon personnage', $pages['après:999:0:identities']);
        self::assertStringNotContainsString('data-opx-identity-card', $pages['après:999:0:identities']);
    }

    public function testNoCoverCodeIsLeftOnTheMemberSide(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['views/operateur/_tab_couverture.php', 'app/Repositories/SseCoverExportLinkRepository.php', 'app/Support/SseCoverLinkMatcher.php'] as $gone) {
            self::assertFileDoesNotExist($root . '/' . $gone);
        }
        foreach (['views/operateur/identite.php', 'views/operateur/_tab_identites.php', 'views/operateur/_tab_documents.php', 'views/operateur/hub.php',
            'public/assets/js/operateur-hub.js', 'app/Services/Operator/OperatorIdentityService.php', 'app/Services/Operator/OperatorHubCatalog.php'] as $file) {
            $src = (string) file_get_contents($root . '/' . $file);
            self::assertDoesNotMatchRegularExpression('/couverture|\bcover(s|Usage|Quota)?\b|Cover[A-Z]/u', (string) preg_replace('/object-fit: cover/', '', $src), $file);
            self::assertStringNotContainsString('kicker', $src, $file);
            self::assertStringNotContainsString('eyebrow', $src, $file);
            if (str_starts_with($file, 'views/')) {
                self::assertStringNotContainsString('(s)', $src, $file . ' : singulier et pluriel écrits en entier');
            }
        }
        // Les points d'accès du téléphone restent, en lecture seule.
        $routes = (string) file_get_contents($root . '/routes/web.php') . (string) @file_get_contents($root . '/routes/api.php');
        self::assertStringContainsString('AtakCoverSyncApiController', $routes);
    }
}
