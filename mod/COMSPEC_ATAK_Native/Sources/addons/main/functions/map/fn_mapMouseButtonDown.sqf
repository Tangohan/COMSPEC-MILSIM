/* Clic gauche : action de l'outil actif. Clic droit : quitte l'outil (retour à la sélection). */
params ["_map","_button","_mx","_my",["_shift",false],["_ctrl",false],["_alt",false]];
if (_button isEqualTo 0) exitWith { [_map ctrlMapScreenToWorld [_mx,_my],_shift,_ctrl,_alt] call comspec_atak_native_fnc_mapSelect; true };
if (_button isEqualTo 1) exitWith {
    private _s = uiNamespace getVariable ["COMSPEC_ATAK_State",createHashMap];
    if ((_s getOrDefault ["mapMode","SELECT"]) isNotEqualTo "SELECT") then {
        ["SELECT"] call comspec_atak_native_fnc_mapToolSet;
        [{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
    };
    true
};
false
