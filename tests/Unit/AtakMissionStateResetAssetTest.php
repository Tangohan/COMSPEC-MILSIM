<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Remises à zéro par mission de l'ATAK natif (MEM-006, MEM-010, MEM-011 / DEF-002, drapeaux de l'app Admin).
 *
 * uiNamespace survit d'une mission à l'autre, contrairement aux boucles CBA et aux scripts lancés par spawn. Une poignée
 * de boucle, un drapeau « en cours » ou l'état d'une app restés d'une partie précédente bloquaient une fonction (envoi
 * Bluetooth de fichiers, relevé de l'app Admin) ou affichaient des données périmées (cible « TGT », salle de plan)
 * jusqu'au redémarrage d'Arma. fn_stateInit, appelé au preInit de chaque mission (XEH_preInit), les remet à zéro.
 *
 * Test statique (lecture des .sqf ; la CI ne lance que PHPUnit). Il échoue si une de ces remises disparaît, si sa valeur
 * ne correspond plus à la lecture de la variable, si une lecture sans valeur par défaut apparaît (elle recevrait nil
 * après la remise), ou si fn_stateInit n'est plus appelé au preInit.
 */
final class AtakMissionStateResetAssetTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';

    /** @var array<string, string>|null chemin relatif => source, tous les .sqf de l'addon */
    private static ?array $sqf = null;

    private function read(string $rel): string
    {
        $src = file_get_contents(dirname(__DIR__, 2) . '/' . self::MAIN . $rel);
        self::assertNotFalse($src, 'Source illisible : ' . $rel);

        return (string) $src;
    }

    /** @return array<string, string> */
    private function allSqf(): array
    {
        if (self::$sqf === null) {
            $root = dirname(__DIR__, 2) . '/' . self::MAIN;
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            self::$sqf = [];
            foreach ($it as $file) {
                if (strtolower($file->getExtension()) === 'sqf') {
                    self::$sqf[substr($file->getPathname(), strlen($root))] = (string) file_get_contents($file->getPathname());
                }
            }
        }
        self::assertGreaterThan(500, count(self::$sqf), 'Sources SQF de l\'ATAK natif introuvables');

        return self::$sqf;
    }

    /**
     * Remises de fn_stateInit : nom => valeur ("-1", "nil" ou "false"). Commentaires retirés : un nom cité dans un
     * commentaire ne compte pas.
     *
     * @return array<string, string>
     */
    private function resets(): array
    {
        $code = (string) preg_replace('~//[^\n]*~', '', $this->read('functions/core/fn_stateInit.sqf'));
        $out = [];
        preg_match_all('~\{\s*uiNamespace\s+setVariable\s*\[\s*_x\s*,\s*(-1|nil|false)\s*\]\s*;?\s*\}\s*forEach\s*\[([^\]]*)\]~', $code, $lists, PREG_SET_ORDER);
        foreach ($lists as $list) {
            preg_match_all('~"(\w+)"~', $list[2], $names);
            foreach ($names[1] as $name) {
                $out[$name] = $list[1];
            }
        }
        preg_match_all('~uiNamespace\s+setVariable\s*\[\s*"(\w+)"\s*,\s*(-1|nil|false)\s*\]~', $code, $single, PREG_SET_ORDER);
        foreach ($single as $one) {
            $out[$one[1]] = $one[2];
        }

        return $out;
    }

    /** @return iterable<string, array{string, string, string, string}> variable, valeur, fichier qui la lit, lecture */
    public static function resetsProvider(): iterable
    {
        yield 'MEM-006 pompe d\'envoi Bluetooth' => ['COMSPEC_ATAK_BtPump', '-1', 'functions/bluetooth/fn_btAction.sqf', '(uiNamespace getVariable ["COMSPEC_ATAK_BtPump", -1]) < 0'];
        yield 'MEM-010 salle de l\'app Plan' => ['COMSPEC_ATAK_PlanRoomId', 'nil', 'functions/plan/fn_planAction.sqf', 'uiNamespace getVariable ["COMSPEC_ATAK_PlanRoomId", ""]'];
        yield 'MEM-011 / DEF-002 app Feux' => ['COMSPEC_ATAK_Fires', 'nil', 'functions/pages/fn_firesState.sqf', 'uiNamespace getVariable ["COMSPEC_ATAK_Fires", createHashMap]'];
        yield 'MEM-011 / DEF-002 app Guerre électronique' => ['COMSPEC_ATAK_Ew', 'nil', 'functions/pages/fn_ewAction.sqf', 'uiNamespace getVariable ["COMSPEC_ATAK_Ew", createHashMap]'];
        yield 'MEM-011 / DEF-002 app Logistique' => ['COMSPEC_ATAK_Logi', 'nil', 'functions/pages/fn_logisticsAction.sqf', 'uiNamespace getVariable ["COMSPEC_ATAK_Logi", createHashMap]'];
        yield 'MEM-011 / DEF-002 app BDA' => ['COMSPEC_ATAK_BdaForm', 'nil', 'functions/pages/fn_bdaAction.sqf', 'uiNamespace getVariable ["COMSPEC_ATAK_BdaForm", createHashMap]'];
        yield 'App Admin, boucle de relevé' => ['COMSPEC_ATAK_AdminPfh', 'nil', 'functions/admin/fn_pageAdmin.sqf', 'isNil { uiNamespace getVariable "COMSPEC_ATAK_AdminPfh" }'];
        yield 'App Admin, lecture du parc Athena' => ['COMSPEC_ATAK_AdminFleetBusy', 'false', 'functions/admin/fn_adminClient.sqf', 'uiNamespace getVariable ["COMSPEC_ATAK_AdminFleetBusy", false]'];
        yield 'App Admin, redessin différé' => ['COMSPEC_ATAK_AdminRenderQ', 'false', 'functions/admin/fn_adminClient.sqf', 'uiNamespace getVariable ["COMSPEC_ATAK_AdminRenderQ", false]'];
    }

    #[DataProvider('resetsProvider')]
    public function testVariableIsResetAtEachMission(string $var, string $value, string $readerFile, string $read): void
    {
        $resets = $this->resets();
        self::assertArrayHasKey($var, $resets, $var . ' n\'est plus remise à zéro par fn_stateInit');
        self::assertSame($value, $resets[$var], 'Valeur de remise à zéro de ' . $var);
        // La valeur suit la lecture : -1 pour un test « < 0 », nil pour isNil ou une lecture avec valeur par défaut,
        // false pour un drapeau lu avec false par défaut. Si cette lecture change, la remise à zéro est à revoir.
        self::assertStringContainsString($read, $this->read($readerFile), 'Lecture attendue de ' . $var);
    }

    #[DataProvider('resetsProvider')]
    public function testNoReadDependsOnAValueFromThePreviousMission(string $var): void
    {
        // Une lecture sans valeur par défaut (uiNamespace getVariable "NOM") recevrait nil après la remise à zéro :
        // seule la forme isNil { … } est admise.
        $bare = [];
        foreach ($this->allSqf() as $rel => $src) {
            if (preg_match_all('~getVariable\s+"' . $var . '"~', $src, $m, PREG_OFFSET_CAPTURE)) {
                foreach ($m[0] as [, $at]) {
                    if (!preg_match('~isNil\s*\{\s*uiNamespace\s*$~', substr($src, max(0, $at - 40), $at - max(0, $at - 40)))) {
                        $bare[] = $rel;
                    }
                }
            }
        }
        self::assertSame([], $bare, 'Lecture de ' . $var . ' sans valeur par défaut');
    }

    public function testStateInitRunsAtEachMissionPreInit(): void
    {
        // Seul le preInit de mission appelle fn_stateInit sans condition ; fn_open ne l'appelle que si l'état manque,
        // donc jamais en pleine mission (aucune boucle vivante n'est oubliée par ces remises).
        self::assertMatchesRegularExpression('~^\[\]\s*call\s+comspec_atak_native_fnc_stateInit\s*;~m', $this->read('XEH_preInit.sqf'));
        self::assertStringContainsString(
            'if (isNil { uiNamespace getVariable "COMSPEC_ATAK_State" }) then { [] call comspec_atak_native_fnc_stateInit; };',
            $this->read('functions/ui/fn_open.sqf')
        );
    }
}
