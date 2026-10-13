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
private _acc = missionNamespace getVariable ["COMSPEC_ATAK_PerfAcc", createHashMap];
if (_key isEqualTo "report") exitWith {
    missionNamespace setVariable ["COMSPEC_ATAK_PerfAcc", createHashMap];
    (missionNamespace getVariable ["COMSPEC_ATAK_PerfSlow", [0, 0]]) params ["_slowN", "_slowMax"];
    missionNamespace setVariable ["COMSPEC_ATAK_PerfSlow", [0, 0]];
    if ((count _acc) isEqualTo 0 && {_slowN isEqualTo 0}) exitWith {};
    private _rows = (keys _acc) apply { [(_acc get _x) select 1, _x] };
    _rows sort false;
    private _parts = _rows apply {
        private _c = _acc get (_x select 1);
        format ["%1 %2× moy %3 ms max %4 ms", _x select 1, _c select 0, ((_c select 1) / ((_c select 0) max 1) * 1000) toFixed 2, ((_c select 2) * 1000) toFixed 1]
    };
    private _d = missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull];
    diag_log format ["[COMSPEC ATAK NATIVE][PERF] Boucles 30 s : %1 img/s, images lentes (plus de 50 ms) %2 (pire %3 ms), drone %4, cibles marquées %5, page %6 ; %7",
        round diag_fps, _slowN, round (_slowMax * 1000), ["aucun", "appairé"] select (!isNull _d), count (missionNamespace getVariable ["COMSPEC_ATAK_Locks", createHashMap]),
        (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""], [_parts joinString " ; ", "rien mesuré"] select ((count _parts) isEqualTo 0)];
};
private _dt = diag_tickTime - _t0;
private _c = _acc getOrDefault [_key, []];
if ((count _c) isEqualTo 0) then { _c = [0, 0, 0]; _acc set [_key, _c]; };
_c set [0, (_c select 0) + 1];
_c set [1, (_c select 1) + _dt];
if (_dt > (_c select 2)) then { _c set [2, _dt]; };
missionNamespace setVariable ["COMSPEC_ATAK_PerfAcc", _acc];
