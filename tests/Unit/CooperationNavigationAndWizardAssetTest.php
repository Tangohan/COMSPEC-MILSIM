<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CooperationNavigationAndWizardAssetTest extends TestCase
{
    private function read(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    public function testSixTabsStillReachEveryHistoricalScreen(): void
    {
        $nav = $this->read('views/back_office/cooperation/missions/_nav.php');
        foreach (['Synthèse', 'Proposition & négociation', 'Espace commun', 'Réunions', 'Structures & liaisons', 'Journal & REX'] as $tab) {
            self::assertStringContainsString("'" . $tab . "'", $nav);
        }
        foreach (['show', 'edit', 'negotiate', 'exchange', 'consent', 'timeline', 'meeting', 'orbat', 'rex', 'archive'] as $screen) {
            self::assertStringContainsString('cooperation_mission_' . $screen . '_url($mid)', $nav, $screen);
        }
        self::assertStringContainsString('aria-disabled="true"', $nav);
        self::assertStringContainsString('_progress.php', $nav);
    }

    public function testConductOnlyUpdatesSubmittedFieldsAndAdvancesThroughAConfirmedButton(): void
    {
        $c = $this->read('app/Controllers/Web/InterteamMissionWebController.php');
        self::assertStringContainsString('Seuls les champs envoyés sont modifiés', $c);
        $show = $this->read('views/back_office/cooperation/missions/show.php');
        self::assertStringContainsString('data-coop-advance', $show);
        self::assertStringContainsString('id="coop-advance-dialog"', $show);
        self::assertStringNotContainsString('<select id="operational_stage"', $show);
        self::assertFileExists(dirname(__DIR__, 2) . '/public/assets/js/cooperation/conduct.js');
    }

    public function testCreationWizardKeepsTheExistingEndpoint(): void
    {
        $create = $this->read('views/back_office/cooperation/missions/create.php');
        self::assertStringContainsString('action="<?= $h(cooperation_mission_index_url()) ?>"', $create);
        self::assertStringContainsString('name="title"', $create);
        self::assertStringContainsString('name="partner_tenant_ids[]"', $create);
        self::assertStringContainsString('Enregistrer en brouillon', $create);
        $c = $this->read('app/Controllers/Web/InterteamMissionWebController.php');
        self::assertStringContainsString("send_invitations", $c);
        self::assertStringContainsString("'/back-office/cooperation/missions', [InterteamMissionWebController::class, 'store']", $this->read('routes/web.php'));
    }

    public function testListOffersStateFiltersSearchAndActionFlag(): void
    {
        $index = $this->read('views/back_office/cooperation/missions/index.php');
        self::assertStringContainsString('data-coop-filter', $index);
        self::assertStringContainsString('data-coop-search', $index);
        self::assertStringContainsString('Action requise', $index);
        self::assertStringContainsString("\$prog['short']", $index);
    }
}
