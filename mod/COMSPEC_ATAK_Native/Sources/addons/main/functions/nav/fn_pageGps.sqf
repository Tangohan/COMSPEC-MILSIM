/*
    App GPS : guidage en cours (manœuvre, reste, arrivée), ma position, destination par grille ou sur la carte,
    raccourcis (étape active des points de passage, marqueurs les plus proches).
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _f = uiNamespace getVariable ["COMSPEC_ATAK_Gps", createHashMap];
private _fmt = { params ["_m"]; switch (true) do { case (_m >= 1000): { format ["%1 km", (_m / 1000) toFixed 1] }; case (_m >= 100): { format ["%1 m", (round (_m / 10)) * 10] }; default { format ["%1 m", round _m] }; } };
private _card = { params ["_deg"]; ["N", "NE", "E", "SE", "S", "SO", "O", "NO"] select ((round (_deg / 45)) mod 8) };
private _rows = [];
private _r = missionNamespace getVariable ["COMSPEC_ATAK_Route", createHashMap];
if ((count _r) > 0) then {
    private _g = [] call comspec_atak_native_fnc_routeGuide;
    if ((count _g) > 0) then {
        _g params ["_txt", "_dn", "_icon", "_left", "_eta", "_label"];
        _rows pushBack ["hero", format ["\z\comspec_atak_native\addons\main\data\%1.paa", _icon], format ["<t size='1.4' font='COMSPEC_BarlowBold'>%1</t>  <t size='1.05'>%2</t><br/><t color='#5cc76b'>%3 min</t> · %4 · arrivée %5<br/><t size='0.85' color='#8a9a93'>vers %6</t>",
            [_dn] call _fmt, _txt, ceil (_eta / 60), [_left] call _fmt, [(dayTime + _eta / 3600) mod 24, "HH:MM"] call BIS_fnc_timeToString, _label]];
        _rows pushBack ["buttons", [["VOIR SUR LA CARTE", { ['map'] call comspec_atak_native_fnc_gpsAction; }, true], ["ARRÊTER", { ['stop'] call comspec_atak_native_fnc_gpsAction; }]]];
    };
} else {
    _rows pushBack ["hero", "\z\comspec_atak_native\addons\main\data\app_gps.paa", "<t size='1.2' font='COMSPEC_BarlowBold'>Aucun guidage</t><br/><t color='#8a9a93'>Choisissez une destination : grille, carte, étape ou marqueur. L'itinéraire suit les routes et pistes.</t>"];
};
if (missionNamespace getVariable ["COMSPEC_ATAK_RouteBusy", false]) then { _rows pushBack ["text", "<t color='#f2ab33'>Calcul de l'itinéraire…</t>"]; };

private _veh = vehicle player;
// Position telle que le GPS la donne (brouillage, dégâts, ciel masqué, attente des satellites : fn_ewEffects, fn_gpsFix).
([] call comspec_atak_native_fnc_ewEffects) params ["", "", "", "", "_gErr", "_gOff", "_gSt"];
([] call comspec_atak_native_fnc_gpsFix) params ["", "", "_gSats", "_gWhy"];
private _gTxt = switch (_gSt) do {
    case "ACQ": { format ["<t color='#f2ab33'>%1</t>", "Recherche des satellites…" call comspec_atak_native_fnc_tr] };
    case "NET": { format ["<t color='#f2ab33'>%1</t>", format ["Pas de fix · position réseau à %1 m près" call comspec_atak_native_fnc_tr, _gErr]] };
    case "NOFIX": { format ["<t color='#e5483a'>%1</t>", ["Pas de fix · dernière position connue" call comspec_atak_native_fnc_tr, "Aucune position" call comspec_atak_native_fnc_tr] select (_gErr isEqualTo 0)] };
    default { format ["%1 · <t color='%3'>±%2 m</t>", format ["%1 satellites" call comspec_atak_native_fnc_tr, _gSats], _gErr max 3, ["#5cc76b", "#f2ab33"] select (_gErr >= 15)] };
};
private _gPos = (getPosASL player) vectorAdd _gOff;
_rows append [
    ["section", "Ma position", ""],
    ["info", "Signal GPS", _gTxt]
];
if (_gWhy isNotEqualTo [] && {_gSt isNotEqualTo "OFF"}) then { _rows pushBack ["text", format ["<t size='0.85' color='#8a9a93'>%1</t>", (_gWhy apply { _x call comspec_atak_native_fnc_tr }) joinString ", "]]; };
_rows append [
    ["info", "Grille", format ["<t font='EtelkaMonospacePro'>%1</t>", ["—", [_gPos, 8] call comspec_atak_native_fnc_gridRef] select (_gSt isNotEqualTo "NOFIX" || {_gErr > 0})]],
    ["info", "Altitude", format ["%1 m", round ((getPosASL player) select 2)]],
    ["info", "Cap · vitesse", format ["%1° %2 · %3 km/h", round getDir _veh, [getDir _veh] call _card, round (speed _veh) max 0]]
];
_rows append [
    ["section", "Destination", "Grille de 4 à 10 chiffres"],
    ["edit", "gpsGrid", "Grille (ex. 1540 1728)", _f getOrDefault ["grid", ""]],
    ["buttons", [["Y ALLER", { ['grid'] call comspec_atak_native_fnc_gpsAction; }, true], ["CHOISIR SUR LA CARTE", { ['pick'] call comspec_atak_native_fnc_gpsAction; }]]]
];
// Raccourcis
private _w = missionNamespace getVariable ["COMSPEC_ATAK_Waypoints", createHashMap];
private _pts = _w getOrDefault ["pts", []];
private _marks = (allMapMarkers select { (markerText _x) isNotEqualTo "" && {(markerShape _x) isEqualTo "ICON"} && {(markerAlpha _x) > 0} }) apply { [player distance2D (getMarkerPos _x), _x] };
_marks sort true;
_marks = _marks select [0, 8];
if ((count _pts) > 0 || {(count _marks) > 0}) then { _rows pushBack ["section", "Raccourcis", ""]; };
if ((count _pts) > 0) then {
    (_pts select ((_w getOrDefault ["idx", 0]) min ((count _pts) - 1))) params ["_p", "_n"];
    _rows pushBack ["buttons", [[format ["ÉTAPE %1 · %2", _n, [player distance2D _p] call _fmt], { ['wp'] call comspec_atak_native_fnc_gpsAction; }]]];
};
{
    _x params ["_d", "_m"];
    _rows pushBack ["buttons", [[format ["%1 · %2 %3", ([markerText _m] call comspec_atak_native_fnc_markerAutoName), [_d] call _fmt, [player getDir (getMarkerPos _m)] call _card], compile format ["['marker', %1] call comspec_atak_native_fnc_gpsAction;", str _m]]]];
} forEach _marks;
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
