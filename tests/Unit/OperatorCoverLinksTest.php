<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\OperatorHubController;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\OperatorHubRepository;
use App\Repositories\SseCoverExportLinkRepository;
use App\Repositories\SseCoverExportRepository;
use App\Services\Alerts\AlertPresentationService;
use App\Services\Operator\OperatorIdentityService;
use App\Services\Security\FileRateLimiter;
use App\Services\Sse\MemberIdentityPapersService;
use App\Services\Sse\SseCoverExportService;
use App\Services\Sse\SseCoverSyncService;
use App\Support\SseCoverLinkMatcher;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseIdentitySqlite;

/**
 * Couvertures liées (Mon identité › Couvertures) : une couverture référence une identité du membre et les papiers de
 * cette identité qu'elle emporte (sse_cover_export_links) ; le fichier envoyé au jeu porte exactement ces papiers.
 * Lien à la création, propriété, changement de sélection, rétro-remplissage, table absente, compteurs des onglets,
 * rendu des pages avec des données.
 */
final class OperatorCoverLinksTest extends TestCase
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
        $this->now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
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

    private function service(): OperatorIdentityService
    {
        $exports = new SseCoverExportRepository();
        $noop = static function (): void {
        };

        return new OperatorIdentityService(null, null, null, null, null, null, $exports, new SseCoverExportService($exports, null, null, $noop, 'test-key'), $noop);
    }

    /** @param array<string, string> $over */
    private function identity(int $tenant, int $user, array $over = []): int
    {
        $res = $this->service()->saveIdentity($tenant, $user, null, $over + [
            'last_name' => 'Gyllen', 'first_name' => 'Jake', 'sex' => 'M', 'birth_date' => '1990-04-02', 'birth_place' => 'Kavala', 'nationality' => 'ALT',
        ]);
        self::assertTrue($res['ok'], (string) ($res['error'] ?? ''));

        return (int) $res['id'];
    }

    /** Papier demandé par le membre ; $issue : émis par un responsable. */
    private function paper(int $tenant, int $user, int $identityId, string $type, bool $issue = true): int
    {
        $res = $this->service()->requestDocument($tenant, $user, $identityId, $type, 'ALT');
        self::assertTrue($res['ok'], (string) ($res['error'] ?? ''));
        if ($issue) {
            $papers = new MemberIdentityPapersService(null, null, null, null, null, static function (): void {
            });
            $done = $papers->action($tenant, $user, $papers->ownedDocument($tenant, $user, (int) $res['id'], true), 'issue', '', 99);
            self::assertTrue($done['ok'], (string) ($done['message'] ?? ''));
        }

        return (int) $res['id'];
    }

    /** @return array<string, mixed> « cover » du fichier d'une couverture */
    private function fileCover(int $coverId): array
    {
        $json = (string) $this->pdo->query('SELECT payload_json FROM sse_cover_exports WHERE id = ' . $coverId)->fetchColumn();
        $parsed = SseCoverExportService::parse($json, new \DateTimeImmutable('@0'));
        self::assertTrue($parsed['ok'], 'fichier relu par le contrôle strict partagé avec le mod');
        self::assertTrue((new SseCoverExportService(new SseCoverExportRepository(), null, null, static function (): void {
        }, 'test-key'))->verifySignature($parsed['file']), 'signature du serveur valable');

        return $parsed['cover'] + ['_file' => $parsed['file']];
    }

    /** @return array<string, mixed> */
    private function cover(int $coverId, int $tenant = 1, int $user = 10): array
    {
        foreach ($this->service()->linkedCovers($tenant, $user) as $c) {
            if ($c['id'] === $coverId) {
                return $c;
            }
        }
        self::fail('couverture absente');
    }

    // ------------------------------------------------------------------ Lien à la création

    public function testACoverIsBuiltOnAnIdentityAndCarriesExactlyTheChosenPapers(): void
    {
        $s = $this->service();
        $main = $this->identity(1, 10);
        $passport = $this->paper(1, 10, $main, 'passport');
        $card = $this->paper(1, 10, $main, 'id_card');
        $this->paper(1, 10, $main, 'driving_license', false);

        $res = $s->generateCover(1, 10, 'athena', '', null, 'fr', null, null, $main, [$passport], ['profession' => 'Négociant en vins', 'history' => "A grandi à Kavala.\nA repris le commerce familial."]);
        self::assertTrue($res['ok'], (string) ($res['error'] ?? ''));
        $link = (new SseCoverExportLinkRepository())->forUser(1, 10)[(int) $res['id']] ?? null;
        self::assertSame(['identity_id' => $main, 'document_ids' => [$passport]], $link, 'le lien référence l’identité et le papier choisi');

        $file = $this->fileCover((int) $res['id']);
        self::assertSame('Jake Gyllen', $file['name']);
        self::assertSame('1990-04-02', $file['dob']);
        self::assertSame('Négociant en vins', $file['profession']);
        self::assertSame(['A grandi à Kavala.', 'A repris le commerce familial.'], $file['history']);
        self::assertCount(1, $file['docs'], 'le fichier porte le papier choisi, pas la carte d’identité');
        self::assertSame('passport', $file['docs'][0]['type']);
        self::assertSame((string) $this->pdo->query('SELECT doc_number FROM sse_id_documents WHERE id = ' . $passport)->fetchColumn(), $file['docs'][0]['number']);

        $view = $this->cover((int) $res['id']);
        self::assertTrue($view['linked']);
        self::assertSame($main, $view['identity']['id']);
        self::assertSame([$passport], array_column($view['papers'], 'id'));
        self::assertTrue($view['papers'][0]['in_file']);
        self::assertSame([$card], array_column($view['addable'], 'id'), 'la carte d’identité valable reste à ajouter');
        self::assertSame(['paper_waiting'], array_column($view['warnings'], 'code'), 'le permis demandé n’est pas encore émis');
        self::assertSame([], $view['missing'], 'passeport, carte et permis : rien de typique ne manque');
        self::assertNotContains('file_differs', array_column($view['hints'], 'code'));
        self::assertArrayNotHasKey('kind', $view['identity'], 'jamais la nature de l’identité');

        // Le téléphone du jeu reçoit exactement ces papiers.
        $sent = (new SseCoverSyncService(new SseCoverExportRepository(), new SseCoverExportService(new SseCoverExportRepository(), null, null, static function (): void {
        }, 'test-key')))->filesFor(1, 10, [(string) $res['ref']]);
        self::assertSame([], $sent['errors']);
        self::assertSame($file['docs'], $sent['files'][0]['cover']['docs']);
    }

    public function testWithoutAChoiceEveryValidPaperOfTheIdentityIsCarried(): void
    {
        $s = $this->service();
        $main = $this->identity(1, 10);
        $alias = $this->identity(1, 10, ['last_name' => 'Markos', 'first_name' => 'Petros', 'birth_date' => '1987-07-15']);
        $a = $this->paper(1, 10, $main, 'passport');
        $b = $this->paper(1, 10, $main, 'id_card');
        $this->paper(1, 10, $alias, 'passport');
        $lost = $this->paper(1, 10, $main, 'driving_license');
        self::assertTrue($s->documentAction(1, 10, $lost, 'lost')['ok']);

        $res = $s->generateCover(1, 10, 'athena', '', null, 'fr', null, null, $main);
        self::assertTrue($res['ok'], (string) ($res['error'] ?? ''));
        $ids = (new SseCoverExportLinkRepository())->forUser(1, 10)[(int) $res['id']]['document_ids'];
        sort($ids);
        self::assertSame([$a, $b], $ids, 'ni le papier perdu, ni celui de l’autre identité');
        self::assertCount(2, $this->fileCover((int) $res['id'])['docs']);

        // Identité d'emprunt sans papier valable : couverture créée, liée, sans papier, avec l'avertissement.
        $bare = $this->identity(1, 10, ['last_name' => 'Vide', 'first_name' => 'Anna', 'birth_date' => '1992-01-01']);
        $res = $s->generateCover(1, 10, 'athena', '', null, 'fr', null, null, $bare, []);
        self::assertTrue($res['ok'], (string) ($res['error'] ?? ''));
        self::assertSame([], $this->fileCover((int) $res['id'])['docs']);
        $view = $this->cover((int) $res['id']);
        self::assertSame('no_paper', $view['warnings'][0]['code']);
        self::assertSame(['passport', 'id_card', 'driving_license'], array_column($view['missing'], 'type'), 'papiers typiques à demander');
    }

    // ------------------------------------------------------------------ Propriété

    public function testPapersOfAnotherIdentityMemberOrCommunityAreRefused(): void
    {
        $s = $this->service();
        $main = $this->identity(1, 10);
        $alias = $this->identity(1, 10, ['last_name' => 'Markos', 'first_name' => 'Petros', 'birth_date' => '1987-07-15']);
        $mine = $this->paper(1, 10, $main, 'passport');
        $ofAlias = $this->paper(1, 10, $alias, 'passport');
        $notIssued = $this->paper(1, 10, $main, 'id_card', false);
        $otherIdentity = $this->identity(1, 11, ['last_name' => 'Durand', 'first_name' => 'Paul']);
        $ofOtherMember = $this->paper(1, 11, $otherIdentity, 'passport');
        $foreignIdentity = $this->identity(2, 20, ['last_name' => 'Smith', 'first_name' => 'John']);
        $ofOtherCommunity = $this->paper(2, 20, $foreignIdentity, 'passport');

        foreach (['autre identité' => $ofAlias, 'autre membre' => $ofOtherMember, 'autre communauté' => $ofOtherCommunity, 'pas émis' => $notIssued, 'inconnu' => 99999] as $why => $docId) {
            $res = $s->generateCover(1, 10, 'athena', '', null, 'fr', null, null, $main, [$mine, $docId]);
            self::assertFalse($res['ok'], $why);
            self::assertSame('paper', $res['error'], $why);
        }
        foreach (['autre membre' => $otherIdentity, 'autre communauté' => $foreignIdentity, 'inconnue' => 99999] as $why => $identityId) {
            $res = $s->generateCover(1, 10, 'athena', '', null, 'fr', null, null, $identityId, []);
            self::assertFalse($res['ok'], $why);
            self::assertSame('no_identity', $res['error'], $why);
        }
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_cover_exports')->fetchColumn(), 'aucune couverture créée par un refus');
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_cover_export_links')->fetchColumn());

        // Même contrôle quand on change les papiers d'une couverture existante ; et personne d'autre ne la modifie.
        $cover = (int) $s->generateCover(1, 10, 'athena', '', null, 'fr', null, null, $main, [$mine])['id'];
        foreach ([$ofAlias, $ofOtherMember, $ofOtherCommunity, $notIssued] as $docId) {
            self::assertFalse($s->setCoverDocuments(1, 10, $cover, [$mine, $docId])['ok']);
        }
        self::assertFalse($s->setCoverDocuments(1, 11, $cover, [])['ok']);
        self::assertFalse($s->setCoverDocuments(2, 20, $cover, [])['ok']);
        self::assertFalse($s->attachCover(1, 11, $cover)['ok']);
        self::assertSame([$mine], (new SseCoverExportLinkRepository())->forUser(1, 10)[$cover]['document_ids']);
        self::assertSame([], (new SseCoverExportLinkRepository())->forUser(1, 11), 'un lien ne se lit que pour son membre');
        self::assertSame([], (new SseCoverExportLinkRepository())->forUser(2, 10));
    }

    public function testTheCreationLimitOverTwentyFourHoursIsKept(): void
    {
        $s = $this->service();
        $main = $this->identity(1, 10);
        for ($n = 0; $n < SseCoverExportService::LIMIT_SELF; $n++) {
            self::assertTrue($s->generateCover(1, 10, 'athena', '', null, 'fr', null, null, $main, [])['ok']);
        }
        self::assertSame('limit', $s->generateCover(1, 10, 'athena', '', null, 'fr', null, null, $main, [])['error']);
    }

    // ------------------------------------------------------------------ Changement de sélection

    public function testChangingThePapersRewritesAndResignsTheFile(): void
    {
        $s = $this->service();
        $main = $this->identity(1, 10);
        $passport = $this->paper(1, 10, $main, 'passport');
        $card = $this->paper(1, 10, $main, 'id_card');
        $created = $this->now->modify('-2 days');
        $res = $s->generateCover(1, 10, 'athena', '', null, 'fr', null, $created, $main, [$passport]);
        $id = (int) $res['id'];
        $before = $this->fileCover($id);
        self::assertNotNull($s->downloadCover(1, 10, $id, $this->now->modify('-1 day')));
        self::assertSame('synced', $this->cover($id)['sync']);

        self::assertTrue($s->setCoverDocuments(1, 10, $id, [$card, $passport], $this->now)['ok']);
        $after = $this->fileCover($id);
        self::assertSame($before['_file']['ref'], $after['_file']['ref'], 'même référence');
        self::assertSame($before['_file']['expires_at'], $after['_file']['expires_at'], 'même date d’expiration');
        self::assertNotSame($before['_file']['content_sha256'], $after['_file']['content_sha256']);
        $types = array_column($after['docs'], 'type');
        sort($types);
        self::assertSame(['id_card', 'passport'], $types);
        self::assertSame($before['name'], $after['name']);
        $view = $this->cover($id);
        self::assertSame('update', $view['sync'], 'le téléphone voit une mise à jour');
        self::assertCount(2, $view['papers']);
        self::assertSame([], $view['addable']);

        // Tout retirer : couverture sans papier, signalée.
        self::assertTrue($s->setCoverDocuments(1, 10, $id, [], $this->now)['ok']);
        self::assertSame([], $this->fileCover($id)['docs']);
        self::assertSame('no_paper', $this->cover($id)['warnings'][0]['code']);

        // Un papier emporté qui expire ou se perd est signalé sur la couverture.
        self::assertTrue($s->setCoverDocuments(1, 10, $id, [$passport], $this->now)['ok']);
        self::assertTrue($s->documentAction(1, 10, $passport, 'lost')['ok']);
        self::assertSame(['paper_lost'], array_column($this->cover($id)['warnings'], 'code'));

        // Une couverture non rattachée n'a pas de papiers à choisir.
        $random = $s->generateCover(1, 10, 'random', 'ALT', 'F', 'fr', null);
        self::assertTrue($random['ok']);
        self::assertFalse($s->setCoverDocuments(1, 10, (int) $random['id'], [$card])['ok']);
        self::assertFalse($this->cover((int) $random['id'])['linked']);
        self::assertNull($this->cover((int) $random['id'])['candidate_id'], 'personne fictive : aucun rapprochement possible');
    }

    public function testConsistencyHintsAreOnlyIndications(): void
    {
        $s = $this->service();
        $main = $this->identity(1, 10);
        $passport = $this->paper(1, 10, $main, 'passport');
        $id = (int) $s->generateCover(1, 10, 'athena', '', null, 'fr', null, null, $main, [$passport])['id'];
        self::assertSame([], $this->cover($id)['hints']);

        // Papier délivré avant la naissance et qui expire avant la couverture ; identité renommée depuis.
        $this->pdo->exec("UPDATE sse_id_documents SET issued_on = '1980-01-01', expires_on = '" . $this->now->modify('+10 days')->format('Y-m-d') . "' WHERE id = " . $passport);
        $this->pdo->exec("UPDATE sse_identities SET last_name = 'Gylen', nationality = 'TNA' WHERE id = " . $main);
        $codes = array_column($this->cover($id)['hints'], 'code');
        foreach (['paper_country', 'paper_before_birth', 'paper_expires_first', 'name'] as $code) {
            self::assertContains($code, $codes);
        }
        self::assertSame('valid', $this->cover($id)['status'], 'une indication ne bloque rien');
    }

    // ------------------------------------------------------------------ Rétro-remplissage

    public function testBackfillLinksOnlyUnambiguousCovers(): void
    {
        $s = $this->service();
        $exportService = new SseCoverExportService(new SseCoverExportRepository(), null, null, static function (): void {
        }, 'test-key');
        $main = $this->identity(1, 10);
        $passport = $this->paper(1, 10, $main, 'passport');
        // Couvertures d'avant le lien : écrites sans passer par le lien.
        $sure = $exportService->generateFromIdentity(1, 10, 10, 'fr', false);
        self::assertTrue($sure['ok'], (string) ($sure['error'] ?? ''));
        $random = $s->generateCover(1, 10, 'random', 'ALT', 'F', 'fr', null);
        // Autre membre : deux identités de même nom et même date de naissance (ambigu).
        $twinA = $this->identity(1, 11, ['last_name' => 'Durand', 'first_name' => 'Paul', 'birth_date' => '1985-05-05']);
        $this->paper(1, 11, $twinA, 'passport');
        $ambiguous = $exportService->generateFromIdentity(1, 11, 11, 'fr', false);
        self::assertTrue($ambiguous['ok'], (string) ($ambiguous['error'] ?? ''));
        $this->pdo->exec("INSERT INTO sse_identities (tenant_id, user_id, kind, last_name, first_name, sex, birth_date, created_at) SELECT tenant_id, user_id, 'alias', last_name, first_name, sex, birth_date, created_at FROM sse_identities WHERE id = " . $twinA);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_cover_export_links')->fetchColumn());

        $log = '';
        $done = SseCoverLinkMatcher::backfill($this->pdo, static function (string $m) use (&$log): void {
            $log .= $m;
        });
        self::assertSame(['linked' => 1, 'left' => 2], $done);
        self::assertStringContainsString('rattachées', $log);
        self::assertSame(['identity_id' => $main, 'document_ids' => [$passport]], (new SseCoverExportLinkRepository())->forUser(1, 10)[(int) $sure['id']]);
        self::assertArrayNotHasKey((int) $random['id'], (new SseCoverExportLinkRepository())->forUser(1, 10), 'personne fictive : non rattachée');
        self::assertSame([], (new SseCoverExportLinkRepository())->forUser(1, 11), 'deux identités possibles : non rattachée');
        self::assertSame(['linked' => 0, 'left' => 2], SseCoverLinkMatcher::backfill($this->pdo), 'rejouable sans effet');
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_cover_export_links')->fetchColumn());

        // Le fichier n'a pas été touché, et il porte bien le papier retrouvé.
        $view = $this->cover((int) $sure['id']);
        self::assertTrue($view['linked']);
        self::assertTrue($view['papers'][0]['in_file']);
        self::assertNull($this->cover((int) $ambiguous['id'], 1, 11)['candidate_id']);
        self::assertFalse($s->attachCover(1, 11, (int) $ambiguous['id'])['ok']);
    }

    public function testAnUnlinkedCoverCanBeAttachedWhenItMatchesOneIdentity(): void
    {
        $s = $this->service();
        $main = $this->identity(1, 10);
        $passport = $this->paper(1, 10, $main, 'passport');
        $old = (new SseCoverExportService(new SseCoverExportRepository(), null, null, static function (): void {
        }, 'test-key'))->generateFromIdentity(1, 10, 10, 'fr', false);
        $view = $this->cover((int) $old['id']);
        self::assertFalse($view['linked']);
        self::assertSame($main, $view['candidate_id']);
        self::assertTrue($s->attachCover(1, 10, (int) $old['id'])['ok']);
        self::assertSame([$passport], array_column($this->cover((int) $old['id'])['papers'], 'id'));
        self::assertFalse($s->attachCover(1, 10, (int) $old['id'])['ok'], 'déjà rattachée');

        // La copie garde l'identité et les papiers.
        $copy = $s->duplicateCover(1, 10, (int) $old['id']);
        self::assertTrue($copy['ok']);
        self::assertSame(['identity_id' => $main, 'document_ids' => [$passport]], (new SseCoverExportLinkRepository())->forUser(1, 10)[(int) $copy['id']]);
    }

    public function testTheStepIsDeclaredOnceAndTheMigrationReplaysOnSqlite(): void
    {
        $root = dirname(__DIR__, 2);
        $registry = require $root . '/database/steps/registry.php';
        $files = array_column($registry, 'file', 'id');
        self::assertSame('4440_sse_cover_export_links.php', $files['sse_cover_export_links'] ?? null);
        self::assertFileExists($root . '/database/steps/4440_sse_cover_export_links.php');
        self::assertCount(1, glob($root . '/database/steps/4440_*.php') ?: [], 'numéro d’étape pris une seule fois');
        // Migration rejouée : sans effet.
        (require $root . '/bootstrap/sse_cover_export_links_migration.php')($this->pdo);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_cover_export_links')->fetchColumn());
    }

    // ------------------------------------------------------------------ Table absente

    public function testWithoutTheLinkTableCoversAreShownUnlinkedNeverAnError(): void
    {
        $s = $this->service();
        $main = $this->identity(1, 10);
        $passport = $this->paper(1, 10, $main, 'passport');
        $linked = (int) $s->generateCover(1, 10, 'athena', '', null, 'fr', null, null, $main, [$passport])['id'];
        $this->pdo->exec('DROP TABLE sse_cover_export_links');

        self::assertSame([], (new SseCoverExportLinkRepository())->forUser(1, 10));
        self::assertFalse((new SseCoverExportLinkRepository())->save(1, 10, $linked, $main, [$passport], '2026-11-11 12:00:00'));
        $view = $this->cover($linked);
        self::assertFalse($view['linked'], 'non rattachée');
        self::assertNull($view['identity']);
        self::assertSame($main, $view['candidate_id']);
        self::assertSame(['by_identity' => [], 'by_document' => []], $s->coverUsage(1, 10));
        // Créer reste possible : le fichier porte les papiers, seul le lien manque.
        $res = $s->generateCover(1, 10, 'athena', '', null, 'fr', null, null, $main, [$passport]);
        self::assertTrue($res['ok'], (string) ($res['error'] ?? ''));
        self::assertCount(1, $this->fileCover((int) $res['id'])['docs']);
        self::assertFalse($s->attachCover(1, 10, $linked)['ok']);
        self::assertTrue($s->editCover(1, 10, $linked, ['city' => 'Pyrgos'])['ok']);
        self::assertSame(2, $s->summary(1, 10)['covers']);
        $this->renderPages(1, 10, ['Non rattachée', 'Rattacher à une identité']);
    }

    // ------------------------------------------------------------------ Onglets

    public function testTabsCountCoversPerIdentityAndPerPaper(): void
    {
        $s = $this->service();
        $main = $this->identity(1, 10);
        $alias = $this->identity(1, 10, ['last_name' => 'Markos', 'first_name' => 'Petros', 'birth_date' => '1987-07-15']);
        $passport = $this->paper(1, 10, $main, 'passport');
        $card = $this->paper(1, 10, $main, 'id_card');
        $a = (int) $s->generateCover(1, 10, 'athena', '', null, 'fr', null, null, $main, [$passport, $card])['id'];
        $b = (int) $s->generateCover(1, 10, 'athena', '', null, 'fr', null, null, $main, [$passport])['id'];
        $s->generateCover(1, 10, 'random', 'ALT', 'F', 'fr', null);

        $usage = $s->coverUsage(1, 10);
        self::assertEqualsCanonicalizing([$a, $b], array_column($usage['by_identity'][$main], 'id'));
        self::assertArrayNotHasKey($alias, $usage['by_identity']);
        self::assertEqualsCanonicalizing([$a, $b], array_column($usage['by_document'][$passport], 'id'));
        self::assertSame([$a], array_column($usage['by_document'][$card], 'id'));

        // Une couverture révoquée ne compte plus.
        self::assertTrue($s->revokeCover(1, 10, $b)['ok']);
        $usage = $s->coverUsage(1, 10);
        self::assertSame([$a], array_column($usage['by_identity'][$main], 'id'));
        self::assertSame([$a], array_column($usage['by_document'][$passport], 'id'));
        self::assertSame(['by_identity' => [], 'by_document' => []], $s->coverUsage(1, 11), 'rien pour un autre membre');
    }

    public function testPagesRenderWithLinkedAndUnlinkedCoversWithEveryPhpWarningFatal(): void
    {
        $s = $this->service();
        $main = $this->identity(1, 10);
        $alias = $this->identity(1, 10, ['last_name' => 'Markos', 'first_name' => 'Petros', 'birth_date' => '1987-07-15', 'nationality' => '']);
        $passport = $this->paper(1, 10, $main, 'passport');
        $card = $this->paper(1, 10, $main, 'id_card');
        $this->paper(1, 10, $main, 'driving_license', false);
        $linked = (int) $s->generateCover(1, 10, 'athena', '', null, 'fr', null, null, $main, [$passport])['id'];
        $bare = (int) $s->generateCover(1, 10, 'athena', '', null, 'fr', null, null, $alias, [])['id'];
        $s->generateCover(1, 10, 'random', 'ALT', 'F', 'fr', null);
        // Papier emporté devenu inutilisable, identité renommée : avertissements et indications rendus.
        self::assertTrue($s->documentAction(1, 10, $passport, 'stolen')['ok']);
        $this->pdo->exec("UPDATE sse_identities SET last_name = 'Gylen' WHERE id = " . $main);

        $html = $this->renderPages(1, 10, [
            'Identité utilisée', 'Papiers emportés', 'Non rattachée', 'Aucun papier pour cette couverture.', 'Papier déclaré volé',
            'Papier demandé, pas encore émis', 'Ajouter un papier à cette couverture', 'Demander ce papier', 'À vérifier',
            'id="couverture-' . $linked . '"', 'id="couverture-' . $bare . '"', 'id="dlg-papiers-' . $linked . '"', 'name="documents[' . $main . '][]"',
            'Personne fictive, sans identité ni papiers Athena', 'opx-doc-svg',
        ]);
        self::assertStringNotContainsString('(s)', $html['covers'], 'singulier et pluriel écrits en entier');
        // Onglet Papiers : les couvertures qui emportent chaque papier ; onglet Identités : nombres avec liens.
        self::assertStringContainsString('Emporté par la couverture', $html['documents']);
        self::assertStringContainsString('#couverture-' . $linked, $html['documents']);
        self::assertStringContainsString('Dans aucune couverture.', $html['documents'], 'la carte d’identité (' . $card . ') n’est dans aucune couverture');
        self::assertStringContainsString('1 couverture</a>', $html['identities']);
        self::assertStringContainsString('#couverture-' . $linked, $html['identities']);
        self::assertStringContainsString('3 papiers', $html['identities']);
        foreach (glob(dirname(__DIR__, 2) . '/views/operateur/_tab_*.php') ?: [] as $view) {
            $src = (string) file_get_contents($view);
            self::assertStringNotContainsString('kicker', $src, basename($view) . ' : aucun petit libellé au-dessus des titres');
            self::assertStringNotContainsString('eyebrow', $src, basename($view));
        }
        // Lien « en créer une » d'une identité sans couverture : la création guidée s'ouvre sur cette identité.
        $third = $this->identity(1, 10, ['last_name' => 'Tiers', 'first_name' => 'Lea', 'birth_date' => '1995-03-03']);
        $again = $this->renderPages(1, 10, [], ['identite' => (string) $third]);
        self::assertMatchesRegularExpression('/id="dlg-couverture"[^>]*data-opx-autoopen/', $again['covers']);
        self::assertMatchesRegularExpression('/name="identity_id" value="' . $third . '"[^>]*checked/', $again['covers']);
    }

    /**
     * Rend les quatre pages dans le vrai gabarit, tout avertissement PHP devenant une erreur (comme en production).
     *
     * @param list<string> $coverNeedles textes attendus dans l'onglet Couvertures
     * @param array<string, string> $query
     * @return array<string, string> page → HTML
     */
    private function renderPages(int $tenant, int $user, array $coverNeedles, array $query = []): array
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_GET = $query;
        Session::set('user_id', $user);
        Session::set('tenant_id', $tenant);
        $limiter = $this->createMock(FileRateLimiter::class);
        $limiter->method('tooManyAttempts')->willReturn(false);
        $c = new OperatorHubController(
            new OperatorHubRepository(),
            $this->service(),
            static fn (): ?array => ['tenant' => $tenant, 'user' => $user, 'row' => ['id' => $user, 'display_name' => '', 'steam_id' => null]],
            static fn (string $p): bool => false,
            static fn (string $path): bool => true,
            null,
            static fn (Request $r): bool => true,
            static function (string $type, string $message): void {
            },
            $limiter,
            static fn (int $t, int $u): array => [],
        );
        $out = [];
        $level = ob_get_level();
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            foreach (['index', 'identities', 'documents', 'covers'] as $method) {
                $_SERVER['REQUEST_URI'] = '/operateur';
                $res = $c->$method(new Request());
                self::assertSame(200, $res->statusCode(), $method);
                self::assertStringContainsString('dash-cc dash-cc--rail', $res->body(), $method . ' : rendu dans le gabarit du tableau de bord');
                self::assertStringContainsString('</html>', $res->body(), $method . ' : page rendue jusqu’au bout');
                self::assertStringNotContainsString('Indisponible pour l’instant.', $res->body(), $method);
                $out[$method] = $res->body();
            }
        } finally {
            restore_error_handler();
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
        foreach ($coverNeedles as $needle) {
            self::assertStringContainsString($needle, $out['covers'], $needle);
        }

        return $out;
    }
}
