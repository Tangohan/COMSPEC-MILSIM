/*
    Rejeu de mission (app AAR) : lecture et réglages.
      ["play"]            lecture / pause ;
      ["step", n]         image suivante (+1) ou précédente (-1) ;
      ["seek", i]         aller à l'image i ;
      ["speed"]           vitesse suivante (x10, x30, x60, x120 du temps réel) ;
      ["trails"]          traces derrière les unités ;
      ["follow"]          carte centrée sur moi pendant la lecture ;
      ["clear"]           efface l'enregistrement ;
      ["tick", dt]        avance de la lecture (boucle de la page).
    État : uiNamespace COMSPEC_ATAK_AarPlay (HashMap idx, playing, speed, trails, follow).
*/
params [["_act", "play"], ["_arg", 0]];
private _p = uiNamespace getVariable ["COMSPEC_ATAK_AarPlay", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_AarPlay", _p];
private _log = missionNamespace getVariable ["COMSPEC_ATAK_Aar", []];
private _last = ((count _log) - 1) max 0;
private _render = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "AAR") then { ["AAR"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
switch (_act) do {
    case "play": {
        private _on = !(_p getOrDefault ["playing", false]);
        if (_on && {(_p getOrDefault ["idx", 0]) >= _last}) then { _p set ["idx", 0]; };
        _p set ["playing", _on];
        call _render;
    };
    case "step": { _p set ["playing", false]; _p set ["idx", 0 max (((floor (_p getOrDefault ["idx", 0])) + _arg) min _last)]; call _render; };
    case "seek": { _p set ["idx", 0 max (_arg min _last)]; ["sync"] call comspec_atak_native_fnc_aarAction; };
    case "speed": {
        private _all = [10, 30, 60, 120];
        _p set ["speed", _all select (((_all find (_p getOrDefault ["speed", 30])) + 1) mod (count _all))];
        call _render;
    };
    case "trails": { _p set ["trails", !(_p getOrDefault ["trails", true])]; call _render; };
    case "follow": { _p set ["follow", !(_p getOrDefault ["follow", false])]; call _render; };
    case "clear": {
        missionNamespace setVariable ["COMSPEC_ATAK_Aar", []];
        missionNamespace setVariable ["COMSPEC_ATAK_AarEvents", []];
        _p set ["idx", 0]; _p set ["playing", false];
        ["INFO", "Enregistrement de mission effacé", 3, 10] call comspec_atak_native_fnc_notify;
        call _render;
    };
    case "tick": {
        if !(_p getOrDefault ["playing", false]) exitWith {};
        // Une image = 10 s de mission : à x30, 3 images par seconde.
        private _i = (_p getOrDefault ["idx", 0]) + _arg * (_p getOrDefault ["speed", 30]) / 10;
        if (_i >= _last) then { _i = _last; _p set ["playing", false]; call _render; };
        _p set ["idx", _i];
        ["sync"] call comspec_atak_native_fnc_aarAction;
    };
    case "sync": {
        // Curseur et heure affichés, carte recentrée si « suivre ».
        disableSerialization;
        (uiNamespace getVariable ["COMSPEC_ATAK_AarCtrls", []]) params [["_slider", controlNull], ["_label", controlNull], ["_map", controlNull]];
        private _i = floor (_p getOrDefault ["idx", 0]);
        private _f = _log param [_i, []];
        if (!isNull _slider) then { _slider sliderSetPosition _i; };
        if (!isNull _label && {(count _f) > 0}) then {
            private _t0 = (_log select 0) select 0;
            private _el = round (((_f select 0) - _t0) / 60);
            _label ctrlSetStructuredText parseText format ["<t font='RobotoCondensedBold'>%1</t>  <t color='#8a9a93' size='0.85'>H+%2 min · image %3 / %4 · %5 unités</t>", _f select 1, _el, _i + 1, count _log, count (_f select 2)];
        };
        if (!isNull _map && {_p getOrDefault ["follow", false]} && {(count _f) > 0}) then {
            private _me = (_f select 2) select { _x select 5 };
            if ((count _me) > 0) then { _map ctrlMapAnimAdd [0.2, ctrlMapScale _map, [(_me select 0) select 0, (_me select 0) select 1]]; ctrlMapAnimCommit _map; };
        };
    };
};
true
