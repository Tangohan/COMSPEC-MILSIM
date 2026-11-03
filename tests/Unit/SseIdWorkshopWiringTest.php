<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\SseIdWorkshopController;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseIdentitySqlite;

/**
 * Câblage COMSPEC IDENTITY Lot 6 : routes de l'atelier, des kits types, de la préparation de mission, des briefs et
 * de l'import / export ; droits ; étape de migration 3815 rejouable ; garde-fous des vues (SIMULATION, CSRF, export
 * image, briefs dans Mes papiers, carte des contrôles).
 */
final class SseIdWorkshopWiringTest extends TestCase
{
    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path));
    }

    public function testRoutesAreRegisteredWithFixedPathsBeforeIds(): void
    {
        $routes = self::read('routes/web.php');
        $c = '[\\App\\Controllers\\Web\\SseIdWorkshopController::class, ';
        foreach ([
            ['get', '/atak/sse/papiers/atelier', 'workshopIndex'],
            ['get', '/atak/sse/papiers/atelier/nouveau', 'workshopEditor'],
            ['get', '/atak/sse/papiers/atelier/mrz', 'workshopMrz'],
            ['get', '/atak/sse/papiers/atelier/{id}', 'workshopEditor'],
            ['post', '/atak/sse/papiers/atelier', 'workshopStore'],
            ['post', '/atak/sse/papiers/atelier/{id}/cycle', 'workshopCycle'],
            ['post', '/atak/sse/papiers/atelier/{id}/dupliquer', 'workshopDuplicate'],
            ['post', '/atak/sse/papiers/atelier/{id}', 'workshopUpdate'],
            ['get', '/atak/sse/papiers/kits-types', 'kitTemplates'],
            ['post', '/atak/sse/papiers/kits-types/{id}/affecter', 'kitTemplateAssign'],
            ['get', '/atak/sse/papiers/preparation', 'missionPrep'],
            ['post', '/atak/sse/papiers/preparation', 'missionPrepCommit'],
            ['post', '/atak/sse/papiers/preparation/recherches', 'wantedStore'],
            ['post', '/atak/sse/papiers/preparation/lots/{id}/retirer', 'batchArchive'],
            ['get', '/atak/sse/papiers/briefs', 'briefsIndex'],
            ['post', '/atak/sse/papiers/briefs', 'briefStore'],
            ['get', '/atak/sse/papiers/echanges/export', 'transferExport'],
            ['post', '/atak/sse/papiers/echanges/import', 'transferImport'],
        ] as [$verb, $path, $method]) {
            self::assertStringContainsString("\$router->{$verb}('{$path}', {$c}'{$method}'], \$mwSsePortal);", $routes);
        }
        self::assertLessThan(strpos($routes, "'/atak/sse/papiers/atelier/{id}'"), strpos($routes, "'/atak/sse/papiers/atelier/nouveau'"));
        self::assertLessThan(strpos($routes, "'/atak/sse/papiers/atelier/{id}'"), strpos($routes, "'/atak/sse/papiers/atelier/mrz'"));
        self::assertLessThan(strpos($routes, "post('/atak/sse/papiers/atelier/{id}'"), strpos($routes, "'/atak/sse/papiers/atelier/{id}/cycle'"));
        self::assertLessThan(strpos($routes, "post('/atak/sse/papiers/kits-types/{id}'"), strpos($routes, "'/atak/sse/papiers/kits-types/{id}/affecter'"));
    }

    public function testRightsNeverGrantGuestsAndKeepTheTruthForConfidential(): void
    {
        self::assertSame(['view' => false, 'manage' => false, 'truth' => false], SseIdWorkshopController::rightsFor(true, true, true, true));
        self::assertSame(['view' => true, 'manage' => true, 'truth' => false], SseIdWorkshopController::rightsFor(false, true, true, false));
        self::assertSame(['view' => true, 'manage' => false, 'truth' => false], SseIdWorkshopController::rightsFor(false, false, true, false), 'Encadrement sans gestion SSE : lecture');
        self::assertSame(['view' => true, 'manage' => false, 'truth' => false], SseIdWorkshopController::rightsFor(false, true, false, false), 'gestion SSE sans habilitation : lecture');
        self::assertSame(['view' => false, 'manage' => false, 'truth' => true], SseIdWorkshopController::rightsFor(false, false, false, true));
        $ctrl = self::read('app/Controllers/Web/SseIdWorkshopController.php');
        // Chaque écriture vérifie le droit de gestion et le jeton CSRF.
        self::assertSame(substr_count($ctrl, "if (!\$r['manage'] || !Csrf::validate("), 13);
    }

    public function testMigrationStepIsRegisteredAndReplayable(): void
    {
        self::assertFileExists(dirname(__DIR__, 2) . '/database/steps/3815_atak_sse_identity_lot6.php');
        $registry = self::read('database/steps/registry.php');
        self::assertStringContainsString("'id' => 'atak_sse_identity_lot6'", $registry);
        self::assertStringContainsString("'file' => '3815_atak_sse_identity_lot6.php', 'rerun' => true", $registry);
        self::assertStringContainsString("bootstrap/atak_sse_identity_lot6_migration.php", self::read('app/Repositories/SseIdentityRepository.php'));
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $pdo = SseIdentitySqlite::open();
        try {
            $again = SseIdentitySqlite::migrate($pdo);
            self::assertStringContainsString('sse_id_kit_templates (déjà présente)', $again);
            self::assertStringContainsString('sse_id_briefs (déjà présente)', $again);
            self::assertStringNotContainsString('[OK] sse_identities.batch_id', $again);
            $cols = array_column($pdo->query('PRAGMA table_info(sse_id_documents)')->fetchAll(\PDO::FETCH_ASSOC), 'name');
            foreach (['layout', 'print_json', 'duplicated_from_id', 'batch_id'] as $col) {
                self::assertContains($col, $cols);
            }
        } finally {
            SseIdentitySqlite::close();
        }
    }

    public function testViewsKeepTheSimulationGuardrailsCsrfAndExports(): void
    {
        $preview = self::read('views/atak/sse/papers/_doc_preview.php');
        self::assertStringContainsString('class="idw-watermark"', $preview);
        self::assertSame(1, substr_count($preview, "\$pvWatermark = '<div class=\"idw-watermark\""));
        self::assertSame(5, substr_count($preview, '<?= $pvWatermark ?>'), 'filigrane SIMULATION sur chaque face');
        self::assertStringContainsString('SPÉCIMEN DE JEU — SIMULATION', $preview);
        $editor = self::read('views/atak/sse/papers/workshop_editor.php');
        self::assertStringContainsString("assets/vendor/html2canvas-1.4.1/html2canvas.min.js", $editor);
        self::assertFileExists(dirname(__DIR__, 2) . '/public/assets/vendor/html2canvas-1.4.1/html2canvas.min.js');
        self::assertStringContainsString('data-mrz-url', $editor);
        foreach (['workshop_editor', 'kit_template_form', 'kit_templates', 'mission_prep', 'briefs', 'transfer'] as $v) {
            self::assertStringContainsString('Csrf::field()', self::read('views/atak/sse/papers/' . $v . '.php'), $v);
            self::assertStringContainsString("require __DIR__ . '/_subnav.php';", self::read('views/atak/sse/papers/' . $v . '.php'), $v . ' : bandeau SIMULATION');
        }
        $js = self::read('public/assets/js/sse-id-workshop.js');
        self::assertStringContainsString("canvas.toDataURL('image/png')", $js);
        self::assertStringContainsString('data-mrz-url', $js);
        self::assertStringContainsString("'atelier' => ['atelier', 'Atelier de documents']", self::read('views/atak/sse/papers/_subnav.php'));
        self::assertStringContainsString('Mes briefs de couverture', self::read('views/admin/member_situation/mes_papiers.php'));
        self::assertStringContainsString("'briefs' => \$briefs,", self::read('app/Controllers/Admin/MemberIdentityPapersController.php'));
        self::assertStringContainsString('id="idw-checks-map"', self::read('views/atak/sse/papers/dashboard.php'));
        self::assertStringContainsString('L.CRS.Simple', self::read('public/assets/js/sse-id-checks-map.js'));
        self::assertStringNotContainsString('color-mix', self::read('public/assets/css/sse-id-workshop.css'), 'html2canvas ne lit pas color-mix');
    }
}
