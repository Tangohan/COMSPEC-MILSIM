<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseIdCountryRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseIdKitRepository;
use App\Repositories\SseIdSettingsRepository;
use App\Repositories\SsePersonRepository;
use App\Repositories\SseWatchlistRepository;
use App\Services\Sse\MemberIdentityAutoService;
use App\Services\Sse\MemberIdentityPapersService;
use App\Services\Sse\MemberIdentitySyncService;
use App\Services\Sse\SseIdentityPackService;
use App\Services\Sse\SseIdMemberPhotoService;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseIdentitySqlite;

/**
 * Identité réelle + couverture transmises ensemble (réglage par communauté), « Bloqué » seulement quand rien ne part,
 * « Ajouter au kit » en un clic, « Tout générer », photo d'identité (types, taille, EXIF, rangement privé, communauté)
 * et portrait réduit envoyé au jeu.
 */
final class MemberIdentityDualPhotoTest extends TestCase
{
    private PDO $pdo;
    private string $dir;

    /** @var list<array<string, mixed>> */
    private array $audit = [];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = SseIdentitySqlite::open();
        $this->pdo->exec("INSERT INTO users (id, tenant_id, steam_id, display_name) VALUES (10, 1, '76561198000000010', 'Jake'), (12, 1, '76561198000000012', 'Autre'), (20, 2, '76561198000000020', 'Ailleurs')");
        $this->dir = sys_get_temp_dir() . '/sse-photo-' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0777, true);
        $this->audit = [];
    }

    protected function tearDown(): void
    {
        SseIdentitySqlite::close();
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($this->dir);
    }

    private function papers(): MemberIdentityPapersService
    {
        return new MemberIdentityPapersService(null, null, null, null, null, function (array $e): void {
            $this->audit[] = $e;
        });
    }

    /** @return array<string, mixed> */
    private function pack(int $tenant = 1): array
    {
        $watch = $this->createMock(SseWatchlistRepository::class);
        $watch->method('listActive')->willReturn([]);
        $persons = $this->createMock(SsePersonRepository::class);
        $persons->method('listForContext')->willReturn([]);

        return (new SseIdentityPackService(new SseIdDocumentRepository(), new SseIdentityRepository(), new SseIdCountryRepository(), $watch, $persons, new SseIdKitRepository()))
            ->build($tenant, 1, null);
    }

    /** @return array{0: int, 1: int, 2: int, 3: int} identité réelle, sa carte, couverture, son passeport (émis) */
    private function jakeAndCover(): array
    {
        $s = $this->papers();
        $real = $s->savePrimary(1, 10, ['last_name' => 'Gylenhall', 'first_name' => 'Jake', 'sex' => 'M', 'birth_date' => '1988-07-16', 'birth_place' => 'Colombus', 'nationality' => 'ALT'], 1)['id'];
        $cover = $s->createAlias(1, 10, ['kind' => 'cover', 'last_name' => 'Oscar', 'first_name' => 'De-Aligeara', 'nationality' => 'ALT'], true, 1)['id'];
        $docs = [];
        foreach ([[$real, 'id_card'], [$cover, 'passport']] as [$iid, $type]) {
            $d = $s->saveDocument(1, 10, $s->ownedIdentity(1, 10, $iid, true), null, ['doc_type' => $type, 'country_code' => 'ALT', 'issued_on' => '2025-01-10', 'expires_on' => '2035-01-10'], true, 1)['id'];
            $s->action(1, 10, $s->ownedDocument(1, 10, $d, true), 'issue', '', 1);
            $docs[] = $d;
        }

        return [$real, $docs[0], $cover, $docs[1]];
    }

    /** @return array<string, mixed> */
    private function diag(bool $realWithCover, int $brief = 0): array
    {
        return MemberIdentitySyncService::diagnose($this->papers()->section(1, 10, '76561198000000010', true), (new SseIdCountryRepository())->listForTenant(1, false), '2026-11-09', $realWithCover, $brief);
    }

    public function testBothIdentitiesGoToTheGameByDefaultAndTheSettingSendsOnlyTheCover(): void
    {
        [$real, $realDoc, $cover, $coverDoc] = $this->jakeAndCover();
        self::assertTrue((new SseIdSettingsRepository())->realWithCover(1), 'défaut : oui');
        self::assertEqualsCanonicalizing([$realDoc, $coverDoc], $this->pack()['kits'][0]['documents'], 'réelle + couverture');
        self::assertTrue($this->pack()['settings']['real_with_cover']);
        $d = $this->diag(true);
        self::assertEqualsCanonicalizing([$real, $cover], $d['sent_identity_ids']);
        self::assertSame('incomplete', $d['identities'][$cover]['state'], 'seule la photo manque : jamais « Bloqué »');

        // Réglage décoché : sans fiche de couverture, l'identité réelle seule ; avec une fiche, la couverture seule.
        (new SseIdSettingsRepository())->setRealWithCover(1, false);
        self::assertSame([$realDoc], $this->pack()['kits'][0]['documents']);
        $this->pdo->exec("INSERT INTO sse_id_briefs (tenant_id, user_id, identity_id, title, created_at) VALUES (1, 10, {$cover}, 'Infiltration', '2026-11-01 10:00:00')");
        self::assertSame([$coverDoc], $this->pack()['kits'][0]['documents']);
        $off = $this->diag(false, $cover);
        self::assertSame([$cover], $off['sent_identity_ids']);
        $notSent = array_column($off['identities'][$real]['issues'], null, 'code')['identity_not_sent'];
        self::assertSame('incomplete', $notSent['level'], 'la couverture part : l’identité réelle restée ne bloque pas');
        self::assertSame(['label' => 'Ajouter automatiquement', 'kitadd' => [$realDoc]], $notSent['fix']);
        self::assertSame('#papiers-kit', $notSent['fix2']['href']);
        self::assertTrue((new SseIdSettingsRepository())->realWithCover(2), 'autre communauté : défaut');
    }

    public function testAComposedCoverKitGetsTheRealIdentityAddedOnlyWhenTheSettingAllowsIt(): void
    {
        [, $realDoc, , $coverDoc] = $this->jakeAndCover();
        $this->papers()->saveKit(1, 10, null, null, [$coverDoc], true, 1);
        self::assertEqualsCanonicalizing([$coverDoc, $realDoc], $this->pack()['kits'][0]['documents']);
        (new SseIdSettingsRepository())->setRealWithCover(1, false);
        self::assertSame([$coverDoc], $this->pack()['kits'][0]['documents']);
        // Kit sans le papier réel, réglage décoché : « Hors du kit » incomplet (la couverture part), avec « Ajouter au kit ».
        $issue = $this->diag(false)['documents'][$realDoc]['issues'][0];
        self::assertSame(['not_in_kit', 'incomplete', 'Ajouter au kit'], [$issue['code'], $issue['level'], $issue['fix']['label']]);
    }

    public function testBlockedOnlyWhenNothingGoesAndAddToKitIsOneClickWithoutDroppingTheAutomaticKit(): void
    {
        [$real, $realDoc, , $coverDoc] = $this->jakeAndCover();
        $s = $this->papers();
        $alias = $s->createAlias(1, 10, ['kind' => 'alias', 'last_name' => 'Renard', 'first_name' => 'Luc', 'nationality' => 'ALT'], true, 1)['id'];
        $aliasDoc = $s->saveDocument(1, 10, $s->ownedIdentity(1, 10, $alias, true), null, ['doc_type' => 'id_card', 'country_code' => 'ALT', 'expires_on' => '2035-01-10'], true, 1)['id'];
        $s->action(1, 10, $s->ownedDocument(1, 10, $aliasDoc, true), 'issue', '', 1);
        self::assertSame('incomplete', $this->diag(true)['documents'][$aliasDoc]['state']);

        $sync = new MemberIdentitySyncService(null, null, null, null, null, function (array $e): void {
            $this->audit[] = $e;
        });
        $res = $sync->kitAdd(1, 10, [$aliasDoc], true, true, 1);
        self::assertTrue($res['ok'], $res['message']);
        self::assertEqualsCanonicalizing([$realDoc, $coverDoc, $aliasDoc], $this->pack()['kits'][0]['documents'], 'rien n’est retiré du kit automatique');
        self::assertContains('id_member.sync_kit_add', array_column($this->audit, 'action'));
        self::assertFalse($sync->kitAdd(1, 12, [$aliasDoc], true, true, 1)['ok'], 'papier d’un autre membre');
        self::assertFalse($sync->kitAdd(2, 10, [$aliasDoc], true, true, 1)['ok'], 'autre communauté');

        // Rien ne part (tout révoqué) : là, c'est bloqué.
        foreach ([$realDoc, $coverDoc, $aliasDoc] as $d) {
            $s->action(1, 10, $s->ownedDocument(1, 10, $d, true), 'revoke', 'fin', 1);
        }
        self::assertSame('blocked', $this->diag(true)['state']);
        self::assertSame([], $this->diag(true)['sent_identity_ids']);
        unset($real);
    }

    public function testAddToKitByAManagerWithoutClearanceKeepsTheHiddenCover(): void
    {
        [$real, $realDoc, , $coverDoc] = $this->jakeAndCover();
        $s = $this->papers();
        // Kit automatique : un permis de l'identité réelle (deux papiers du même type ne partent pas : on prend un permis).
        $lic = $s->saveDocument(1, 10, $s->ownedIdentity(1, 10, $real, true), null, ['doc_type' => 'driving_license', 'country_code' => 'ALT', 'expires_on' => '2035-01-10'], true, 1)['id'];
        $s->action(1, 10, $s->ownedDocument(1, 10, $lic, true), 'issue', '', 1);
        $sync = new MemberIdentitySyncService(null, null, null, null, null, function (array $e): void {
            $this->audit[] = $e;
        });
        self::assertTrue($sync->kitAdd(1, 10, [$lic, $coverDoc], false, true, 1)['ok'], 'la couverture, invisible, n’est pas ajoutée par lui');
        self::assertEqualsCanonicalizing([$realDoc, $coverDoc, $lic], $this->pack()['kits'][0]['documents'], 'couverture gardée');
    }

    public function testGenerateAllCreatesWhatIsMissingWithoutReplacingAnything(): void
    {
        $auto = new MemberIdentityAutoService(null, null, null, null, function (array $e): void {
            $this->audit[] = $e;
        });
        $proposal = ['last_name' => 'Gylenhall', 'first_name' => 'Jake', 'birth_date' => '', 'birth_place' => '', 'sex' => 'M', 'nationality' => 'ALT', 'nationality_text' => 'Altis'];
        $res = $auto->generateAll(1, 10, $proposal, true, false, 1, 42);
        self::assertTrue($res['ok']);
        self::assertStringStartsWith('Créé : identité principale Jake GYLENHALL', $res['message']);
        $section = $this->papers()->section(1, 10, '76561198000000010', false);
        $primary = $section['primary'];
        self::assertSame(['Gylenhall', 'Jake', 'ALT'], [$primary['last_name'], $primary['first_name'], $primary['nationality']]);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', (string) $primary['birth_date'], 'date tirée');
        self::assertNotSame('', (string) $primary['birth_place']);
        $types = array_column(array_filter($section['documents'], static fn (array $d): bool => $d['workflow'] === 'issued'), 'doc_type');
        self::assertContains('id_card', $types);
        self::assertContains('military_id', $types, 'grade : carte militaire');
        foreach ($section['documents'] as $d) {
            self::assertNotSame('', (string) $d['expires_on']);
            self::assertNotSame('', (string) $d['authority']);
        }
        // Deuxième fois : rien à faire, rien de remplacé.
        $again = $auto->generateAll(1, 10, ['last_name' => 'Autre', 'first_name' => 'Nom'] + $proposal, true, false, 1, 7);
        self::assertStringStartsWith('Rien à générer', $again['message']);
        self::assertSame('Gylenhall', (new SseIdentityRepository())->find((int) $primary['id'], 1)['last_name']);
        self::assertContains('id_member.generate_all', array_column($this->audit, 'action'));
        self::assertSame('ready', MemberIdentitySyncService::diagnose($this->papers()->section(1, 10, '76561198000000010', false), (new SseIdCountryRepository())->listForTenant(1, false), '2026-11-09')['documents'][(int) $section['documents'][0]['id']]['state']);
    }

    public function testGenerateAllCompletesAnExistingIdentityAndIssuesTheCoverPaperWithClearance(): void
    {
        $s = $this->papers();
        $id = $s->savePrimary(1, 10, ['last_name' => 'Gylenhall', 'first_name' => 'Jake', 'nationality' => 'ALT'], 1)['id'];
        $cover = $s->createAlias(1, 10, ['kind' => 'cover', 'last_name' => 'Oscar', 'first_name' => 'Paul', 'nationality' => 'ALT'], true, 1)['id'];
        $res = (new MemberIdentityAutoService(null, null, null, null, function (array $e): void {
            $this->audit[] = $e;
        }))->generateAll(1, 10, ['birth_place' => 'Colombus', 'sex' => 'X'], false, true, 1, 3);
        self::assertStringContainsString('identité principale complétée (date de naissance, lieu de naissance, sexe)', $res['message']);
        self::assertSame('Colombus', (new SseIdentityRepository())->find($id, 1)['birth_place']);
        self::assertStringContainsString('(couverture)', $res['message']);
        $coverDocs = array_filter($s->section(1, 10, '', true)['documents'], static fn (array $d): bool => (int) $d['identity_id'] === $cover && $d['workflow'] === 'issued');
        self::assertCount(1, $coverDocs);
        self::assertNotContains('military_id', array_column($s->section(1, 10, '', true)['documents'], 'doc_type'), 'sans grade : pas de carte militaire');
    }

    public function testPhotoUploadAcceptsOnlyJpegAndPngWithinTheSizeAndRemovesExif(): void
    {
        $svc = new SseIdMemberPhotoService($this->dir, false);
        $file = fn (string $name, string $bytes): string => (string) (file_put_contents($this->dir . '/' . $name, $bytes) !== false ? $this->dir . '/' . $name : '');
        // JPEG minimal avec un bloc EXIF (APP1) contenant une position GPS fictive.
        $exif = "Exif\0\0GPS-48.8566,2.3522";
        $jpeg = "\xFF\xD8" . "\xFF\xE0\x00\x10JFIF\0\x01\x01\0\0\x01\0\x01\0\0" . "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif . "\xFF\xDA\x00\x08\x01\x01\x00\x00\x3F\x00\xAA\xBB\xFF\xD9";
        $ok = $svc->storeFile($file('p.jpg', $jpeg), 1);
        self::assertTrue($ok['ok'], (string) ($ok['error'] ?? ''));
        self::assertMatchesRegularExpression('#^private:sse-id-photos/1/[a-f0-9]{16}\.jpg$#', $ok['path']);
        $stored = (string) file_get_contents((string) $svc->absolute($ok['path']));
        self::assertStringNotContainsString('GPS-48.8566', $stored, 'EXIF retiré');
        self::assertStringStartsWith("\xFF\xD8", $stored);
        self::assertSame(1, SseIdMemberPhotoService::tenantOf($ok['path']));
        self::assertStringNotContainsString('/public/', str_replace('\\', '/', (string) $svc->absolute($ok['path'])), 'hors de la racine web');

        // Types refusés : GIF, WebP, texte renommé, fichier vide ; taille au-delà de 4 Mo.
        foreach (['a.gif' => "GIF89a\x01\x00\x01\x00", 'b.webp' => 'RIFF' . pack('V', 20) . 'WEBPVP8 ' . str_repeat("\0", 12), 'c.jpg' => '<?php echo 1;', 'd.png' => ''] as $name => $bytes) {
            self::assertFalse($svc->storeFile($file($name, $bytes), 1)['ok'], $name);
        }
        self::assertFalse($svc->storeFile($file('big.jpg', "\xFF\xD8\xFF" . str_repeat('a', SseIdMemberPhotoService::MAX_BYTES)), 1)['ok']);
        self::assertFalse($svc->storeFile($file('e.jpg', $jpeg), 0)['ok'], 'communauté requise');
        // Chemins non sûrs refusés ; ancienne photo publique lisible.
        self::assertNull($svc->absolute('private:sse-id-photos/1/../../../.env'));
        self::assertNull($svc->absolute('../config/app.php'));
        self::assertNotNull($svc->absolute('uploads/sse-id-photos/1_abc.jpg', $this->dir));
        self::assertNull(SseIdMemberPhotoService::tenantOf('uploads/sse-id-photos/1_abc.jpg'));
        // Photo de profil : seulement depuis public/uploads, existante.
        self::assertFalse($svc->fromPortrait('', 1)['ok']);
        self::assertFalse($svc->fromPortrait('../../etc/passwd', 1, $this->dir)['ok']);
        mkdir($this->dir . '/uploads/portraits', 0777, true);
        file_put_contents($this->dir . '/uploads/portraits/x.jpg', $jpeg);
        self::assertTrue($svc->fromPortrait('uploads/portraits/x.jpg', 1, $this->dir)['ok']);
    }

    public function testPhotoIsSquaredAndAReducedPortraitGoesToTheGameInThePack(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD requis (portrait réduit, recadrage)');
        }
        $img = imagecreatetruecolor(400, 300);
        imagefilledrectangle($img, 0, 0, 199, 299, (int) imagecolorallocate($img, 250, 250, 250));
        $src = $this->dir . '/src.png';
        imagepng($img, $src);
        $svc = new SseIdMemberPhotoService($this->dir, true);
        $res = $svc->storeFile($src, 1);
        self::assertTrue($res['ok']);
        $abs = (string) $svc->absolute($res['path']);
        [$w, $h] = (array) getimagesize($abs);
        self::assertSame([300, 300], [$w, $h], 'recadrée au carré');
        $mosaic = SseIdMemberPhotoService::mosaic($abs);
        self::assertNotNull($mosaic);
        self::assertSame(300, strlen($mosaic));
        self::assertMatchesRegularExpression('/^[0-9a-f]{300}$/', $mosaic);

        [$real] = $this->jakeAndCover();
        // Photo publique (atelier) : le paquet en tire le portrait réduit, gardé en base.
        $public = base_path('public/uploads/sse-id-photos');
        @mkdir($public, 0777, true);
        $name = 'test_' . bin2hex(random_bytes(4)) . '.png';
        copy($src, $public . '/' . $name);
        try {
            (new SseIdentityRepository())->setImages($real, 1, ['photo_path' => 'uploads/sse-id-photos/' . $name]);
            $photos = $this->pack()['settings']['photos'];
            self::assertCount(1, $photos);
            self::assertSame($real, $photos[0][0]);
            self::assertSame(300, strlen($photos[0][1]));
            self::assertSame($photos[0][1], $this->pdo->query("SELECT photo_mosaic FROM sse_identities WHERE id = {$real}")->fetchColumn());
        } finally {
            @unlink($public . '/' . $name);
        }
    }
}
