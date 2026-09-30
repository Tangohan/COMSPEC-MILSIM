<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Personnel\AssignmentTargetCatalog;
use PHPUnit\Framework\TestCase;

final class AssignmentTargetCatalogTest extends TestCase
{
    public function testParseRefAcceptePosteAavEtOffre(): void
    {
        self::assertSame(
            ['kind' => 'poste', 'source' => 'billet', 'id' => 12],
            AssignmentTargetCatalog::parseRef('poste:billet:12')
        );
        self::assertSame(
            ['kind' => 'poste', 'source' => 'job', 'id' => 4],
            AssignmentTargetCatalog::parseRef('poste:job:4')
        );
        self::assertSame(
            ['kind' => 'aav', 'source' => 'campaign', 'id' => 9],
            AssignmentTargetCatalog::parseRef('aav:campaign:9')
        );
        self::assertSame(
            ['kind' => 'aav', 'source' => 'billet', 'id' => 3],
            AssignmentTargetCatalog::parseRef('aav:billet:3')
        );
        self::assertSame(
            ['kind' => 'offre', 'source' => 'opening', 'id' => 8],
            AssignmentTargetCatalog::parseRef('offre:opening:8')
        );
        self::assertSame('Poste', AssignmentTargetCatalog::KIND_LABELS['poste']);
        self::assertSame('AAV', AssignmentTargetCatalog::KIND_LABELS['aav']);
        self::assertSame('Offre', AssignmentTargetCatalog::KIND_LABELS['offre']);
    }

    public function testParseRefRejetteLesCombinaisonsInvalides(): void
    {
        self::assertNull(AssignmentTargetCatalog::parseRef(''));
        self::assertNull(AssignmentTargetCatalog::parseRef('poste:opening:1'));
        self::assertNull(AssignmentTargetCatalog::parseRef('offre:billet:1'));
        self::assertNull(AssignmentTargetCatalog::parseRef('aav:job:1'));
        self::assertNull(AssignmentTargetCatalog::parseRef('poste:billet:0'));
        self::assertNull(AssignmentTargetCatalog::parseRef('inconnu:billet:1'));
    }

    public function testDemandesExposentLesGroupesDeCibles(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/admin/member_situation/avancement.php');
        self::assertStringContainsString('AAV · Appels à volontaire', $view);
        self::assertStringContainsString('name="target_ref"', $view);
        self::assertStringContainsString("'postes' => 'Postes'", $view);
        self::assertStringContainsString("'offres' => 'Offres'", $view);

        $ctrl = (string) file_get_contents($root . '/app/Controllers/Admin/Organization/MemberAdvancementController.php');
        self::assertStringContainsString('AssignmentTargetCatalog', $ctrl);
        self::assertStringContainsString('target_ref', $ctrl);

        $staff = (string) file_get_contents($root . '/views/admin/effectifs_workspace/rh_mobility.php');
        self::assertStringContainsString('name="target_ref"', $staff);
        self::assertStringContainsString('AAV · Appels à volontaire', $staff);

        $boot = (string) file_get_contents($root . '/bootstrap/rh_dossier_individuel_migration.php');
        self::assertStringContainsString('target_kind', $boot);
        self::assertStringContainsString('target_billet_id', $boot);
        self::assertStringContainsString('target_opening_id', $boot);
        self::assertStringContainsString('target_campaign_id', $boot);
    }
}
