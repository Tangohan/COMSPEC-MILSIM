<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Import de couverture (app Ma couverture) : câblage DLL, SQF et dictionnaires, et règle de licence (lecture locale, aucune
 * requête réseau). Le code SQF et C# ne s'exécute pas en CI : ces contrôles lisent les sources.
 */
final class AtakCoverImportAssetTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    private static function read(string $rel): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(self::ROOT . '/' . $rel));
    }

    public function testDllReadsLocallyAndNeverUsesTheNetwork(): void
    {
        $cs = self::read('mod/UptoDate/COMSPECExtension/Extension_CoverImport.cs');
        foreach (['HttpClient', '_baseUrl', 'SendGet', 'SendPost', 'WebRequest', 'Socket', 'Process.Start', 'Assembly.Load', 'Activator'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $cs, $forbidden . ' interdit : l\'import est local');
        }
        self::assertStringContainsString('LocalDocumentsPath("Arma 3 - COMSPEC")', $cs);
        // Nom de fichier borné : jamais un chemin fourni par l'appelant.
        self::assertStringContainsString('comspec-couverture-CVR-[0-9A-F]{8}', $cs);
        self::assertStringContainsString('CoverFileMaxBytes = 262144', $cs);
        self::assertStringContainsString('MaxDepth = 6', $cs);
        self::assertStringContainsString('ReparsePoint', $cs);
        self::assertStringContainsString('SHA256.HashData', $cs);

        $ext = self::read('mod/UptoDate/COMSPECExtension/Extension.cs');
        self::assertStringContainsString('if (function is "ReadCoverFile" or "ReadCoverFileChunk")', $ext);
        self::assertMatchesRegularExpression('/GetAtakFeatures.*?HashText,ReadCoverFile,ReadCoverFileChunk/s', $ext);
        // Servie avant HandleGameAuth : pas d'attente d'un jeton, de connexion ni d'autorisation d'Athena.
        self::assertLessThan(strpos($ext, 'var gameAuth = HandleGameAuth(function, args);'), strpos($ext, 'CoverImportCommand(function, args)'));
        self::assertStringContainsString(
            'Extension_CoverImport.cs',
            self::read('mod/COMSPEC_ATAK_Native/COMSPECATAKNativeExtension/COMSPECATAKNativeExtension.csproj')
        );
    }

    public function testSqfImportIsWiredAndKeepsOutOfIdentityProfilesAndRadio(): void
    {
        $dir = 'mod/@COMSPEC_SSE/addons/identity/';
        $import = self::read($dir . 'functions/fn_idCoverImport.sqf');
        self::assertStringContainsString('class idCoverImport {};', self::read($dir . 'config.cpp'));
        // Lecture locale directe : ni uplink, ni extensionCall Athena.
        self::assertStringContainsString('"COMSPECExtension" callExtension', $import);
        self::assertStringNotContainsString('SubmitIdCheck', $import);
        self::assertStringNotContainsString('comspec_sse_fnc_extensionCall', $import);
        self::assertStringNotContainsString('comspec_sse_fnc_radioGuard', $import);
        self::assertStringNotContainsString('call compile', $import);
        self::assertStringNotContainsString('execVM', $import);

        $server = self::read($dir . 'functions/fn_idServer.sqf');
        self::assertStringContainsString('case "coverImport":', $server);
        self::assertStringContainsString('case "coverForget":', $server);
        self::assertStringContainsString('["serverSet", [_me, _c]]', $server);

        $legend = self::read($dir . 'functions/fn_idLegend.sqf');
        self::assertStringContainsString('(_reg getOrDefault ["imports", createHashMap])', $legend);
        self::assertStringContainsString('_L set ["imported", true];', $legend);

        // Identité utilisée en jeu remontée quelle que soit son origine (athena, import compris), seulement si la transmission
        // automatique est permise : le site la compare sans l'écrire (MemberIdentitySyncService).
        $sync = self::read($dir . 'functions/fn_idCoverSync.sqf');
        self::assertStringContainsString('["origin", _origin]', $sync);
        self::assertStringContainsString('comspec_sse_id_autoTransmitCommunity', $sync);
        self::assertStringContainsString('["resend"] call comspec_sse_fnc_idCoverImport;', self::read($dir . 'XEH_postInitClient.sqf'));

        $page = self::read($dir . 'functions/fn_idCoverPage.sqf');
        // Depuis feat/atak-couvertures-sync (4.4.0) : import d'abord depuis Athena (fenêtre Mes couvertures sur Athena),
        // le fichier reste possible (IMPORTER UN FICHIER) avec une seule ligne d'aide.
        self::assertStringContainsString('"IMPORTER UN FICHIER" call _tr', $page);
        self::assertStringContainsString('Sans connexion : sur Athena › Ma couverture, téléchargez le fichier, déposez-le dans Documents\Arma 3 - COMSPEC, puis touchez Importer un fichier.', $page);
        self::assertStringContainsString('fn_idCoverAthena', $page);
        // Le bouton « Demander la mise à jour » existant ne change pas.
        self::assertStringContainsString('["pull"] call _act, false, !_wait', $page);
    }

    public function testSyncWindowFollowsTheCoverActiveOnTheSite(): void
    {
        $dir = 'mod/@COMSPEC_SSE/addons/identity/';
        $sqf = self::read($dir . 'functions/fn_idCoverAthena.sqf');
        // Champ « active » de la liste du site (SseCoverSyncService::listFor) : marque, présélection, priorité à l'import.
        self::assertStringContainsString('(_x getOrDefault ["active", false]) isEqualTo true', $sqf);
        self::assertStringContainsString('private _first = if (_onSite in _here) then { _onSite } else { _here param [0, ""] };', $sqf);
        // Site ancien (champ absent) : ni retrait des copies du poste, ni présélection.
        self::assertStringContainsString('private _knows = (_covers findIf { "active" in _x }) >= 0;', $sqf);
        // Couverture révoquée : copie retirée du poste, jamais la couverture en cours ; hors de la liste, plus d'activation.
        self::assertStringContainsString('(keys _lib) select { !(_x in _refs) && {_x isNotEqualTo _current} }', $sqf);
        self::assertStringContainsString('if (((_ui getOrDefault ["covers", []]) findIf { (_x get "ref") isEqualTo _arg }) < 0) exitWith {};', $sqf);
        self::assertStringNotContainsString('["forget"] call comspec_sse_fnc_idCoverImport', $sqf);
        // Aucune alerte Arma, aucune nouvelle requête : seules « ~covers » et « ~cover: » partent, derrière la garde.
        foreach (['hint ', 'hintSilent', 'systemChat', 'BIS_fnc_showNotification', 'cutText', 'comspec_sse_fnc_radioGuard', 'callExtension'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $sqf, $forbidden);
        }
        self::assertSame(2, substr_count($sqf, 'private _g = call _gate;'));
        self::assertSame(1, substr_count($sqf, '["pull", "~covers"] call comspec_sse_fnc_idCoverAthena'));
        // Textes des ajouts : stringtable de l'addon, anglais et français.
        $st = self::read($dir . 'stringtable.xml');
        foreach (['STR_COMSPEC_SSE_ID_CoverSiteActive', 'STR_COMSPEC_SSE_ID_CoverSiteActiveHint', 'STR_COMSPEC_SSE_ID_CoverGoneHint'] as $key) {
            self::assertStringContainsString('localize "' . $key . '"', $sqf, $key);
            self::assertMatchesRegularExpression('/<Key ID="' . $key . '">\s*<Original>[^<]+<\/Original>\s*<English>[^<]+<\/English>\s*<French>[^<]+<\/French>/', $st, $key);
        }
    }

    public function testEveryNewTextIsTranslated(): void
    {
        $en = self::read('mod/COMSPEC_ATAK_Native/Sources/addons/main/i18n/en.txt');
        $tsv = self::read('mod/@COMSPEC_SSE/tools/i18n_identity_en.tsv');
        $keys = [
            'IMPORTER MA COUVERTURE', 'Importer ma couverture', 'Oublier l\'import', 'Aucun fichier importé', 'Couverture importée : %1.',
            '1. Sur Athena > Ma couverture, cliquez Générer puis Télécharger.', '3. Ici, cliquez Importer.',
            'Ce fichier de couverture a expiré. Générez-en un nouveau sur Athena.',
        ];
        foreach ($keys as $k) {
            self::assertStringContainsString('["' . str_replace('"', '""', $k) . '", "', $en, $k);
            self::assertStringContainsString($k . "\t", $tsv, $k);
        }
        // Chaque message d'erreur du SQF a sa traduction.
        $sqf = self::read('mod/@COMSPEC_SSE/addons/identity/functions/fn_idCoverImport.sqf');
        preg_match_all('/\["(?:unknown|no_file|bad_name|too_large|json|keys|format|version|header|expired|hash|cover|read)", "([^"]+)"\]/', $sqf, $m);
        self::assertGreaterThanOrEqual(12, count($m[1]));
        foreach ($m[1] as $text) {
            self::assertStringContainsString('["' . $text . '", "', $en, $text);
        }
    }

    public function testChangelogAnnouncesItWithBothLanguages(): void
    {
        $log = str_replace("\r\n", "\n", self::read('CHANGELOG-ATAK.md'));
        $i = strpos($log, '## À venir : importer sa couverture générée sur Athena');
        if ($i !== false) {
            // Avant la sortie : entrée non versionnée.
            $block = substr($log, $i, 3000);
            self::assertStringContainsString("### À annoncer (devient « Résumé joueur » avec le numéro de version)\nType : nouveauté\nFR : ", $block);
            self::assertStringContainsString("\nEN : ", $block);
            return;
        }
        // Après la sortie : l'import de la couverture est annoncé dans le « Résumé joueur » du pack, en français et en anglais.
        $i = strpos($log, '## Pack 4.2.0 Washington');
        self::assertNotFalse($i);
        $end = strpos($log, "\n## ", $i + 1);
        $block = substr($log, $i, $end === false ? null : $end - $i);
        self::assertStringContainsString("### Résumé joueur\nType : nouveauté\nFR : ", $block);
        self::assertStringContainsString("\nEN : ", $block);
        self::assertStringContainsString('couverture', $block);
        self::assertStringContainsString('cover', $block);
    }
}
