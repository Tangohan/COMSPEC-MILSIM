/*
    Compteurs de performance pour le journal ([PERF] du RPT) : temps passé dans les boucles du téléphone (drone, marquage,
    carte, pages), pour trouver ce qui fait ramer en jeu (retour de TTRD : « le drone fait lag, ou le marquage ? »).
      ["clé", t0]   ajoute diag_tickTime - t0 au compteur « clé » : [appels, temps total, pire appel] (secondes).
      ["report"]    écrit la ligne de bilan et remet les compteurs à zéro (toutes les 30 s, XEH_postInitClient) :
                    [PERF] Boucles 30 s : 42 img/s, images lentes 3 (pire 180 ms) ; clé n× moy x ms max y ms ; ...
                    (les plus coûteuses d'abord). Rien n'est écrit si rien n'a été mesuré et aucune image n'a ralenti.
    Le chronomètre (diag_tickTime) est au dixième de milliseconde près après quelques heures de jeu : les moyennes sous
    0,1 ms sont indicatives, les totaux et les pires appels restent fiables.
*/
params [["_key", ""], ["_t0", 0]];
private _dt = diag_tickTime - _t0;
private _acc = missionNamespace getVariable ["COMSPEC_ATAK_PerfAcc", createHashMap];
if (_key isEqualTo "report") exitWith {
    // Mode profilage (fn_profiler) : c'est lui qui fait le bilan, toutes les 10 s.
    if (missionNamespace getVariable ["COMSPEC_ATAK_Prof", false]) exitWith {};
    missionNamespace setVariable ["COMSPEC_ATAK_PerfAcc", createHashMap];
    (missionNamespace getVariable ["COMSPEC_ATAK_PerfSlow", [0, 0]]) params ["_slowN", "_slowMax"];
    missionNamespace setVariable ["COMSPEC_ATAK_PerfSlow", [0, 0]];
    private _rows = (keys _acc) apply { [(_acc get _x) select 1, _x] };
    _rows sort false;
    // Dernier bilan gardé pour l'onglet PERFS du Debug : 12 plus coûteux [clé, appels, moy ms, max ms, total ms] et
    // rendus complets de page par page sur la même fenêtre (fn_pageRender).
    private _renders = uiNamespace getVariable ["COMSPEC_ATAK_RenderCount", createHashMap];
    uiNamespace setVariable ["COMSPEC_ATAK_RenderCount", createHashMap];
    missionNamespace setVariable ["COMSPEC_ATAK_PerfLast", [diag_tickTime, round diag_fps, _slowN, round (_slowMax * 1000),
        (_rows select [0, 12]) apply { private _c = _acc get (_x select 1); [_x select 1, _c select 0, (_c select 1) / ((_c select 0) max 1) * 1000, (_c select 2) * 1000, (_c select 1) * 1000] },
        _renders toArray false,
        // Suivis (3.0.1) : dessin de la carte (marqueurs, repères Athena et leur halo, zones, routes, cartes des apps
        // Zones et Routes) et papiers du SSE (conditions des menus ACE), montrés même hors des 12 plus coûteux.
        (["carte marqueurs mission (image)", "carte repères Athena (image)", "carte zones (image)", "carte routes (image)",
            "carte Zones (image)", "carte Routes (image)", "SSE papiers (idDocsOf)"] select { _x in _acc }) apply {
            private _c = _acc get _x; [_x, _c select 0, (_c select 1) / ((_c select 0) max 1) * 1000, (_c select 2) * 1000, (_c select 1) * 1000] }]];
    if ((count _acc) isEqualTo 0 && {_slowN isEqualTo 0}) exitWith {};
    private _parts = _rows apply {
        private _c = _acc get (_x select 1);
        format ["%1 %2× moy %3 ms max %4 ms", _x select 1, _c select 0, ((_c select 1) / ((_c select 0) max 1) * 1000) toFixed 2, ((_c select 2) * 1000) toFixed 1]
    };
    private _d = missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull];
    diag_log format ["[COMSPEC ATAK NATIVE][PERF] Boucles 30 s : %1 img/s, images lentes (plus de 50 ms) %2 (pire %3 ms), drone %4, cibles marquées %5, page %6 ; %7",
        round diag_fps, _slowN, round (_slowMax * 1000), ["aucun", "appairé"] select (!isNull _d), count (missionNamespace getVariable ["COMSPEC_ATAK_Locks", createHashMap]),
        (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""], [_parts joinString " ; ", "rien mesuré"] select ((count _parts) isEqualTo 0)];
};
private _c = _acc getOrDefault [_key, []];
if ((count _c) isEqualTo 0) then { _c = [0, 0, 0]; _acc set [_key, _c]; };
_c set [0, (_c select 0) + 1];
_c set [1, (_c select 1) + _dt];
if (_dt > (_c select 2)) then { _c set [2, _dt]; };
missionNamespace setVariable ["COMSPEC_ATAK_PerfAcc", _acc];
// Mesure des performances (fn_perfLog, réglage décoché par défaut) : mêmes mesures, bilans [COMSPEC perf] à part.
if (missionNamespace getVariable ["COMSPEC_ATAK_PerfLog", false]) then { ["acc", [_key, _dt]] call comspec_atak_native_fnc_perfLog; };
// Temps mesuré dans l'image en cours, toutes clés confondues : la ligne « Image figée » (XEH_postInitClient) dit
// ainsi quelle part de l'image figée a été passée dans les boucles du mod (le reste : moteur, autres mods).
missionNamespace setVariable ["COMSPEC_ATAK_PerfFrame", (missionNamespace getVariable ["COMSPEC_ATAK_PerfFrame", 0]) + _dt];
// Mode profilage : ce qui a tourné pendant l'image (saccades) et les pics de plus de 5 ms (20 par bilan).
if (missionNamespace getVariable ["COMSPEC_ATAK_Prof", false]) then {
    COMSPEC_ATAK_ProfFrame pushBack [_key, _dt];
    if (_dt > 0.005 && {(count COMSPEC_ATAK_ProfSpikes) < 20}) then { COMSPEC_ATAK_ProfSpikes pushBack [_key, _dt]; };
};
