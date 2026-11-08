<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SsePortalNav;
use PHPUnit\Framework\TestCase;

/**
 * COMSPEC IDENTITY (Lot 1) : câblage site ↔ COMSPEC Link ↔ mod SSE.
 * Routes de l'API et du portail, entrée de navigation, commandes de la DLL (et liste annoncée au jeu), étape de
 * migration inscrite dans la file active.
 */
final class SseIdentityWiringAssetTest extends TestCase
{
    private static function read(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }

    public function testApiAndPortalRoutesAreRegistered(): void
    {
        $routes = self::read('routes/web.php');
        foreach ([
            "get('/api/sse/identity/pack', [\\App\\Controllers\\Api\\SseIdentityApiController::class, 'pack'])",
            "get('/api/sse/identity/documents/lookup', [\\App\\Controllers\\Api\\SseIdentityApiController::class, 'lookup'])",
            "post('/api/sse/identity/checks', [\\App\\Controllers\\Api\\SseIdentityApiController::class, 'checksStore'])",
        ] as $needle) {
            self::assertStringContainsString($needle, $routes);
        }
        // Pages du portail derrière les deux gardes du portail SSE, « nouvelle » / « nouveau » avant /{id}.
        self::assertSame(24, preg_match_all("#'/atak/sse/papiers[^']*', \\[\\\\App\\\\Controllers\\\\Web\\\\SseIdentityRegistryController::class, '\\w+'\\], \\\$mwSsePortal\\)#", $routes));
        self::assertLessThan(strpos($routes, "'/atak/sse/papiers/identites/{id}'"), strpos($routes, "'/atak/sse/papiers/identites/nouvelle'"));
        self::assertLessThan(strpos($routes, "'/atak/sse/papiers/documents/{id}'"), strpos($routes, "'/atak/sse/papiers/documents/nouveau'"));
        // Pays réels stylisés (univers réaliste-simulation) : import avant /pays/{id}.
        self::assertLessThan(strpos($routes, "'/atak/sse/papiers/pays/{id}'"), strpos($routes, "get('/atak/sse/papiers/pays/reels'"));
        self::assertLessThan(strpos($routes, "'/atak/sse/papiers/pays/{id}'"), strpos($routes, "post('/atak/sse/papiers/pays/reels'"));
    }

    public function testPortalNavigationAndPersonFileShowThePapers(): void
    {
        $nav = SsePortalNav::sections(false, false);
        self::assertSame('papiers', SsePortalNav::activeId($nav, '/public/atak/sse/papiers/documents/12'));
        self::assertSame('identite', SsePortalNav::sectionOf($nav, 'papiers'));
        self::assertSame('identites', SsePortalNav::activeId($nav, '/public/atak/sse/identites/4'));

        self::assertStringContainsString("'idPapers' => SseIdentityRegistryController::personPapers(", self::read('app/Controllers/Web/SsePortalController.php'));
        self::assertStringContainsString("require __DIR__ . '/papers/_person_block.php';", self::read('views/atak/sse/person_show.php'));
        $sub = self::read('views/atak/sse/papers/_subnav.php');
        self::assertStringContainsString('SIMULATION', $sub);
        self::assertStringContainsString('content: "SIMULATION"', self::read('public/assets/css/sse-papers.css'));
    }

    public function testLinkDllServesTheFourCommandsAndAnnouncesThem(): void
    {
        $ext = self::read('mod/UptoDate/COMSPECExtension/Extension.cs');
        self::assertMatchesRegularExpression('/MobileLink,GetIdentityPack,GetIdentityPackChunk,QueryIdDocument,SubmitIdCheck"/', $ext);
        self::assertStringContainsString("if (function == \"GetIdentityPackChunk\")\n            return IdentityPackChunk(args);", str_replace("\r\n", "\n", $ext));
        self::assertStringContainsString('if (function is "GetIdentityPack" or "QueryIdDocument" or "SubmitIdCheck")', $ext);

        $id = self::read('mod/UptoDate/COMSPECExtension/Extension_Identity.cs');
        // Blocs du paquet lus par fn_idAthenaPack : un nom absent ici n'arrive jamais en jeu.
        self::assertStringContainsString('or "since" or "settings")', $id);
        self::assertStringContainsString('["kits"] = new(), ["briefs"] = new(),', $id);
        self::assertStringContainsString('"/api/sse/identity/pack?map="', $id);
        self::assertStringContainsString('"/api/sse/identity/documents/lookup"', $id);
        self::assertStringContainsString('"/api/sse/identity/checks"', $id);
        self::assertStringContainsString('IdentityPackChunkBytes = 6000', $id);
        self::assertStringContainsString('string.Concat(args.Select(a => a ?? ""))', $id, 'morceaux de 4000 caractères recollés');
        self::assertStringContainsString('sb.Append("\"\"")', $id, 'guillemets doublés pour parseSimpleArray');
        // La DLL de l'ATAK natif compile le même fichier.
        self::assertStringContainsString('Extension_Identity.cs', self::read('mod/COMSPEC_ATAK_Native/COMSPECATAKNativeExtension/COMSPECATAKNativeExtension.csproj'));
    }

    public function testMigrationStepIsQueued(): void
    {
        $registry = self::read('database/steps/registry.php');
        self::assertStringContainsString("'id' => 'atak_sse_identity'", $registry);
        self::assertStringContainsString("'file' => '3730_atak_sse_identity.php'", $registry);
        self::assertStringContainsString("/bootstrap/atak_sse_identity_migration.php'", self::read('database/steps/3730_atak_sse_identity.php'));
        $migration = self::read('bootstrap/atak_sse_identity_migration.php');
        foreach (['sse_id_countries', 'sse_identities', 'sse_id_documents', 'sse_id_document_instances', 'sse_id_checks', 'uq_sse_id_check_idem', 'uq_sse_id_document_number'] as $name) {
            self::assertStringContainsString("'" . $name . "'", $migration);
        }
    }
}
