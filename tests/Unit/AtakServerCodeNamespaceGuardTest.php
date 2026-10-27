<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * ATAK natif et connect, classe SEC-021 / SEC-031 (MP2-01) : du code ne doit jamais être tenu là où le réseau peut le
 * remplacer, ni construit à partir d'une valeur que le réseau fournit.
 *
 *  a/b. Variable globale ou missionNamespace (un client les réécrit par publicVariable), variable d'objet (un
 *       setVariable diffusé la réécrit) : le code va dans localNamespace / uiNamespace (jamais synchronisés, patron de
 *       fn_dataServer) ou dans une fonction CfgFunctions (finale : le moteur refuse de la réécrire).
 *  c.   `compile format` : une valeur partagée passe par str, jamais recopiée entre apostrophes ('%1').
 *  d.   Une fonction d'un autre paquet n'est appelée que si la config du jeu la déclare : un nom resté vide (paquet
 *       absent, ou jamais construit) se remplit par publicVariable.
 *
 * Rouge sur origin/main 14cdeb242 (FtCheck et PfhSpread en globale, SrvKeep, TocDo et une cinquantaine de fermetures
 * en missionNamespace, fermetures de frappe sur le drone, fn_relayCond qui compile une variable d'objet, boutons qui
 * recopient des identifiants partagés, cache ECOTI compilé, gardes !isNil sur ATHENA_fnc_getRealismParam), vert après
 * le correctif.
 */
final class AtakServerCodeNamespaceGuardTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main';
    private const CONNECT = 'mod/UptoDate/Sources/comspec-overwatch-addons/connect';

    /**
     * Globales de code non finales tolérées (rapport relaycond, § 10) :
     * - crochets atak_athena posés par le natif quand atak_athena manque : notre code ne les appelle jamais
     *   (fn_receiveOrder passe par comspec_atak_native_fnc_athenaSignal, finale) ;
     * - substitut ZEN de connect : compileFinal bloquerait le vrai ZEN chargé après connect (« Attempt to override
     *   final function », docs/bugs/2026-08-16) ; son appelant natif exige l'addon ZEN (fn_relayZeus).
     * Toute autre globale de code (`NOM = {…}` ou `NOM = compile …`) est refusée ; compileFinal est permis.
     */
    private const GLOBAL_CODE_ALLOWED = [
        'comspec_overwatch_atak_athena_fnc_athena_onNotify' => self::MAIN . '/XEH_postInitClient.sqf',
        'comspec_overwatch_atak_athena_fnc_athena_onVibrate' => self::MAIN . '/XEH_postInitClient.sqf',
        'zen_attributes_fnc_addAttribute' => self::CONNECT . '/XEH_preInit.sqf',
    ];

    /**
     * Fermetures sorties de missionNamespace (ac03bee51, 03c9285a8 et suites) et l'espace local qui les tient, plus le
     * cache ECOTI de connect (COMSPEC_EcotiDrawCache) : des données, mais ses couleurs passent par
     * fn_ecotiNormalizeColor, qui compile les chaînes ; en missionNamespace, un client le remplaçait par publicVariable.
     */
    private const MOVED = [
        'uiNamespace' => [
            'COMSPEC_ATAK_KeyMain', 'COMSPEC_ATAK_KeyPosition', 'COMSPEC_ATAK_StowDown', 'COMSPEC_ATAK_ActionBlock',
            'COMSPEC_ATAK_HitPush', 'COMSPEC_ATAK_ModelOk', 'COMSPEC_ATAK_TocDo',
            'COMSPEC_ATAK_BatterySwapAction', 'COMSPEC_ATAK_DroneCan', 'COMSPEC_ATAK_DroneIsMine', 'COMSPEC_ATAK_CamMine',
            'COMSPEC_ATAK_RepairDone', 'COMSPEC_ATAK_CleanDone', 'COMSPEC_ATAK_DataActTitles', 'COMSPEC_ATAK_DataAddAction',
            'COMSPEC_ATAK_DockStaff', 'COMSPEC_ATAK_DockActions', 'COMSPEC_ATAK_DroneFovFn', 'COMSPEC_ATAK_Mpu5Rerender',
            'COMSPEC_ATAK_Mpu5IsRadio', 'COMSPEC_ATAK_RelayZeusSet', 'COMSPEC_ATAK_RelayZeusNum', 'COMSPEC_ATAK_RelayZeusOf',
            'COMSPEC_ATAK_DateCamPose', 'COMSPEC_ATAK_V3dSet', 'COMSPEC_ATAK_ScreenCond', 'COMSPEC_ATAK_VestMark',
            'COMSPEC_ATAK_OwRepair', 'COMSPEC_ATAK_GwKids', 'COMSPEC_ATAK_PersoSet', 'COMSPEC_ATAK_VestUse',
            'COMSPEC_ATAK_NoticeKeep', 'COMSPEC_ATAK_JpcPose', 'COMSPEC_ATAK_ZeusTargets',
        ],
        'localNamespace' => [
            'COMSPEC_ATAK_FtCheck', 'COMSPEC_ATAK_SrvKeep', 'COMSPEC_fnc_isOwnedMapMarker', 'COMSPEC_CbaSettingsBootCode',
            'COMSPEC_ServerEnqueueMarker', 'COMSPEC_MarkerResyncSoon', 'COMSPEC_ATAK_DroneDetonateFn', 'COMSPEC_ATAK_DroneBoomFn',
            'COMSPEC_CombatAcePos', 'COMSPEC_ExplosiveKindFromTrigger', 'COMSPEC_ExplosiveKindFromClackers',
            'COMSPEC_GpsBeaconIsBridge', 'COMSPEC_GpsBeaconScan', 'COMSPEC_RadioProximityTxCount',
            'COMSPEC_SuperPingAttachVanilla', 'COMSPEC_ZeusPickPlayer', 'COMSPEC_ZeusOpenPlayerAtak', 'COMSPEC_ZeusApplyAtakFx',
            'COMSPEC_ZenPlaceRoleplayZone', 'COMSPEC_ZeusOpenTheaterSurvey', 'COMSPEC_ZeusToggleGpsBeacon',
            'COMSPEC_ZeusTogglePhoneTrack', 'COMSPEC_ZeusConfigurePhoneTrack', 'COMSPEC_ZeusApplyAllyTrack',
            'COMSPEC_ZeusToggleAllyTrack', 'COMSPEC_ZeusToggleEnemyAi', 'COMSPEC_ZeusCollectAllyAi',
            'COMSPEC_EcotiDrawCache',
        ],
    ];

    /**
     * `compile` d'une valeur lue sur autre chose qu'un espace local, toléré : la valeur est posée sur un contrôle
     * d'interface (variables de contrôle locales à la machine).
     */
    private const COMPILE_READ_ALLOWED = [
        self::CONNECT . '/functions/fn_mapContextMenuShow.sqf' => 'COMSPEC_MapContextAction',
    ];

    /**
     * Arguments de `compile format` lus directement dans une table, tolérés : identifiant de notification (compteur
     * local de fn_notify, nombre recopié sans guillemets) et catalogue d'apps (fn_appList).
     */
    private const FORMAT_ARG_ALLOWED = [
        self::MAIN . '/functions/pages/fn_pageNotifs.sqf' => '_n getOrDefault ["id", -1]',
        self::MAIN . '/functions/pages/fn_pageSettings.sqf' => '_x get "id"',
    ];

    /**
     * Nombre de `compile format` dont le gabarit recopie un %N entre apostrophes, par fichier, relevés et classés
     * (valeurs locales : clés figées, réglages, catalogue, radios du joueur ; rapport relaycond, § 10). Un site de plus
     * fait échouer le test : vérifier que la valeur ne vient pas du réseau, ou la passer par str, puis ajuster.
     */
    private const QUOTED_FORMAT_BASELINE = [
        self::CONNECT . '/functions/fn_registerZenAtakPlayerActions.sqf' => 1,
        self::MAIN . '/functions/datalink/fn_pageDataLink.sqf' => 1,
        self::MAIN . '/functions/garmin/fn_pageGarmin.sqf' => 1,
        self::MAIN . '/functions/map/fn_mapCtxMenu.sqf' => 1,
        self::MAIN . '/functions/map/fn_markerPalette.sqf' => 4,
        self::MAIN . '/functions/network/fn_relayZeus.sqf' => 1,
        self::MAIN . '/functions/pages/fn_firesCff.sqf' => 1,
        self::MAIN . '/functions/pages/fn_pageAlerts.sqf' => 1,
        self::MAIN . '/functions/pages/fn_pageAthena.sqf' => 1,
        self::MAIN . '/functions/pages/fn_pageBda.sqf' => 1,
        self::MAIN . '/functions/pages/fn_pageBft.sqf' => 4,
        self::MAIN . '/functions/pages/fn_pageBriefing.sqf' => 7,
        self::MAIN . '/functions/pages/fn_pageDating.sqf' => 3,
        self::MAIN . '/functions/pages/fn_pageDebug.sqf' => 2,
        self::MAIN . '/functions/pages/fn_pageDiscord.sqf' => 1,
        self::MAIN . '/functions/pages/fn_pageEw.sqf' => 2,
        self::MAIN . '/functions/pages/fn_pageFires.sqf' => 2,
        self::MAIN . '/functions/pages/fn_pageFood.sqf' => 3,
        self::MAIN . '/functions/pages/fn_pageFrs.sqf' => 1,
        self::MAIN . '/functions/pages/fn_pageGallery.sqf' => 2,
        self::MAIN . '/functions/pages/fn_pageGroup.sqf' => 5,
        self::MAIN . '/functions/pages/fn_pageLinkAlly.sqf' => 2,
        self::MAIN . '/functions/pages/fn_pageLivecam.sqf' => 1,
        self::MAIN . '/functions/pages/fn_pageLogistics.sqf' => 3,
        self::MAIN . '/functions/pages/fn_pageMedical.sqf' => 2,
        self::MAIN . '/functions/pages/fn_pageNotifs.sqf' => 3,
        self::MAIN . '/functions/pages/fn_pageOrderCompose.sqf' => 1,
        self::MAIN . '/functions/pages/fn_pageOsint.sqf' => 1,
        self::MAIN . '/functions/pages/fn_pageRecents.sqf' => 2,
        self::MAIN . '/functions/pages/fn_pageRelief.sqf' => 3,
        self::MAIN . '/functions/pages/fn_pageSettings.sqf' => 16,
        self::MAIN . '/functions/pages/fn_pageTasks.sqf' => 1,
        self::MAIN . '/functions/pages/fn_pageWaveRelay.sqf' => 6,
        self::MAIN . '/functions/pages/fn_pageWeather.sqf' => 1,
        self::MAIN . '/functions/screen/fn_pageScreenTime.sqf' => 1,
        self::MAIN . '/functions/shell/fn_aceMenu.sqf' => 2,
        self::MAIN . '/functions/shell/fn_pagePatches.sqf' => 1,
        self::MAIN . '/functions/tactical/fn_pageBreach.sqf' => 3,
        self::MAIN . '/functions/teams/fn_ftRows.sqf' => 2,
        self::MAIN . '/functions/teams/fn_pageInterTeam.sqf' => 1,
    ];

    /** Les 19 boutons corrigés (famille c) : fragment attendu après le correctif, fragment de main qui ne doit pas revenir. */
    private const FIXED_FORMATS = [
        ['pages/fn_pageMedical.sqf', "isEqualTo %1)]; [{ ['MEDICAL'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;\", str _id]", "isEqualTo '%1')]"],
        ['pages/fn_pageMedical.sqf', "\"['locate', %1] call comspec_atak_native_fnc_medicalAction;\", str format [\"%1\", _grid]]", "\"['locate', '%1'] call comspec_atak_native_fnc_medicalAction;\""],
        ['pages/fn_pageMedical.sqf', "\"['triage', 'en_cours', %1] call comspec_atak_native_fnc_medicalAction;\", str _id]", "'en_cours', '%1'"],
        ['pages/fn_pageMedical.sqf', "\"['triage', 'traite', %1] call comspec_atak_native_fnc_medicalAction;\", str _id]", "'traite', '%1'"],
        ['pages/fn_pageMedical.sqf', "\"['triage', 'kia', %1] call comspec_atak_native_fnc_medicalAction;\", str _id]", "'kia', '%1'"],
        ['pages/fn_pageMedical.sqf', "\"['locateReq', %1] call comspec_atak_native_fnc_medicalAction;\", str format [\"%1\", _r get \"id\"]]", "'locateReq', '%1'"],
        ['pages/fn_pageMedical.sqf', "\"['status', %1, %2] call comspec_atak_native_fnc_medicalAction;\", str format [\"%1\", _r get \"id\"], str format [\"%1\", _st + 1]]", "'status', '%1', '%2'"],
        ['pages/fn_pageMedical.sqf', "\"['status', %1, '-1'] call comspec_atak_native_fnc_medicalAction;\", str format [\"%1\", _r get \"id\"]]", "'status', '%1', '-1'"],
        ['pages/fn_pageLogistics.sqf', "\"['locate', %1] call comspec_atak_native_fnc_logisticsAction;\", str format [\"%1\", _id]]", "\"['locate', '%1'] call comspec_atak_native_fnc_logisticsAction;\""],
        ['pages/fn_pageLogistics.sqf', "\"['cancel', %1] call comspec_atak_native_fnc_logisticsAction;\", str format [\"%1\", _id]]", "'cancel', '%1'"],
        ['pages/fn_pageLogistics.sqf', "\"['%1', %2] call comspec_atak_native_fnc_logisticsAction;\", _a, str format [\"%1\", _id]]", "['%1', '%2'] call comspec_atak_native_fnc_logisticsAction"],
        ['teams/fn_ftRows.sqf', "\"['join', %1] call comspec_atak_native_fnc_ftAction;\", str format [\"%1\", _tid]]", "'join', '%1'"],
        ['teams/fn_ftRows.sqf', "\"['editOpen', %1] call comspec_atak_native_fnc_ftAction;\", str format [\"%1\", _tid]]", "'editOpen', '%1'"],
        ['teams/fn_ftRows.sqf', "\"['edit', %1] call comspec_atak_native_fnc_ftAction;\", str format [\"%1\", _tid]]", "'edit', '%1'"],
        ['teams/fn_ftRows.sqf', "\"['lock', %1] call comspec_atak_native_fnc_ftAction;\", str format [\"%1\", _tid]]", "'lock', '%1'"],
        ['teams/fn_ftRows.sqf', "\"['delete', %1] call comspec_atak_native_fnc_ftAction;\", str format [\"%1\", _tid]]", "'delete', '%1'"],
        ['pages/fn_pageTracking.sqf', "[[%1, %2, 0], 0.02] call comspec_atak_native_fnc_mapCenter;\", str (_p select 0), str (_p select 1)]", "_p select 0, _p select 1]"],
        ['pages/fn_pageTracking.sqf', "[[%1, %2, 0], 0.05] call comspec_atak_native_fnc_mapCenter;\", str (_c select 0), str (_c select 1)]", "_c select 0, _c select 1]"],
        ['pages/fn_pageNotifs.sqf', "\"[%1] call comspec_atak_native_fnc_navigate;\", str _page]", "\"['%1'] call comspec_atak_native_fnc_navigate;\", _page]"],
    ];

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private function read(string $rel): string
    {
        return (string) file_get_contents($this->root() . '/' . $rel);
    }

    /** Code SQF sans commentaires (// et bloc), lignes et chaînes conservées (les noms de variables en dépendent). */
    private function code(string $src): string
    {
        $out = '';
        $n = strlen($src);
        for ($i = 0; $i < $n; $i++) {
            $c = $src[$i];
            $next = $i + 1 < $n ? $src[$i + 1] : '';
            if ($c === '/' && $next === '/') {
                while ($i < $n && $src[$i] !== "\n") {
                    $i++;
                }
                $out .= "\n";
                continue;
            }
            if ($c === '/' && $next === '*') {
                $end = strpos($src, '*/', $i + 2);
                $stop = $end === false ? $n : $end + 2;
                $out .= str_repeat("\n", substr_count(substr($src, $i, $stop - $i), "\n"));
                $i = $stop - 1;
                continue;
            }
            if ($c === '"' || $c === "'") {
                $j = self::stringEnd($src, $i);
                $out .= substr($src, $i, $j - $i + 1);
                $i = $j;
                continue;
            }
            $out .= $c;
        }

        return $out;
    }

    /** Indice du guillemet qui ferme la chaîne SQF ouverte en $i (guillemet doublé = guillemet échappé). */
    private static function stringEnd(string $s, int $i): int
    {
        $q = $s[$i];
        $n = strlen($s);
        $j = $i + 1;
        while ($j < $n) {
            if ($s[$j] === $q) {
                if ($j + 1 < $n && $s[$j + 1] === $q) {
                    $j += 2;
                    continue;
                }
                break;
            }
            $j++;
        }

        return min($j, $n - 1);
    }

    /** @return array<string, string> chemin relatif => code sans commentaires, pour les .sqf du natif et de connect. */
    private function sqfCode(): array
    {
        static $cache = [];
        $key = $this->root();
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        $files = [];
        foreach ([self::MAIN, self::CONNECT] as $dir) {
            $abs = $this->root() . '/' . $dir;
            if (!is_dir($abs)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($abs, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f instanceof \SplFileInfo && $f->isFile() && strtolower($f->getExtension()) === 'sqf') {
                    $rel = substr($f->getPathname(), strlen($this->root()) + 1);
                    $files[$rel] = $this->code((string) file_get_contents($f->getPathname()));
                }
            }
        }
        ksort($files);
        self::assertGreaterThan(900, count($files), 'extraction incomplète : natif et connect attendus');

        return $cache[$key] = $files;
    }

    private static function lineOf(string $code, int $offset): int
    {
        return substr_count(substr($code, 0, $offset), "\n") + 1;
    }

    /** @return list<string> arguments de premier niveau d'une liste SQF dont le contenu (sans crochets) est donné. */
    private static function topLevelArgs(string $inner): array
    {
        $args = [];
        $depth = 0;
        $cur = '';
        $n = strlen($inner);
        for ($i = 0; $i < $n; $i++) {
            $c = $inner[$i];
            if ($c === '"' || $c === "'") {
                $j = self::stringEnd($inner, $i);
                $cur .= substr($inner, $i, $j - $i + 1);
                $i = $j;
                continue;
            }
            if ($c === '[' || $c === '(' || $c === '{') {
                $depth++;
            } elseif ($c === ']' || $c === ')' || $c === '}') {
                $depth--;
            }
            if ($c === ',' && $depth === 0) {
                $args[] = trim($cur);
                $cur = '';
                continue;
            }
            $cur .= $c;
        }
        if (trim($cur) !== '') {
            $args[] = trim($cur);
        }

        return $args;
    }

    /** @return list<array{0: int, 1: list<string>}> pour chaque `compile format [...]` : position et arguments. */
    private static function compileFormats(string $code): array
    {
        $out = [];
        if (!preg_match_all('/\bcompile(?:Final)?\s+format\s*\[/', $code, $m, PREG_OFFSET_CAPTURE)) {
            return [];
        }
        $n = strlen($code);
        foreach ($m[0] as $hit) {
            $start = (int) $hit[1] + strlen($hit[0]);
            $depth = 1;
            $i = $start;
            while ($i < $n) {
                $c = $code[$i];
                if ($c === '"' || $c === "'") {
                    $i = self::stringEnd($code, $i) + 1;
                    continue;
                }
                if ($c === '[') {
                    $depth++;
                } elseif ($c === ']') {
                    $depth--;
                    if ($depth === 0) {
                        break;
                    }
                }
                $i++;
            }
            $out[] = [(int) $hit[1], self::topLevelArgs(substr($code, $start, $i - $start))];
        }

        return $out;
    }

    public function testFixedServerHoldersAreMachineLocalOrFinal(): void
    {
        $srv = $this->code($this->read(self::MAIN . '/XEH_postInitServer.sqf'));
        foreach (['COMSPEC_ATAK_FtCheck', 'COMSPEC_ATAK_SrvKeep'] as $v) {
            self::assertMatchesRegularExpression('/localNamespace\s+setVariable\s*\[\s*"' . $v . '"/', $srv, $v);
            self::assertDoesNotMatchRegularExpression('/missionNamespace\s+setVariable\s*\[\s*"' . $v . '"/', $srv, $v);
            self::assertDoesNotMatchRegularExpression('/(?m)^\s*' . $v . '\s*=\s*\{/', $srv, $v);
        }

        // PfhSpread : fonction finale (CfgFunctions), plus de variable globale ni d'appel par ce nom.
        self::assertStringContainsString('class pfhSpread {};', $this->read(self::MAIN . '/config.cpp'));
        self::assertFileExists($this->root() . '/' . self::MAIN . '/functions/core/fn_pfhSpread.sqf');
        self::assertDoesNotMatchRegularExpression('/(?m)^\s*COMSPEC_ATAK_PfhSpread\s*=/', $this->code($this->read(self::MAIN . '/XEH_preInit.sqf')));
        foreach ($this->sqfCode() as $rel => $c) {
            self::assertStringNotContainsString('call COMSPEC_ATAK_PfhSpread', $c, $rel);
        }

        // connect : fermetures exécutées par les gestionnaires de repères (fn_initServerHub au serveur, XEH_postInit et
        // fn_startSyncLoops sur les postes, serveur hébergé compris).
        $hub = $this->code($this->read(self::CONNECT . '/functions/fn_initServerHub.sqf'));
        self::assertMatchesRegularExpression('/localNamespace\s+setVariable\s*\[\s*"COMSPEC_ServerEnqueueMarker"/', $hub);
        self::assertMatchesRegularExpression('/localNamespace\s+setVariable\s*\[\s*"COMSPEC_fnc_isOwnedMapMarker"/', $hub);
        foreach ([self::CONNECT . '/XEH_postInit.sqf', self::CONNECT . '/functions/fn_startSyncLoops.sqf'] as $rel) {
            self::assertMatchesRegularExpression('/localNamespace\s+setVariable\s*\[\s*"COMSPEC_MarkerResyncSoon"/', $this->code($this->read($rel)), $rel);
        }
    }

    public function testMovedHoldersNeverTouchMissionNamespace(): void
    {
        $all = $this->sqfCode();
        $offenders = [];
        foreach (self::MOVED as $ns => $names) {
            foreach ($names as $name) {
                $q = preg_quote($name, '/');
                $posed = false;
                foreach ($all as $rel => $c) {
                    if (preg_match('/missionNamespace\s+(?:setVariable|getVariable)\s*\[?\s*["\']' . $q . '["\']/', $c, $m, PREG_OFFSET_CAPTURE) === 1) {
                        $offenders[] = "$rel:" . self::lineOf($c, (int) $m[0][1]) . " : $name en missionNamespace";
                    }
                    if (preg_match('/(?m)^\s*' . $q . '\s*=/', $c, $m, PREG_OFFSET_CAPTURE) === 1) {
                        $offenders[] = "$rel:" . self::lineOf($c, (int) $m[0][1]) . " : $name en variable globale";
                    }
                    $posed = $posed || preg_match('/' . $ns . '\s+setVariable\s*\[\s*"' . $q . '"/', $c) === 1;
                }
                if (!$posed) {
                    $offenders[] = "$name : plus posé en $ns";
                }
            }
        }
        // Anciennes variables du drone (fn_droneCmd) : plus jamais sur l'objet.
        foreach (['COMSPEC_DroneDetonateFn', 'COMSPEC_DroneBoomFn'] as $old) {
            foreach ($all as $rel => $c) {
                if (str_contains($c, '"' . $old . '"')) {
                    $offenders[] = "$rel : $old (code sur une variable du drone)";
                }
            }
        }
        self::assertSame([], $offenders, "Fermeture revenue dans un espace synchronisable :\n" . implode("\n", $offenders));
    }

    public function testNoCodeHeldWhereTheNetworkCanReplaceIt(): void
    {
        $offenders = [];
        foreach ($this->sqfCode() as $rel => $c) {
            $add = static function (int $offset, string $what) use (&$offenders, $rel, $c): void {
                $offenders[] = "$rel:" . self::lineOf($c, $offset) . " : $what";
            };
            // Locales qui tiennent du code non final dans ce fichier.
            $codeLocals = [];
            if (preg_match_all('/(?:private\s+)?(_[A-Za-z0-9_]+)\s*=\s*(?:\{|compile\b(?!Final))/', $c, $lm)) {
                $codeLocals = array_flip($lm[1]);
            }
            // a/b. Code écrit dans missionNamespace, directement ou par une locale (quel que soit le drapeau de diffusion).
            if (preg_match_all('/missionNamespace\s+setVariable\s*\[\s*["\']([A-Za-z0-9_]+)["\']\s*,\s*(\{|compile\b(?!Final)|_[A-Za-z0-9_]+)/', $c, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($m as $hit) {
                    $val = $hit[2][0];
                    if ($val[0] !== '_' || isset($codeLocals[$val])) {
                        $add((int) $hit[0][1], "missionNamespace setVariable [\"{$hit[1][0]}\", $val …]");
                    }
                }
            }
            // a/b. Code assigné à une variable globale (donc missionNamespace), hors liste tolérée.
            if (preg_match_all('/(?m)^[ \t]*([A-Za-z][A-Za-z0-9_]*)\s*=\s*(?:\{|compile\b(?!Final))/', $c, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($m as $hit) {
                    $name = $hit[1][0];
                    if ((self::GLOBAL_CODE_ALLOWED[$name] ?? '') !== $rel) {
                        $add((int) $hit[0][1], "globale $name = code");
                    }
                }
            }
            // a/b. Code lu dans missionNamespace sous un nom écrit en dur (exécuté, ou attendu avec un code par défaut).
            if (preg_match_all('/\b(?:call|spawn)\s*\(\s*missionNamespace\s+getVariable\s*\[?\s*["\']([A-Za-z0-9_]+)["\']/', $c, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($m as $hit) {
                    $add((int) $hit[0][1], "call (missionNamespace getVariable \"{$hit[1][0]}\")");
                }
            }
            if (preg_match_all('/missionNamespace\s+getVariable\s*\[\s*["\']([A-Za-z0-9_]+)["\']\s*,\s*\{/', $c, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($m as $hit) {
                    $add((int) $hit[0][1], "missionNamespace getVariable [\"{$hit[1][0]}\", {…}] (code attendu)");
                }
            }
            // a/b. Code diffusé sur le réseau par setVariable [nom, code, true] (objet, groupe ou espace).
            if (preg_match_all('/setVariable\s*\[\s*["\']([A-Za-z0-9_]+)["\']\s*,\s*(_[A-Za-z0-9_]+)\s*,\s*true\s*\]/', $c, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($m as $hit) {
                    if (isset($codeLocals[$hit[2][0]])) {
                        $add((int) $hit[0][1], "setVariable [\"{$hit[1][0]}\", {$hit[2][0]}, true] (code diffusé)");
                    }
                }
            }
            if (preg_match_all('/setVariable\s*\[\s*["\']([A-Za-z0-9_]+)["\']\s*,\s*\{/', $c, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($m as $hit) {
                    // Fin du bloc de code (accolades équilibrées, chaînes sautées), puis drapeau de diffusion.
                    $i = (int) $hit[0][1] + strlen($hit[0][0]) - 1;
                    $depth = 0;
                    $n = strlen($c);
                    while ($i < $n) {
                        $ch = $c[$i];
                        if ($ch === '"' || $ch === "'") {
                            $i = self::stringEnd($c, $i) + 1;
                            continue;
                        }
                        if ($ch === '{') {
                            $depth++;
                        } elseif ($ch === '}') {
                            $depth--;
                            if ($depth === 0) {
                                break;
                            }
                        }
                        $i++;
                    }
                    if (preg_match('/^\}\s*,\s*true\s*\]/', substr($c, $i, 40)) === 1) {
                        $add((int) $hit[0][1], "setVariable [\"{$hit[1][0]}\", {…}, true] (code diffusé)");
                    }
                }
            }
            // b. Gestionnaire d'événement d'objet qui exécute du code lu sur l'objet lui-même (fn_droneCmd sur main).
            if (preg_match_all('/(?<![A-Za-z])add(?:MP)?EventHandler\s*\[\s*"[A-Za-z]+"\s*,\s*\{[^}]*\b(?:call|spawn)\s*\(\s*\(\s*_this\s+select\s+\d+\s*\)\s*getVariable/', $c, $m, PREG_OFFSET_CAPTURE)) {
                foreach ($m[0] as $hit) {
                    $add((int) $hit[1], "gestionnaire d'objet qui exécute une variable de l'objet");
                }
            }
        }
        self::assertSame([], $offenders, "Code tenu là où le réseau peut le remplacer :\n" . implode("\n", $offenders));
    }

    public function testNothingCompilesAnObjectOrPublicVariable(): void
    {
        $local = '/(uiNamespace|localNamespace|profileNamespace|parsingNamespace)\s+getVariable/';
        $offenders = [];
        foreach ($this->sqfCode() as $rel => $c) {
            $allowed = self::COMPILE_READ_ALLOWED[$rel] ?? null;
            if (preg_match_all('/\bcompile(?:Final)?\s+\(?\s*(_[A-Za-z0-9_]+)\b/', $c, $m, PREG_OFFSET_CAPTURE)) {
                foreach ($m[1] as $hit) {
                    $var = $hit[0];
                    $at = (int) $hit[1];
                    // Dernière affectation de cette locale avant le compile.
                    if (!preg_match_all('/(?:private\s+)?' . preg_quote($var, '/') . '\s*=\s*([^;]+);/', substr($c, 0, $at), $am)) {
                        continue;
                    }
                    $rhs = trim((string) end($am[1]));
                    if (str_contains($rhs, 'getVariable') && preg_match($local, $rhs) !== 1
                        && !($allowed !== null && str_contains($rhs, '"' . $allowed . '"'))) {
                        $offenders[] = "$rel:" . self::lineOf($c, $at) . " : compile $var ← $rhs";
                    }
                }
            }
            if (preg_match_all('/\bcompile(?:Final)?\s*\(\s*([^;]{0,120}?\bgetVariable\b)/', $c, $m, PREG_OFFSET_CAPTURE)) {
                foreach ($m[1] as $hit) {
                    if (preg_match($local, $hit[0]) !== 1) {
                        $offenders[] = "$rel:" . self::lineOf($c, (int) $hit[1]) . ' : compile (' . trim($hit[0]) . ' …)';
                    }
                }
            }
        }
        self::assertSame([], $offenders, "compile d'une valeur lue dans une variable d'objet ou publique :\n" . implode("\n", $offenders));
    }

    public function testCompiledButtonsPassSharedValuesThroughStr(): void
    {
        $all = $this->sqfCode();
        $offenders = [];
        // Les 19 boutons corrigés gardent leur forme.
        foreach (self::FIXED_FORMATS as [$file, $want, $old]) {
            $rel = self::MAIN . '/functions/' . $file;
            $c = $all[$rel] ?? '';
            if (!str_contains($c, $want)) {
                $offenders[] = "$rel : forme corrigée absente [$want]";
            }
            if (str_contains($c, $old)) {
                $offenders[] = "$rel : forme de main revenue [$old]";
            }
        }
        $quoted = [];
        foreach ($all as $rel => $c) {
            foreach (self::compileFormats($c) as [$at, $args]) {
                // Aucun argument lu directement dans une table ou une variable partagée sans passer par str.
                foreach (array_slice($args, 1) as $arg) {
                    $direct = preg_match('/^_[A-Za-z0-9_]+\s+(?:get|getOrDefault)\b/', $arg) === 1
                        || (preg_match('/^\(?\s*[A-Za-z_][A-Za-z0-9_ ]*\)?\s+getVariable\b/', $arg) === 1
                            && preg_match('/^\(?\s*(uiNamespace|localNamespace|profileNamespace|parsingNamespace)\b/', $arg) !== 1);
                    if ($direct && (self::FORMAT_ARG_ALLOWED[$rel] ?? null) !== $arg) {
                        $offenders[] = "$rel:" . self::lineOf($c, $at) . " : argument lu sans str [$arg]";
                    }
                }
                // %N recopié entre apostrophes : compté, comparé au relevé.
                if (isset($args[0]) && preg_match('/^"(?:[^"]|"")*\'%\d/', $args[0]) === 1) {
                    $quoted[$rel] = ($quoted[$rel] ?? 0) + 1;
                }
            }
        }
        foreach ($quoted as $rel => $k) {
            $max = self::QUOTED_FORMAT_BASELINE[$rel] ?? 0;
            if ($k > $max) {
                $offenders[] = "$rel : $k compile format à '%N' (relevé : $max) ; vérifier l'origine de la valeur ou la passer par str";
            }
        }
        self::assertSame([], $offenders, "compile format qui recopie une valeur partagée :\n" . implode("\n", $offenders));
    }

    public function testOtherPackageFunctionsAreCalledOnlyWhenTheConfigDeclaresThem(): void
    {
        $all = $this->sqfCode();
        $offenders = [];
        // ATHENA_fnc_getRealismParam n'est dans aucun PBO (realism_config sans config.cpp) : chaque garde passe par
        // CfgFunctions.
        foreach ($all as $rel => $c) {
            foreach (preg_split('/\R/', $c) ?: [] as $i => $line) {
                if (preg_match('/isNil\s*"ATHENA_fnc_getRealismParam"/', $line) === 1
                    && !str_contains($line, 'configFile >> "CfgFunctions" >> "ATHENA"')) {
                    $offenders[] = "$rel:" . ($i + 1) . ' : garde isNil sans CfgFunctions sur ATHENA_fnc_getRealismParam';
                }
            }
        }
        // Code serveur (fichier à garde !isServer de premier niveau, ou ligne qui teste isServer) : pas de garde isNil
        // seule sur une fonction d'un autre paquet.
        foreach ($all as $rel => $c) {
            $server = basename($rel) === 'XEH_postInitServer.sqf' || preg_match('/(?m)^if\s*\(\s*!\s*isServer\s*\)\s*exitWith/', $c) === 1;
            $own = str_starts_with($rel, self::MAIN) ? '/^(comspec_atak_native|cba|bis)_fnc_/i' : '/^(comspec_overwatch_connect|comspec_overwatch_main|cba|bis)_fnc_/i';
            foreach (preg_split('/\R/', $c) ?: [] as $i => $line) {
                if (!$server && preg_match('/\bisServer\b/', $line) !== 1) {
                    continue;
                }
                if (preg_match_all('/isNil\s*"([A-Za-z0-9_]+?_fnc_[A-Za-z0-9_]+)"/', $line, $m)) {
                    foreach ($m[1] as $name) {
                        if (preg_match($own, $name) !== 1 && !str_contains($line, 'configFile >>')) {
                            $offenders[] = "$rel:" . ($i + 1) . " : serveur, garde isNil seule sur $name (autre paquet)";
                        }
                    }
                }
            }
        }
        // Natif serveur → connect : seulement si connect est chargé.
        $connectGuard = [
            '/functions/network/fn_relayTower.sqf' => 'if (isClass (configFile >> "CfgPatches" >> "comspec_overwatch_connect")) then { [_o, true] call comspec_overwatch_connect_fnc_syncAtakRelay; };',
            '/functions/network/fn_relayConvert.sqf' => 'if (isClass (configFile >> "CfgPatches" >> "comspec_overwatch_connect")) then { [_o, true] call comspec_overwatch_connect_fnc_syncAtakRelay; };',
            '/functions/network/fn_moduleRelay.sqf' => 'if (isServer && {isClass (configFile >> "CfgPatches" >> "comspec_overwatch_connect")}) then { [_x, true] call comspec_overwatch_connect_fnc_syncAtakRelay; };',
        ];
        foreach ($connectGuard as $f => $want) {
            $c = $all[self::MAIN . $f] ?? '';
            if (!str_contains($c, $want)) {
                $offenders[] = self::MAIN . "$f : syncAtakRelay sans garde CfgPatches comspec_overwatch_connect";
            }
        }
        // Attributs Zeus des relais : seulement avec l'addon ZEN (le substitut de connect est non final).
        $zeus = $all[self::MAIN . '/functions/network/fn_relayZeus.sqf'] ?? '';
        if (!str_contains($zeus, 'private _zenAttr = isClass (configFile >> "CfgPatches" >> "zen_attributes");')
            || preg_match('/if\s*\(\s*!_zenAttr\s*\)\s*exitWith\s*\{\s*\};(?:(?!\};).)*?\]\s*call\s+zen_attributes_fnc_addAttribute/s', $zeus) !== 1) {
            $offenders[] = self::MAIN . '/functions/network/fn_relayZeus.sqf : appel de zen_attributes_fnc_addAttribute sans garde CfgPatches zen_attributes';
        }
        self::assertSame([], $offenders, "Fonction d'un autre paquet appelée sans que la config la déclare :\n" . implode("\n", $offenders));
    }
}
