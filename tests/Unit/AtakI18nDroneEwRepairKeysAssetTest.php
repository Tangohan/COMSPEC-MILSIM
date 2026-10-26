<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * DEF-005, DEF-017, DEF-029 — textes affichés en français à un joueur anglais : relèvement « GONIO » sur la carte,
 * notification « Téléphone ATAK réparé · … », libellés drone « OBSERVER » et « NEUTR. », suffixe des cadres de
 * verrouillage de la vue FLIR (« LOCK_003 450m PERDU » passait entier à fn_tr, sans clé).
 *
 * Test statique (lecture de i18n/en.txt et des .sqf). Il échoue si une des quatre clés disparaît ou change, si
 * le texte du code ne correspond plus à sa clé, si le suffixe FLIR n'est plus traduit à part (comme dans
 * fn_lockAction), ou si en.txt n'est plus trié comme l'écrit tools/i18n.py (clé en minuscules).
 */
final class AtakI18nDroneEwRepairKeysAssetTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';

    private function read(string $rel): string
    {
        $src = file_get_contents(dirname(__DIR__, 2) . '/' . self::MAIN . $rel);
        self::assertNotFalse($src, 'Source illisible : ' . $rel);

        return (string) $src;
    }

    /** @return list<array{0: string, 1: string}> paires [français, anglais] de en.txt, dans l'ordre du fichier */
    private function pairs(): array
    {
        $out = [];
        $bad = [];
        foreach (explode("\n", $this->read('i18n/en.txt')) as $l) {
            if ($l === '[' || $l === ']' || $l === '') {
                continue;
            }
            if (preg_match('~^\["((?:[^"]|"")*)", "((?:[^"]|"")*)"\],?$~', $l, $m) !== 1) {
                $bad[] = $l;
                continue;
            }
            $out[] = [str_replace('""', '"', $m[1]), str_replace('""', '"', $m[2])];
        }
        self::assertSame([], $bad, 'Lignes illisibles dans en.txt');

        return $out;
    }

    public static function keysProvider(): array
    {
        return [
            'DEF-005 relèvement gonio' => ['GONIO', 'DF'],
            'DEF-017 réparation' => ['Téléphone ATAK réparé · %1', 'ATAK phone repaired · %1'],
            'DEF-029 tâche Observer' => ['OBSERVER', 'OBSERVE'],
            'DEF-029 verrouillage neutralisé' => ['NEUTR.', 'DOWN'],
        ];
    }

    #[DataProvider('keysProvider')]
    public function testKeyIsTranslated(string $fr, string $en): void
    {
        $found = array_values(array_filter($this->pairs(), static fn (array $p): bool => $p[0] === $fr));
        self::assertCount(1, $found, 'Clé absente (ou en double) dans en.txt : ' . $fr);
        self::assertSame($en, $found[0][1]);
    }

    public function testDictionaryStaysSortedLikeI18nTool(): void
    {
        // tools/i18n.py save_dict : sorted(clé.lower()). Ordre des octets UTF-8 = ordre des points de code.
        $keys = array_map(static fn (array $p): string => mb_strtolower($p[0], 'UTF-8'), $this->pairs());
        self::assertGreaterThan(15000, count($keys));
        $inversions = [];
        for ($i = 1, $n = count($keys); $i < $n; $i++) {
            if (strcmp($keys[$i - 1], $keys[$i]) > 0) {
                $inversions[] = '« ' . $keys[$i - 1] . ' » avant « ' . $keys[$i] . ' »';
            }
        }
        self::assertSame([], $inversions, 'en.txt n\'est plus trié comme l\'écrit tools/i18n.py');
    }

    public function testCodeSendsExactlyTheseTextsToTranslation(): void
    {
        // Relèvement : le type (« GONIO », « BROUILLEUR »…) passe seul par fn_tr.
        self::assertStringContainsString('format ["%1 %2°", _kind call comspec_atak_native_fnc_tr', $this->read('functions/map/fn_ewDraw.sqf'));
        // Réparation : notification traduite à l'affichage, texte identique à la clé.
        self::assertStringContainsString('format ["Téléphone ATAK réparé · %1", ["Caisse à outils utilisée.", "Kit de réparation consommé."] select _kit]', $this->read('functions/core/fn_repairStart.sqf'));
        // Tâche drone sur la carte.
        self::assertStringContainsString('([" ORBITE", " OBSERVER"] select (_k isEqualTo "OBSERVE")) call comspec_atak_native_fnc_tr', $this->read('functions/drone/fn_droneDraw.sqf'));
    }

    public function testLockSuffixIsTranslatedApartInFlirAndLockViews(): void
    {
        $suffix = '(["", " PERDU", " NEUTR."] select ((["TRACK", "LOST", "DOWN"] find _state) max 0)) call comspec_atak_native_fnc_tr';
        foreach (['functions/drone/fn_droneFlir.sqf', 'functions/drone/fn_lockAction.sqf'] as $f) {
            self::assertStringContainsString($suffix, $this->read($f), $f . ' : suffixe du cadre de verrouillage non traduit à part');
        }
    }
}
