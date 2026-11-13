<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * « Voir les vrais papiers » du Zeus : fenêtre Papiers de l'IA (fn_idZeusPapers) au lieu d'une ligne dans le bandeau de
 * ZEN. Hors jeu : câblage (serveur -> poste du Zeus, entrées ZEN / menu IA RP / IA de la zone), contrôles de la
 * visionneuse présents dans la fenêtre, clés de stringtable (français et anglais), et assemblage de l'analyse
 * (fn_idZeusAnalysis, pure) : seuils de qualité identiques à fn_idIssue, chaque défaut a son sens, explication de la
 * qualité recalculée ici sur des documents d'exemple (même table que le SQF, lue dans le fichier).
 * En jeu : mod/@COMSPEC_SSE/tools/test_id_zeus_analysis.sqf (console de débogage).
 */
final class IdZeusPapersAssetTest extends TestCase
{
    private const ID = 'mod/@COMSPEC_SSE/addons/identity/';

    public function testServerAnswersTheZeusWithDataNotABanner(): void
    {
        $srv = self::read(self::ID . 'functions/fn_idServer.sqf');
        self::assertStringContainsString('["zeusPapers", [[_u, _pub] call comspec_sse_fnc_idZeusPapersData]] call _reply;', $srv);
        self::assertStringNotContainsString('Vrais papiers de %1', $srv);
        self::assertStringNotContainsString('défauts : %1 · puce', $srv);
        // Toujours réservé au Zeus (fn_isAuthority) avant toute lecture.
        self::assertMatchesRegularExpression('/case "truth": \{\s*if !\(\[_me\] call comspec_sse_fnc_isAuthority\)/', $srv);

        $client = self::read(self::ID . 'functions/fn_idClient.sqf');
        self::assertStringContainsString('case "zeusPapers": { ["data", [_a param [0, createHashMap]]] call comspec_sse_fnc_idZeusPapers; };', $client);

        $data = self::read(self::ID . 'functions/fn_idZeusPapersData.sqf');
        self::assertStringContainsString('comspec_sse_fnc_idZeusAnalysis', $data);
        self::assertStringContainsString('[_d] call _pub', $data);
        self::assertStringContainsString('"ofClass"', $data, 'papiers trouvés dans un objet ou sur la personne');
    }

    public function testEveryEntryOpensTheWindow(): void
    {
        $open = 'call comspec_sse_fnc_idZeusPapers';
        self::assertStringContainsString('["open", [_obj]] ' . $open, self::read(self::ID . 'functions/fn_idZenRegister.sqf'));
        self::assertStringContainsString('["open", [_u]] ' . $open, self::read('mod/@COMSPEC_SSE/addons/ia_rp/functions/fn_iarpZen.sqf'));
        self::assertStringContainsString('["open", [_u, _id]] ' . $open, self::read('mod/@COMSPEC_SSE/addons/editor/functions/fn_editorAi.sqf'));
        foreach (['fn_idZenRegister.sqf', '../../ia_rp/functions/fn_iarpZen.sqf'] as $f) {
            self::assertStringNotContainsString('["send", ["truth"', self::read(self::ID . 'functions/' . $f));
        }
    }

    public function testWindowCarriesTheViewerControlsAndFunctionsAreDeclared(): void
    {
        $hpp = self::read(self::ID . 'dialogs/idZeusPapers.hpp');
        foreach ([93704, 93705, 93706, 93710, 93720] as $idc) {
            self::assertStringContainsString('idc = ' . $idc . ';', $hpp, "contrôle $idc de la visionneuse");
        }
        self::assertStringContainsString("['stop'] call comspec_sse_fnc_idFace", $hpp);
        $cfg = self::read(self::ID . 'config.cpp');
        foreach (['idZeusPapers', 'idZeusPapersData', 'idZeusAnalysis'] as $fn) {
            self::assertStringContainsString('class ' . $fn . ' {};', $cfg);
            self::assertFileExists(self::path(self::ID . 'functions/fn_' . $fn . '.sqf'));
        }
        self::assertStringContainsString('#include "dialogs\\idZeusPapers.hpp"', $cfg);
        self::assertStringContainsString('#include "ui_macros.hpp"', $cfg);
        self::assertFileExists(self::path(self::ID . 'ui_macros.hpp'));
        self::assertStringContainsString('"identity"]', self::read('mod/@COMSPEC_SSE/tools/sync_headers.py'));

        // La visionneuse garde son rendu ; en mode Zeus : zone réservée, sans zoom, scanner, relevé ni fermer.
        $viewer = self::read(self::ID . 'functions/fn_idViewerOnLoad.sqf');
        self::assertStringContainsString('private _box = _st getOrDefault ["box", []];', $viewer);
        self::assertStringContainsString('[93711, !_zeus]', $viewer);
        self::assertStringContainsString('[93713, !_zeus]', $viewer);
    }

