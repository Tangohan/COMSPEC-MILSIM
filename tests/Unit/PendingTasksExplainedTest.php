<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\BackOffice\Menu\BackOfficeMenuBadges;
use App\Services\BackOffice\Menu\BadgeProvider;
use App\Services\BackOffice\Menu\Badges\PlanEndBadge;
use App\Services\BackOffice\Menu\Badges\PlanEndTaskCopy;
use App\Services\BackOffice\Menu\PendingTaskDetail;
use App\Services\BackOffice\Menu\PendingTaskDetailProvider;
use App\Services\BackOffice\Menu\PendingTaskExplainer;
use PHPUnit\Framework\TestCase;

/**
 * Bloc « Tâches en attente » du tableau de bord : chaque tâche déclarée dans config/back_office_menu.php s'explique
 * (quoi, conséquence, action, bouton), la tâche « formule » dit de quelle formule il s'agit, quand et ce qui s'arrête,
 * et une tâche sans explication s'affiche comme avant.
 */
final class PendingTasksExplainedTest extends TestCase
{
    private const NOW = 1_790_000_000;

    /** @return array<string, mixed> */
    private static function config(): array
    {
        $cfg = require dirname(__DIR__, 2) . '/config/back_office_menu.php';

        return is_array($cfg) ? $cfg : [];
    }

    private static function at(int $deltaDays): string
    {
        return date('c', self::NOW + $deltaDays * 86400);
    }

    private static function planName(string $slug): string
    {
        return ['pro' => 'Pro', 'standard' => 'Standard', 'free' => 'Gratuite'][$slug] ?? $slug;
    }

    private static function hasAtak(string $slug): bool
    {
        return in_array($slug, ['pro', 'standard'], true);
    }

    /**
     * @param array<string, mixed> $tenant
     */
    private static function formula(array $tenant, string $locale, int $graceDays = 0, string $owner = ''): ?PendingTaskDetail
    {
        return PlanEndTaskCopy::compose(
            $tenant + ['name' => 'Task Force Alpha'],
            $locale,
            static fn (string $slug): string => self::planName($slug),
            static fn (string $slug): bool => self::hasAtak($slug),
            $graceDays,
            $owner,
            self::NOW
        );
    }

    /** @return array<string, mixed> */
    private static function trial(int $endsInDays): array
    {
        return ['plan_slug' => 'free', 'subscription_status' => 'none', 'settings' => json_encode(['founder_trial_ends_at' => self::at($endsInDays)])];
    }

    /** @return array<string, mixed> */
    private static function gift(int $endsInDays): array
    {
        return ['plan_slug' => 'free', 'subscription_status' => 'none', 'settings' => ['plan_gift' => [
            'gift_id' => 4,
            'plan_slug' => 'standard',
            'ends_at' => date('Y-m-d H:i:s', self::NOW + $endsInDays * 86400),
        ]]];
    }

    public function testEveryDeclaredTaskExplainsItselfWithAnAction(): void
    {
        $badges = new BackOfficeMenuBadges(self::config());
        $explainer = new PendingTaskExplainer($badges);
        $declared = $badges->declared();
        self::assertNotSame([], $declared);

        foreach ($declared as $key => $def) {
            $selfComposed = class_exists($def['provider']) && is_subclass_of($def['provider'], PendingTaskDetailProvider::class);
            if ($selfComposed) {
                continue;
            }
            foreach ([1, 4] as $count) {
                $d = $explainer->fromConfig($key, $count, false);
                self::assertNotNull($d, $key . ' : explication absente de config/back_office_menu.php');
                self::assertNotSame('', $d->explain, $key);
                self::assertNotSame('', $d->consequence, $key);
                self::assertNotSame('', $d->action, $key);
                self::assertNotSame('', $d->button, $key);
                self::assertSame($def['path'], $d->path, $key . ' : le bouton mène à la page de la pastille');
                self::assertStringNotContainsString('%d', $d->explain, $key);
                foreach ([$d->explain, $d->consequence, $d->action, $d->button, $d->who, $d->help] as $text) {
                    foreach (['slug', 'RBAC', 'ORBAT', 'tenant'] as $jargon) {
                        self::assertStringNotContainsString($jargon, $text, $key . ' : jargon interne');
                    }
                }
            }
        }
        // La tâche « formule » compose ses textes elle-même.
        self::assertTrue(is_subclass_of(PlanEndBadge::class, PendingTaskDetailProvider::class));
    }

