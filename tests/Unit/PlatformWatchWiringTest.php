<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditActionLabel;
use App\Services\Cron\CronSchedule;
use App\Services\Watch\WatchSchema;
use PHPUnit\Framework\TestCase;

/**
 * Câblage de la surveillance (détecteurs du mod, charge, priorité sous charge) : routes réservées à l'administration
 * de la plateforme, ordre des intergiciels, crochets d'ingestion, étape de migration, données personnelles.
 */
final class PlatformWatchWiringTest extends TestCase
{
    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private function read(string $rel): string
    {
        return (string) file_get_contents(self::root() . '/' . $rel);
    }

    public function testNewAdminPagesAreReservedToPlatformAdministrators(): void
    {
        $routes = $this->read('routes/web.php');
        foreach ([
            'get /admin/system/surcharge', 'post /admin/system/surcharge/reglages',
            'get /admin/system/alertes-mod', 'post /admin/system/alertes-mod/reglages', 'post /admin/system/alertes-mod/{id}/traiter',
        ] as $route) {
            [$method, $path] = explode(' ', $route, 2);
            self::assertMatchesRegularExpression(
                '#router->' . $method . "\\('" . preg_quote($path, '#') . "', \\[[^\\]]+\\], \\[AuthMiddleware::class, SystemAdminMiddleware::class\\]\\);#",
                $routes,
                $route . ' doit passer par SystemAdminMiddleware'
            );
        }
        $nav = $this->read('views/partials/platform_admin_sidebar.php');
        self::assertStringContainsString("'path' => 'admin/system/surcharge'", $nav);
        self::assertStringContainsString("'path' => 'admin/system/alertes-mod'", $nav);
    }

    public function testEveryWritingActionChecksTheCsrfToken(): void
    {
        foreach (['SystemLoadController', 'SystemModAlertsController'] as $controller) {
            $source = $this->read('app/Controllers/Admin/System/' . $controller . '.php');
            // Toutes les actions (hors la page de lecture « index ») vérifient le jeton.
            $actions = preg_match_all('/public function \w+\(Request \$request/', $source);
            self::assertSame($actions, substr_count($source, 'Csrf::validate(') + 1, $controller . ' : une action sans jeton CSRF');
        }
    }

    public function testLoadShedRunsAfterTheModKeyCheckAndBeforeTheRateLimiters(): void
    {
        $app = $this->read('app/Core/Application.php');
        $tactical = strpos($app, 'ComspecTacticalApiMiddleware()');
        $shed = strpos($app, 'LoadShedMiddleware()');
        $rate = strpos($app, 'RateLimitMiddleware()');
        self::assertNotFalse($shed);
        self::assertGreaterThan($tactical, $shed, 'la clé du mod doit être résolue avant le choix de ralentir');
        self::assertGreaterThan($shed, $rate);
        // La mesure part après l'envoi de la réponse, et une exception non rattrapée compte aussi.
        self::assertGreaterThan(strpos($app, '$response->send();'), strpos($app, 'WatchRecorder::finish('));
        self::assertStringContainsString('WatchRecorder::failed(', $app);
    }

    public function testModIngestionHooksOnlyAppendToTheSpool(): void
    {
        $guard = $this->read('app/Support/AtakArmaWriteGuard.php');
        self::assertStringContainsString('ModSignals::actor(', $guard);
        self::assertStringContainsString('ModSignals::refusal(', $guard);
        $controller = $this->read('app/Controllers/Api/AtakApiController.php');
        self::assertStringContainsString('ModSignals::position(', $controller);
        // Aucune base, aucun verrou sur le chemin d'ingestion.
        $signals = $this->read('app/Services/Watch/ModSignals.php') . $this->read('app/Services/Watch/WatchSpool.php');
        foreach (['PDO', 'Database::', 'flock(', 'prepare(', 'LOCK_EX'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $signals, 'chemin chaud : ' . $forbidden);
        }
    }

