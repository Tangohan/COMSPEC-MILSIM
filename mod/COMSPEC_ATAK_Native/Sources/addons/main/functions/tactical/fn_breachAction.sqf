/*
    Actions de l'app Breacher.
      "handle" : essaie la poignée de la porte au contact (révèle le verrou)
      "mat", m / "meth", m : matériau de la porte et méthode du calcul de charge
      "countdown", s : durée du compte à rebours      "charges" : mise à feu des charges cochées au top
      "go" : top synchronisé envoyé au groupe (événement comspec_atak_native_breach)
*/
params [["_act", ""], ["_arg", ""]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_Breach", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_Breach", _s];
private _render = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "BREACH") then { ["BREACH"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
switch (_act) do {
    case "handle": {
        // Essayer la poignée : seule façon de savoir si la porte est verrouillée.
        private _door = [] call comspec_atak_native_fnc_breachBuilding;
        if ((count _door) isEqualTo 0) exitWith { ["WARNING", "Placez-vous contre la porte", 3, 20] call comspec_atak_native_fnc_notify; };
        _door params ["_b", "_i"];
        private _locked = (_b getVariable [format ["bis_disabled_Door_%1", _i], 0]) isEqualTo 1;
        private _t = _s getOrDefault ["tested", createHashMap];
        _t set [format ["%1|%2", _b call BIS_fnc_netId, _i], ["free", "locked"] select _locked];
        _s set ["tested", _t];
        [["INFO", "WARNING"] select _locked, ["La poignée tourne : porte non verrouillée", "Porte verrouillée"] select _locked, 3, 20] call comspec_atak_native_fnc_notify;
        call _render;
    };
    case "mat": { _s set ["mat", _arg]; call _render; };
    case "meth": { _s set ["meth", _arg]; call _render; };
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
