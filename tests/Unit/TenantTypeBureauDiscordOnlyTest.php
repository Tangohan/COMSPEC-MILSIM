<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Community\PublicPageBuilder;
use App\Services\Community\TenantTypeConfig;
use App\Services\Effectifs\RosterOnlyMemberService;
use App\Support\EmailPrivacy;
use App\Support\RosterOnlyMember;
use PHPUnit\Framework\TestCase;

/**
 * Profil « Bureau des effectifs » : registre tenu sans compte, recrutement par Discord seulement,
 * menu de gestion allégé (et pareil pour « Carte ATAK »).
 */
final class TenantTypeBureauDiscordOnlyTest extends TestCase
{
    public function testSimplifiedProfilesHideUselessBackOfficePages(): void
    {
        foreach (['back-office/recruitments', 'back-office/invitations', 'back-office/roleplay/immersion', 'back-office/ressources/effectifs/elevations', 'jnet'] as $path) {
            self::assertTrue(TenantTypeConfig::backOfficePathHidden('effectifs', $path), $path);
        }
        foreach (['back-office/ressources/effectifs', 'back-office/atak/roleplay', 'back-office/organisation/structure', 'back-office/community'] as $path) {
            self::assertFalse(TenantTypeConfig::backOfficePathHidden('effectifs', $path), $path);
        }
        self::assertTrue(TenantTypeConfig::backOfficePathHidden('atak', 'back-office/atak/data-inspector'));
        self::assertFalse(TenantTypeConfig::backOfficePathHidden('atak', 'back-office/invitations'), 'ATAK players still need invitations');
        self::assertSame([], TenantTypeConfig::hiddenBackOfficePaths('full'));
    }

    public function testGuideStepsNeverPointToHiddenPages(): void
    {
        foreach (['atak', 'effectifs', 'full'] as $type) {
            foreach (TenantTypeConfig::profileGuide($type)['steps'] as $step) {
                self::assertFalse(TenantTypeConfig::backOfficePathHidden($type, $step['path']), $type . ' → ' . $step['path']);
            }
        }
    }

    public function testOnlyFullProfileRecruitsOnTheSite(): void
    {
        self::assertTrue(TenantTypeConfig::recruitsOnDiscordOnly('effectifs'));
        self::assertTrue(TenantTypeConfig::recruitsOnDiscordOnly('atak'));
        self::assertFalse(TenantTypeConfig::recruitsOnDiscordOnly('full'));
        self::assertTrue(TenantTypeConfig::rosterWithoutAccounts('effectifs'));
        self::assertFalse(TenantTypeConfig::rosterWithoutAccounts('atak'));
    }

    public function testFullProfileOpensTheBureauModeWithoutLosingItsModules(): void
    {
        self::assertTrue(TenantTypeConfig::rosterBureauMode('effectifs'));
        self::assertTrue(TenantTypeConfig::rosterBureauMode('full'));
        self::assertTrue(TenantTypeConfig::rosterBureauMode('Complet-inconnu'), 'un type inconnu retombe sur Complet');
        self::assertFalse(TenantTypeConfig::rosterBureauMode('atak'));
        // Le profil Complet garde le recrutement sur le site et toutes les pages des effectifs.
        self::assertFalse(TenantTypeConfig::recruitsOnDiscordOnly('full'));
        self::assertTrue(TenantTypeConfig::moduleAllowed('full', 'personnel'));
        self::assertTrue(TenantTypeConfig::uriAllowed('full', 'back-office/ressources/effectifs/sans-comptes'));
        self::assertFalse(TenantTypeConfig::uriAllowed('atak', 'back-office/ressources/effectifs/sans-comptes'));
    }

