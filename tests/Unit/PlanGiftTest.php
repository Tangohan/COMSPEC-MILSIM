<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PlanGift;
use PHPUnit\Framework\TestCase;

final class PlanGiftTest extends TestCase
{
    public function testActiveGiftReadFromSettingsUntilItsEndDate(): void
    {
        $now = strtotime('2026-10-13 12:00:00');
        $settings = ['plan_gift' => ['gift_id' => 7, 'plan_slug' => 'Pro', 'ends_at' => '2026-11-12 23:59:59']];
        self::assertSame(
            ['gift_id' => 7, 'plan_slug' => 'pro', 'ends_at' => '2026-11-12 23:59:59'],
            PlanGift::activeFromSettings($settings, $now)
        );
        self::assertSame('pro', PlanGift::activeFromSettings(json_encode($settings), $now)['plan_slug'] ?? null, 'JSON brut');
        self::assertNull(PlanGift::activeFromSettings($settings, strtotime('2026-11-13 00:00:00')), 'offre terminée');

        $forever = ['plan_gift' => ['gift_id' => 8, 'plan_slug' => 'elite', 'ends_at' => null]];
        self::assertSame('elite', PlanGift::activeFromSettings($forever, $now)['plan_slug'] ?? null, 'sans limite');

        self::assertNull(PlanGift::activeFromSettings(['plan_gift' => null], $now), 'offre retirée');
        self::assertNull(PlanGift::activeFromSettings([], $now));
        self::assertNull(PlanGift::activeFromSettings('', $now));
        self::assertNull(PlanGift::activeFromSettings(null, $now));
    }

    public function testEffectivePlanPrefersRunningGiftThenFallsBackToBasePlan(): void
    {
        $now = strtotime('2026-10-13 12:00:00');
        $gift = ['gift_id' => 3, 'plan_slug' => 'pro', 'ends_at' => '2026-11-12 23:59:59'];
        $free = ['plan_slug' => 'free', 'subscription_status' => 'none', 'settings' => json_encode(['plan_gift' => $gift])];
        self::assertSame('pro', PlanGift::effectivePlanSlug($free, $now), 'formule offerte en cours');
        self::assertSame('free', PlanGift::effectivePlanSlug($free, strtotime('2026-11-13 00:00:00')), 'offre expirée');

        $revoked = ['plan_slug' => 'free', 'subscription_status' => 'none', 'settings' => json_encode(['plan_gift' => null])];
        self::assertSame('free', PlanGift::effectivePlanSlug($revoked, $now), 'offre retirée');

        $paid = ['plan_slug' => 'standard', 'subscription_status' => 'active', 'settings' => json_encode(['plan_gift' => $gift])];
        self::assertSame('pro', PlanGift::effectivePlanSlug($paid, $now), 'l’offre prime sur l’abonnement payant');
        $paid['settings'] = null;
        self::assertSame('standard', PlanGift::effectivePlanSlug($paid, $now));

        $trial = ['plan_slug' => 'free', 'subscription_status' => 'none', 'settings' => json_encode(['founder_trial_ends_at' => '2026-10-20'])];
        self::assertSame('pro', PlanGift::effectivePlanSlug($trial, $now), 'essai fondateur');
        self::assertSame('free', PlanGift::effectivePlanSlug(['plan_slug' => ''], $now));
    }

    public function testDirectoryAndFicheShowTheEffectivePlan(): void
    {
        $root = dirname(__DIR__, 2);
        $repo = (string) file_get_contents($root . '/app/Repositories/TenantRepository.php');
        self::assertStringContainsString("'effective_plan_slug' => \\App\\Support\\PlanGift::effectivePlanSlug(\$row)", $repo);
        self::assertStringContainsString('t.settings,', $repo);
        $index = (string) file_get_contents($root . '/views/admin/system/tenants_index.php');
        self::assertStringContainsString("\$t['effective_plan_slug']", $index);
        $fiche = (string) file_get_contents($root . '/views/admin/system/tenants_plan_form.php');
        self::assertStringContainsString('PlanGift::effectivePlanSlug($tenant)', $fiche);
        $gate = (string) file_get_contents($root . '/app/Services/Platform/FeatureGateService.php');
        self::assertStringContainsString('return PlanGift::effectivePlanSlug($tenant);', $gate);
    }

