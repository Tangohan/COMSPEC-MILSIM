<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sse\MemberIdentitySyncService;
use App\Services\Sse\SseIdentityPackService;
use PHPUnit\Framework\TestCase;

/**
 * Paquet d'identité qui arrive vraiment en jeu : relais « self » par le téléphone du joueur (serveur sans COMSPEC Link,
 * sans Zeus), nouveaux essais, raison précise dans Ma couverture, état réel sur la fiche membre (« Prévu » tant que le jeu
 * n'a rien confirmé), couverture à télécharger depuis la fiche membre.
 */
final class SseIdentityDeliveryTest extends TestCase
{
    private const ID = 'mod/@COMSPEC_SSE/addons/identity/functions/';

    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path));
    }

    public function testWithoutAnyGameReportNothingIsClaimed(): void
    {
        $d = MemberIdentitySyncService::delivery([], true);
        self::assertSame('unknown', $d['state']);
        self::assertSame('Pas encore confirmé par le jeu', $d['title']);
    }

    public function testAthenaOriginIsReceivedAndNotesAreReplaced(): void
    {
        $d = MemberIdentitySyncService::delivery([[
            'origin' => 'athena', 'identity_ref' => 'IDT-000001', 'updated_at' => '2026-11-10 19:19:00', 'server' => 'COMSPEC #1',
            'pack' => 'ok', 'pack_via' => 'self', 'docs' => [['Carte d\'identité', 'États-Unis', 'US47036925']],
        ]], false);
        self::assertSame('received', $d['state']);
        self::assertStringContainsString('relayés par son propre téléphone', $d['why']);
        $diag = ['sent_identity_id' => 1, 'identities' => [1 => ['note' => 'Prévue pour le jeu.']], 'documents' => [7 => ['note' => 'Prévu pour le jeu (kit de mission).']]];
        $out = MemberIdentitySyncService::withDelivery($diag, $d, [['id' => 7, 'doc_number' => 'US47036925']]);
        self::assertSame('Reçue en jeu le 2026-11-10 19:19 UTC.', $out['identities'][1]['note']);
        self::assertSame('Remis en jeu le 2026-11-10 19:19 UTC.', $out['documents'][7]['note']);
    }

    public function testGeneratedIdentitySaysWhichStepFailedAndWhatToDo(): void
    {
        $d = MemberIdentitySyncService::delivery([[
            'origin' => 'generated', 'updated_at' => '2026-11-10 19:19:00', 'pack' => 'relay_fail', 'pack_code' => 'uplink_off',
        ]], true);
        self::assertSame('not_received', $d['state']);
        self::assertStringContainsString('service Athena sur Non', $d['why']);
        self::assertStringContainsString('Demander la mise à jour', $d['todo']);
        $key = MemberIdentitySyncService::delivery([['origin' => 'generated', 'pack' => 'server_fetch', 'pack_code' => 'unauthorized']], true);
        self::assertStringContainsString('refuse sa clé', $key['why']);
        $diag = ['sent_identity_id' => 1, 'identities' => [1 => ['note' => 'Prévue pour le jeu.']], 'documents' => []];
        self::assertStringStartsWith('Prévue pour le jeu, mais pas reçue', MemberIdentitySyncService::withDelivery($diag, $d, [])['identities'][1]['note']);
    }

    public function testCoverOriginsStayHiddenWithoutClearance(): void
    {
        self::assertSame('unknown', MemberIdentitySyncService::delivery([['origin' => 'import']], false)['state']);
        self::assertSame('other', MemberIdentitySyncService::delivery([['origin' => 'import']], true)['state']);
    }

    public function testDiagnoseNoLongerSaysUsedInGameBeforeTheGameConfirms(): void
    {
        $svc = self::read('app/Services/Sse/MemberIdentitySyncService.php');
        self::assertStringNotContainsString("'Utilisée en jeu.'", $svc);
        self::assertStringNotContainsString("'Part en jeu (kit", $svc);
        self::assertStringContainsString("'Prévue pour le jeu.'", $svc);
    }

    public function testSelfPackIsServedOnlyForTheLinkedAccount(): void
    {
        self::assertSame('~self', SseIdentityPackService::SELF_OP);
        $api = self::read('app/Controllers/Api/SseIdentityApiController.php');
        self::assertStringContainsString('str_starts_with($op, SseIdentityPackService::SELF_OP)', $api);
        self::assertStringContainsString("'error' => 'unlinked'", $api);
        self::assertLessThan(strpos($api, 'ShortCache::remember('), strpos($api, '->buildSelf('), 'paquet « self » jamais servi par le cache partagé');
        $pack = self::read('app/Services/Sse/SseIdentityPackService.php');
        self::assertStringContainsString("'watch' => [],", $pack);
        self::assertStringContainsString("\$d['forgery_kind'] = null;", $pack);
    }

    public function testModRelaysRetriesAndExplains(): void
    {
        $pack = self::read(self::ID . 'fn_idAthenaPack.sqf');
        foreach (['case "applySelf": {', 'case "want": {', 'case "state": {', '"~self"', 'packRetry', '60 * (_tries min 5)', '_ku isEqualTo _uid'] as $needle) {
            self::assertStringContainsString($needle, $pack);
        }
        // Plus d'abandon après 5 essais à une minute.
        self::assertStringNotContainsString('if (_n < 5)', $pack);
        $server = self::read(self::ID . 'fn_idServer.sqf');
        self::assertStringContainsString('case "packFail": {', $server);
        self::assertStringContainsString('["applySelf", [_me, _raw]] call comspec_sse_fnc_idAthenaPack;', $server);
        self::assertStringContainsString('["want", [_me]] call comspec_sse_fnc_idAthenaPack;', $server);
        $client = self::read(self::ID . 'fn_idClient.sqf');
        self::assertStringContainsString('COMSPEC_AthenaReady', $client);
        self::assertLessThan(strpos($client, '["fetch", ["", "self"]'), strpos($client, 'COMSPEC_AthenaReady'), 'aucune requête avant la liaison');
        $legend = self::read(self::ID . 'fn_idLegend.sqf');
        self::assertStringNotContainsString('aucun Zeus relié', $legend);
        self::assertStringContainsString('_L set ["packStep"', $legend);
        self::assertStringContainsString('["pack", _sheet getOrDefault ["packStep", ""]]', self::read(self::ID . 'fn_idCoverSync.sqf'));
        $en = self::read('mod/COMSPEC_ATAK_Native/Sources/addons/main/i18n/en.txt');
        $xx = self::read('mod/COMSPEC_ATAK_Native/localisation/template/xx.txt');
        // Raisons par étape (blocs « { "…" } » du diagnostic) et conseil « En attendant » : traduites dans les deux dictionnaires.
        $a = strpos($legend, 'private _why = switch (_step)');
        $b = strpos($legend, '_diag pushBack ["ko", "Paquet Athena non chargé", _why];');
        self::assertNotFalse($a);
        self::assertNotFalse($b);
        preg_match_all('/\{ "([^"]{40,})" \}/u', substr($legend, $a, $b - $a), $m);
        $texts = array_merge($m[1], ['En attendant', 'Athena ne prévoit aucun papier pour votre compte sur cette carte : rien à vous remettre.']);
        self::assertGreaterThan(10, count($texts));
        foreach ($texts as $fr) {
            self::assertStringContainsString('["' . $fr . '", "', $en, $fr);
            self::assertStringContainsString('["' . $fr . '", "", "', $xx, $fr);
        }
    }

    public function testMemberSheetOffersTheCoverDownloadAndKeepsItsHelper(): void
    {
        $routes = self::read('routes/web.php');
        self::assertStringContainsString("post('/back-office/ressources/effectifs/membres/{id}/papiers/couverture', [\\App\\Controllers\\Admin\\MemberCoverExportController::class, 'generateForMember']", $routes);
        self::assertStringContainsString("get('/back-office/ressources/effectifs/membres/{id}/papiers/couverture/{exportId}/telecharger', [\\App\\Controllers\\Admin\\MemberCoverExportController::class, 'downloadForMember']", $routes);
        $view = self::read('views/admin/effectifs_workspace/partials/member_papers.php');
        self::assertStringContainsString("require dirname(__DIR__, 3) . '/partials/sse_cover_export.php';", $view);
        $partial = self::read('views/partials/sse_cover_export.php');
        self::assertStringContainsString('<option value="athena">', $partial);
        self::assertStringContainsString('Documents\\Arma 3 - COMSPEC\\comspec-couverture-CVR-', $partial);
        $controller = self::read('app/Controllers/Admin/MemberCoverExportController.php');
        self::assertStringContainsString("inputString('source') === 'athena'", $controller);
        self::assertStringContainsString('self::LIMIT_MANAGER', self::read('app/Services/Sse/SseCoverExportService.php'));
        // La boucle de l'historique n'écrase plus l'échappement $h de la fiche membre (page cassée après une reprise).
        $sync = self::read('views/admin/effectifs_workspace/partials/member_papers_sync.php');
        self::assertStringNotContainsString("as \$h):", $sync);
        self::assertStringContainsString('Arrivée réelle en jeu', $sync);
    }
}