    public function testShedRefusalNeverUsesTheWordsTheModReadsAsMaintenanceOrAuthRefusal(): void
    {
        // Extension.cs : un 503 dont le corps contient « maintenance » met toute la liaison en pause ; « unauthorized » est un refus définitif.
        $source = strtolower($this->read('app/Middleware/LoadShedMiddleware.php'));
        $body = substr($source, (int) strpos($source, 'public static function refusal'));
        foreach (['maintenance', 'unauthorized', '429'] as $word) {
            self::assertStringNotContainsString($word, $body, $word);
        }
        self::assertStringContainsString('503', $body);
        self::assertStringContainsString("'retry-after'", $body);
    }

    public function testMigrationStepIsTheNextOneAndKeepsDataShort(): void
    {
        $registry = $this->read('database/steps/registry.php');
        self::assertStringContainsString("'file' => '3980_platform_watch.php', 'rerun' => true", $registry);
        self::assertFileExists(self::root() . '/database/steps/3980_platform_watch.php');
        self::assertLessThanOrEqual(30, WatchSchema::RETENTION_ALERT_DAYS);
        self::assertLessThanOrEqual(8, WatchSchema::RETENTION_HOUR_DAYS);
        self::assertLessThanOrEqual(3, WatchSchema::RETENTION_MINUTE_DAYS);
        // Purge par lots bornés.
        foreach (['app/Repositories/PlatformAbuseAlertRepository.php', 'app/Repositories/PlatformLoadRepository.php'] as $rel) {
            $source = $this->read($rel);
            self::assertMatchesRegularExpression('/DELETE FROM .* LIMIT /', str_replace("\n", ' ', $source), $rel);
            self::assertStringContainsString('maxBatches', $source, $rel);
        }
        self::assertStringContainsString('RETENTION_', $this->read('app/Services/Watch/WatchFlusher.php'));
    }

    public function testAlertsKeepNoAddressAndNoEmail(): void
    {
        $tables = implode(' ', WatchSchema::tables());
        foreach (['ip', 'ip_address', 'email', 'mail', 'user_agent'] as $column) {
            self::assertDoesNotMatchRegularExpression('/\b' . $column . '\b/i', $tables, 'colonne ' . $column);
        }
        $repo = $this->read('app/Repositories/PlatformAbuseAlertRepository.php');
        self::assertStringNotContainsString('email', strtolower($repo));
        $signals = strtolower($this->read('app/Services/Watch/ModSignals.php'));
        self::assertStringNotContainsString('remote_addr', $signals);
        self::assertStringNotContainsString('clientip', $signals);
        // Les alertes ne se lisent que dans l'administration de la plateforme.
        $routes = $this->read('routes/web.php');
        self::assertSame(0, substr_count($routes, 'PlatformAbuseAlertRepository'));
    }

    public function testCronJobAndAuditLabelsAreRegistered(): void
    {
        $container = $this->read('app/Core/Container.php');
        self::assertSame(2, substr_count($container, 'PlatformWatchCronJob::class'));
        self::assertSame(5, CronSchedule::intervalMinutes('platform_watch'));
        foreach ([AuditAction::PLATFORM_LOAD_SETTINGS_UPDATED, AuditAction::PLATFORM_ABUSE_ALERT_HANDLED] as $slug) {
            $label = AuditActionLabel::toFrench($slug);
            self::assertNotSame($slug, $label);
            self::assertStringNotContainsString('_', $label);
        }
    }

    public function testNoPlanFeatureKeyIsAddedAndPlansAreRankedByTheEffectivePlan(): void
    {
        // Pas de nouvelle clé de formule : le rang vient de PlanGift::effectivePlanSlug (cadeaux compris).
        $index = $this->read('app/Services/Watch/PlanRankIndex.php');
        self::assertStringContainsString('PlanGift::effectivePlanSlug', $index);
        self::assertStringContainsString('PlanGift::rank', $index);
        $catalog = $this->read('app/Services/Billing/SubscriptionPlanFeaturesCatalog.php');
        self::assertStringNotContainsString('load_shed', $catalog);
    }
}
