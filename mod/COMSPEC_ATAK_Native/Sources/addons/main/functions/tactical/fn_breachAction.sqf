/*
    Actions de l'app Breacher.
      "aim" : vise le bâtiment regardé       "map" : centre la carte dessus
      "countdown", s : durée du compte à rebours      "charges" : mise à feu des charges cochées au top
      "go" : top synchronisé envoyé au groupe (événement comspec_atak_native_breach)
*/
params [["_act", ""], ["_arg", ""]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_Breach", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_Breach", _s];
private _render = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "BREACH") then { ["BREACH"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
switch (_act) do {
    case "aim": {
        private _b = [] call comspec_atak_native_fnc_breachBuilding;
        if (isNull _b) exitWith { ["WARNING", "Aucun bâtiment avec des portes dans votre regard", 3, 20] call comspec_atak_native_fnc_notify; };
        _s set ["building", _b];
        call _render;
    };
    case "map": {
        private _b = _s getOrDefault ["building", objNull];
        if (isNull _b) exitWith {};
        ["MAP"] call comspec_atak_native_fnc_navigate;
        [{ [_this, 0.01] call comspec_atak_native_fnc_mapCenter; }, [(getPosASL _b) select 0, (getPosASL _b) select 1]] call CBA_fnc_execNextFrame;
    };
    case "countdown": { _s set ["countdown", _arg]; call _render; };
    case "charges": { _s set ["charges", !(_s getOrDefault ["charges", false])]; call _render; };
    case "go": {
        private _cd = _s getOrDefault ["countdown", 5];
        if (_s getOrDefault ["charges", false]) then {
            // Les charges partent au top : délai de la séquence = compte à rebours, sans intervalle.
            private _e = uiNamespace getVariable ["COMSPEC_ATAK_Explo", createHashMap];
            uiNamespace setVariable ["COMSPEC_ATAK_Explo", _e];
            private _d0 = _e getOrDefault ["delay", 5]; private _g0 = _e getOrDefault ["gap", 1];
            _e set ["delay", _cd]; _e set ["gap", 0];
            ["seq"] call comspec_atak_native_fnc_exploAction;
            _e set ["delay", _d0]; _e set ["gap", _g0];
        };
        ["comspec_atak_native_breach", [name player, _cd], units group player] call CBA_fnc_targetEvent;
        ["SUCCESS", format ["Top envoyé au groupe : brèche dans %1 s", _cd], 3, 40] call comspec_atak_native_fnc_notify;
    };
};
true