    public function testBureauDesignIsScopedToBureauModeAndUsesNoForbiddenClass(): void
    {
        $root = dirname(__DIR__, 2);
        $css = (string) file_get_contents($root . '/public/assets/css/back-office-effectifs-bureau.css');
        $shell = (string) file_get_contents($root . '/views/admin/effectifs_workspace/shell.php');
        self::assertStringContainsString('bo-eff-workspace--bureau', $shell);
        self::assertStringContainsString('rosterBureauMode', $shell);
        preg_match_all('/^[^\s\/@}*][^{]*\{/m', $css, $m);
        foreach ($m[0] as $selector) {
            if (str_starts_with($selector, 'html[') || str_contains($selector, 'bo-eff-workspace--bureau')) {
                continue;
            }
            self::fail('Sélecteur hors du mode bureau : ' . $selector);
        }
        self::assertDoesNotMatchRegularExpression('/kicker|eyebrow|overline|surtitre/i', $css);
    }

    public function testRosterOnlyEmailIsReservedAndMasked(): void
    {
        $email = RosterOnlyMember::syntheticEmail();
        self::assertNotFalse(filter_var($email, FILTER_VALIDATE_EMAIL));
        self::assertTrue(RosterOnlyMember::isRosterEmail($email));
        self::assertStringEndsWith('.invalid', $email);
        self::assertFalse(RosterOnlyMember::isRosterEmail('jean@example.org'));
        self::assertSame(RosterOnlyMember::label(), EmailPrivacy::mask($email));
    }

    public function testSpreadsheetImportReadsFrenchTemplateAndExportColumns(): void
    {
        $fr = RosterOnlyMemberService::parseCsv("\xEF\xBB\xBFPrénom;Nom;Indicatif;Grade;Affectation;Steam ID\nJean;Martin;Alpha 1-1;SGT;1re Section;76561198000000000\n;;;;;\n");
        self::assertNull($fr['error']);
        self::assertCount(1, $fr['rows']);
        self::assertSame('Alpha 1-1', $fr['rows'][0]['callsign']);
        self::assertSame('1re Section', $fr['rows'][0]['unit']);

        $export = RosterOnlyMemberService::parseCsv("Nom affiché,Indicatif,E-mail,Grade\nJohn Doe,Bravo,x@y.z,CPL\n");
        self::assertNull($export['error']);
        self::assertSame('John Doe', $export['rows'][0]['display_name']);
        self::assertSame('x@y.z', $export['rows'][0]['email'], 'l’e-mail du tableur est maintenant lu');

        self::assertNotNull(RosterOnlyMemberService::parseCsv("Foo;Bar\n1;2\n")['error']);
    }

    public function testHeroSectionKeepsItsImage(): void
    {
        $layout = PublicPageBuilder::normalize(['blocks' => [
            ['type' => 'section', 'key' => 'hero', 'props' => ['image' => 'https://example.org/a.jpg']],
            ['type' => 'section', 'key' => 'about', 'props' => ['image' => 'https://example.org/b.jpg']],
            ['type' => 'section', 'key' => 'video', 'props' => ['image' => 'javascript:alert(1)']],
        ]]);
        $byKey = [];
        foreach ($layout['blocks'] as $b) {
            $byKey[$b['key'] ?? $b['id']] = $b;
        }
        self::assertSame('https://example.org/a.jpg', $byKey['hero']['props']['image']);
        self::assertArrayNotHasKey('image', $byKey['about']['props']);
        self::assertArrayNotHasKey('image', $byKey['video']['props']);
    }

    public function testViewsExplainDiscordOnlyRecruitment(): void
    {
        $root = dirname(__DIR__, 2);
        $showcase = (string) file_get_contents($root . '/views/community/show_showcase.php');
        self::assertStringContainsString('recruitsOnDiscordOnly', $showcase);
        self::assertStringContainsString('Rejoindre sur Discord', $showcase);
        $inscription = (string) file_get_contents($root . '/views/admin/organization/inscription_settings.php');
        self::assertStringContainsString('Recrutement : uniquement sur Discord', $inscription);
        $enlist = (string) file_get_contents($root . '/app/Controllers/Web/EnlistmentController.php');
        self::assertStringContainsString('discordOnlyResponse', $enlist);
        self::assertFileExists($root . '/views/enlistment/discord_only.php');
        $shell = (string) file_get_contents($root . '/views/admin/effectifs_workspace/shell.php');
        self::assertStringContainsString('Importer un tableur', $shell);
    }
}
