<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\PublicPlayerCvController;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\UserPublicCvRepository;
use App\Services\PublicCv\PublicCvDataLoader;
use App\Support\PublicCv\PublicCvBannerStorage;
use App\Support\PublicCv\PublicCvPresenter;
use App\Support\PublicCv\PublicCvSettings;
use PHPUnit\Framework\TestCase;

final class PublicPlayerCvTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/joueur/viper-6';
        $_SERVER['HTTP_HOST'] = 'athena.test';
        $GLOBALS['__app_locale'] = 'fr';
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__app_locale']);
    }

    /** @return array<string, mixed> */
    private static function raw(): array
    {
        return [
            'user' => [
                'id' => 7,
                'email' => 'jean.dupont@example.org',
                'display_name' => 'Jean Dupont',
                'callsign' => 'Viper-6',
                'steam_id' => '76561198000000000',
                'status' => 'active',
            ],
            'grade' => ['label_long' => 'Sergent-chef', 'label_short' => 'SCH'],
            'unit_name' => '1re section',
            'tenant' => ['name' => 'Task Force Lynx', 'slug' => 'tf-lynx', 'contact_email' => 'staff@example.org'],
            'avatar_url' => '/uploads/portraits/7.jpg',
            'banner_url' => 'https://cdn.athena.test/banniere-lynx.jpg',
            'decorations' => [['definition_name' => 'Croix de la valeur', 'decoration_grade' => 'Bronze', 'awarded_at' => '2026-03-14', 'citation_text' => 'Secret de citation']],
            'qualifications' => [['definition_name' => 'Chef de bord CH-47', 'definition_short_name' => 'CDB', 'obtained_at' => '2025-11-02', 'issued_by' => 'jean.dupont@example.org']],
            'certificates' => [['course_title' => 'Navigation de nuit', 'status' => 'valid', 'issued_at' => '2026-01-10'], ['course_title' => 'Ancienne formation', 'status' => 'revoked']],
            'academy' => ['jtac' => ['completed' => true, 'updated' => 1767225600, 'snapshot' => ['title' => ['fr' => 'Licence JTAC', 'en' => 'JTAC licence']]], '__notes' => ['text' => 'Notes privées']],
            'operations' => ['count' => 42, 'recent' => [['title' => 'Opération Tempête', 'starts_at' => '2026-10-01 20:00:00', 'event_type' => 'operation', 'location' => 'Terrain secret']]],
            'flight_seconds' => 36000 + 1800,
            'photos' => [['title' => 'Insertion', 'storage_path' => 'community-media/1.jpg']],
        ];
    }

    /** @param array<string, bool> $sections */
    private static function settings(array $sections = [], int $enabled = 1): array
    {
        return [
            'user_id' => 7,
            'tenant_id' => 3,
            'enabled' => $enabled,
            'slug' => 'viper-6',
            'bio' => 'Pilote de transport, toujours partant de nuit.',
            'sections_json' => PublicCvSettings::encodeSections(array_merge(PublicCvSettings::defaultSections(), $sections)),
        ];
    }

    private function controller(?array $settings, ?array $raw = null): PublicPlayerCvController
    {
        $repo = $this->createMock(UserPublicCvRepository::class);
        $repo->method('findBySlug')->willReturn($settings);
        $loader = $this->createMock(PublicCvDataLoader::class);
        $loader->method('load')->willReturn($raw);

        return new PublicPlayerCvController($repo, $loader);
    }

    public function testUnknownSlugReturns404(): void
    {
        $response = $this->controller(null)->show(new Request(), ['slug' => 'viper-6']);
        self::assertSame(404, $response->statusCode());
        self::assertStringContainsString('n’existe pas', $response->body());
    }

    public function testDisabledPageReturns404WithoutLeakingAnything(): void
    {
        $response = $this->controller(self::settings([], 0), self::raw())->show(new Request(), ['slug' => 'viper-6']);
        self::assertSame(404, $response->statusCode());
        self::assertStringNotContainsString('Viper-6', $response->body());
    }

    public function testInvalidSlugOrFormerMemberReturns404(): void
    {
        self::assertSame(404, $this->controller(self::settings(), self::raw())->show(new Request(), ['slug' => '../etc'])->statusCode());
        self::assertSame(404, $this->controller(self::settings(), null)->show(new Request(), ['slug' => 'viper-6'])->statusCode());
    }

    public function testEnabledPageRendersProfileWithOpenGraphAndNoPrivateData(): void
    {
        $response = $this->controller(self::settings(), self::raw())->show(new Request(), ['slug' => 'viper-6']);
        $body = $response->body();
        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('Viper-6', $body);
        self::assertStringContainsString('Sergent-chef', $body);
        self::assertStringContainsString('Task Force Lynx', $body);
        self::assertStringContainsString('Croix de la valeur', $body);
        self::assertStringContainsString('Chef de bord CH-47', $body);
        self::assertStringContainsString('Navigation de nuit', $body);
        self::assertStringContainsString('Licence JTAC', $body);
        self::assertStringContainsString('Opération Tempête', $body);
        self::assertStringContainsString('>10<small>h</small>', $body);
        self::assertStringContainsString('property="og:title"', $body);
        self::assertStringContainsString('property="og:type" content="profile"', $body);
        self::assertStringContainsString('noindex', $body);
        // Maquette « dossier opérateur » : bannière de la communauté, code de qualification, journal daté et typé.
        self::assertStringContainsString('src="https://cdn.athena.test/banniere-lynx.jpg"', $body);
        self::assertStringContainsString('<i>CDB</i>', $body);
        self::assertStringContainsString('datetime="2026-10-01">01.10.26</time>', $body);
        self::assertStringContainsString('pcv-tag--op">Opération</span>', $body);
        self::assertMatchesRegularExpression('#class="pcv-btn pcv-btn--pri" href="[^"]*/c/tf-lynx"#', $body);
        self::assertStringNotContainsString('Terrain secret', $body, 'Le lieu des opérations n’est jamais affiché');

        self::assertDoesNotMatchRegularExpression('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', $body, 'Aucune adresse e-mail dans la page');
        foreach (['example.org', 'Jean', 'Dupont', 'Terrain secret', '76561198000000000', 'Secret de citation', 'Notes privées', 'Ancienne formation'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, strip_tags($body) . self::attributes($body), 'Fuite : ' . $forbidden);
        }
    }

    public function testHiddenSectionsAreNotRendered(): void
    {
        $hidden = ['decorations' => false, 'qualifications' => false, 'operations' => false, 'unit' => false, 'bio' => false, 'counters' => false];
        $body = $this->controller(self::settings($hidden), self::raw())->show(new Request(), ['slug' => 'viper-6'])->body();
        self::assertStringContainsString('Viper-6', $body);
        foreach (['Croix de la valeur', 'Chef de bord CH-47', 'Opération Tempête', 'Sergent-chef', '1re section', 'Task Force Lynx', 'Pilote de transport', 'Heures de vol', 'id="pcv-decorations"', 'id="pcv-operations"', 'id="pcv-qualifications"', 'id="pcv-unit"', 'banniere-lynx', 'Découvrir la communauté'] as $absent) {
            self::assertStringNotContainsString($absent, $body, 'Section masquée rendue : ' . $absent);
        }
        self::assertStringContainsString('Navigation de nuit', $body);
    }

    public function testPresenterIsAWhitelist(): void
    {
        $cv = PublicCvPresenter::present(self::raw(), self::settings());
        $json = (string) json_encode($cv, JSON_UNESCAPED_UNICODE);
        self::assertStringNotContainsString('example.org', $json);
        self::assertStringNotContainsString('Dupont', $json);
        self::assertSame('Viper-6', $cv['callsign']);
        self::assertSame('10 h', $cv['counters'][1]['value']);
    }

    public function testPresenterOrdersTrainingsAndSplitsCounters(): void
    {
        $cv = PublicCvPresenter::present(self::raw(), self::settings());
        // Licence JTAC (janvier 2026, 1er janv.) puis Navigation de nuit (10 janv. 2026) : la plus récente en tête.
        self::assertSame(['Navigation de nuit', 'Licence JTAC'], array_column($cv['trainings'], 'title'));
        self::assertSame(['10', 'h'], [$cv['counters'][1]['number'], $cv['counters'][1]['unit']]);
        self::assertSame(['42', null], [$cv['counters'][0]['number'], $cv['counters'][0]['unit']]);
        self::assertSame('CDB', $cv['qualifications'][0]['code']);
        self::assertSame(['title' => 'Opération Tempête', 'date' => 'octobre 2026', 'day' => '01.10.26', 'iso' => '2026-10-01', 'type' => 'Opération'], $cv['operations'][0]);
    }

    public function testBannerOnlyFromSafeAddressAndWithCommunity(): void
    {
        $raw = self::raw();
        foreach (['javascript:alert(1)', 'data:image/png;base64,AAAA', '//evil.test/x.jpg', 'https://x.test/a.jpg") ; background:url(x'] as $bad) {
            $raw['banner_url'] = $bad;
            self::assertNull(PublicCvPresenter::present($raw, self::settings())['banner_url'], $bad);
        }
        $raw['banner_url'] = 'uploads/branding/lynx.jpg';
        self::assertSame('/uploads/branding/lynx.jpg', PublicCvPresenter::present($raw, self::settings())['banner_url']);
        self::assertSame('ASSET:uploads/branding/lynx.jpg', PublicCvPresenter::present($raw, self::settings(), ['asset_url' => static fn (string $p): string => 'ASSET:' . $p])['banner_url']);
        self::assertNull(PublicCvPresenter::present(self::raw(), self::settings(['unit' => false]))['banner_url']);
    }

    public function testCallsignFallsBackToPseudonymNeverToDisplayName(): void
    {
        $raw = self::raw();
        $raw['user']['callsign'] = '';
        self::assertSame('Opérateur', PublicCvPresenter::present($raw, self::settings())['callsign']);
        $raw['user']['public_name'] = 'Lynx';
        self::assertSame('Lynx', PublicCvPresenter::present($raw, self::settings())['callsign']);
    }

    public function testSlugRules(): void
    {
        self::assertSame('eclair-2-1', PublicCvSettings::normalizeSlug('  Éclair 2/1 '));
        self::assertNull(PublicCvSettings::slugError('viper-6'));
        self::assertNotNull(PublicCvSettings::slugError('ab'));
        self::assertNotNull(PublicCvSettings::slugError('admin'));
        self::assertNotNull(PublicCvSettings::slugError('-bad-'));
        self::assertSame(['unit' => true, 'roles' => false], array_slice(PublicCvSettings::sectionsFromForm(['unit', 'inconnue']), 0, 2));
    }

    /** Données de jeu, fonctions, poste, ancienneté et promotions (ajoutées après la première version). */
    private static function rawFull(): array
    {
        return array_merge(self::raw(), [
            'unit_name' => null,
            'unit_post' => ['role_name' => 'Chef de groupe', 'unit_name' => '2e section'],
            'jobs' => [
                ['name' => 'Fusilier', 'detail' => 'Grenadier', 'is_primary' => 1],
                ['name' => 'Infirmier', 'detail' => 'CLS', 'is_primary' => 0],
                ['name' => 'Fusilier', 'detail' => 'Grenadier', 'is_primary' => 0],
            ],
            'member_since' => '2022-03-10 20:00:00',
            'promotions' => [
                ['event_date' => '2025-06-01', 'description' => 'Grade : Sergent-chef · Fonction : Chef de groupe · Accès : Encadrement secret · Droits d’accès ajustés'],
                ['event_date' => '2024-02-01', 'description' => 'Sanction disciplinaire sans grade'],
            ],
            'distances' => ['totals' => ['foot_m' => 412300, 'land_m' => 1280400, 'air_m' => 3920000, 'sea_m' => 50, 'halo_m' => 9200, 'halo_jumps' => 7]],
            'play' => ['total_seconds' => 1834 * 3600, 'sessions_total' => 212],
            'game_roles' => [['key' => 'CDE', 'label' => 'CDE', 'seconds' => 720 * 3600], ['key' => 'SLOT:Grenadier', 'label' => '', 'seconds' => 300 * 3600], ['key' => 'PIL', 'label' => 'PIL', 'seconds' => 120]],
        ]);
    }

    public function testNewSectionsDefaultsKeepOptInHiddenOnExistingPages(): void
    {
        // Page enregistrée avant l’ajout des nouvelles sections : clés absentes.
        $legacy = PublicCvSettings::decodeSections('{"unit":1,"counters":0}');
        self::assertTrue($legacy['roles']);
        self::assertTrue($legacy['activity']);
        self::assertFalse($legacy['since']);
        self::assertFalse($legacy['career']);
        self::assertFalse($legacy['counters']);

        $cv = PublicCvPresenter::present(self::rawFull(), self::settings());
        self::assertNull($cv['member_since']);
        self::assertSame([], $cv['career']);
    }

    public function testActivityCountersAndDistanceBreakdown(): void
    {
        $cv = PublicCvPresenter::present(self::rawFull(), self::settings());
        self::assertSame(['operations', 'play', 'distance', 'flight', 'qualifications', 'trainings'], array_column($cv['counters'], 'key'));
        self::assertSame(["1\u{202F}834", 'h', 'sur 212 parties'], [$cv['counters'][1]['number'], $cv['counters'][1]['unit'], $cv['counters'][1]['hint']]);
        self::assertSame(["5\u{202F}622", 'km'], [$cv['counters'][2]['number'], $cv['counters'][2]['unit']]);
        // Du plus long au plus court ; la mer (50 m) n’apparaît pas ; les sauts sont comptés.
        self::assertSame(['air', 'land', 'foot', 'halo'], array_column($cv['distance_modes'], 'key'));
        self::assertSame(['9,2', '7 sauts', 100], [$cv['distance_modes'][3]['km'], $cv['distance_modes'][3]['note'], $cv['distance_modes'][0]['share']]);
        // Codes de rôle traduits ; rôle de moins de 10 minutes ignoré.
        self::assertSame(['Chef d’équipe', 'Grenadier'], array_column($cv['game_roles'], 'label'));

        $body = $this->controller(self::settings(), self::rawFull())->show(new Request(), ['slug' => 'viper-6'])->body();
        self::assertStringContainsString('id="pcv-activity"', $body);
        self::assertStringContainsString('Rôles tenus en mission', $body);
        self::assertStringContainsString('Heures de jeu', $body);

        $hidden = $this->controller(self::settings(['activity' => false]), self::rawFull())->show(new Request(), ['slug' => 'viper-6'])->body();
        foreach (['id="pcv-activity"', 'id="pcv-roles"', 'Heures de jeu', 'Kilomètres'] as $absent) {
            self::assertStringNotContainsString($absent, $hidden, 'Activité masquée rendue : ' . $absent);
        }
        self::assertStringContainsString('Heures de vol', $hidden);
    }

    public function testJobsAndUnitPost(): void
    {
        $cv = PublicCvPresenter::present(self::rawFull(), self::settings());
        self::assertSame('Fusilier — Grenadier', $cv['job']);
        self::assertSame(['Infirmier — CLS'], $cv['other_jobs']);
        self::assertSame('Chef de groupe', $cv['post']);
        self::assertSame('2e section', $cv['unit'], 'Affectation reprise de l’organigramme si aucune unité de rattachement');

        $body = $this->controller(self::settings(), self::rawFull())->show(new Request(), ['slug' => 'viper-6'])->body();
        self::assertStringContainsString('<li class="pcv-chip">Fusilier — Grenadier</li>', $body);
        self::assertStringContainsString('<dt>Poste</dt><dd>Chef de groupe</dd>', $body);

        $none = PublicCvPresenter::present(self::rawFull(), self::settings(['roles' => false]));
        self::assertSame([null, [], null], [$none['job'], $none['other_jobs'], $none['post']]);
    }

    public function testMemberSinceAndCareerAreOptInAndNeverShowReasons(): void
    {
        $settings = self::settings(['since' => true, 'career' => true]);
        $cv = PublicCvPresenter::present(self::rawFull(), $settings);
        self::assertSame('mars 2022', $cv['member_since']);
        self::assertSame([['title' => 'Sergent-chef', 'subtitle' => 'Chef de groupe', 'date' => 'juin 2025']], $cv['career']);

        $body = $this->controller($settings, self::rawFull())->show(new Request(), ['slug' => 'viper-6'])->body();
        self::assertStringContainsString('id="pcv-career"', $body);
        self::assertStringContainsString('<dt>Membre depuis</dt><dd>Mars 2022</dd>', $body);
        foreach (['Encadrement secret', 'Droits d’accès', 'Sanction'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $body);
        }
    }

    public function testBannerFallbackOwnThenCommunityThenPlain(): void
    {
        $own = 'uploads/public-cv/b-7-0123456789abcdef.jpg';
        $body = $this->controller(self::settings() + ['banner_path' => $own], self::raw())->show(new Request(), ['slug' => 'viper-6'])->body();
        self::assertStringContainsString($own, $body);
        self::assertStringNotContainsString('banniere-lynx', $body);
        // La bannière perso reste même si la section communauté est masquée.
        $body = $this->controller(self::settings(['unit' => false]) + ['banner_path' => $own], self::raw())->show(new Request(), ['slug' => 'viper-6'])->body();
        self::assertStringContainsString($own, $body);

        // Chemin enregistré invalide : ignoré, la bannière de la communauté reprend.
        $body = $this->controller(self::settings() + ['banner_path' => '../../config/app.php'], self::raw())->show(new Request(), ['slug' => 'viper-6'])->body();
        self::assertStringContainsString('banniere-lynx', $body);
        self::assertStringNotContainsString('config/app.php', $body);

        $raw = self::raw();
        $raw['banner_url'] = null;
        $body = $this->controller(self::settings(), $raw)->show(new Request(), ['slug' => 'viper-6'])->body();
        self::assertStringContainsString('pcv-hero pcv-hero--plain', $body);
        self::assertStringNotContainsString('class="pcv-hero-bg"', $body);
    }

    public function testBannerUploadRejectsInvalidFiles(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pcv-banner-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $storage = new PublicCvBannerStorage($dir);
        $file = static function (string $content, string $name) use ($dir): array {
            $tmp = $dir . DIRECTORY_SEPARATOR . 'up-' . bin2hex(random_bytes(3));
            file_put_contents($tmp, $content);

            return ['name' => $name, 'tmp_name' => $tmp, 'size' => strlen($content), 'error' => UPLOAD_ERR_OK, 'type' => 'image/jpeg'];
        };

        self::assertSame(['path' => null, 'error' => null], $storage->store(7, ['error' => UPLOAD_ERR_NO_FILE]));
        self::assertStringContainsString('Formats acceptés', (string) $storage->store(7, $file('<?php echo 1; ?>', 'photo.jpg'))['error']);
        self::assertStringContainsString('Formats acceptés', (string) $storage->store(7, $file('<svg xmlns="http://www.w3.org/2000/svg"/>', 'b.svg'))['error']);
        self::assertStringContainsString('iPhone', (string) $storage->store(7, $file("\x00\x00\x00\x18ftypheic\x00\x00\x00\x00", 'IMG_1.HEIC'))['error']);
        $big = $file('x', 'b.jpg');
        $big['size'] = PublicCvBannerStorage::effectiveMaxBytes() + 1;
        self::assertStringContainsString('trop lourde', (string) $storage->store(7, $big)['error']);
        self::assertNotNull($storage->store(7, ['error' => UPLOAD_ERR_INI_SIZE])['error']);
        self::assertSame([], glob($dir . '/public/uploads/public-cv/*') ?: [], 'Aucun fichier publié pour un envoi refusé');

        self::assertTrue(PublicCvBannerStorage::isStoredPath('uploads/public-cv/b-7-0123456789abcdef.jpg'));
        foreach (['uploads/public-cv/../../.env', 'uploads/banners/x.jpg', 'uploads/public-cv/b-7-0123456789abcdef.php', 'https://evil.test/x.jpg'] as $bad) {
            self::assertFalse(PublicCvBannerStorage::isStoredPath($bad), $bad);
            self::assertNull(PublicCvBannerStorage::publicUrl($bad), $bad);
        }
    }

    public function testBannerUploadIsReencodedWithoutCameraData(): void
    {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) {
            self::markTestSkipped('Bibliothèque d’images (GD) absente.');
        }
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pcv-banner-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $src = $dir . DIRECTORY_SEPARATOR . 'src.png';
        $im = imagecreatetruecolor(1200, 400);
        imagepng($im, $src);
        $small = $dir . DIRECTORY_SEPARATOR . 'small.png';
        imagepng(imagecreatetruecolor(300, 100), $small);

        $storage = new PublicCvBannerStorage($dir);
        $tooSmall = $storage->store(7, ['name' => 's.png', 'tmp_name' => $small, 'size' => filesize($small), 'error' => UPLOAD_ERR_OK]);
        self::assertStringContainsString('trop petite', (string) $tooSmall['error']);

        $ok = $storage->store(7, ['name' => 'b.png', 'tmp_name' => $src, 'size' => filesize($src), 'error' => UPLOAD_ERR_OK]);
        self::assertNull($ok['error']);
        self::assertTrue(PublicCvBannerStorage::isStoredPath($ok['path']));
        $abs = $dir . '/public/' . $ok['path'];
        self::assertFileExists($abs);
        self::assertSame("\xFF\xD8\xFF", substr((string) file_get_contents($abs), 0, 3), 'Toujours ré-encodée en JPEG');
        $storage->delete($ok['path']);
        self::assertFileDoesNotExist($abs);
    }

    public function testNotFoundViewIsStandalone(): void
    {
        $r = PublicPlayerCvController::notFound();
        self::assertInstanceOf(Response::class, $r);
        self::assertSame(404, $r->statusCode());
    }

    private static function attributes(string $html): string
    {
        preg_match_all('/(?:content|alt|title|href|src)="([^"]*)"/', $html, $m);

        return implode(' ', array_filter($m[1], static fn (string $v): bool => !str_starts_with($v, 'http') && !str_starts_with($v, '/')));
    }
}
