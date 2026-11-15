<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseFileRepository;
use App\Support\SseCaptureNotes;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseFilesSqlite;

/**
 * Réalisme du SEEK II : motifs de capture (blessures, post-mortem) validés et rangés avec la fiche, réglages
 * d'interactions et de taille, lecture médicale sans dépendance dure, câblage du dialogue relu dans les sources.
 */
final class SeekRealismeTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';
    private const CONNECT = self::ROOT . 'mod/UptoDate/Sources/comspec-overwatch-addons/connect/';
    private const SSE = self::ROOT . 'mod/@COMSPEC_SSE/addons/biometrics/';

    private ?PDO $pdo = null;

    protected function tearDown(): void
    {
        if ($this->pdo !== null) {
            SseFilesSqlite::close();
            $this->pdo = null;
        }
    }

    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents($path));
    }

    // ------------------------------------------------------------------ motifs de capture (site)

    public function testNotesAreCleanedAndBounded(): void
    {
        $long = str_repeat('é', 400);
        $notes = SseCaptureNotes::notes([
            'main droite : non capturable (garrot posé)', "  <b>script</b>\n\t ", 12, null, ['x'], 'main droite : non capturable (garrot posé)', $long,
            'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h',
        ]);
        self::assertCount(SseCaptureNotes::MAX_NOTES, $notes);
        self::assertSame('main droite : non capturable (garrot posé)', $notes[0]);
        self::assertSame('script', $notes[1]);
        self::assertSame(SseCaptureNotes::NOTE_LENGTH, mb_strlen($notes[2]));
        foreach ($notes as $n) {
            self::assertStringNotContainsString('<', $n);
            self::assertDoesNotMatchRegularExpression('/[\x00-\x1F]/', $n);
        }
        self::assertSame([], SseCaptureNotes::notes('texte'));
    }

    public function testDetailKeepsIntegersInRange(): void
    {
        self::assertSame([-2, 0, 100, 55, 7], SseCaptureNotes::detail([-9, 0, 5000, '55', 6.6, 'x', null]));
        self::assertCount(SseCaptureNotes::MAX_DETAIL, SseCaptureNotes::detail(range(1, 40)));
        self::assertSame([], SseCaptureNotes::detail('x'));
    }

    public function testCaptureFieldIsOptionalAndOnlyKnownModalitiesSurvive(): void
    {
        self::assertNull(SseCaptureNotes::encode(null));
        self::assertNull(SseCaptureNotes::encode(['gps' => ['notes' => ['x']]]));
        $json = SseCaptureNotes::encode([
            'empreintes' => ['quality' => 140, 'detail' => [1, 2, -2], 'notes' => ['relevé post-mortem']],
            'iris' => ['quality' => 'abc', 'detail' => [], 'notes' => []],
            'adn' => ['notes' => ['ignoré']],
        ]);
        self::assertNotNull($json);
        $back = SseCaptureNotes::decode($json);
        self::assertSame(['empreintes'], array_keys($back));
        self::assertSame(100, $back['empreintes']['quality']);
        self::assertSame([1, 2, -2], $back['empreintes']['detail']);
        self::assertSame(['relevé post-mortem'], $back['empreintes']['notes']);
        self::assertSame([], SseCaptureNotes::decode('pas du json'));
        self::assertSame('Empreintes', SseCaptureNotes::label('empreintes'));
    }

    public function testSeekRecordKeepsCaptureNotesOnSqlite(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = SseFilesSqlite::open();
        $files = new SseFileRepository();
        $stored = $files->upsertSeek(1, [
            'rec' => 'REC-20261115-0042', 'idempotency_key' => 'SEEK-CAP-1', 'verdict' => 'concordant', 'subject_unknown' => 0, 'enrolled' => 0,
            'mod_fingers' => 1, 'mod_iris' => 0, 'mod_face' => 0,
            'capture_json' => SseCaptureNotes::encode(['empreintes' => ['quality' => 61, 'detail' => [2, 3, -2], 'notes' => ['main gauche : non capturable (garrot posé)']]]),
        ]);
        // findSeek rend déjà la fiche hydratée (motifs décodés).
        $seek = $files->findSeek($stored['id'], 1);
        self::assertIsArray($seek);
        self::assertSame(['main gauche : non capturable (garrot posé)'], $seek['capture']['empreintes']['notes']);
        self::assertSame(61, $seek['capture']['empreintes']['quality']);
        // Fiche sans motifs : champ vide, jamais d'erreur.
        $other = $files->upsertSeek(1, ['rec' => 'REC-20261115-0043', 'idempotency_key' => 'SEEK-CAP-2', 'verdict' => 'inconnu', 'subject_unknown' => 1, 'enrolled' => 0, 'mod_fingers' => 0, 'mod_iris' => 0, 'mod_face' => 0]);
        self::assertSame([], (array) ($files->findSeek($other['id'], 1) ?? [])['capture']);
    }

    // ------------------------------------------------------------------ jeu : médical et capture

    public function testMedicalReadHasNoHardDependencyAndCanBeDisabled(): void
    {
        $med = self::read(self::SSE . 'functions/fn_bioMedical.sqf');
        foreach (['ace_medical', 'kat_main', 'kat_advancedMedical', 'acm_main'] as $patch) {
            self::assertStringContainsString('isClass (configFile >> "CfgPatches" >> "' . $patch . '")', $med);
        }
        self::assertStringContainsString('ace_medical_tourniquets', $med);
        self::assertStringContainsString('ace_medical_fractures', $med);
        self::assertStringContainsString('ace_medical_bandagedWounds', $med);
        self::assertStringContainsString('getHitPointDamage', $med);
        self::assertStringContainsString('comspec_seek_fx_medical', $med);
        foreach (['garrot posé', 'attelle posée', 'brûlure', 'bandage'] as $why) {
            self::assertStringContainsString($why, $med);
        }
        self::assertStringContainsString('class bioMedical {};', self::read(self::SSE . 'config.cpp'));

        $cap = self::read(self::SSE . 'functions/fn_bioCapture.sqf');
        self::assertStringContainsString('comspec_sse_fnc_bioMedical', $cap);
        self::assertStringContainsString('non capturable', $cap);
        self::assertStringContainsString('relevé post-mortem', $cap);
        self::assertStringContainsString('_out set ["reasons", _reasons];', $cap);
    }

    public function testInteractionsSettingsAndDialogWiring(): void
    {
        $pre = self::read(self::CONNECT . 'XEH_preInit.sqf');
        foreach (['comspec_seek_fx_mode', 'comspec_seek_fx_medical', 'comspec_seek_scale', 'comspec_seek_anchor'] as $setting) {
            self::assertStringContainsString('"' . $setting . '"', $pre);
        }
        self::assertStringContainsString('[[0, 1, 2], ["Aucune", "Simples", "Complètes"], 2]', $pre);

        $cfg = self::read(self::CONNECT . 'config.cpp');
        self::assertStringContainsString('class seekFx {};', $cfg);
        self::assertStringContainsString('class seekScale {};', $cfg);

        $hpp = self::read(self::CONNECT . 'display_sse_person.hpp');
        foreach (['idc = 9710;', 'idc = 9711;', 'idc = 9712;'] as $idc) {
            self::assertSame(1, substr_count($hpp, $idc), $idc);
        }
        self::assertStringContainsString("['cycleMode'] call comspec_overwatch_connect_fnc_seekFx", $hpp);
        self::assertStringContainsString('9710, 9711, 9712', self::read(self::CONNECT . 'functions/fn_sseTerminalPage.sqf'));

        $load = self::read(self::CONNECT . 'functions/fn_ssePersonDialogOnLoad.sqf');
        self::assertStringContainsString('["hook"] call comspec_overwatch_connect_fnc_seekFx', $load);
        self::assertStringContainsString('["apply"] call comspec_overwatch_connect_fnc_seekScale', $load);

        // Mode « aucune » et autre matériel : le déroulé d'avant (barre ACE) reste en place.
        $sample = self::read(self::CONNECT . 'functions/fn_sseBiometricSample.sqf');
        self::assertStringContainsString('_fxMode > 0', $sample);
        self::assertStringContainsString('ace_common_fnc_progressBar', $sample);
        self::assertStringContainsString('["capture", [_kind, _target, {', $sample);
    }

    public function testFxNeverShowsAlertsAndAlwaysTerminates(): void
    {
        $fx = self::read(self::CONNECT . 'functions/fn_seekFx.sqf');
        foreach (['systemChat', 'hintSilent', 'hint ', 'titleText', 'cutText', 'BIS_fnc_showNotification', 'comspec_overwatch_connect_fnc_announce'] as $banned) {
            self::assertStringNotContainsString($banned, $fx, $banned);
        }
        // Frame-indépendant : durées par diag_tickTime ; bouton PASSER et délai maximum sur chaque mini-jeu.
        self::assertStringContainsString('diag_tickTime', $fx);
        self::assertStringContainsString('COMSPEC_SeekFxSkip', $fx);
        self::assertGreaterThanOrEqual(4, substr_count($fx, 'diag_tickTime - _t0'));
        foreach (['_hold', '_track', '_cables', '_wipe', '_auto', '_place'] as $game) {
            self::assertStringContainsString('private ' . $game . ' = {', $fx);
        }
        self::assertStringContainsString('"MAIN DROITE · 4 DOIGTS"', $fx);
        self::assertStringContainsString('"POUCES · 2 DOIGTS"', $fx);
        self::assertStringContainsString('non capturable', $fx);
        // État local au joueur : rien n'est publié sur le réseau.
        self::assertStringNotContainsString('publicVariable', $fx);
        self::assertStringNotContainsString('remoteExec', $fx);

        $scale = self::read(self::CONNECT . 'functions/fn_seekScale.sqf');
        self::assertStringContainsString('ctrlSetScale', $scale);
        self::assertStringContainsString('profileNamespace setVariable ["COMSPEC_SeekScale", _k]', $scale);
        self::assertStringContainsString('[1, 0.75, 0.55, 0.4]', $scale);
    }

    public function testCaptureNotesTravelWithTheRecordAndTheSamples(): void
    {
        $send = self::read(self::CONNECT . 'functions/fn_seekSend.sqf');
        self::assertStringContainsString('_payload set ["capture", _capture]', $send);
        $submit = self::read(self::CONNECT . 'functions/fn_ssePersonDialogSubmit.sqf');
        self::assertStringContainsString('"detail":[%4],"notes":[%5]', $submit);
        $svc = self::read(self::ROOT . 'app/Services/Sse/SseFileQueryService.php');
        self::assertStringContainsString("SseCaptureNotes::encode(\$body['capture'] ?? null)", $svc);
        self::assertStringContainsString('capture_json', self::read(self::ROOT . 'bootstrap/sse_file_queries_migration.php'));
        self::assertStringContainsString('sse_seek_capture_notes', self::read(self::ROOT . 'database/steps/registry.php'));
        self::assertStringContainsString('sse_seek_records DROP COLUMN capture_json', self::read(self::ROOT . 'migrations/rollback/sse_seek_capture_notes_down.sql'));
        self::assertStringContainsString("\$s['capture']", self::read(self::ROOT . 'views/atak/sse/files/seek.php'));
    }

    /** @return iterable<string, array{0: string}> */
    public static function sqfFiles(): iterable
    {
        foreach (['fn_seekFx', 'fn_seekScale'] as $f) {
            yield $f => [self::CONNECT . 'functions/' . $f . '.sqf'];
        }
        yield 'fn_bioMedical' => [self::SSE . 'functions/fn_bioMedical.sqf'];
    }

    #[DataProvider('sqfFiles')]
    public function testSqfBracketsAreBalanced(string $path): void
    {
        $s = self::read($path);
        $s = (string) preg_replace('/"(?:[^"]|"")*"/', '', $s);
        $s = (string) preg_replace('#//[^\n]*#', '', $s);
        $s = (string) preg_replace('#/\*.*?\*/#s', '', $s);
        foreach (['{}', '[]', '()'] as $pair) {
            self::assertSame(substr_count($s, $pair[0]), substr_count($s, $pair[1]), basename($path) . ' ' . $pair);
        }
    }
}
