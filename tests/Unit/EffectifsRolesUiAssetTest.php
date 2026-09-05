<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EffectifsRolesUiAssetTest extends TestCase
{
    public function testRolesPageHasLoosenedSpacingAndHumanLabels(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/admin/effectifs_workspace/roles.php');
        $css = (string) file_get_contents($root . '/public/assets/css/effectifs_lms.css');
        $dispatch = (string) file_get_contents($root . '/app/Support/DevDispatchCatalog.php');

        self::assertStringContainsString('eff-roles-page', $view);
        self::assertStringContainsString('Gouvernance', $view);
        self::assertStringContainsString('Rôles d’accès', $view);
        self::assertStringContainsString('distincts des fonctions opérationnelles et des grades', $view);
        self::assertStringContainsString('Pilotage', $view);
        self::assertStringContainsString('Deux couches, un même principe', $view);
        self::assertStringContainsString('Membres', $view);
        self::assertStringContainsString('Droits', $view);
        self::assertStringNotContainsString('endpoint', $view);
        self::assertStringNotContainsString('JSON', $view);
        self::assertStringNotContainsString('slug', strtolower($view));

        self::assertStringContainsString('.eff-roles-page {', $css);
        self::assertStringContainsString('padding-inline: clamp(1rem, 2.8vw, 2.5rem);', $css);
        self::assertStringContainsString('.eff-roles-page .eff-metric {', $css);
        self::assertStringContainsString('padding: 1.4rem 1.45rem 1.3rem;', $css);
        self::assertStringContainsString('.eff-roles-page .eff-metric__k {', $css);
        self::assertStringContainsString('margin: 0 0 0.65rem;', $css);
        self::assertStringContainsString('.eff-roles-page .eff-toolbar {', $css);
        self::assertStringContainsString('padding: 1.55rem 1.7rem;', $css);
        self::assertStringContainsString('.eff-roles-page .eff-toolbar__actions {', $css);
        self::assertStringContainsString('gap: 0.9rem;', $css);
        self::assertStringContainsString('.eff-roles-page .eff-list-meta {', $css);
        self::assertStringContainsString('margin-bottom: 1.4rem;', $css);
        self::assertStringContainsString('.eff-roles-page .eff-role-grid--cards,', $css);
        self::assertStringContainsString('gap: 1.7rem;', $css);
        self::assertStringContainsString('.eff-roles-page .eff-role-card {', $css);
        self::assertStringContainsString('padding: 1.8rem 1.75rem 1.55rem;', $css);
        self::assertStringContainsString('.eff-roles-page .eff-role-card__badges {', $css);
        self::assertStringContainsString('margin-top: 0.8rem;', $css);
        self::assertStringContainsString('.eff-roles-page .eff-role-card .eff-act {', $css);
        self::assertStringContainsString('min-height: 2.4rem;', $css);
        self::assertStringContainsString('padding: 0.55rem 1rem;', $css);
        self::assertStringContainsString('.eff-roles-page .eff-hint-panel {', $css);
        self::assertStringContainsString('padding: 1.9rem 2rem;', $css);
        self::assertStringContainsString('margin-top: 2.35rem;', $css);

        self::assertStringContainsString('La page des rôles a plus d’air', $dispatch);
        self::assertStringContainsString('$pr(270,', $dispatch);
    }
}
