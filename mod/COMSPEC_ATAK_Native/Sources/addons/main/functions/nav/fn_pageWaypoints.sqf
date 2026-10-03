/* Points de passage : liste des étapes, navigation (flèche de cap en mode mini), partage au groupe, GPS vers l'étape. */
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _w = missionNamespace getVariable ["COMSPEC_ATAK_Waypoints", createHashMap];
private _pts = _w getOrDefault ["pts", []];
private _idx = _w getOrDefault ["idx", 0];
private _nav = _w getOrDefault ["nav", false];
private _card = { params ["_deg"]; ["N", "NE", "E", "SE", "S", "SO", "O", "NO"] select ((round (_deg / 45)) mod 8) };
private _fmt = { params ["_m"]; [format ["%1 m", round _m], format ["%1 km", (_m / 1000) toFixed 1]] select (_m >= 1000) };
private _rows = [["section", "Points de passage", [format ["%1 étape(s)%2", count _pts, ["", format [" · reçu de %1", _w getOrDefault ["from", ""]]] select ((_w getOrDefault ["from", ""]) isNotEqualTo "")], "Ajoutez des étapes avec l'outil carte « Points de passage »"] select ((count _pts) isEqualTo 0)]];
if ((count _pts) > 0) then {
    (_pts select _idx) params ["_ap", "_an"];
    _rows pushBack ["text", format ["<t size='1.2' font='RobotoCondensedBold' color='%1'>%2 %3</t><br/>%4 · %5° %6 · <t font='EtelkaMonospacePro'>%7</t>",
        ["#8a9a93", "#5cc76b"] select _nav, ["EN PAUSE :", "CAP SUR"] select _nav, _an, [player distance2D _ap] call _fmt, round (player getDir _ap), [player getDir _ap] call _card, [_ap, 8] call comspec_atak_native_fnc_gridRef]];
    _rows pushBack ["buttons", [
        ["<", { ["prev"] call comspec_atak_native_fnc_wpAction; }],
        [["NAVIGUER", "PAUSE"] select _nav, { ["nav"] call comspec_atak_native_fnc_wpAction; }, true],
        [">", { ["next"] call comspec_atak_native_fnc_wpAction; }]
    ]];
    _rows pushBack ["buttons", [["GPS VERS L'ÉTAPE", { ["gps"] call comspec_atak_native_fnc_wpAction; }], ["PARTAGER AU GROUPE", { ["share"] call comspec_atak_native_fnc_wpAction; }]]];
    // Longueur totale de l'itinéraire à pied.
    private _tot = 0; private _prev = getPosATL player;
    for "_i" from _idx to ((count _pts) - 1) do { _tot = _tot + (_prev distance2D ((_pts select _i) select 0)); _prev = (_pts select _i) select 0; };
    _rows pushBack ["info", "Reste à parcourir", format ["%1 · ~%2 min à pied", [_tot] call _fmt, ceil (_tot / 1.4 / 60)]];
    _rows pushBack ["section", "Étapes", ""];
    {
        _x params ["_p", "_n"];
        private _prevP = if (_forEachIndex isEqualTo 0) then { getPosATL player } else { (_pts select (_forEachIndex - 1)) select 0 };
        _rows pushBack ["person", "\A3\ui_f\data\map\markers\military\flag_CA.paa", format ["<t font='RobotoCondensedBold' color='%1'>%2</t>  <t size='0.8' color='#8a9a93'>%3</t><br/><t size='0.8'>%4 depuis l'étape précédente · %5°</t>",
            ["#c9d4cf", "#5cc76b"] select (_forEachIndex isEqualTo _idx), _n, [_p, 8] call comspec_atak_native_fnc_gridRef, [_prevP distance2D _p] call _fmt, round (_prevP getDir _p)],
            [["ALLER", compile format ["['goto', %1] call comspec_atak_native_fnc_wpAction;", _forEachIndex]], ["X", compile format ["['del', %1] call comspec_atak_native_fnc_wpAction;", _forEachIndex]]],
            [[0.95, 0.75, 0.18, 1], [0.36, 0.78, 0.42, 1]] select (_forEachIndex isEqualTo _idx)];
    } forEach _pts;
    _rows pushBack ["buttons", [["TOUT EFFACER", { ["clear"] call comspec_atak_native_fnc_wpAction; }]]];
};
_rows pushBack ["buttons", [["AJOUTER SUR LA CARTE", { ["MAP"] call comspec_atak_native_fnc_navigate; ["WP"] call comspec_atak_native_fnc_mapToolMenu; }, true]]];
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