    public function testEndDateFromDurationOrCustomDate(): void
    {
        $now = strtotime('2026-10-13 12:00:00');
        self::assertSame('2026-11-12 23:59:59', PlanGift::resolveEndsAt(30, '', $now));
        self::assertNull(PlanGift::resolveEndsAt(0, '', $now), 'sans limite');
        self::assertSame('2027-01-31 23:59:59', PlanGift::resolveEndsAt(30, '2027-01-31', $now), 'la date saisie prime');
        self::assertFalse(PlanGift::resolveEndsAt(30, '2026-10-01', $now), 'date passée');
        self::assertFalse(PlanGift::resolveEndsAt(30, '31/01/2027', $now), 'format invalide');
    }

    public function testCopyAndMessageFollowTheReadersLanguage(): void
    {
        $fr = PlanGift::copy('fr', 'Task Force Alpha', 'Pro', '2026-11-12 23:59:59');
        self::assertSame('Une formule est offerte à Task Force Alpha', $fr['subject']);
        self::assertStringContainsString('12/11/2026', $fr['duration']);

        $en = PlanGift::copy('en-GB', 'Task Force Alpha', 'Pro', '2026-11-12 23:59:59');
        self::assertSame('A plan has been gifted to Task Force Alpha', $en['subject']);
        self::assertStringContainsString('November 12, 2026', $en['duration']);
        self::assertSame(array_keys($fr), array_keys($en), 'mêmes textes dans les deux langues');

        self::assertSame('Included at no cost, with no end date.', PlanGift::copy('en', 'X', 'Pro', null)['duration']);

        self::assertSame('Bravo', PlanGift::message('Bravo', 'Well done', 'fr'));
        self::assertSame('Well done', PlanGift::message('Bravo', 'Well done', 'en'));
        self::assertSame('Bravo', PlanGift::message('Bravo', '', 'en'), 'repli sur le français');
        self::assertSame('Well done', PlanGift::message('', 'Well done', 'fr'), 'repli sur l’anglais');
    }

    public function testEmailTemplateRendersBothLanguages(): void
    {
        if (!function_exists('email_html_layout')) {
            require_once dirname(__DIR__, 2) . '/app/Support/helpers.php';
        }
        $render = static function (string $lang): array {
            $displayName = 'Viper';
            $tenantName = 'Task Force Alpha';
            $planName = 'Pro';
            $endsAt = null;
            $adminMessage = 'Merci <b>!</b>';
            $manageUrl = 'https://example.test/back-office';

            return include dirname(__DIR__, 2) . '/views/emails/plan_gift_granted.php';
        };
        $fr = $render('fr');
        self::assertStringContainsString('L’équipe Athena offre la formule Pro à Task Force Alpha.', $fr['html']);
        self::assertStringContainsString('Merci &lt;b&gt;!&lt;/b&gt;', $fr['html'], 'message échappé');
        self::assertStringContainsString('Bonjour Viper,', $fr['text']);

        $en = $render('en');
        self::assertStringContainsString('The Athena team is offering the Pro plan to Task Force Alpha.', $en['html']);
        self::assertStringContainsString('translate="no"', $en['html']);
        self::assertStringContainsString('Open management: https://example.test/back-office', $en['text']);
    }

    public function testRoutesAndMenu(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertMatchesRegularExpression("#get\\('/admin/formules-offertes', .*SystemAdminMiddleware#", $routes);
        self::assertMatchesRegularExpression("#post\\('/admin/formules-offertes/\\{id\\}/retirer', .*SystemAdminMiddleware#", $routes);
        self::assertStringContainsString("'/compte/formule-offerte/{id}/vu'", $routes);
        self::assertStringContainsString("'admin/formules-offertes'", (string) file_get_contents($root . '/views/partials/platform_admin_sidebar.php'));
        self::assertStringContainsString('plan_gift_notice_modal.php', (string) file_get_contents($root . '/views/layout/main.php'));
        self::assertStringContainsString('plan_gift_migration.php', (string) file_get_contents($root . '/run-migrations.php'));
    }
}
