<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\BackOffice\Menu\BackOfficeMenuBadges;
use App\Services\BackOffice\Menu\BadgeProvider;
use App\Services\BackOffice\Menu\FavoritesList;
use App\Services\BackOffice\Menu\MenuDecorator;
use PHPUnit\Framework\TestCase;

/**
 * Menu latéral du back-office : pastilles, pages nouvelles, favoris.
 */
final class BackOfficeMenuTest extends TestCase
{
    public function testBadgeTextStopsAtNinePlus(): void
    {
        self::assertSame('', BackOfficeMenuBadges::format(0));
        self::assertSame('1', BackOfficeMenuBadges::format(1));
        self::assertSame('9', BackOfficeMenuBadges::format(9));
        self::assertSame('9+', BackOfficeMenuBadges::format(10));
        self::assertSame('9+', BackOfficeMenuBadges::format(250));
        self::assertSame('3 candidature(s)', BackOfficeMenuBadges::title('%d candidature(s)', 3));
    }

    public function testBadgeNeedsPermissionAndVisiblePage(): void
    {
        $badges = new BackOfficeMenuBadges(self::config());
        $none = $badges->visibleKeys(static fn (string $p): bool => false, static fn (string $p): bool => true);
        self::assertSame([], $none);

        $recruiter = $badges->visibleKeys(
            static fn (string $p): bool => $p === 'organization.recruitment.manage',
            static fn (string $p): bool => true
        );
        self::assertSame(['candidatures'], $recruiter);

        // Droit présent mais page retirée du menu (profil de communauté) : pas de compteur.
        $hidden = $badges->visibleKeys(
            static fn (string $p): bool => true,
            static fn (string $p): bool => $p !== 'back-office/recruitments'
        );
        self::assertNotContains('candidatures', $hidden);
        self::assertContains('formule', $hidden);
    }

    public function testProvidersAreCalledPerTenantAndErrorsGiveZero(): void
    {
        $seen = [];
        $ok = new class ($seen) implements BadgeProvider {
            /** @param list<int> $seen */
            public function __construct(private array &$seen)
            {
            }

            public function badge(int $tenantId, int $userId): ?array
            {
                $this->seen[] = $tenantId;

                return ['count' => 4];
            }
        };
        $broken = new class () implements BadgeProvider {
            public function badge(int $tenantId, int $userId): ?array
            {
                throw new \RuntimeException('base indisponible');
            }
        };
        $cfg = self::config();
        $cfg['badges']['candidatures']['provider'] = $ok::class;
        $cfg['badges']['formule']['provider'] = $broken::class;
        $badges = new BackOfficeMenuBadges($cfg, [$ok::class => $ok, $broken::class => $broken]);

        $rows = $badges->computeKeys(7, 3, ['candidatures', 'formule', 'inconnue']);
        self::assertSame([7], $seen);
        self::assertSame(4, $rows['candidatures']['count']);
        self::assertFalse($rows['candidatures']['important']);
        self::assertSame('4 à instruire', $rows['candidatures']['title']);
        self::assertSame(0, $rows['formule']['count']);
        self::assertFalse($rows['formule']['important'], 'Un compteur à zéro n’est jamais « important ».');
        self::assertArrayNotHasKey('inconnue', $rows);
        self::assertSame([], $badges->compute(0, 3, static fn (): bool => true, static fn (): bool => true));
    }

