<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\DataCampaignBlocks;
use App\Support\DataCampaignOrigin;
use PHPUnit\Framework\TestCase;

/**
 * Préambule de la fiabilisation : origine (Athena chaque année, forcée, organisation), dates dans la langue et le fuseau,
 * états, confidentialité du demandeur, périmètre, cas dégradés, et rendu réel de la vue. Fonctions pures, sans base ni mock.
 */
final class DataCampaignOriginTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    /** @return array<string, mixed> */
    private static function campaign(array $over = []): array
    {
        return $over + [
            'id' => 3, 'title' => 'Fiabilisation 2026', 'campaign_year' => 2026, 'auto_created' => 1, 'auto_year' => 2026,
            'forced_on' => null, 'created_by' => null, 'opens_on' => '2026-11-15', 'opened_at' => '2026-11-15 08:00:00',
            'due_on' => '2026-12-31', 'unit_ids' => [], 'blocks' => DataCampaignBlocks::keys(),
        ];
    }

    /** @return array<string, mixed> */
    private static function ctx(array $over = []): array
    {
        return $over + ['lang' => 'fr', 'today' => '2026-11-20', 'tenant_name' => 'Task Force Phoenix', 'timezone' => 'Europe/Paris'];
    }

    public function testThreeOrigins(): void
    {
        self::assertSame(DataCampaignOrigin::ANNUAL, DataCampaignOrigin::origin(self::campaign()));
        self::assertSame(DataCampaignOrigin::FORCED, DataCampaignOrigin::origin(self::campaign(['forced_on' => '2026-11-10'])));
        self::assertSame(DataCampaignOrigin::ORGANIZATION, DataCampaignOrigin::origin(self::campaign(['auto_created' => 0, 'auto_year' => null, 'created_by' => 12])));
        self::assertSame(DataCampaignOrigin::ORGANIZATION, DataCampaignOrigin::origin(self::campaign(['auto_created' => 0, 'created_by' => null])));
    }

    public function testAnnualSentenceAndSummaryFrench(): void
    {
        $d = DataCampaignOrigin::describe(self::campaign(), self::ctx());
        self::assertSame('Cette vérification est demandée par Athena, la plateforme, comme chaque année.', $d['who']);
        self::assertSame('Demandé par Athena · du 15/11 au 31/12/2026 · ~2 min', $d['summary']);
        self::assertSame('du 15 novembre au 31 décembre 2026', $d['period']);
        self::assertSame(47, $d['days_total']);
        self::assertSame(41, $d['days_left']);
        self::assertSame('dc-3', $d['key']);
    }

    public function testForcedSentenceNamesTheOrganization(): void
    {
        $d = DataCampaignOrigin::describe(self::campaign(['forced_on' => '2026-11-10']), self::ctx());
        self::assertStringContainsString('demandée par Athena, la plateforme, à la demande de l’organisation Task Force Phoenix', $d['who']);
        self::assertSame('Athena, à la demande de Task Force Phoenix', $d['who_short']);
    }

    public function testOrganizationDoesNotNameAPersonByDefault(): void
    {
        $c = self::campaign(['auto_created' => 0, 'created_by' => 12]);
        $d = DataCampaignOrigin::describe($c, self::ctx(['requester_name' => 'Jean Dupont']));
        self::assertStringContainsString('par l’organisation Task Force Phoenix, ouverte par un responsable de l’organisation.', $d['who']);
        self::assertStringNotContainsString('Dupont', implode(' ', $d['bullets']) . $d['summary']);
    }

    public function testOrganizationNamesThePersonOnlyWhenAllowed(): void
    {
        $c = self::campaign(['auto_created' => 0, 'created_by' => 12]);
        $d = DataCampaignOrigin::describe($c, self::ctx(['requester_name' => 'Jean Dupont', 'name_requester' => true]));
        self::assertStringContainsString('ouverte par Jean Dupont, responsable de l’organisation', $d['who']);
        $deleted = DataCampaignOrigin::describe($c, self::ctx(['requester_name' => 'Compte supprimé', 'name_requester' => true]));
        self::assertStringNotContainsString('supprimé', $deleted['who']);
        self::assertStringContainsString('un responsable de l’organisation', $deleted['who']);
        $blank = DataCampaignOrigin::describe($c, self::ctx(['requester_name' => '   ', 'name_requester' => true]));
        self::assertStringContainsString('un responsable de l’organisation', $blank['who']);
    }

    public function testOrganizationWithoutNameFallsBackToYourOrganization(): void
    {
        $d = DataCampaignOrigin::describe(self::campaign(['auto_created' => 0]), self::ctx(['tenant_name' => '  ']));
        self::assertStringContainsString('demandée par votre organisation', $d['who']);
        self::assertSame('Demandé par votre organisation · du 15/11 au 31/12/2026 · ~2 min', $d['summary']);
    }

    public function testEnglishTextAndDates(): void
    {
        $d = DataCampaignOrigin::describe(self::campaign(), self::ctx(['lang' => 'en']));
        self::assertSame('This check is requested by Athena, the platform, as every year.', $d['who']);
        self::assertSame('from November 15 to December 31, 2026', $d['period']);
        self::assertSame('Requested by Athena · from Nov 15 to Dec 31, 2026 · ~2 min', $d['summary']);
        self::assertSame('Ask a question', $d['contact_link']);
    }

    public function testPeriodAcrossTwoYearsAndFirstOfMonth(): void
    {
        self::assertSame('du 1er décembre 2026 au 15 janvier 2027', DataCampaignOrigin::period('2026-12-01', '2027-01-15'));
        self::assertSame('du 01/12/2026 au 15/01/2027', DataCampaignOrigin::period('2026-12-01', '2027-01-15', 'fr', true));
        self::assertSame('from December 1, 2026 to January 15, 2027', DataCampaignOrigin::period('2026-12-01', '2027-01-15', 'en'));
    }

    public function testMissingDates(): void
    {
        self::assertSame('', DataCampaignOrigin::period('', ''));
        self::assertSame('jusqu’au 31 décembre 2026', DataCampaignOrigin::period('', '2026-12-31'));
        self::assertSame('à partir du 15 novembre 2026', DataCampaignOrigin::period('2026-11-15', ''));
        $d = DataCampaignOrigin::describe(self::campaign(['opens_on' => null, 'opened_at' => null, 'due_on' => null, 'title' => '']), self::ctx());
        self::assertSame(DataCampaignOrigin::UNDATED, $d['state']);
        self::assertSame('Aucune date n’est indiquée pour cette campagne.', $d['when']);
        self::assertSame('Demandé par Athena · ~2 min', $d['summary']);
        self::assertNull($d['days_left']);
        self::assertSame('', $d['title']);
        $junk = DataCampaignOrigin::describe(self::campaign(['opens_on' => '0000-00-00', 'opened_at' => '0000-00-00 00:00:00', 'due_on' => '2026-02-31']), self::ctx());
        self::assertSame('', $junk['period']);
    }

    public function testStartFallsBackToOpenedAtInTheCommunityTimezone(): void
    {
        $default = date_default_timezone_get();
        date_default_timezone_set('UTC');
        try {
            $c = self::campaign(['opens_on' => null, 'opened_at' => '2026-11-14 23:30:00']);
            self::assertSame('2026-11-15', DataCampaignOrigin::describe($c, self::ctx(['timezone' => 'Europe/Paris']))['start_iso']);
            self::assertSame('2026-11-14', DataCampaignOrigin::describe($c, self::ctx(['timezone' => 'UTC']))['start_iso']);
            self::assertSame('2026-11-15', DataCampaignOrigin::describe($c, self::ctx(['timezone' => 'Nope/Invalid']))['start_iso']);
        } finally {
            date_default_timezone_set($default);
        }
    }

    public function testTodayFollowsTheCommunityTimezone(): void
    {
        $now = new \DateTimeImmutable('2026-12-31 23:30:00', new \DateTimeZone('UTC'));
        $paris = DataCampaignOrigin::describe(self::campaign(), ['now' => $now, 'timezone' => 'Europe/Paris']);
        self::assertSame(DataCampaignOrigin::OVERDUE, $paris['state']);
        $utc = DataCampaignOrigin::describe(self::campaign(), ['now' => $now, 'timezone' => 'UTC']);
        self::assertSame(DataCampaignOrigin::LAST_DAY, $utc['state']);
    }

    public function testStates(): void
    {
        self::assertSame(DataCampaignOrigin::UPCOMING, DataCampaignOrigin::state('2026-12-01', '2027-01-15', '2026-11-20'));
        self::assertSame(DataCampaignOrigin::ONGOING, DataCampaignOrigin::state('2026-11-15', '2026-12-31', '2026-11-20'));
        self::assertSame(DataCampaignOrigin::LAST_DAY, DataCampaignOrigin::state('2026-11-15', '2026-12-31', '2026-12-31'));
        self::assertSame(DataCampaignOrigin::OVERDUE, DataCampaignOrigin::state('2026-11-15', '2026-12-31', '2027-01-02'));
        self::assertSame(DataCampaignOrigin::UNDATED, DataCampaignOrigin::state('2026-11-15', '', '2026-11-20'));
        self::assertSame('Dernier jour', DataCampaignOrigin::stateLabel(DataCampaignOrigin::LAST_DAY));
        self::assertSame('Upcoming', DataCampaignOrigin::stateLabel(DataCampaignOrigin::UPCOMING, 'en'));
    }

    public function testOverdueIsInsistentButNeverBlocking(): void
    {
        $d = DataCampaignOrigin::describe(self::campaign(['due_on' => '2026-11-14']), self::ctx());
        self::assertSame(DataCampaignOrigin::OVERDUE, $d['state']);
        self::assertTrue($d['overdue']);
        self::assertStringContainsString('dépassée de 6 jours', $d['when']);
        self::assertStringContainsString('rien n’est bloqué', $d['when']);
        self::assertStringContainsString('dès maintenant', $d['when']);
    }

    public function testUpcomingAndLastDayTexts(): void
    {
        $up = DataCampaignOrigin::describe(self::campaign(['opens_on' => '2026-12-01']), self::ctx());
        self::assertSame(DataCampaignOrigin::UPCOMING, $up['state']);
        self::assertStringContainsString('pas encore ouverte', $up['when']);
        $last = DataCampaignOrigin::describe(self::campaign(['due_on' => '2026-11-20']), self::ctx());
        self::assertStringContainsString('dernier jour', $last['when']);
    }

    public function testScopeNamesUnitsAndCapsTheList(): void
    {
        $d = DataCampaignOrigin::describe(self::campaign(['unit_ids' => [3, 7]]), self::ctx(['unit_names' => ['1re Compagnie', 'Section Alpha']]));
        self::assertSame('Cette campagne concerne les membres de : 1re Compagnie, Section Alpha.', $d['scope']);
        $names = array_map(static fn (int $i): string => 'Unité ' . $i, range(1, 9));
        $many = DataCampaignOrigin::describe(self::campaign(['unit_ids' => range(1, 9)]), self::ctx(['unit_names' => $names]));
        self::assertStringContainsString('Unité 6 et 3 autres.', $many['scope']);
        $unnamed = DataCampaignOrigin::describe(self::campaign(['unit_ids' => [3]]), self::ctx());
        self::assertStringContainsString('certaines unités', $unnamed['scope']);
        self::assertSame('', DataCampaignOrigin::describe(self::campaign(), self::ctx())['scope']);
    }

    public function testBulletsCoverWhoWhyWhatWhenAndPrivacy(): void
    {
        $d = DataCampaignOrigin::describe(self::campaign(), self::ctx(['block_labels' => ['Identité', 'Heures']]));
        self::assertCount(5, $d['bullets']);
        self::assertStringContainsString('Vous relirez : Identité, Heures.', $d['bullets'][2]);
        self::assertStringContainsString('Seules les informations de votre propre fiche', $d['bullets'][2]);
        self::assertStringContainsString('Environ 2 minutes', $d['bullets'][3]);
        self::assertStringContainsString('pas par les autres membres', $d['bullets'][4]);
        self::assertStringContainsString('Rien n’est modifié sans validation', $d['bullets'][4]);
    }

    public function testVeryLongAccentuatedNamesAreKeptWhole(): void
    {
        $org = 'Association « Les Chevaliers d’Émeraude » de la Haute-Garonne et des Pyrénées-Atlantiques';
        $d = DataCampaignOrigin::describe(self::campaign(['auto_created' => 0]), self::ctx(['tenant_name' => $org]));
        self::assertStringContainsString($org, $d['who']);
        self::assertStringContainsString($org, $d['summary']);
    }

    /** Rend la vraie vue de la page. */
    private static function render(array $campaign, array $ctx, string $step = 'accueil', array $others = []): string
    {
        $blocks = [];
        foreach (DataCampaignBlocks::keys() as $key) {
            $blocks[$key] = ['key' => $key, 'label' => DataCampaignBlocks::label($key), 'lead' => 'Lead', 'state' => '', 'portrait' => null, 'rows' => [['label' => 'Grade', 'value' => 'Caporal-chef']], 'notice' => '', 'links' => [], 'flags' => [], 'fields' => [], 'changed_since_answer' => false];
        }
        $vars = [
            'schemaReady' => true, 'campaign' => $campaign, 'member' => ['confirmed_at' => ''],
            'campaignView' => ['blocks' => $blocks, 'answered' => 0, 'total' => count($blocks), 'complete' => false, 'flags' => [], 'confirmed_at' => '', 'changed' => []],
            'step' => $step, 'history' => [], 'unitFollow' => null, 'csrfToken' => 'x', 'comingFromLogin' => false, 'today' => (string) $ctx['today'],
            'preamble' => DataCampaignOrigin::describe($campaign, $ctx), 'otherCampaigns' => $others,
        ];

        return (static function () use ($vars): string {
            extract($vars, EXTR_SKIP);
            ob_start();
            require self::ROOT . '/views/admin/member_situation/data_campaign.php';

            return (string) ob_get_clean();
        })();
    }

    public function testViewShowsOriginDatesAndNoEyebrow(): void
    {
        $html = self::render(self::campaign(), self::ctx());
        self::assertStringContainsString('Pourquoi cette demande ?', $html);
        self::assertStringContainsString('Cette vérification est demandée par Athena, la plateforme, comme chaque année.', $html);
        self::assertStringContainsString('du 15 novembre au 31 décembre 2026', $html);
        self::assertStringContainsString('Demandé par Athena · du 15/11 au 31/12/2026 · ~2 min', $html);
        self::assertStringContainsString('data-dcm-pre="dc-3"', $html);
        self::assertStringContainsString('Poser une question', $html);
        self::assertStringContainsString('jnet/courrier/nouveau', $html);
        self::assertStringNotContainsString('eyebrow', strtolower($html));
        self::assertStringNotContainsString('dcm-reassure', $html);
    }

    public function testViewOverdueShowsBadgeAndOtherCampaigns(): void
    {
        $other = DataCampaignOrigin::describe(self::campaign(['id' => 4, 'auto_created' => 0, 'title' => 'Mise à jour des qualifications']), self::ctx());
        $html = self::render(self::campaign(['due_on' => '2026-11-14']), self::ctx(), 'accueil', [$other]);
        self::assertStringContainsString('Date de fin dépassée', $html);
        self::assertStringContainsString('Autres campagnes ouvertes', $html);
        self::assertStringContainsString('Mise à jour des qualifications', $html);
        self::assertStringContainsString('Demandé par Task Force Phoenix', $html);
    }

    public function testViewBlockStepHasFoldedReminder(): void
    {
        $html = self::render(self::campaign(['auto_created' => 0, 'created_by' => 12]), self::ctx(), 'identity');
        self::assertStringContainsString('dcm-pre--mini', $html);
        self::assertStringNotContainsString('data-dcm-pre=', $html);
        self::assertStringNotContainsString('id="dcm-pre-mini" open', $html);
        self::assertStringContainsString('ouverte par un responsable de l’organisation', $html);
    }

    public function testStylesheetKeepsTextAtLeast13Pixels(): void
    {
        $css = (string) file_get_contents(self::ROOT . '/public/assets/css/data-campaign-member.css');
        preg_match_all('/\.dcm-(?:pre|others)[^{]*\{[^}]*font-size:\s*([\d.]+)px/', $css, $m);
        self::assertNotSame([], $m[1]);
        foreach ($m[1] as $px) {
            self::assertGreaterThanOrEqual(13.0, (float) $px);
        }
    }

    public function testReminderEmailRepeatsOriginAndDates(): void
    {
        if (!function_exists('email_html_layout')) {
            self::markTestSkipped('Gabarit e-mail indisponible.');
        }
        $d = DataCampaignOrigin::describe(self::campaign(), self::ctx());
        $vars = ['displayName' => 'Mathieu', 'tenantName' => 'X', 'year' => 2026, 'dueOn' => '2026-12-31', 'pageUrl' => 'https://example.test/p', 'originLine' => $d['who'], 'periodLine' => $d['period'], 'stateLine' => ''];
        $mail = (static function () use ($vars): array {
            extract($vars, EXTR_SKIP);

            return require self::ROOT . '/views/emails/data_campaign_reminder.php';
        })();
        self::assertStringContainsString($d['who'], $mail['text']);
        self::assertStringContainsString('Du 15 novembre au 31 décembre 2026', $mail['text']);
        self::assertStringContainsString('Du 15 novembre au 31 décembre 2026', $mail['html']);
    }
}
