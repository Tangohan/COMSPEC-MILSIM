/*
    Actions de l'app Tireur d'élite.
      "laser" : télémètre (point visé, jusqu'à 3 km) et angle de site     "range" : relit la distance saisie
      "wind", bool : vent saisi ou vent du jeu                           "from", "L"/"R" : côté d'où vient le vent saisi
*/
params [["_act", ""], ["_arg", ""]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_Sniper", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_Sniper", _s];
private _keep = {
    private _r = ["sniRange", ""] call comspec_atak_native_fnc_formValue;
    if (_r isNotEqualTo "") then { _s set ["range", _r]; };
    private _w = ["sniWind", ""] call comspec_atak_native_fnc_formValue;
    if (_w isNotEqualTo "") then { _s set ["windSpeed", _w]; };
};
call _keep;
switch (_act) do {
    case "laser": {
        private _from = eyePos player;
        private _dir = player weaponDirection (currentWeapon player);
        if ((vectorMagnitude _dir) < 0.5) then { _dir = getCameraViewDirection player; };
        private _hit = lineIntersectsSurfaces [_from, _from vectorAdd (_dir vectorMultiply 3000), player, objNull, true, 1, "VIEW", "FIRE"];
        if ((count _hit) isEqualTo 0) exitWith { ["WARNING", "Télémètre : pas d'écho (au-delà de 3 km ?)", 3, 20] call comspec_atak_native_fnc_notify; };
        private _d = _from vectorDistance ((_hit select 0) select 0);
        _s set ["range", str round _d];
        _s set ["incl", asin ((_dir select 2) max -1 min 1)];
        ["INFO", format ["Télémètre : %1 m, site %2°", round _d, round asin ((_dir select 2) max -1 min 1)], 3, 20] call comspec_atak_native_fnc_notify;
    };
    case "range": { _s set ["incl", 0]; };
    case "wind": { _s set ["windManual", _arg]; };
    case "from": { _s set ["windFrom", _arg]; };
};
[{ ["SNIPER"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
true