    public function testDecoratorSumsUniqueKeysOnParentsAndMarksNewAndFavorites(): void
    {
        $groups = [[
            'key' => 'effectifs',
            'items' => [[
                'label' => 'Recrutement',
                'href' => '/back-office/recruitments',
                'children' => [
                    ['label' => 'Candidatures', 'href' => '/back-office/recruitments'],
                    ['label' => 'Section', 'children' => [
                        ['label' => 'Corrections', 'href' => '/back-office/personnel/corrections'],
                        ['label' => 'Consignes', 'href' => '/back-office/ressources/atak-consignes'],
                    ]],
                    ['label' => 'Calendrier', 'href' => '/back-office/events?vue=calendrier'],
                ],
            ]],
        ]];
        $badges = [
            'candidatures' => ['count' => 3, 'important' => false, 'title' => '3 candidatures', 'path' => 'back-office/recruitments'],
            'corrections' => ['count' => 12, 'important' => true, 'title' => '12 corrections', 'path' => 'back-office/personnel/corrections'],
        ];
        $out = MenuDecorator::decorate(
            $groups,
            $badges,
            ['back-office/ressources/atak-consignes'],
            ['back-office/events?vue=calendrier'],
            static fn (string $href): string => trim((string) strtok($href, '?'), '/')
        );
        $item = $out[0]['items'][0];
        self::assertSame(15, $item['_badge_count'], 'Le parent et sa page « Candidatures » partagent la clé : comptée une fois.');
        self::assertTrue($item['_badge_important']);
        self::assertTrue($item['_has_new']);
        self::assertFalse($item['_new']);
        self::assertSame(3, $item['children'][0]['_badge_count']);
        self::assertSame(12, $item['children'][1]['_badge_count']);
        self::assertTrue($item['children'][1]['children'][1]['_new']);
        self::assertTrue($item['children'][2]['_fav']);
        self::assertSame('back-office/events?vue=calendrier', $item['children'][2]['_fav_key']);
        self::assertFalse($item['children'][0]['_fav']);
    }

    public function testFavoritesDropPagesNoLongerAllowedAndUseMenuLabels(): void
    {
        $groups = MenuDecorator::decorate([[
            'items' => [[
                'label' => 'Recrutement',
                'href' => '/back-office/recruitments',
                'children' => [['label' => 'Candidatures', 'href' => '/back-office/recruitments']],
            ]],
        ]], [], [], [], static fn (string $href): string => trim($href, '/'));
        $favs = [
            ['path' => 'back-office/recruitments', 'label' => ''],
            ['path' => 'back-office/audit', 'label' => 'Journal'],
            ['path' => 'back-office/ancienne-page', 'label' => ''],
            ['path' => 'back-office/interdite', 'label' => 'Secret'],
        ];
        $out = MenuDecorator::favorites(
            $groups,
            $favs,
            static fn (string $p): bool => $p !== 'back-office/interdite',
            static fn (string $p): string => $p === 'back-office/ancienne-page' ? 'Titre de la page' : ''
        );
        self::assertSame(['back-office/recruitments', 'back-office/audit', 'back-office/ancienne-page'], array_column($out, 'path'));
        self::assertSame('Candidatures', $out[0]['label'], 'Le libellé de la page la plus précise du menu.');
        self::assertSame('Journal', $out[1]['label']);
        self::assertSame('Titre de la page', $out[2]['label']);
    }

    public function testNewPagesExpireAfterWindowOrVisit(): void
    {
        $now = (int) strtotime('2026-11-10 12:00:00');
        $declared = [
            ['path' => 'back-office/a', 'since' => '2026-11-08'],
            ['path' => 'back-office/b', 'since' => '2026-09-01'],
            ['path' => 'back-office/c', 'since' => '2026-12-01'],
            ['path' => 'back-office/d', 'since' => '2026-11-01'],
        ];
        self::assertSame(['back-office/a', 'back-office/d'], MenuDecorator::activeNew($declared, 30, [], $now));
        self::assertSame(['back-office/a'], MenuDecorator::activeNew($declared, 30, ['back-office/d'], $now));
        self::assertSame('back-office/a', MenuDecorator::visitedNew(['back-office/a'], 'back-office/a/detail'));
        self::assertNull(MenuDecorator::visitedNew(['back-office/a'], 'back-office/ab'));
    }

