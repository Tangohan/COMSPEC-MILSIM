/*
    App JTAC : désignation (laser ou visée, code laser ACE), cible en grille 10 chiffres, altitude, distance et azimut,
    contrôle de proximité des amis (danger close), appareils en station et appels au pilote
    (envoi de la cible, CLEARED HOT, CONTINUE, ABORT) reçus dans sa messagerie ; raccourcis 9-LINE et carte.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_Jtac", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_Jtac", _s];
private _grey = "#8a9a93";
private _card = { params ["_deg"]; ["N", "NE", "E", "SE", "S", "SO", "O", "NO"] select ((round (_deg / 45)) mod 8) };
private _dist = { params ["_m"]; [format ["%1 m", round _m], format ["%1 km", (_m / 1000) toFixed 1]] select (_m >= 1000) };
private _code = player getVariable ["ace_laser_code", 1111];
if !(_code isEqualType 0) then { _code = 1111; };
private _tgt = [] call comspec_atak_native_fnc_jtacTarget;
private _locked = _s getOrDefault ["target", []];
private _rows = [];
private _lasing = (count _tgt) > 0 && {(_tgt select 1) isEqualTo "laser"};
_rows pushBack ["hero", "\z\comspec_atak_native\addons\main\data\app_jtac.paa", format ["<t size='1.3' font='RobotoCondensedBold'>JTAC</t>  <t font='RobotoCondensedBold' color='%1'>● %2</t><br/><t size='0.85' color='%3'>Code laser %4 · %5</t>",
    ["#8a9a93", "#e5483a"] select _lasing, ["LASER ÉTEINT", "LASER ACTIF"] select _lasing, _grey, _code,
    ["visez la cible puis VERROUILLER", "désignation en cours"] select _lasing]];

// Désignation.
_rows pushBack ["segment", "Code laser", [1111, 1688, 1788, 1888] apply { [str _x, compile format ["['code', %1] call comspec_atak_native_fnc_jtacAction;", _x], _x isEqualTo _code] }];
_rows pushBack ["buttons", [["VERROUILLER LA CIBLE", { ['lock'] call comspec_atak_native_fnc_jtacAction; }, true], ["EFFACER", { ['unlock'] call comspec_atak_native_fnc_jtacAction; }, false, (count _locked) > 0]]];
if ((count _locked) >= 2) then {
    private _p = _locked;
    private _d = player distance2D _p;
    private _az = player getDir _p;
    _rows pushBack ["section", "Cible", _s getOrDefault ["source", ""]];
    _rows pushBack ["info", "Grille", format ["<t font='EtelkaMonospacePro'>%1</t>", [_p, 10] call comspec_atak_native_fnc_gridRef]];
    _rows pushBack ["info", "Altitude", format ["%1 m", round (getTerrainHeightASL _p)]];
    _rows pushBack ["info", "Depuis moi", format ["%1 · %2° (%3 mils) %4", [_d] call _dist, round _az, round (_az * 17.7778), [_az] call _card]];
    // Amis les plus proches de la cible : danger close sous 500 m (règle simplifiée, munitions légères).
    private _fr = (allUnits select { alive _x && {side group _x isEqualTo side group player} && {(_x distance2D _p) < 1000} }) apply { [_x distance2D _p, _x] };
    _fr sort true;
    if ((count _fr) isEqualTo 0) then {
        _rows pushBack ["info", "Amis", "<t color='#5cc76b'>aucun à moins de 1 km</t>"];
    } else {
        (_fr select 0) params ["_fd", "_fu"];
        _rows pushBack ["info", "Amis", format ["<t color='%1' font='RobotoCondensedBold'>%2 à %3%4</t>", ["#f2ab33", "#e5483a"] select (_fd < 500), count _fr, [_fd] call _dist, [" (plus proche)", " · DANGER CLOSE"] select (_fd < 500)]];
    };
    _rows pushBack ["buttons", [["9-LINE", { ['nine'] call comspec_atak_native_fnc_jtacAction; }, true], ["CARTE", { ['map'] call comspec_atak_native_fnc_jtacAction; }]]];
} else {
    _rows pushBack ["text", format ["<t color='%1'>Visez la cible (ou désignez-la au laser) puis VERROUILLER : grille, altitude, amis à proximité et 9-LINE pré-rempli.</t>", _grey]];
};

// Appareils en station.
private _air = vehicles select { _x isKindOf "Air" && {alive _x} && {(count crew _x) > 0} && {side group (effectiveCommander _x) isEqualTo side group player} && {(_x distance2D player) < 20000} };
private _sel = _s getOrDefault ["air", objNull];
if !(_sel in _air) then { _sel = objNull; };
_rows pushBack ["section", "Appareils en station", ["Aucun appareil ami en vol", format ["%1 appareil(s)", count _air]] select ((count _air) > 0)];
{
    private _pil = driver _x;
    private _isSel = _x isEqualTo _sel;
    _rows pushBack ["text", format ["<t font='RobotoCondensedBold' color='%1'>%2</t>  <t color='%3' size='0.85'>%4</t><br/><t size='0.85' color='%3'>%5 · %6 m sol · %7 km/h · cap %8° · %9</t>",
        ["#c9d4cf", "#5cc76b"] select _isSel, groupId group _x, _grey, getText (configOf _x >> "displayName"),
        [format ["pilote %1", name _pil], "pilote IA"] select (!isPlayer _pil), round ((getPosATL _x) select 2), round speed _x, round getDir _x, [_x distance2D player] call _dist]];
    _rows pushBack ["buttons", [[["CHOISIR", "CHOISI"] select _isSel, compile format ["['pick', %1] call comspec_atak_native_fnc_jtacAction;", str (_x call BIS_fnc_netId)], _isSel]]];
} forEach (_air select [0, 8]);

// Contrôle de l'appareil choisi.
if (!isNull _sel) then {
    _rows pushBack ["section", format ["Contrôle : %1", groupId group _sel], "Les appels arrivent dans la messagerie de l'équipage"];
    private _type = _s getOrDefault ["type", 2];
    _rows pushBack ["segment", "Type de contrôle", [1, 2, 3] apply { [format ["TYPE %1", _x], compile format ["['type', %1] call comspec_atak_native_fnc_jtacAction;", _x], _x isEqualTo _type] }];
    _rows pushBack ["buttons", [["ENVOYER LA CIBLE", { ['call', 'target'] call comspec_atak_native_fnc_jtacAction; }, true, (count _locked) > 0]]];
    _rows pushBack ["buttons", [
        ["CLEARED HOT", { ['call', 'hot'] call comspec_atak_native_fnc_jtacAction; }, true, (count _locked) > 0],
        ["CONTINUE", { ['call', 'continue'] call comspec_atak_native_fnc_jtacAction; }],
        ["ABORT", { ['call', 'abort'] call comspec_atak_native_fnc_jtacAction; }]
    ]];
};
private _log = _s getOrDefault ["log", []];
if ((count _log) > 0) then {
    _rows pushBack ["section", "Appels envoyés", ""];
    { _rows pushBack ["text", format ["<t size='0.85'><t color='%1'>%2</t>  %3</t>", _grey, _x select 0, _x select 1]]; } forEach (_log select [0, 8]);
};
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
