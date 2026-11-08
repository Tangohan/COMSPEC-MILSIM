<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Billing\PlanOfferSummary;
use App\Services\Billing\SubscriptionPlanAdminService;
use App\Services\Billing\SubscriptionPlanFeaturesCatalog as Catalog;
use PHPUnit\Framework\TestCase;

/**
 * Catalogue unique des paramètres de formule : toute clé de features_json lue par le code y figure, les clés
 * « non contrôlées » sont vraiment ignorées par le code, l'enregistrement garde les clés inconnues et « Illimité »
 * enregistre 0. Puis le câblage de l'administration des formules et de la page publique.
 */
final class PlanFeaturesCatalogCoverageTest extends TestCase
{
    private const GATE_METHODS = 'planIntCap|allows|allowsLimitedFeatureModule|allowsWithCatalogDefault|intWithCatalogDefault|intWhenDefined|quotaStatusForFeature|recordQuotaUse|maybeRecordQuotaSoftBlock';

    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private function read(string $path): string
    {
        return (string) file_get_contents(self::root() . '/' . $path);
    }

    /**
     * Clés de features_json lues dans app/ et views/ : 2e argument des contrôles de FeatureGateService, refus
     * PlanFeatureDenial::upgradeView(), boucles sur des constantes, et $features['…'] dans les fichiers qui lisent
     * subscription_plans.features_json (les options d'alertes, autre features_json, sont exclues).
     *
     * @return array<string, list<string>> clé => fichiers
     */
    private static function keysReadByCode(): array
    {
        $files = [];
        foreach (['app', 'views'] as $dir) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::root() . '/' . $dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if (str_ends_with((string) $f, '.php')) {
                    $files[] = str_replace('\\', '/', (string) $f);
                }
            }
        }
        $consts = [];
        $sources = [];
        foreach ($files as $f) {
            $src = (string) file_get_contents($f);
            $sources[$f] = $src;
            // Espaces en tête : le chargeur des tests peut retirer « final ».
            if (preg_match('/^\s*(?:final |abstract |readonly )*(?:class|interface|enum) (\w+)/m', $src, $m)) {
                preg_match_all("/const (\\w+) = '([a-z_]+)';/", $src, $mm, PREG_SET_ORDER);
                foreach ($mm as $c) {
                    $consts[$m[1] . '::' . $c[1]] = $c[2];
                }
            }
        }
        $found = [];
        foreach ($sources as $f => $src) {
            if (str_ends_with($f, 'SubscriptionPlanFeaturesCatalog.php')) {
                continue;
            }
            $cls = preg_match('/^\s*(?:final |abstract |readonly )*class (\w+)/m', $src, $m) ? $m[1] : '';
            $resolve = static function (string $tok) use ($consts, $cls): ?string {
                $tok = trim($tok);
                if (preg_match("/^'([a-z_]+)'$/", $tok, $m)) {
                    return $m[1];
                }
                if (preg_match('/^([\w\x5c]+)::(\w+)$/', $tok, $m)) {
                    $parts = explode('\\', $m[1]);
                    $c = end($parts);
                    $c = in_array($c, ['self', 'static'], true) ? $cls : $c;

                    return $consts[$c . '::' . $m[2]] ?? null;
                }

                return null;
            };
            $tokens = [];
            // 1er argument = communauté ou palier (variable, appel, ternaire), 2e = la clé.
            preg_match_all('/->(?:' . self::GATE_METHODS . ')\(\s*(?:\(int\)\s*)?\$[^,;{}]*?,\s*([^,()]+)/', $src, $mm);
            $tokens = array_merge($tokens, $mm[1]);
            preg_match_all('/recordQuotaLimitReached\([^,]+,[^,]+,\s*([^)]+)\)/', $src, $mm);
            $tokens = array_merge($tokens, $mm[1]);
            preg_match_all('/PlanFeatureDenial::upgradeView\(\s*([^,)]+)/', $src, $mm);
            $tokens = array_merge($tokens, $mm[1]);
            if (preg_match('/->intWithCatalogDefault\([^,]+,\s*\$key\)|->allowsWithCatalogDefault\([^,]+,\s*\$key\)/', $src)) {
                preg_match_all('/foreach \(\[([^\]]+)\] as \$key\)/', $src, $mm);
                foreach ($mm[1] as $list) {
                    $tokens = array_merge($tokens, explode(',', $list));
                }
            }
            if (preg_match('/->intWhenDefined\([^,]+,\s*\$key\)/', $src)) {
                // Plafond choisi par variable : toutes les constantes de la classe valent des clés de formule.
                foreach ($consts as $name => $val) {
                    if (str_starts_with($name, $cls . '::')) {
                        $tokens[] = "'" . $val . "'";
                    }
                }
            }
            if (str_contains($src, 'features_json') && !preg_match('/TenantAlert|AlertPresentation|tenant_alerts/', $src)) {
                preg_match_all('/\$(?:features|f|feat|planFeatures)\[\s*([^\]]+)\]/', $src, $mm);
                $tokens = array_merge($tokens, $mm[1]);
                preg_match_all("/\\['features'\\]\\['([a-z_]+)'\\]/", $src, $mm);
                foreach ($mm[1] as $k) {
                    $tokens[] = "'" . $k . "'";
                }
            }
            foreach ($tokens as $tok) {
                $key = $resolve($tok);
                if ($key !== null) {
                    $found[$key][] = substr($f, strlen(str_replace('\\', '/', self::root())) + 1);
                }
            }
        }
        ksort($found);

        return $found;
    }

    public function testEveryKeyReadByTheCodeIsInTheCatalog(): void
    {
        $read = self::keysReadByCode();
        $missing = array_diff(array_keys($read), array_keys(Catalog::definitions()));
        self::assertSame([], array_values($missing), 'Clés lues par le code mais absentes de SubscriptionPlanFeaturesCatalog : '
            . implode(', ', array_map(static fn (string $k): string => $k . ' (' . implode(', ', array_unique($read[$k])) . ')', $missing)));
        // Le relevé trouve bien les lectures connues (sinon il ne prouverait rien).
        foreach (['atak', 'events', 'training', 'max_members', 'max_training_courses', 'max_active_invite_links', 'max_custom_qualifications', 'registry_featured'] as $known) {
            self::assertArrayHasKey($known, $read, $known);
        }
    }

    public function testUnreadKeysAreReallyIgnoredByTheCode(): void
    {
        $read = array_keys(self::keysReadByCode());
        $unread = array_values(array_diff(array_keys(Catalog::definitions()), $read));
        sort($unread);
        $declared = Catalog::unreadKeys();
        sort($declared);
        self::assertSame($unread, $declared, 'Le drapeau « read » du catalogue doit suivre ce que le code lit vraiment.');
    }

    public function testCatalogEntriesAreTyped(): void
    {
        foreach (Catalog::definitions() as $key => $def) {
            self::assertArrayHasKey($def['group'], Catalog::GROUPS, $key);
            foreach (['label', 'label_en', 'help', 'help_en'] as $f) {
                self::assertNotSame('', trim($def[$f]), $key . '.' . $f);
            }
            self::assertContains($def['type'], ['bool', 'int'], $key);
            if ($def['type'] === 'int') {
                self::assertNotSame('', $def['unit'] ?? '', $key);
                self::assertGreaterThanOrEqual(1, $def['min']);
                self::assertSame(Catalog::QUOTA_MAX, $def['max']);
            }
            foreach (Catalog::defaultsByPlanSlug() as $slug => $defaults) {
                self::assertArrayHasKey($key, $defaults, $slug . ' : ' . $key);
            }
        }
        self::assertSame(count(Catalog::BOOL_FEATURES) + count(Catalog::INT_FEATURES), count(Catalog::definitions()));
    }

    public function testEnglishLabelsAreTheOnesShownToEnglishVisitors(): void
    {
        $dict = require self::root() . '/lang/en/site_auto.php';
        foreach (Catalog::definitions() as $key => $def) {
            self::assertSame($def['label_en'], $dict[$def['label']] ?? null, $key . ' (libellé)');
            self::assertSame($def['help_en'], $dict[$def['help']] ?? null, $key . ' (description)');
            if (isset($def['unit'])) {
                self::assertSame($def['unit_en'], $dict[$def['unit']] ?? null, $key . ' (unité)');
            }
        }
        foreach (Catalog::GROUPS as $g) {
            self::assertSame($g['label_en'], $dict[$g['label']] ?? null);
        }
    }

    public function testSavingKeepsUnknownKeysAndUnlimitedStoresZero(): void
    {
        $existing = ['legacy_flag' => 'garde', 'beta_x' => ['a' => 1], 'max_members' => 25, 'atak' => true];
        $input = [
            'feature_messages' => '1',
            'feature_max_members' => '40',
            'feature_max_members_unlimited' => '1',
            'feature_max_training_courses' => '12',
        ];
        self::assertSame([], Catalog::validateForm($input));
        $out = Catalog::buildFeaturesFromForm($input, $existing);
        self::assertSame('garde', $out['legacy_flag']);
        self::assertSame(['a' => 1], $out['beta_x']);
        self::assertSame(0, $out['max_members'], '« Illimité » coché : 0, même si un nombre est resté dans le champ.');
        self::assertSame(12, $out['max_training_courses']);
        self::assertTrue($out['messages']);
        self::assertFalse($out['atak'], 'Interrupteur décoché : module retiré.');
        self::assertSame(['legacy_flag', 'beta_x'], Catalog::unknownKeys($out));
        // Champ vidé : plafond actuel, sinon celui du palier, jamais « illimité » par accident.
        $kept = Catalog::buildFeaturesFromForm(['feature_max_active_invite_links' => ' '], ['max_training_courses' => 9], 'free');
        self::assertSame(2, $kept['max_active_invite_links']);
        self::assertSame(9, $kept['max_training_courses']);
        self::assertSame('Illimité', Catalog::formatValue('max_members', 0));
    }

    public function testServerValidationRejectsBadQuotas(): void
    {
        self::assertArrayHasKey('max_members', Catalog::validateForm(['feature_max_members' => '0']));
        self::assertArrayHasKey('max_members', Catalog::validateForm(['feature_max_members' => '-3']));
        self::assertArrayHasKey('max_members', Catalog::validateForm(['feature_max_members' => '2.5']));
        self::assertArrayHasKey('max_members', Catalog::validateForm(['feature_max_members' => (string) (Catalog::QUOTA_MAX + 1)]));
        self::assertArrayHasKey('atak', Catalog::validateForm(['feature_atak' => 'peut-être']));
        self::assertSame([], Catalog::validateForm(['feature_max_members' => 'x', 'feature_max_members_unlimited' => '1']));
    }

    public function testPublicCardsComeFromRealValues(): void
    {
        $plans = [
            ['slug' => 'free', 'name' => 'Gratuit', 'features_json' => json_encode(['max_members' => 7, 'atak' => false])],
            ['slug' => 'standard', 'name' => 'Standard', 'features_json' => json_encode(['max_members' => 0, 'atak' => true])],
        ];
        $cards = PlanOfferSummary::cards($plans);
        $flat = static fn (array $card): array => array_map(static fn (array $i): string => $i['label'] . '=' . ($i['value'] ?? ''), $card['items']);
        self::assertContains('Nombre max. de membres=7', $flat($cards[0]));
        self::assertSame('Gratuit', $cards[1]['inherits']);
        self::assertContains('Carte tactique ATAK=', $flat($cards[1]));
        self::assertContains('Nombre max. de membres=Illimité', $flat($cards[1]));
        // Aperçu de l'administration : valeurs proposées à la place des valeurs enregistrées.
        $preview = PlanOfferSummary::cards($plans, ['free' => ['max_members' => 3]]);
        self::assertContains('Nombre max. de membres=3', $flat($preview[0]));
    }

    public function testOveragesOnlyWarnOnLoweredCaps(): void
    {
        $svc = new SubscriptionPlanAdminService(
            null,
            static fn (int $tid): array => ['max_members' => $tid === 2 ? 30 : 5],
            static fn (): array => [
                ['id' => 2, 'name' => 'Alpha', 'plan_slug' => 'standard', 'subscription_status' => 'active', 'settings' => null],
                ['id' => 3, 'name' => 'Bravo', 'plan_slug' => 'standard', 'subscription_status' => 'active', 'settings' => null],
                ['id' => 4, 'name' => 'Charlie', 'plan_slug' => 'pro', 'subscription_status' => 'active', 'settings' => null],
            ],
        );
        self::assertCount(2, $svc->subscribersBySlug()['standard']);
        $over = $svc->overages('standard', ['max_members' => 50], ['max_members' => 20]);
        self::assertSame(['Alpha'], array_column($over, 'tenant_name'));
        self::assertSame([], $svc->overages('standard', ['max_members' => 50], ['max_members' => 0]), 'Illimité : aucun dépassement.');
        self::assertSame('pro_copie_2', SubscriptionPlanAdminService::copySlug('pro', static fn (string $s): bool => $s === 'pro_copie'));
    }

    public function testAdminRoutesAndControllerAreWired(): void
    {
        $routes = $this->read('routes/web.php');
        foreach (["subscription-plans/{id}/apercu', [SystemSubscriptionPlansController::class, 'preview']",
            "subscription-plans/{id}/dupliquer', [SystemSubscriptionPlansController::class, 'duplicate']"] as $r) {
            self::assertStringContainsString($r, $routes);
        }
        $ctrl = $this->read('app/Controllers/Admin/System/SystemSubscriptionPlansController.php');
        self::assertStringContainsString('SubscriptionPlanFeaturesCatalog::validateForm($input)', $ctrl);
        self::assertStringContainsString('buildFeaturesFromForm($input, SubscriptionPlanFeaturesCatalog::decodeFeatures($before), (string) ($before[\'slug\'] ?? \'\'))', $ctrl);
        self::assertStringContainsString('AuditAction::SUBSCRIPTION_PLAN_DUPLICATED', $ctrl);
        self::assertStringContainsString('$this->admin->history($id)', $ctrl);
    }

    public function testEditViewUsesSwitchesUnlimitedAndTabs(): void
    {
        $view = $this->read('views/admin/system/subscription_plans_form.php');
        self::assertStringContainsString('role="switch"', $view);
        self::assertStringContainsString('_unlimited" value="1"', $view);
        self::assertStringContainsString('role="tablist"', $view);
        self::assertStringContainsString('subscription_plan_preview.php', $view);
        self::assertStringContainsString('data-spl-search', $view);
        self::assertStringContainsString('admin-subscription-plans.js', $view);
    }

    public function testComparisonViewListsEveryGroupAndPlan(): void
    {
        $view = $this->read('views/admin/system/subscription_plans_index.php');
        self::assertStringContainsString('PlanCatalog::keysByGroup()', $view);
        self::assertStringContainsString('data-spl-toggle', $view);
        self::assertStringContainsString("/dupliquer'", $view);
        self::assertFileExists(self::root() . '/public/assets/css/admin-subscription-plans.css');
        self::assertFileExists(self::root() . '/public/assets/js/admin-subscription-plans.js');
    }

    public function testPublicPageNoLongerHardcodesFigures(): void
    {
        $view = $this->read('views/platform/upgrade.php');
        self::assertStringContainsString("views/partials/plan_offer_cards.php", $view);
        self::assertStringNotContainsString('10 imports, 5 émetteurs réels', $view);
        self::assertStringNotContainsString('50 imports, 25 émetteurs', $view);
        self::assertStringContainsString('PlanOfferSummary::cards(', $this->read('app/Controllers/Web/PlatformSubscriptionController.php'));
    }
}
