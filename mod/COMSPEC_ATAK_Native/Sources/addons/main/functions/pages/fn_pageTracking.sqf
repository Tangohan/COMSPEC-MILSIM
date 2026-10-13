/*
    App MARQUER TRACKING : toutes les cibles marquées au viseur (caméra du drone ou appareil photo du téléphone,
    fn_lockAction) pour les retrouver et les effacer, et les zones interdites au drone du camp (fn_noFly).
    Pour chaque cible : VOIR SUR LA CARTE, EN MARQUEUR (marqueur permanent prérempli), EN FICHE SSE (fiche FRO
    préremplie), CAMÉRA DU DRONE (caméra accrochée dessus, vue incrustée sur la carte), OUBLIER.
    Filtre (clé trkFilter de l'état) : TOUTES, ACCROCHÉES, PERDUES, NEUTRALISÉES.
    Params (app déclarée avec function=) : [page, [x, y, largeur, hauteur]]
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; [[_t, "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace };
private _filter = _s getOrDefault ["trkFilter", "ALL"];
private _locks = ["list"] call comspec_atak_native_fnc_lockAction;
private _drone = !isNull (missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull]);
private _re = "[{ ['TRACKING'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;";
private _count = { params ["_st"]; { ((_x select 1) get "state") isEqualTo _st } count _locks };
private _rows = [];

_rows pushBack ["section", format ["Cibles marquées : %1", count _locks], "Marquées à la caméra du drone ou à l'appareil photo du téléphone (touche « Marquer la cible »). Le marqueur suit la cible sur la carte tant qu'elle est en vue."];
_rows pushBack ["segment", "Afficher", [
    [format ["TOUTES (%1)", count _locks], compile format ["(uiNamespace getVariable 'COMSPEC_ATAK_State') set ['trkFilter', 'ALL']; %1", _re], _filter isEqualTo "ALL"],
    [format ["ACCROCHÉES (%1)", ["TRACK"] call _count], compile format ["(uiNamespace getVariable 'COMSPEC_ATAK_State') set ['trkFilter', 'TRACK']; %1", _re], _filter isEqualTo "TRACK"],
    [format ["PERDUES (%1)", ["LOST"] call _count], compile format ["(uiNamespace getVariable 'COMSPEC_ATAK_State') set ['trkFilter', 'LOST']; %1", _re], _filter isEqualTo "LOST"],
    [format ["NEUTRALISÉES (%1)", ["DOWN"] call _count], compile format ["(uiNamespace getVariable 'COMSPEC_ATAK_State') set ['trkFilter', 'DOWN']; %1", _re], _filter isEqualTo "DOWN"]
]];
private _shown = _locks select { _filter isEqualTo "ALL" || {((_x select 1) get "state") isEqualTo _filter} };
if ((count _shown) isEqualTo 0) then {
    _rows pushBack ["text", format ["<t color='#8a9a93'>%1</t>", ["Aucune cible dans ce filtre.", "Aucune cible marquée. Visez une personne ou un véhicule avec la caméra du drone ou l'appareil photo, puis appuyez sur « Marquer la cible »."] select ((count _locks) isEqualTo 0)]];
};
{
    _x params ["_k", "_e"];
    private _st = _e get "state";
    private _hex = (createHashMapFromArray [["TRACK", "#e5483a"], ["LOST", "#8a9a93"], ["DOWN", "#5cc76b"]]) getOrDefault [_st, "#c9d4cf"];
    private _ago = round (time - (_e get "seen"));
    private _p = _e get "pos";
    _rows pushBack ["text", format ["<t font='COMSPEC_BarlowBold'>LOCK %1 · %2</t>  <t size='0.8' color='%3'>%4</t><br/><t size='0.8' color='#8a9a93'>%5 · %6 m · %7 · marqué à %8 (%9)</t>",
        [_e get "num", 3] call CBA_fnc_formatNumber, [_e get "name"] call _esc, _hex,
        (createHashMapFromArray [["TRACK", "ACCROCHÉE"], ["LOST", "PERDUE"], ["DOWN", "NEUTRALISÉE"]]) getOrDefault [_st, _st],
        [_p, 8] call comspec_atak_native_fnc_gridRef, round (player distance2D _p), [format ["vue il y a %1 s", _ago], "en vue"] select (_ago < 2),
        _e get "clock", ["drone", "téléphone"] select ((_e get "src") isEqualTo "PHONE")]];
    _rows pushBack ["buttons", [
        ["CARTE", compile format ["['MAP'] call comspec_atak_native_fnc_navigate; [[%1, %2, 0], 0.02] call comspec_atak_native_fnc_mapCenter;", _p select 0, _p select 1], false, true, "Centre la carte sur la cible"],
        ["EN MARQUEUR", compile format ["['toMarker', %1] call comspec_atak_native_fnc_lockAction;", str _k], false, true, "Pose un marqueur permanent à sa position (éditeur de marqueur prérempli)"],
        ["EN FICHE SSE", compile format ["['toSse', %1] call comspec_atak_native_fnc_lockAction;", str _k], false, true, "Ouvre une fiche SSE d'observation (FRO) préremplie avec la cible"],
        ["CAMÉRA DU DRONE", compile format ["['droneCam', %1] call comspec_atak_native_fnc_lockAction;", str _k], false, _drone && {_st isNotEqualTo "DOWN"}, "Pointe la caméra du drone appairé sur la cible et la garde dessus (vue incrustée sur la carte)"],
        ["OUBLIER", compile format ["['release', %1] call comspec_atak_native_fnc_lockAction; %2", str _k, _re], false, true, "Retire le marquage de cette cible, sur le téléphone et sur la carte"]
    ]];
} forEach (_shown select [0, 30]);
if ((count _locks) > 0) then {
    _rows pushBack ["buttons", [["OUBLIER TOUTES LES CIBLES", compile format ["['release', ''] call comspec_atak_native_fnc_lockAction; %1", _re], false, true]]];
};

// Zones interdites au drone (camp du joueur).
private _zones = ["list"] call comspec_atak_native_fnc_noFly;
_rows pushBack ["section", format ["Zones interdites au drone : %1", count _zones], "Clic droit sur la carte > « Zone interdite au drone ». Un drone du camp qui y entre en ressort aussitôt ; un ordre qui y mène est refusé."];
if ((count _zones) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucune zone interdite.</t>"]; };
{
    _x params ["_id", "_c", "_r", "_who"];
    _rows pushBack ["text", format ["<t font='COMSPEC_BarlowBold'>%1</t>  <t size='0.8' color='#8a9a93'>rayon %2 m · à %3 m · posée par %4</t>",
        [_c, 8] call comspec_atak_native_fnc_gridRef, _r, round (player distance2D _c), [_who] call _esc]];
    _rows pushBack ["buttons", [
        ["CARTE", compile format ["['MAP'] call comspec_atak_native_fnc_navigate; [[%1, %2, 0], 0.05] call comspec_atak_native_fnc_mapCenter;", _c select 0, _c select 1], false, true],
        ["RETIRER", compile format ["['remove', %1] call comspec_atak_native_fnc_noFly; %2", str _id, _re], false, true, "Retire la zone pour tout le camp"]
    ]];
} forEach _zones;
if ((count _zones) > 1) then {
    _rows pushBack ["buttons", [["RETIRER TOUTES LES ZONES", compile format ["['clear'] call comspec_atak_native_fnc_noFly; %1", _re], false, true, "Retire toutes les zones interdites du camp"]]];
};

[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