    public function testCountIsWrittenInTheExplanationAndSingularIsUsedForOne(): void
    {
        $explainer = new PendingTaskExplainer(new BackOfficeMenuBadges(self::config()));
        $one = $explainer->fromConfig('candidatures', 1, false);
        $many = $explainer->fromConfig('candidatures', 3, false);
        self::assertNotNull($one);
        self::assertNotNull($many);
        self::assertStringStartsWith('1 personne', $one->explain);
        self::assertStringStartsWith('3 personnes', $many->explain);
    }

    public function testUrgentWordingReplacesTheNormalOneWhenTheBadgeIsRed(): void
    {
        $explainer = new PendingTaskExplainer(new BackOfficeMenuBadges(self::config()));
        $calm = $explainer->fromConfig('mise_a_niveau', 2, false);
        $red = $explainer->fromConfig('mise_a_niveau', 2, true);
        self::assertNotNull($calm);
        self::assertNotNull($red);
        self::assertNotSame($calm->consequence, $red->consequence);
        self::assertStringContainsString('obligatoire', $red->consequence);
    }

    public function testTaskWithoutDetailKeepsTheOldDisplay(): void
    {
        $cfg = ['badges' => [
            'a' => ['path' => 'back-office/x', 'provider' => 'X', 'any_permissions' => [], 'tone' => 'notif', 'title' => '%d à faire'],
            'b' => ['path' => 'back-office/y', 'provider' => 'Y', 'any_permissions' => [], 'tone' => 'notif', 'title' => '%d autre', 'task' => ['explain' => 'Seulement un texte']],
        ]];
        $explainer = new PendingTaskExplainer(new BackOfficeMenuBadges($cfg));

        self::assertNull($explainer->describe('a', 2, false, 5, 7));
        self::assertNull($explainer->describe('b', 2, false, 5, 7), 'une explication incomplète ne s’affiche pas à moitié');
        self::assertNull($explainer->describe('inconnue', 2, false, 5, 7));
    }

    public function testProviderCanComposeItsOwnDetailAndFallsBackToConfigWhenItHasNone(): void
    {
        $detail = new PendingTaskDetail('Titre', 'Quoi', 'Conséquence', 'Action', 'Bouton', 'back-office/z');
        $provider = new class ($detail) implements BadgeProvider, PendingTaskDetailProvider {
            public function __construct(private ?PendingTaskDetail $detail)
            {
            }

            public function badge(int $tenantId, int $userId): ?array
            {
                return ['count' => 1];
            }

            public function detail(int $tenantId, int $userId, string $locale): ?PendingTaskDetail
            {
                return $this->detail;
            }
        };
        $cfg = ['badges' => ['k' => [
            'path' => 'back-office/p', 'provider' => $provider::class, 'any_permissions' => [], 'tone' => 'notif', 'title' => 't',
            'task' => ['explain' => 'Texte fixe', 'consequence' => 'C', 'action' => 'A', 'button' => 'B'],
        ]]];
        $badges = new BackOfficeMenuBadges($cfg, [$provider::class => $provider]);
        $explainer = new PendingTaskExplainer($badges);

        $d = $explainer->describe('k', 1, false, 5, 7);
        self::assertNotNull($d);
        self::assertSame('Quoi', $d->explain);
        self::assertSame('back-office/z', $d->path);

        $empty = new class implements BadgeProvider, PendingTaskDetailProvider {
            public function badge(int $tenantId, int $userId): ?array
            {
                return ['count' => 1];
            }

            public function detail(int $tenantId, int $userId, string $locale): ?PendingTaskDetail
            {
                return null;
            }
        };
        $cfg['badges']['k']['provider'] = $empty::class;
        $fallback = (new PendingTaskExplainer(new BackOfficeMenuBadges($cfg, [$empty::class => $empty])))->describe('k', 1, false, 5, 7);
        self::assertNotNull($fallback);
        self::assertSame('Texte fixe', $fallback->explain);
        self::assertSame('back-office/p', $fallback->path);
    }

