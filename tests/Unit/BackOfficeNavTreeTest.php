<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\BackOfficeNavTree;
use PHPUnit\Framework\TestCase;

final class BackOfficeNavTreeTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private function navFixture(): array
    {
        return [
            [
                'key' => 'pilotage',
                'label' => 'PILOTAGE',
                'items' => [
                    ['label' => 'Tableau de bord', 'href' => 'back-office', 'active' => false],
                ],
            ],
            [
                'key' => 'organisation',
                'label' => 'ORGANISATION',
                'items' => [
                    [
                        'label' => 'Organisation',
                        'href' => 'back-office/organisation-effectifs',
                        'active' => false,
                        'children' => [
                            [
                                'label' => 'Structure',
                                'children' => [
                                    ['label' => 'Unités', 'href' => 'back-office/units', 'active' => false],
                                    ['label' => 'Équipes', 'href' => 'back-office/teams', 'active' => false],
                                ],
                            ],
                            [
                                'label' => 'Identification',
                                'children' => [
                                    ['label' => 'Matricules', 'href' => 'back-office/organisation/matricules', 'active' => false],
                                    ['label' => 'Secret', 'href' => 'back-office/secret', 'active' => false],
                                ],
                            ],
                            ['label' => 'Disponibilité', 'href' => 'back-office/organisation/disponibilite', 'active' => false],
                        ],
                    ],
                ],
            ],
            [
                'key' => 'operations',
                'label' => 'OPÉRATIONS',
                'items' => [
                    [
                        'label' => 'Événements',
                        'href' => 'back-office/events',
                        'children' => [
                            ['label' => 'Inscriptions en cours', 'href' => 'back-office/events', 'active' => false],
                            ['label' => 'Calendrier', 'href' => 'back-office/events?vue=calendrier', 'active' => true],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function testFilterDropsForbiddenPagesAndFlattensSingleLinkSections(): void
    {
        $allowed = static fn (string $href): bool => $href !== 'back-office/secret'
            && $href !== 'back-office/organisation-effectifs';
        $groups = BackOfficeNavTree::filter($this->navFixture(), $allowed);

        $org = $groups[1]['items'][0];
        // Parent sans droit : il pointe sur la première page autorisée (dans un sous-menu).
        self::assertSame('back-office/units', $org['href']);
        self::assertTrue(BackOfficeNavTree::isSection($org['children'][0]));
        // « Identification » n’a plus qu’une page : elle remonte d’un niveau.
        self::assertFalse(BackOfficeNavTree::isSection($org['children'][1]));
        self::assertSame('Matricules', $org['children'][1]['label']);
        self::assertSame('Disponibilité', $org['children'][2]['label']);

        $none = BackOfficeNavTree::filter($this->navFixture(), static fn (string $h): bool => $h === 'back-office');
        self::assertCount(1, $none, 'Un groupe sans aucune page autorisée disparaît.');
        self::assertSame('pilotage', $none[0]['key']);
    }

    public function testDeepPageMarksItsSectionAndItemAsActive(): void
    {
        // Aucune entrée marquée : la correspondance de chemin (préfixe) désigne la page.
        $tree = $this->navFixture();
        $tree[2]['items'][0]['children'][1]['active'] = false;
        $groups = BackOfficeNavTree::resolve($tree, 'back-office/teams/12/edit');
        $org = $groups[1]['items'][0];

        self::assertTrue($org['active']);
        self::assertFalse($org['current']);
        self::assertTrue($org['children'][0]['active']);
        self::assertTrue($org['children'][0]['children'][1]['current']);
        self::assertFalse($org['children'][0]['children'][0]['active']);
        self::assertFalse($org['children'][1]['active']);
        self::assertFalse($groups[0]['items'][0]['active'], 'Le tableau de bord ne s’allume pas sur une sous-page.');
        self::assertFalse($groups[2]['items'][0]['active']);
    }

    public function testExplicitFlagWinsOverSamePathSibling(): void
    {
        $groups = BackOfficeNavTree::resolve($this->navFixture(), 'back-office/events');
        $events = $groups[2]['items'][0];

        self::assertTrue($events['active']);
        self::assertFalse($events['children'][0]['active']);
        self::assertTrue($events['children'][1]['current']);
    }

    public function testParentPageIsCurrentWhenNoChildMatches(): void
    {
        $tree = $this->navFixture();
        $tree[2]['items'][0]['children'][1]['active'] = false;
        $tree[1]['items'][0]['active'] = true;
        $groups = BackOfficeNavTree::resolve($tree, 'back-office/organisation-effectifs');

        self::assertTrue($groups[1]['items'][0]['current']);
        self::assertFalse($groups[1]['items'][0]['children'][0]['active']);
    }

    public function testExactlyOnePageIsCurrent(): void
    {
        foreach (['back-office', 'back-office/units', 'back-office/organisation/disponibilite', 'back-office/inconnu'] as $path) {
            $tree = $this->navFixture();
            $tree[2]['items'][0]['children'][1]['active'] = false;
            $count = 0;
            $walk = static function (array $nodes) use (&$walk, &$count): void {
                foreach ($nodes as $node) {
                    if (!empty($node['current'])) {
                        $count++;
                    }
                    $walk($node['items'] ?? $node['children'] ?? []);
                }
            };
            $walk(BackOfficeNavTree::resolve($tree, $path));
            self::assertSame($path === 'back-office/inconnu' ? 0 : 1, $count, $path);
        }
    }

    public function testSidebarUsesThreeLevelsAndAccessibleToggles(): void
    {
        $root = dirname(__DIR__, 2);
        $nav = (string) file_get_contents($root . '/views/partials/ath_sidebar_nav.php');
        $js = (string) file_get_contents($root . '/public/assets/js/back-office-sidebar.js');
        $css = (string) file_get_contents($root . '/public/assets/css/back-office-refresh.css');

        // Intitulés des groupes depuis « Clarify back-office navigation » (375b52b87) : plus explicites que les anciens mots seuls.
        $order = ["'label' => 'TABLEAU DE BORD ET SUIVI'", "'label' => 'MEMBRES ET SUIVI RH'", "'label' => 'UNITÉS ET RÉFÉRENTIELS'", "'label' => 'DROITS ET PERMISSIONS'",
            "'label' => 'OPÉRATIONS'", "'label' => 'ROLEPLAY'", "'label' => 'ATAK · CARTE ET TERMINAUX'", "'label' => 'FORMATIONS ET DOCUMENTS'",
            "'label' => 'COMMUNAUTÉ'", "'label' => 'RÉGLAGES ET INTÉGRATIONS'"];
        $elsePos = (int) strpos($nav, '} else {');
        $last = $elsePos;
        foreach ($order as $needle) {
            $pos = strpos($nav, $needle, $elsePos);
            self::assertIsInt($pos, $needle);
            self::assertGreaterThan($last, $pos, $needle);
            $last = $pos;
        }

        self::assertStringContainsString("'label' => 'Référentiels'", $nav);
        self::assertStringContainsString("'label' => 'Grades et distinctions'", $nav);
        self::assertStringContainsString('data-ath-section-toggle', $nav);
        self::assertStringContainsString('aria-controls="<?= $h($secId) ?>"', $nav);
        self::assertStringContainsString('aria-expanded="<?= $secOpen ? \'true\' : \'false\' ?>"', $nav);
        self::assertStringContainsString('BackOfficeNavTree::resolve($athNavGroups, $p)', $nav);
        self::assertStringContainsString('setSection', $js);
        self::assertStringContainsString("'ArrowDown'", $js);
        self::assertStringContainsString('.ath-sidebar__section-head', $css);
    }
}