    public function testFavoritePathValidation(): void
    {
        self::assertSame('back-office/audit', FavoritesList::normalizePath('/back-office/audit/'));
        self::assertSame('back-office/events?vue=calendrier', FavoritesList::normalizePath('back-office/events?vue=calendrier#x'));
        self::assertSame('back-office/audit', FavoritesList::normalizePath('https://athena-comspec.eu/public/back-office/audit'));
        self::assertNull(FavoritesList::normalizePath('//evil.example/x'));
        self::assertNull(FavoritesList::normalizePath('back-office/../admin'));
        self::assertNull(FavoritesList::normalizePath('javascript:alert(1)'));
        self::assertNull(FavoritesList::normalizePath(''));
        self::assertNull(FavoritesList::normalizePath('back-office/a|b'));
        self::assertSame('back-office/x', FavoritesList::normalizePath('back-office/x?<script>'));
    }

    public function testFavoritesToggleMoveReorderAndCap(): void
    {
        $list = [];
        [$list, $r] = FavoritesList::toggle($list, 'back-office/a', '  A  ');
        self::assertSame('added', $r);
        self::assertSame([['path' => 'back-office/a', 'label' => 'A']], $list);
        [$list] = FavoritesList::toggle($list, 'back-office/b');
        [$list] = FavoritesList::toggle($list, 'back-office/c');
        self::assertSame(['back-office/b', 'back-office/a', 'back-office/c'], array_column(FavoritesList::move($list, 'back-office/b', -1), 'path'));
        self::assertSame(['back-office/a', 'back-office/b', 'back-office/c'], array_column(FavoritesList::move($list, 'back-office/a', -1), 'path'));
        self::assertSame(
            ['back-office/c', 'back-office/a', 'back-office/b'],
            array_column(FavoritesList::reorder($list, ['back-office/c', 'back-office/inconnu', 'back-office/a']), 'path')
        );
        [$list, $r] = FavoritesList::toggle($list, 'back-office/b');
        self::assertSame('removed', $r);
        self::assertFalse(FavoritesList::has($list, 'back-office/b'));

        $full = [];
        for ($i = 0; $i < FavoritesList::DEFAULT_MAX; $i++) {
            [$full] = FavoritesList::toggle($full, 'back-office/p' . $i);
        }
        [$same, $r] = FavoritesList::toggle($full, 'back-office/trop');
        self::assertSame('full', $r);
        self::assertSame($full, $same);
        self::assertCount(FavoritesList::DEFAULT_MAX, FavoritesList::sanitize(array_merge($full, $full, [['path' => '../x']])));
    }

    public function testDeclaredProvidersExistAndPagesAreInTheMenu(): void
    {
        $root = dirname(__DIR__, 2);
        $cfg = require $root . '/config/back_office_menu.php';
        $aside = BackOfficeMenuCoverageTest::asidePaths();
        foreach ($cfg['badges'] as $key => $def) {
            self::assertTrue(class_exists($def['provider']), $key);
            self::assertTrue(is_subclass_of($def['provider'], BadgeProvider::class), $key);
            self::assertArrayHasKey($def['path'], $aside, 'Pastille « ' . $key . ' » sur une page absente du menu.');
            self::assertContains($def['tone'], ['notif', 'important'], $key);
            self::assertNotSame([], $def['any_permissions'], $key);
        }
        foreach ($cfg['new'] as $row) {
            self::assertArrayHasKey($row['path'], $aside, 'Page « Nouveau » absente du menu : ' . $row['path']);
            self::assertNotFalse(strtotime($row['since']));
        }
        self::assertLessThanOrEqual(50, $cfg['favorites_max']);
    }

    /** @return array<string, mixed> */
    private static function config(): array
    {
        return [
            'cache_seconds' => 0,
            'poll_seconds' => 60,
            'badges' => [
                'candidatures' => ['path' => 'back-office/recruitments', 'provider' => 'X', 'any_permissions' => ['organization.recruitment.manage', 'admin.access'], 'tone' => 'notif', 'title' => '%d à instruire'],
                'formule' => ['path' => 'back-office/configuration', 'provider' => 'Y', 'any_permissions' => ['admin.organization'], 'tone' => 'important', 'title' => 'Formule'],
            ],
        ];
    }
}