    public function testEveryKeyExistsInFrenchAndEnglish(): void
    {
        $xml = self::read(self::ID . 'stringtable.xml');
        $doc = simplexml_load_string($xml);
        self::assertNotFalse($doc);
        $have = [];
        foreach ($doc->xpath('//Key') ?: [] as $k) {
            $id = (string) $k['ID'];
            self::assertNotSame('', trim((string) $k->French), $id . ' sans français');
            self::assertNotSame('', trim((string) $k->English), $id . ' sans anglais');
            self::assertSame(
                substr_count((string) $k->French, '%'),
                substr_count((string) $k->English, '%'),
                $id . ' : arguments différents entre français et anglais'
            );
            $have[$id] = true;
        }
        $want = [];
        foreach (['functions/fn_idZeusPapers.sqf', 'functions/fn_idZeusAnalysis.sqf', 'dialogs/idZeusPapers.hpp'] as $f) {
            $src = self::read(self::ID . $f);
            preg_match_all('/STR_COMSPEC_SSE_ZP_(\w+)/', $src, $m);
            preg_match_all('/_p \+ "(\w+)"/', $src, $m2);
            preg_match_all('/"(\w+)" call _(?:L|h)\b/', $src, $m3);
            foreach (array_merge($m[1], $m2[1], $m3[1]) as $k) {
                if (!str_ends_with($k, '_')) {
                    $want['STR_COMSPEC_SSE_ZP_' . $k] = true;
                }
            }
        }
        // Clés composées au rendu : nature, procédé, défauts, boutons et leurs info-bulles.
        $ana = self::read(self::ID . 'functions/fn_idZeusAnalysis.sqf');
        foreach (['civil', 'generated', 'alias', 'cover', 'forged'] as $k) {
            $want['STR_COMSPEC_SSE_ZP_Kind_' . $k] = true;
        }
        foreach (array_keys(self::rules($ana)) as $fk) {
            $want['STR_COMSPEC_SSE_ZP_Fk_' . $fk] = true;
        }
        foreach (self::knownFlaws($ana) as $f) {
            $want['STR_COMSPEC_SSE_ZP_F_' . $f . '_T'] = true;
            $want['STR_COMSPEC_SSE_ZP_F_' . $f . '_D'] = true;
        }
        preg_match_all('/\["\w+", "(Btn\w+)"\]/', self::read(self::ID . 'functions/fn_idZeusPapers.sqf'), $btn);
        self::assertNotEmpty($btn[1]);
        foreach ($btn[1] as $b) {
            $want['STR_COMSPEC_SSE_ZP_' . $b] = true;
            $want['STR_COMSPEC_SSE_ZP_' . $b . '_D'] = true;
        }
        $missing = array_keys(array_diff_key($want, $have));
        sort($missing);
        self::assertSame([], $missing, 'Clés absentes de identity/stringtable.xml');
    }

    public function testQualityThresholdsAreTheOnesOfIdIssue(): void
    {
        $issue = self::read(self::ID . 'functions/fn_idIssue.sqf');
        $fromIssue = [];
        preg_match_all('/case "(counterfeit|altered|photo_swap|stolen_blank)": \{(.*?)\n        \};/s', $issue, $cases, PREG_SET_ORDER);
        self::assertCount(4, $cases);
        foreach ($cases as [, $kind, $body]) {
            preg_match_all('/if \(_q < (\d+)\) then \{[^}]*?_flaws pushBack "(\w+)"/', $body, $m, PREG_SET_ORDER);
            $fromIssue[$kind] = array_map(static fn (array $x): array => [$x[2], (int) $x[1]], $m);
        }
        self::assertSame($fromIssue, self::rules(self::read(self::ID . 'functions/fn_idZeusAnalysis.sqf')));
    }