    public function testFormulaEndingSoonNamesThePlanTheDateAndWhatStopsNext(): void
    {
        $d = self::formula(self::trial(5), 'fr', 7);
        self::assertNotNull($d);
        $date = date('d/m/Y', strtotime(self::at(5)));
        self::assertStringContainsString('Votre essai fondateur (formule « Pro »)', $d->title);
        self::assertStringContainsString('se termine le ' . $date, $d->title);
        self::assertStringContainsString('dans 5 jours', $d->title);
        self::assertStringContainsString('Ce n’est pas un paiement qui a échoué', $d->explain);
        self::assertStringContainsString('photos', $d->consequence);
        self::assertStringContainsString("(période de grâce). Ensuite", $d->consequence);
        self::assertStringContainsString('payante', $d->action);
        self::assertSame(PlanEndTaskCopy::PAGE, $d->path);
        self::assertSame('platform/upgrade', $d->path);
        self::assertSame('Choisir une formule payante', $d->button);
        self::assertTrue($d->localized);
        self::assertSame('fr', $d->lang);
        self::assertStringContainsString('propriétaire', $d->who);
        self::assertNotSame('', $d->help);
    }

    public function testGiftedPlanEndingSoonSaysTheOfferSimplyEnds(): void
    {
        $fr = self::formula(self::gift(5), 'fr');
        self::assertNotNull($fr);
        self::assertStringContainsString('Votre formule offerte « Standard » se termine le', $fr->title);
        self::assertStringContainsString('formule offerte', $fr->explain);
        self::assertStringContainsString('Ce n’est pas un paiement qui a échoué', $fr->explain);

        $en = self::formula(self::gift(5), 'en');
        self::assertNotNull($en);
        self::assertStringStartsWith('Your gifted “Standard” plan ends on ' . date('F j, Y', strtotime(self::at(5))), $en->title);
        self::assertStringContainsString('in 5 days', $en->title);
        self::assertStringContainsString('No payment has failed: the offer simply comes to an end', $en->explain);
        self::assertSame('Choose a paid plan', $en->button);
        self::assertSame('en', $en->lang);
    }

    /** @return array<string, mixed> */
    private static function paid(int $endsInDays): array
    {
        return ['plan_slug' => 'pro', 'subscription_status' => 'active', 'settings' => [
            'manual_billing' => ['reference' => 'ATH-7KQ2XM', 'plan_slug' => 'pro', 'interval' => 'monthly', 'ends_at' => date('Y-m-d H:i:s', self::NOW + $endsInDays * 86400)],
        ]];
    }

    public function testPaidPlanEndingSoonAsksToRenewAndNamesTheOwner(): void
    {
        $d = self::formula(self::paid(2), 'fr', 0, 'Camille Durand');
        self::assertNotNull($d);
        self::assertStringStartsWith('Votre formule « Pro » se termine le ', $d->title);
        self::assertStringContainsString('dans 2 jours', $d->title);
        self::assertStringContainsString('Elle ne se renouvelle pas toute seule', $d->explain);
        self::assertSame('Renouveler ma formule', $d->button);
        self::assertStringContainsString('Camille Durand', $d->who);

        $en = self::formula(self::paid(1), 'en');
        self::assertNotNull($en);
        self::assertStringContainsString('(in 1 day)', $en->title);
        self::assertSame('Renew my plan', $en->button);
    }

    public function testPaidPlanLapsedWithoutGraceAsksToRenew(): void
    {
        $d = self::formula(self::paid(-2), 'fr');
        self::assertNotNull($d);
        self::assertSame('Votre formule « Pro » est terminée depuis 2 jours', $d->title);
        self::assertStringContainsString('payée par PayPal', $d->explain);
        self::assertSame('Renouveler ma formule', $d->button);
    }

