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

    public function testRealAndCoverIdentitiesAndThePhotoAreWiredInTheModAndTheSite(): void
    {
        // Mod : rôle de chaque papier Athena, identité présentée, changement d'identité, papiers cachés, portrait imprimé.
        self::assertStringContainsString('class idActive {};', self::read(self::ID . 'config.cpp'));
        $active = self::read(self::ID . 'functions/fn_idActive.sqf');
        foreach (['case "get":', 'case "set":', 'case "roles":', 'case "push":', '"activeRole"'] as $needle) {
            self::assertStringContainsString($needle, $active);
        }
        $give = self::read(self::ID . 'functions/fn_idModuleGive.sqf');
        self::assertStringContainsString('["role", ["real", "cover"] select', $give);
        self::assertStringContainsString('"athenaPhotos"', $give);
        self::assertStringContainsString('if ("photos" in _settings) then {', self::read(self::ID . 'functions/fn_idAthenaPack.sqf'));
        self::assertStringContainsString('_p set ["photoMosaic", _d getOrDefault ["photoMosaic", ""]];', self::read(self::ID . 'functions/fn_idServer.sqf'));
        self::assertStringContainsString('case "identitySwitch": {', self::read(self::ID . 'functions/fn_idServer.sqf'));
        self::assertStringContainsString('(count _mosaic) isEqualTo 300', self::read(self::ID . 'functions/fn_idViewerOnLoad.sqf'));
        self::assertStringContainsString('case "roles": {', self::read(self::ID . 'functions/fn_idClient.sqf'));
        self::assertStringContainsString('"COMSPEC_ID_Hidden", "Papiers cachés (autre identité)"', self::read(self::ID . 'functions/fn_idActionChildren.sqf'));
        self::assertStringContainsString('case "switch": {', self::read(self::ID . 'functions/fn_idCoverPage.sqf'));
        self::assertStringContainsString('_L set ["hasBoth", _bothR];', self::read(self::ID . 'functions/fn_idLegend.sqf'));
        self::assertStringContainsString('["push", [_this]] call comspec_sse_fnc_idActive;', self::read(self::ID . 'functions/fn_idKit.sqf'));
        self::assertStringContainsString('Deux identités sur le même membre', self::read('mod/COMSPEC_ATAK_Native/missions/COMSPEC_Autotest.Stratis/scripts/sse.sqf'));

        // Site : routes, contrôle de la communauté et des droits pour servir la photo, réglage, étape 4260.
        $routes = self::read('routes/web.php');
        $c = '[\\App\\Controllers\\Admin\\MemberIdentityPapersController::class, ';
        foreach (['tout-generer' => 'generateAll', 'kit-ajout' => 'kitAdd', 'identites/{iid}/photo' => 'photoUpload'] as $path => $m) {
            self::assertStringContainsString("post('/back-office/ressources/effectifs/membres/{id}/papiers/{$path}', {$c}'{$m}']", $routes);
            self::assertStringContainsString("post('/atak/sse/papiers/membres/{id}/{$path}', {$c}'{$m}'], \$mwSsePortal)", $routes);
        }
        self::assertStringContainsString("get('/back-office/sse-id-photos/{tenant}/{name}', {$c}'photo'], [AuthMiddleware::class])", $routes);
        $ctrl = self::read('app/Controllers/Admin/MemberIdentityPapersController.php');
        self::assertStringContainsString('$tenant !== $tenantId || !($rights[\'view\'] || $rights[\'sse\'])', $ctrl);
        self::assertStringContainsString("->header('X-Content-Type-Options', 'nosniff')", $ctrl);
        $partial = self::read('views/admin/effectifs_workspace/partials/member_papers_sync.php');
        foreach (['Tout générer', 'enctype="multipart/form-data"', 'accept="image/jpeg,image/png"', 'Reprendre la photo de profil', "/kit-ajout'", 'data-confirm='] as $needle) {
            self::assertStringContainsString($needle, $partial);
        }
        self::assertStringContainsString('name="real_with_cover"', self::read('views/atak/sse/papers/checks.php'));
        self::assertStringContainsString("'file' => '4260_sse_identity_dual_photo.php', 'rerun' => true]", self::read('database/steps/registry.php'));
        // Photos affichées par l'adresse qui sait servir une photo privée.
        foreach (['views/atak/sse/papers/_holder_images.php', 'views/atak/sse/papers/document_show.php', 'views/atak/sse/papers/mission_prep.php', 'app/Services/Sse/SseIdWorkshopService.php'] as $f) {
            self::assertStringContainsString('SseIdMemberPhotoService::url(', self::read($f), $f);
        }
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
