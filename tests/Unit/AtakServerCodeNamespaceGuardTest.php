<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * ATAK natif et connect, classe SEC-021 / SEC-031 (MP2-01) : du code ne doit jamais être tenu là où le réseau peut le
 * remplacer, ni construit à partir d'une valeur que le réseau fournit.
 *
 *  a/b. Variable globale, missionNamespace ou variable d'objet (espaces que le réseau synchronise) : le code va dans
 *       localNamespace / uiNamespace (jamais synchronisés, patron de fn_dataServer) ou dans une fonction CfgFunctions
 *       (finale : le moteur refuse de la réécrire). Cela vaut aussi pour le code rangé dans un conteneur de ces espaces
 *       (HashMap ou tableau : files d'attente, bus d'événements, tables ; forme U1), pour une fonction appelée par un
 *       nom lu dans une valeur partagée (U2) et pour un gestionnaire dont le code est lu dans une valeur partagée (U3).
 *  c.   `compile format` : une valeur partagée passe par str, jamais recopiée entre apostrophes ('%1') ; `compile` d'une
 *       locale : relevé par fichier (cliquet).
 *  d.   Une fonction d'un autre paquet n'est appelée que si la config du jeu la déclare (CfgFunctions ou CfgPatches).
 *
 * Rouge sur origin/main 14cdeb242 (mod identique à 8f3ca9736 : FtCheck et PfhSpread en globale, SrvKeep, TocDo et une
 * cinquantaine de fermetures en missionNamespace, files serveur et d'envoi, bus d'événements et table des actions ACE en
 * missionNamespace, fermetures de frappe sur le drone, fn_relayCond qui compile une variable d'objet, boutons qui
 * recopient des identifiants partagés, cache ECOTI compilé, nom de fonction du mode photo lu dans un réglage, gardes
 * !isNil sur ATHENA_fnc_getRealismParam), vert après le correctif.
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
     * cache ECOTI de connect (COMSPEC_EcotiDrawCache : des données, mais ses couleurs passent par
     * fn_ecotiNormalizeColor, qui compile les chaînes) et, depuis l'itération 3, les conteneurs qui portent du code : file
     * serveur de connect, file d'envoi du natif, bus d'événements de connect, table des actions ACE (natif et connect).
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
            'COMSPEC_EcotiDrawCache', 'COMSPEC_ServerQueue', 'COMSPEC_ATAK_NetQueue', 'COMSPEC_EventBus',
            'COMSPEC_ATAK_AceSrc',
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

    /**
     * `compile` (ou `compileFinal`) d'une locale, par fichier, relevés et classés (rapport relaycond, § 10.1 : registres
     * localNamespace, variable de contrôle d'interface, couleurs de la config, assistants de bouton qui compilent les
     * chaînes de leur page, fn_ecotiNormalizeColor). Un site de plus fait échouer le test : vérifier que la chaîne
     * compilée ne vient ni du réseau ni d'un paramètre qu'un appel distant fournit, puis ajuster.
     */
    private const COMPILE_LOCAL_BASELINE = [
        self::MAIN . '/functions/datalink/fn_dataServer.sqf' => 1,
        self::MAIN . '/functions/map/fn_mapGameMarkers.sqf' => 1,
        self::MAIN . '/functions/map/fn_mapMarkerText.sqf' => 2,
        self::MAIN . '/functions/map/fn_mapOnDraw.sqf' => 1,
        self::MAIN . '/functions/map/fn_mapPlaceText.sqf' => 1,
        self::MAIN . '/functions/map/fn_markerCatalog.sqf' => 1,
        self::MAIN . '/functions/map/fn_markerPalette.sqf' => 1,
        self::MAIN . '/functions/map/fn_markerPaletteData.sqf' => 1,
        self::MAIN . '/functions/map/fn_strokeDraw.sqf' => 1,
        self::MAIN . '/functions/nav/fn_wpCard.sqf' => 1,
        self::MAIN . '/functions/network/fn_relayCond.sqf' => 1,
        self::MAIN . '/functions/pages/fn_pageAlerts.sqf' => 1,
        self::MAIN . '/functions/pages/fn_pageChat.sqf' => 1,
        self::MAIN . '/functions/pages/fn_pageDating.sqf' => 1,
        self::MAIN . '/functions/pages/fn_pageMap.sqf' => 1,
        self::MAIN . '/functions/pages/fn_pageReco.sqf' => 1,
        self::MAIN . '/functions/pages/fn_pageRelief.sqf' => 1,
        self::MAIN . '/functions/pages/fn_pageSalute.sqf' => 1,
        self::MAIN . '/functions/toc/fn_tocScreen.sqf' => 1,
        self::MAIN . '/functions/ui/fn_clickNext.sqf' => 1,
        self::MAIN . '/functions/ui/fn_formRender.sqf' => 1,
        self::CONNECT . '/functions/fn_ecotiNormalizeColor.sqf' => 1,
        self::CONNECT . '/functions/fn_mapContextMenuShow.sqf' => 1,
    ];

    /** Locales nommées comme du code (paramètres sans défaut typé) : une valeur ainsi nommée est traitée comme du code. */
    private const CODE_NAME = '/^_(?:code|fnc?|func\w*|handler\w*|callback\w*|cb|statement|mod)$/i';

    /** Position du code dans les gestionnaires et exécuteurs différés (forme U3). */
    private const HANDLER_CODE_ARG = [
        'addEventHandler' => 1, 'addMissionEventHandler' => 1, 'addMPEventHandler' => 1, 'ctrlAddEventHandler' => 1,
        'displayAddEventHandler' => 1, 'ctrlSetEventHandler' => 1, 'displaySetEventHandler' => 1, 'addAction' => 1,
        'addUserActionEventHandler' => 2,
    ];
    private const CALLED_HANDLER_CODE_ARGS = [
        'CBA_fnc_addEventHandler' => [1], 'CBA_fnc_addEventHandlerArgs' => [1], 'CBA_fnc_addClassEventHandler' => [2],
        'CBA_fnc_addBISEventHandler' => [2], 'CBA_fnc_addPlayerEventHandler' => [1], 'BIS_fnc_addStackedEventHandler' => [2],
        'CBA_fnc_addPerFrameHandler' => [0], 'CBA_fnc_waitAndExecute' => [0], 'CBA_fnc_execNextFrame' => [0],
        'CBA_fnc_waitUntilAndExecute' => [0, 1, 4], 'CBA_fnc_addKeybind' => [3, 4],
        'comspec_atak_native_fnc_pfhSpread' => [0],
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

    /** Position du fermant qui répond à l'ouvrant en $i (chaînes sautées), ou -1. */
    private static function closeOf(string $s, int $i): int
    {
        $depth = 0;
        $n = strlen($s);
        for ($j = $i; $j < $n; $j++) {
            $c = $s[$j];
            if ($c === '"' || $c === "'") {
                $j = self::stringEnd($s, $j);
                continue;
            }
            if ($c === '[' || $c === '(' || $c === '{') {
                $depth++;
            } elseif ($c === ']' || $c === ')' || $c === '}') {
                $depth--;
                if ($depth === 0) {
                    return $j;
                }
            }
        }

        return -1;
    }

    /** @return array<int, int> pour chaque fermant ] ) } : position de son ouvrant (chaînes sautées). */
    private static function bracketPairs(string $s): array
    {
        $pairs = [];
        $stack = [];
        $n = strlen($s);
        for ($i = 0; $i < $n; $i++) {
            $c = $s[$i];
            if ($c === '"' || $c === "'") {
                $i = self::stringEnd($s, $i);
                continue;
            }
            if ($c === '[' || $c === '(' || $c === '{') {
                $stack[] = $i;
            } elseif ($c === ']' || $c === ')' || $c === '}') {
                $open = array_pop($stack);
                if ($open !== null) {
                    $pairs[$i] = $open;
                }
            }
        }

        return $pairs;
    }

    /** Fin de l'expression qui commence en $i : premier « ; » de même profondeur, ou fermant du bloc englobant. */
    private static function exprEnd(string $s, int $i): int
    {
        $depth = 0;
        $n = strlen($s);
        for ($j = $i; $j < $n; $j++) {
            $c = $s[$j];
            if ($c === '"' || $c === "'") {
                $j = self::stringEnd($s, $j);
                continue;
            }
            if ($c === '[' || $c === '(' || $c === '{') {
                $depth++;
            } elseif ($c === ']' || $c === ')' || $c === '}') {
                if ($depth === 0) {
                    return $j;
                }
                $depth--;
            } elseif ($c === ';' && $depth === 0) {
                return $j;
            }
        }

        return $n;
    }

    /** L'expression telle qu'elle s'évalue : chaînes vidées, blocs de code {…} vidés (ce qu'ils exécutent plus tard). */
    private static function bare(string $e): string
    {
        $out = '';
        $n = strlen($e);
        for ($i = 0; $i < $n; $i++) {
            $c = $e[$i];
            if ($c === '"' || $c === "'") {
                $i = self::stringEnd($e, $i);
                $out .= '""';
                continue;
            }
            if ($c === '{') {
                $j = self::closeOf($e, $i);
                $out .= '{}';
                $i = $j < 0 ? $n : $j;
                continue;
            }
            $out .= $c;
        }

        return $out;
    }

    /** L'expression lit-elle une valeur que le réseau peut écrire : getVariable hors des espaces locaux (missionNamespace,
     *  objet, groupe), ou globale COMSPEC autre qu'une fonction ? */
    private static function readsShared(string $e): bool
    {
        $b = self::bare($e);
        if (preg_match_all('/([A-Za-z0-9_]+|[)\]])\s+getVariable\b/', $b, $m)) {
            foreach ($m[1] as $left) {
                if (!in_array($left, ['localNamespace', 'uiNamespace', 'profileNamespace', 'parsingNamespace'], true)) {
                    return true;
                }
            }
        }
        if (preg_match_all('/(?<![A-Za-z0-9_])((?i:comspec)[A-Za-z0-9_]*)/', $b, $m)) {
            foreach ($m[1] as $id) {
                if (stripos($id, '_fnc_') === false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Liaisons qui peuvent donner sa valeur à la locale $v en $pos : la dernière déclaration avant $pos (`private $v =`
     * ou entrée d'une liste params), puis les affectations qui la suivent jusqu'à $pos. Les portées ne sont pas suivies
     * plus finement (limite du test).
     * @return list<array{0: int, 1: string}> position et membre droit (« params » pour un paramètre)
     */
    private static function bindingsBefore(string $c, string $v, int $pos): array
    {
        $q = preg_quote($v, '/');
        $events = [];
        if (preg_match_all('/(?<![A-Za-z0-9_])(private\s+)?' . $q . '\s*=(?!=)\s*/', $c, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as $i => $hit) {
                $at = (int) $hit[1];
                if ($at >= $pos) {
                    continue;
                }
                $from = $at + strlen($hit[0]);
                $events[] = [$at, (string) ($m[1][$i][0] ?? '') !== '', trim(substr($c, $from, self::exprEnd($c, $from) - $from))];
            }
        }
        if (preg_match_all('/"' . $q . '"/', $c, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as $hit) {
                $at = (int) $hit[1];
                if ($at < $pos && preg_match('/\bparams\s*\[[^;]*$/', substr($c, max(0, $at - 400), min(400, $at))) === 1) {
                    $events[] = [$at, true, 'params'];
                }
            }
        }
        usort($events, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $start = 0;
        foreach ($events as $k => $e) {
            if ($e[1]) {
                $start = $k;
            }
        }

        return array_values(array_map(static fn (array $e): array => [(int) $e[0], (string) $e[2]], array_slice($events, $start)));
    }

    /** La locale $v tient-elle du code en $pos : bloc ou compile non final affecté, paramètre au défaut ou au type de
     *  code, ou paramètre sans défaut nommé comme du code (CODE_NAME). */
    private static function isCodeAt(string $c, string $v, int $pos): bool
    {
        foreach (self::bindingsBefore($c, $v, $pos) as [$at, $rhs]) {
            if ($rhs === 'params') {
                $tail = substr($c, $at, 200);
                if (preg_match('/^"[^"]+"\s*,\s*(?:\{|[^,\[\]]*,\s*\[[^\]]*\{\s*\})/', $tail) === 1
                    || (preg_match('/^"[^"]+"\s*[\],]/', $tail) === 1 && preg_match(self::CODE_NAME, $v) === 1)) {
                    return true;
                }
                continue;
            }
            if (($rhs !== '' && $rhs[0] === '{' && self::closeOf($rhs, 0) === strlen($rhs) - 1)
                || preg_match('/^compile\b(?!Final)/', $rhs) === 1) {
                return true;
            }
        }

        return false;
    }

    /** La locale $v désigne-t-elle en $pos un conteneur partagé : membre droit réduit à une lecture de missionNamespace,
     *  d'une variable d'objet ou d'une globale COMSPEC, ou extrait d'un tel conteneur (get, getOrDefault, param,
     *  select n : même tableau ou HashMap, modifié en place) ? */
    private static function isSharedAt(string $c, string $v, int $pos, int $depth = 0): bool
    {
        if ($depth > 3) {
            return false;
        }
        foreach (self::bindingsBefore($c, $v, $pos) as [$at, $rhs]) {
            if ($rhs === 'params') {
                continue;
            }
            $r = $rhs;
            while ($r !== '' && $r[0] === '(' && self::closeOf($r, 0) === strlen($r) - 1) {
                $r = trim(substr($r, 1, -1));
            }
            if (preg_match('/^(?:localNamespace|uiNamespace|profileNamespace|parsingNamespace)\b/', $r) === 1) {
                continue;
            }
            if (preg_match('/^(?:missionNamespace|_[A-Za-z0-9_]+|player|\([^()]*\))\s+getVariable\s*(?:\[[^;]*\]|"[^"]*")$/', $r) === 1
                || (preg_match('/^(?i:comspec)[A-Za-z0-9_]*$/', $r) === 1 && stripos($r, '_fnc_') === false)) {
                return true;
            }
            if (preg_match('/^(_[A-Za-z0-9_]+)\s+(?:get|getOrDefault|param|select\s*(?![\s{\[]))\b/', $r, $mm) === 1
                && self::isSharedAt($c, $mm[1], $at, $depth + 1)) {
                return true;
            }
        }

        return false;
    }

    /** Raison pour laquelle l'expression $e (hors blocs), lue en $pos, vient du réseau : lecture partagée directe, globale,
     *  ou locale liée à une lecture partagée (bindingsBefore) ; null sinon. `_x` et `_this` ne sont pas suivis. */
    private static function sharedOrigin(string $c, string $e, int $pos): ?string
    {
        if (self::readsShared($e)) {
            return 'lecture partagée';
        }
        $b = trim(self::bare($e));
        if (preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $b) === 1 && stripos($b, '_fnc_') === false
            && !in_array(strtolower($b), ['nil', 'true', 'false', 'objnull', 'grpnull', 'controlnull', 'displaynull'], true)) {
            return "globale $b";
        }
        if (preg_match_all('/(?<![A-Za-z0-9_])(_[A-Za-z0-9_]+)/', $b, $m)) {
            foreach (array_unique($m[1]) as $v) {
                if (in_array($v, ['_x', '_y', '_this', '_forEachIndex', '_thisEventHandler'], true)) {
                    continue;
                }
                foreach (self::bindingsBefore($c, $v, $pos) as [, $rhs]) {
                    if ($rhs !== 'params' && self::readsShared($rhs)) {
                        return "$v ← " . substr((string) preg_replace('/\s+/', ' ', $rhs), 0, 80);
                    }
                }
            }
        }

        return null;
    }

    /** La valeur $v range-t-elle du code : bloc, locale de code ($isCode), élément `x param [i, {…}]`, tableau littéral
     *  ou createHashMapFromArray qui en contient ? */
    private static function carriesCode(string $v, callable $isCode, int $depth = 0): bool
    {
        $v = trim($v);
        if ($v === '' || $depth > 5) {
            return false;
        }
        if ($v[0] === '{') {
            return true;
        }
        if (preg_match('/^_[A-Za-z0-9_]+$/', $v) === 1) {
            return (bool) $isCode($v);
        }
        if (preg_match('/^_[A-Za-z0-9_]+\s+param\s*\[\s*[^,\]]+,\s*\{/', $v) === 1) {
            return true;
        }
        if (preg_match('/^createHashMapFromArray\s*(\[.*)$/s', $v, $m) === 1) {
            $v = trim($m[1]);
        }
        if ($v[0] === '[' && self::closeOf($v, 0) === strlen($v) - 1) {
            foreach (self::topLevelArgs(substr($v, 1, -1)) as $e) {
                if (self::carriesCode($e, $isCode, $depth + 1)) {
                    return true;
                }
            }
        }

        return false;
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

        // connect, file serveur COMSPEC_ServerQueue (code exécuté par le serveur) : localNamespace ; fn_serverEnqueue refuse
        // un appel distant venu d'un client, file JIP comprise, avant toute écriture ; seul fn_initServerHub l'appelle, et
        // les repères y entrent à l'image suivante (hors du contexte d'un appel distant).
        $enq = $this->code($this->read(self::CONNECT . '/functions/fn_serverEnqueue.sqf'));
        $guard = 'if (isRemoteExecutedJIP || {isRemoteExecuted && {!(remoteExecutedOwner in [2, clientOwner])}}) exitWith {';
        self::assertStringContainsString($guard, $enq);
        self::assertStringContainsString('_queue set [_key, [_due, _code, _args]];', $enq);
        self::assertLessThan((int) strpos($enq, '_queue set [_key, [_due, _code, _args]];'), (int) strpos($enq, $guard), 'garde après l\'écriture');
        self::assertLessThan((int) strpos($enq, 'getVariable ["COMSPEC_ServerQueue"'), (int) strpos($enq, $guard), 'garde après la lecture');
        self::assertStringContainsString('localNamespace setVariable ["COMSPEC_ServerQueue", createHashMap];', $hub);
        self::assertStringContainsString('[{ _this call comspec_overwatch_connect_fnc_serverEnqueue; }, [', $hub);
        $callers = [];
        foreach ($this->sqfCode() as $rel => $c) {
            $k = preg_match_all('/\bcomspec_overwatch_connect_fnc_serverEnqueue\b/i', $c);
            if ($k > 0) {
                $callers[basename($rel)] = $k;
            }
        }
        self::assertSame(['fn_initServerHub.sqf' => 4], $callers, 'appelants de serverEnqueue : boucles du hub et repères');
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
        // Cliquet : compile d'une locale, par fichier (une chaîne reçue en paramètre d'une fonction appelable à distance,
        // puis compilée, serait un site de plus).
        foreach ($this->sqfCode() as $rel => $c) {
            $k = preg_match_all('/\bcompile(?:Final)?\s+\(?\s*_[A-Za-z0-9_]+\b/', $c);
            $max = self::COMPILE_LOCAL_BASELINE[$rel] ?? 0;
            if ($k > $max) {
                $offenders[] = "$rel : $k compile d'une locale (relevé : $max) ; vérifier l'origine de la chaîne, puis ajuster";
            }
        }
        self::assertSame([], $offenders, "compile d'une valeur lue dans une variable d'objet ou publique :\n" . implode("\n", $offenders));
    }

    public function testNoCodeStoredInAContainerTheNetworkCanReplace(): void
    {
        $offenders = [];
        foreach ($this->sqfCode() as $rel => $c) {
            // Modifications en place avec du code : sur un conteneur partagé (U1), ou sur une locale que l'on range ensuite.
            $received = [];
            if (preg_match_all('/(?<![A-Za-z0-9_])(_[A-Za-z0-9_]+)\s+(set|pushBack|pushBackUnique|insert|append)(?![A-Za-z0-9_])\s*/', $c, $m, PREG_OFFSET_CAPTURE)) {
                foreach ($m[1] as $i => $hit) {
                    $v = $hit[0];
                    $at = (int) $hit[1];
                    $op = $m[2][$i][0];
                    $from = (int) $m[0][$i][1] + strlen($m[0][$i][0]);
                    $val = trim(substr($c, $from, self::exprEnd($c, $from) - $from));
                    if (in_array($op, ['set', 'insert'], true) && $val !== '' && $val[0] === '[') {
                        $close = self::closeOf($val, 0);
                        $items = $close > 0 ? self::topLevelArgs(substr($val, 1, $close - 1)) : [];
                        $val = $items[1] ?? '';
                    }
                    if (!self::carriesCode($val, static fn (string $l): bool => self::isCodeAt($c, $l, $at))) {
                        continue;
                    }
                    $received[$v][] = $at;
                    if (self::isSharedAt($c, $v, $at)) {
                        $offenders[] = "$rel:" . self::lineOf($c, $at) . " : code rangé dans un conteneur partagé ($v $op …)";
                    }
                }
            }
            // Conteneur qui tient du code (reçu en place, ou littéral qui en contient) rangé là où le réseau peut le
            // remplacer : missionNamespace, globale, variable diffusée.
            $stores = [
                '/missionNamespace\s+setVariable\s*\[\s*[^,\]]+,\s*(_[A-Za-z0-9_]+)\s*[,\]]/',
                '/(?m)^[ \t]*[A-Za-z][A-Za-z0-9_]*\s*=(?!=)\s*(_[A-Za-z0-9_]+)\s*;/',
                '/setVariable\s*\[\s*[^,\]]+,\s*(_[A-Za-z0-9_]+)\s*,\s*true\s*\]/',
            ];
            foreach ($stores as $rx) {
                if (!preg_match_all($rx, $c, $m, PREG_OFFSET_CAPTURE)) {
                    continue;
                }
                foreach ($m[1] as $i => $hit) {
                    $v = $hit[0];
                    $at = (int) $m[0][$i][1];
                    $bind = self::bindingsBefore($c, $v, $at);
                    $since = $bind === [] ? 0 : $bind[0][0];
                    $holds = array_filter($received[$v] ?? [], static fn (int $p): bool => $p >= $since && $p < $at) !== [];
                    foreach ($bind as [, $rhs]) {
                        if ($rhs !== 'params' && $rhs !== '' && $rhs[0] !== '{'
                            && preg_match('/^(?:\[|createHashMapFromArray\b)/', $rhs) === 1
                            && self::carriesCode($rhs, static fn (string $l): bool => self::isCodeAt($c, $l, $at))) {
                            $holds = true;
                        }
                    }
                    if ($holds) {
                        $offenders[] = "$rel:" . self::lineOf($c, $at) . " : conteneur de code $v rangé dans un espace synchronisable";
                    }
                }
            }
        }
        self::assertSame([], $offenders, "Code rangé dans un conteneur que le réseau peut remplacer (U1) :
" . implode("
", $offenders));
    }

    public function testNoFunctionCalledByANameTheNetworkProvides(): void
    {
        $all = $this->sqfCode();
        $offenders = [];
        foreach ($all as $rel => $c) {
            if (!preg_match_all('/missionNamespace\s+getVariable\s*/', $c, $m, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($m[0] as $hit) {
                $at = (int) $hit[1];
                $i = $at + strlen($hit[0]);
                $default = null;
                if (($c[$i] ?? '') === '[') {
                    $close = self::closeOf($c, $i);
                    $args = $close > 0 ? self::topLevelArgs(substr($c, $i + 1, $close - $i - 1)) : [];
                    $name = $args[0] ?? '';
                    $default = $args[1] ?? null;
                } elseif (($c[$i] ?? '') === '(') {
                    $close = self::closeOf($c, $i);
                    $name = $close > 0 ? substr($c, $i, $close - $i + 1) : '';
                } else {
                    $name = preg_match('/\G[A-Za-z0-9_]+/', $c, $mm, 0, $i) === 1 ? $mm[0] : '';
                }
                $name = trim($name);
                if ($name === '' || $name[0] === '"' || $name[0] === "'") {
                    continue;
                }
                // Exécuté : appelé tel quel, attendu avec un code par défaut, ou rangé dans une locale appelée ensuite.
                $before = substr($c, max(0, $at - 60), $at - max(0, $at - 60));
                $run = preg_match('/(?:call|spawn)\s*\(\s*$/', $before) === 1
                    || ($default !== null && str_starts_with(trim($default), '{'));
                if (!$run && preg_match('/(_[A-Za-z0-9_]+)\s*=\s*\(?\s*$/', $before, $mv) === 1) {
                    $run = preg_match('/\b(?:call|spawn)\s+' . preg_quote($mv[1], '/') . '\b/', $c) === 1;
                }
                if (!$run) {
                    continue;
                }
                $why = self::sharedOrigin($c, $name, $at);
                if ($why !== null) {
                    $offenders[] = "$rel:" . self::lineOf($c, $at) . " : fonction appelée par un nom venu du réseau [$name] ($why)";
                }
            }
        }
        // Mode photo : le nom de fonction des réglages d'animation n'est appelé que si CfgFunctions le déclare.
        $photo = $all[self::MAIN . '/functions/pages/fn_photoMode.sqf'] ?? '';
        foreach ([
            'private _ok = false;',
            'if ((toLower _t) isEqualTo _tag && {(("true" configClasses _x) findIf { isClass (_x >> _short) }) >= 0}) exitWith { _ok = true; };',
            '} forEach ("true" configClasses (_x >> "CfgFunctions"));',
            '} forEach [configFile, missionConfigFile];',
            'if !(_known getOrDefault [_lo, false]) exitWith { {} };',
            'private _fnPrev = [_prev] call _animFn;',
            'private _fn = [_anim] call _animFn;',
            'private _fn = [_last] call _animFn;',
        ] as $want) {
            if (!str_contains($photo, $want)) {
                $offenders[] = self::MAIN . "/functions/pages/fn_photoMode.sqf : forme vérifiée absente [$want]";
            }
        }
        if (preg_match_all('/missionNamespace\s+getVariable\s*\[\s*_/', $photo) !== 1 || !str_contains($photo, 'missionNamespace getVariable [_name, {}]')) {
            $offenders[] = self::MAIN . '/functions/pages/fn_photoMode.sqf : fonction lue par un nom hors de la fermeture qui vérifie CfgFunctions';
        }
        self::assertSame([], $offenders, "Fonction appelée par un nom que le réseau fournit (U2) :\n" . implode("\n", $offenders));
    }

    public function testNoHandlerRunsCodeTheNetworkProvides(): void
    {
        $offenders = [];
        foreach ($this->sqfCode() as $rel => $c) {
            $args = [];
            foreach (self::HANDLER_CODE_ARG as $cmd => $idx) {
                if (preg_match_all('/(?<![A-Za-z0-9_])' . $cmd . '\s*\[/', $c, $m, PREG_OFFSET_CAPTURE)) {
                    foreach ($m[0] as $hit) {
                        $open = (int) $hit[1] + strlen($hit[0]) - 1;
                        $close = self::closeOf($c, $open);
                        $a = $close > 0 ? self::topLevelArgs(substr($c, $open + 1, $close - $open - 1)) : [];
                        if (isset($a[$idx])) {
                            $args[] = [(int) $hit[1], "$cmd", $a[$idx]];
                        }
                    }
                }
            }
            $pairs = null;
            foreach (self::CALLED_HANDLER_CODE_ARGS as $fn => $idxs) {
                if (preg_match_all('/\]\s*call\s+' . $fn . '\b/i', $c, $m, PREG_OFFSET_CAPTURE)) {
                    $pairs ??= self::bracketPairs($c);
                    foreach ($m[0] as $hit) {
                        $close = (int) $hit[1];
                        $open = $pairs[$close] ?? -1;
                        if ($open < 0) {
                            continue;
                        }
                        $a = self::topLevelArgs(substr($c, $open + 1, $close - $open - 1));
                        foreach ($idxs as $idx) {
                            if (isset($a[$idx])) {
                                $args[] = [$close, $fn, $a[$idx]];
                            }
                        }
                    }
                }
            }
            if (preg_match_all('/(?<![A-Za-z0-9_])(buttonSetAction|onMapSingleClick)\s+/', $c, $m, PREG_OFFSET_CAPTURE)) {
                foreach ($m[0] as $i => $hit) {
                    $from = (int) $hit[1] + strlen($hit[0]);
                    $args[] = [(int) $hit[1], $m[1][$i][0], substr($c, $from, self::exprEnd($c, $from) - $from)];
                }
            }
            foreach ($args as [$at, $what, $a]) {
                $a = trim($a);
                if ($a === '' || $a[0] === '{' || preg_match('/^(?:"(?:[^"]|"")*"|\'(?:[^\']|\'\')*\')$/', $a) === 1) {
                    continue;
                }
                $why = self::sharedOrigin($c, $a, $at);
                if ($why !== null) {
                    $offenders[] = "$rel:" . self::lineOf($c, $at) . " : $what exécute du code venu du réseau [" . substr($a, 0, 60) . "] ($why)";
                }
            }
        }
        self::assertSame([], $offenders, "Gestionnaire dont le code vient du réseau (U3) :\n" . implode("\n", $offenders));
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