    public function testFormulaLapsedInGracePeriodGivesTheGraceEndDate(): void
    {
        $tenant = self::gift(-3);
        $d = self::formula($tenant, 'fr', 7);
        self::assertNotNull($d);
        $graceEnd = date('d/m/Y', self::NOW + 4 * 86400);
        self::assertStringContainsString('Votre formule offerte « Standard » est terminée depuis 3 jours', $d->title);
        self::assertStringContainsString('période de grâce jusqu’au ' . $graceEnd, $d->title);
        self::assertStringContainsString('encore 4 jours', $d->explain);
        self::assertStringContainsString('Après le ' . $graceEnd, $d->consequence);
        self::assertStringContainsString('Avant le ' . $graceEnd, $d->action);

        $en = self::formula($tenant, 'en', 7);
        self::assertNotNull($en);
        self::assertStringContainsString('ended 3 days ago (grace period until ' . date('F j, Y', self::NOW + 4 * 86400) . ')', $en->title);
        self::assertStringContainsString('No payment failed', $en->explain);
        self::assertStringContainsString('4 days left', $en->explain);
    }

    public function testFormulaLapsedForTenDaysHasNoGraceAndSaysWhatIsStopped(): void
    {
        $d = self::formula(self::trial(-10), 'fr', 7);
        self::assertNotNull($d);
        self::assertStringContainsString('Votre essai fondateur (formule « Pro ») est terminé depuis 10 jours', $d->title);
        self::assertStringNotContainsString('grâce', $d->title);
        self::assertStringContainsString('revenue à sa formule habituelle', $d->explain);
        self::assertStringContainsString('photos', $d->consequence);
        self::assertStringContainsString('sont arrêtées', $d->consequence);

        $en = self::formula(self::trial(-10), 'en', 7);
        self::assertNotNull($en);
        self::assertSame('Your founder trial (“Pro” plan) ended 10 days ago', $en->title);
        self::assertStringContainsString('back on its regular plan', $en->explain);
    }

    public function testFormulaLapsedLongAgoOrStillRunningIsNotATask(): void
    {
        self::assertNull(self::formula(self::trial(-20), 'fr', 7), 'terminée depuis plus de 14 jours : plus de tâche');
        self::assertNull(self::formula(self::trial(30), 'fr', 7), 'se termine dans plus de 7 jours : pas encore de tâche');
        self::assertNull(self::formula(['plan_slug' => 'free', 'settings' => []], 'fr', 7));
    }

    public function testFormulaEndedTodayDoesNotSayZeroDays(): void
    {
        $tenant = ['plan_slug' => 'free', 'subscription_status' => 'none', 'settings' => json_encode(['founder_trial_ends_at' => date('c', self::NOW - 3600)])];
        $d = self::formula($tenant, 'fr');
        self::assertNotNull($d);
        self::assertStringContainsString('s’est terminé aujourd’hui', $d->title);
        self::assertStringNotContainsString('0 jour', $d->title);
    }

    public function testFormulaTaskStaysReservedToThoseWhoManageTheCommunity(): void
    {
        $badges = new BackOfficeMenuBadges(self::config());
        $all = static fn (string $p): bool => true;
        $none = static fn (string $p): bool => false;
        self::assertNotContains('formule', $badges->visibleKeys($none, $all));
        self::assertContains('formule', $badges->visibleKeys(static fn (string $p): bool => $p === 'admin.organization', $all));
        self::assertContains('formule', $badges->visibleKeys(static fn (string $p): bool => $p === 'admin.access', $all));
        self::assertNotContains('formule', $badges->visibleKeys(static fn (string $p): bool => $p === 'organization.recruitment.manage', $all));
    }

    public function testEveryNewFrenchTextHasAnEnglishTranslation(): void
    {
        $en = require dirname(__DIR__, 2) . '/lang/en/site_auto.php';
        self::assertIsArray($en);
        $texts = ['Si vous ne faites rien', 'Ce qu’il faut faire', 'Qu’est-ce que c’est ?', 'Qui peut le faire ?'];
        foreach (self::config()['badges'] as $def) {
            $task = is_array($def) && is_array($def['task'] ?? null) ? $def['task'] : [];
            foreach ($task as $value) {
                foreach ((array) $value as $text) {
                    $texts[] = str_replace('%d', '%1', (string) $text);
                }
            }
        }
        foreach ($texts as $fr) {
            self::assertArrayHasKey($fr, $en, 'traduction anglaise absente : ' . $fr);
            self::assertNotSame($fr, $en[$fr], $fr);
            self::assertNotSame('', trim((string) $en[$fr]), $fr);
        }
    }
}