    public function testEveryFlawIdIssueCanPutHasAMeaning(): void
    {
        preg_match_all('/_flaws pushBack "(\w+)"/', self::read(self::ID . 'functions/fn_idIssue.sqf'), $m);
        $known = self::knownFlaws(self::read(self::ID . 'functions/fn_idZeusAnalysis.sqf'));
        self::assertSame([], array_values(array_diff(array_unique($m[1]), $known)));
    }

    /** Documents d'exemple : l'explication de la qualité, recalculée avec la table lue dans le SQF. */
    public function testQualityExplanationOnSampleDocuments(): void
    {
        $rules = self::rules(self::read(self::ID . 'functions/fn_idZeusAnalysis.sqf'));
        // Faux nom, contrefaçon, qualité 60 : hologramme absent (il fallait 70), police (60) et MRZ (45) évitées.
        self::assertSame(
            ['Q_head', 'Q_missing:holo:70', 'Q_avoided:font:60', 'Q_avoided:mrz:45', 'Q_eye:0.65'],
            self::explain($rules, 'forged', 'counterfeit', 60, ['holo'])
        );
        // Photo substituée, qualité 90 : rien à l'œil (85 atteint).
        self::assertSame(['Q_head', 'Q_avoided:photo:85', 'Q_eye:0.35'], self::explain($rules, 'forged', 'photo_swap', 90, []));
        // Vrais papiers (périmés ou non), couverture : la qualité ne compte pas.
        self::assertSame(['Q_na'], self::explain($rules, 'civil', '', 60, []));
        self::assertSame(['Q_cover'], self::explain($rules, 'cover', '', 90, []));
        // Le SQF suit bien ce chemin (mêmes clés, même ordre).
        $ana = self::read(self::ID . 'functions/fn_idZeusAnalysis.sqf');
        foreach (['"Q_head"', '"Q_missing"', '"Q_avoided"', '"Q_eye"', '"Q_na"', '"Q_cover"', '((1.25 - _q / 100) max 0) toFixed 2'] as $needle) {
            self::assertStringContainsString($needle, $ana);
        }
        // Même calcul de date que fn_idEvaluate (périmé).
        $days = '((_d param [0, 9999]) * 372) + ((_d param [1, 12]) * 31) + (_d param [2, 31])';
        self::assertStringContainsString($days, $ana);
        self::assertStringContainsString('(_yy * 372 + _mm * 31 + _dd)', $ana);
        self::assertFileExists(self::path('mod/@COMSPEC_SSE/tools/test_id_zeus_analysis.sqf'));
    }

    /**
     * @param array<string, list<array{0: string, 1: int}>> $rules
     * @param list<string> $flaws
     * @return list<string>
     */
    private static function explain(array $rules, string $kind, string $fk, int $q, array $flaws): array
    {
        if ($kind === 'cover') {
            return ['Q_cover'];
        }
        if ($kind !== 'forged') {
            return ['Q_na'];
        }
        $out = ['Q_head'];
        foreach ($rules[$fk] ?? [] as [$code, $need]) {
            $out[] = (in_array($code, $flaws, true) ? 'Q_missing:' : 'Q_avoided:') . $code . ':' . $need;
        }
        $out[] = 'Q_eye:' . number_format(max(0, 1.25 - $q / 100), 2, '.', '');

        return $out;
    }

    /** @return array<string, list<array{0: string, 1: int}>> */
    private static function rules(string $ana): array
    {
        self::assertSame(1, preg_match('/private _rules = createHashMapFromArray \[(.*?)\n\];/s', $ana, $m));
        preg_match_all('/\["(\w+)", \[((?:\["\w+", \d+\](?:, )?)+)\]\]/', $m[1], $rows, PREG_SET_ORDER);
        $out = [];
        foreach ($rows as [, $kind, $list]) {
            preg_match_all('/\["(\w+)", (\d+)\]/', $list, $p, PREG_SET_ORDER);
            $out[$kind] = array_map(static fn (array $x): array => [$x[1], (int) $x[2]], $p);
        }

        return $out;
    }

    /** @return list<string> */
    private static function knownFlaws(string $ana): array
    {
        self::assertSame(1, preg_match('/private _known = \[([^\]]*)\];/', $ana, $m));
        preg_match_all('/"(\w+)"/', $m[1], $k);

        return $k[1];
    }

    private static function path(string $rel): string
    {
        return dirname(__DIR__, 2) . '/' . $rel;
    }

    private static function read(string $rel): string
    {
        $p = self::path($rel);
        self::assertFileExists($p);

        return (string) file_get_contents($p);
    }
}
