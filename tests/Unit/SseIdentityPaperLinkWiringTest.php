<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Câblage « papier en jeu ↔ identité Athena » : noms d'objets sans « (simulation) », API publique du mod (fonctions,
 * évènements CBA, suivi Take / Put relayé et vérifié par le serveur), diagnostic de la fiche « Ma couverture », remontée
 * soumise au réglage de transmission, traductions, routes et bloc « Transmission en jeu » de la fiche membre, étape 4250.
 */
final class SseIdentityPaperLinkWiringTest extends TestCase
{
    private const ID = 'mod/@COMSPEC_SSE/addons/identity/';

    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path));
    }

    public function testItemNamesNoLongerSaySimulationButThePaperStillDoes(): void
    {
        $weapons = self::read(self::ID . 'CfgWeapons.hpp');
        self::assertStringNotContainsStringIgnoringCase('(simulation)', $weapons);
        self::assertStringContainsString('displayName = "$STR_COMSPEC_SSE_CFG_ID_MILITARYCARD";', $weapons);
        $table = self::read('mod/@COMSPEC_SSE/addons/main/stringtable.xml');
        foreach (['B8E7E90D9A' => "Carte d'identité", 'BBFD0B835C' => 'Passeport', '5D056C9123' => 'Permis de conduire', 'ID_MILITARYCARD' => 'Carte militaire'] as $key => $fr) {
            self::assertSame(1, preg_match('#<Key ID="STR_COMSPEC_SSE_CFG_' . $key . '">(.*?)</Key>#s', $table, $m), $key);
            self::assertStringNotContainsStringIgnoringCase('simulation', $m[1], $key);
            self::assertStringContainsString('<French>' . $fr . '</French>', $m[1]);
        }
        // La mention reste sur le papier (visionneuse) et dans la description de l'objet.
        self::assertStringContainsString('SIMULATION', self::read(self::ID . 'functions/fn_idViewerOnLoad.sqf'));
        self::assertSame(1, preg_match('#<Key ID="STR_COMSPEC_SSE_CFG_F3E840A9AB">(.*?)</Key>#s', $table, $m));
        self::assertStringContainsString('le papier lui-même porte la mention SIMULATION', $m[1]);
    }

    public function testPublicPaperApiIsDeclaredDocumentedAndServerSide(): void
    {
        $config = self::read(self::ID . 'config.cpp');
        foreach (['idPaperOf', 'idPaperOwner', 'idPaperLink', 'idPaperGive', 'idPaperTrack'] as $fn) {
            self::assertStringContainsString('class ' . $fn . ' {};', $config);
            $sqf = self::read(self::ID . 'functions/fn_' . $fn . '.sqf');
            self::assertStringContainsString('if (!isServer', $sqf, $fn . ' : serveur seulement');
        }
        $of = self::read(self::ID . 'functions/fn_idPaperOf.sqf');
        foreach (['athenaRef', 'athenaIdentityId', 'ownerUid', 'holder', 'foreign', 'custody', '"IDT-"'] as $needle) {
            self::assertStringContainsString($needle, $of);
        }
        $track = self::read(self::ID . 'functions/fn_idPaperTrack.sqf');
        self::assertStringContainsString('"comspec_sse_identity_paperDropped", "comspec_sse_identity_paperPicked"', $track);
        self::assertStringContainsString('CBA_fnc_globalEvent', $track);
        self::assertStringContainsString('comspec_sse_identity_paperLinked', self::read(self::ID . 'functions/fn_idPaperLink.sqf'));
        // Take / Put du joueur relayés au serveur, qui vérifie le poste (owner) et n'applique pas l'anti-flood.
        $client = self::read(self::ID . 'XEH_postInitClient.sqf');
        self::assertStringContainsString('["CAManBase", "Take",', $client);
        self::assertStringContainsString('["CAManBase", "Put",', $client);
        $server = self::read(self::ID . 'functions/fn_idServer.sqf');
        self::assertStringContainsString('case "paperMoved": {', $server);
        self::assertStringContainsString('!(_op in ["begin", "aiCfg", "paperMoved"])', $server);
        // Propriétaire et identité Athena posés à la délivrance ; porteur suivi à la matérialisation et au transfert.
        $issue = self::read(self::ID . 'functions/fn_idIssue.sqf');
        self::assertStringContainsString('["athenaIdentity", _spec getOrDefault ["athenaIdentity", ""]]', $issue);
        self::assertStringContainsString('["ownerUid", _spec getOrDefault ["ownerUid"', $issue);
        self::assertStringContainsString('["hold", [_doc, _where]] call comspec_sse_fnc_idPaperTrack;', self::read(self::ID . 'functions/fn_idMaterialize.sqf'));
        self::assertStringContainsString('["hold", [_doc, _to]] call comspec_sse_fnc_idPaperTrack;', self::read(self::ID . 'functions/fn_idTransfer.sqf'));
        self::assertStringContainsString('["athenaIdentity", call {', self::read(self::ID . 'functions/fn_idModuleGive.sqf'));
        self::assertStringContainsString('### Papiers reliés à Athena (API pour les mods et les scripts)', self::read('mod/@COMSPEC_SSE/docs/guide/Controle_identite.md'));
    }

    public function testCoverSheetExplainsWhyTheAthenaIdentityIsNotUsedAndTheReportRespectsTheSetting(): void
    {
        $legend = self::read(self::ID . 'functions/fn_idLegend.sqf');
        foreach (['_L set ["diag", _diag];', '_L set ["origin", _origin];', 'Paquet Athena non chargé', 'Aucun papier Athena pour votre compte', 'Identité générée par le mod'] as $needle) {
            self::assertStringContainsString($needle, $legend);
        }
        self::assertStringContainsString('(_s getOrDefault ["diag", []])', self::read(self::ID . 'functions/fn_idCoverPage.sqf'));
        $sync = self::read(self::ID . 'functions/fn_idCoverSync.sqf');
        self::assertStringContainsString('comspec_sse_id_autoTransmitCommunity', $sync);
        self::assertLessThan(strpos($sync, '["SubmitIdCheck", _parts]'), strpos($sync, 'comspec_sse_id_autoTransmitCommunity'), 'réglage lu avant tout envoi');
        foreach (['["origin", _origin]', '["identity_ref"', '["last", _sheet'] as $needle) {
            self::assertStringContainsString($needle, $sync);
        }
        $en = self::read('mod/COMSPEC_ATAK_Native/Sources/addons/main/i18n/en.txt');
        foreach (['Paquet Athena non chargé', 'Aucun papier Athena pour votre compte', 'Identité générée par le mod', 'Identité Athena'] as $fr) {
            self::assertStringContainsString('["' . $fr . '", "', $en);
        }
        self::assertStringContainsString('["sse_papier_lie", {', self::read('mod/COMSPEC_ATAK_Native/missions/COMSPEC_Autotest.Stratis/scripts/sse.sqf'));
    }

    public function testMemberSheetSyncRoutesBlockAndMigrationAreWired(): void
    {
        $routes = self::read('routes/web.php');
        $c = '[\\App\\Controllers\\Admin\\MemberIdentityPapersController::class, ';
        foreach (['synchro-jeu' => 'syncResolve', 'historique/{hid}/restaurer' => 'syncRestore', 'envoyer-jeu' => 'syncSend'] as $path => $m) {
            self::assertStringContainsString("post('/back-office/ressources/effectifs/membres/{id}/papiers/{$path}', {$c}'{$m}'], [AuthMiddleware::class, OrganizationAdminMiddleware::class])", $routes);
            self::assertStringContainsString("post('/atak/sse/papiers/membres/{id}/{$path}', {$c}'{$m}'], \$mwSsePortal)", $routes);
        }
        self::assertStringContainsString("require __DIR__ . '/member_papers_sync.php';", self::read('views/admin/effectifs_workspace/partials/member_papers.php'));
        $partial = self::read('views/admin/effectifs_workspace/partials/member_papers_sync.php');
        foreach (['id="papiers-synchro"', 'Transmission en jeu', 'Différence avec le jeu', 'Reprendre la version du jeu', 'Garder les deux', 'Envoyer la version Athena vers le jeu', 'name="_csrf_token"'] as $needle) {
            self::assertStringContainsString($needle, $partial);
        }
        $registry = self::read('database/steps/registry.php');
        self::assertStringContainsString("'file' => '4250_sse_identity_sync.php', 'rerun' => true]", $registry);
        self::assertStringContainsString("/bootstrap/sse_identity_sync_migration.php'", self::read('database/steps/4250_sse_identity_sync.php'));
        $lang = self::read('lang/en/site_auto.php');
        foreach (['Transmission en jeu', 'Reprendre la version du jeu', 'Compte Athena non lié au joueur Steam', 'Papier révoqué : non transmis'] as $fr) {
            self::assertStringContainsString("'" . $fr . "' => '", $lang);
        }
    }
}
