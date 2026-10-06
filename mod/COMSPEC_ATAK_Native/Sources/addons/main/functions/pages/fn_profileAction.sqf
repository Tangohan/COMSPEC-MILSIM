/* Actions de l'app Profil. Params : [action, argument] */
params ["_action", ["_arg", ""]];
private _render = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "PROFILE") then { ["PROFILE"] call comspec_atak_native_fnc_pageRender; }; }, [], 0.5] call CBA_fnc_waitAndExecute; };
switch (_action) do {
    case "battery": { [] call comspec_atak_native_fnc_batterySwap; call _render; };
    case "repair": {
        // Écran et révision : réparation native (kit de réparation ATAK ou caisse à outils), qui remet aussi en état
        // l'appareil Overwatch détruit que sa propre réparation refuse.
        if (_arg in ["screen", "full"]) exitWith { ["start"] call comspec_atak_native_fnc_repairStart; };
        if (isNil "comspec_overwatch_connect_fnc_repairAtak") exitWith { ["WARNING", "Réparation : COMSPEC Overwatch requis", 4, 30] call comspec_atak_native_fnc_notify; };
        [_arg, _render] spawn { params ["_t", "_r"]; [_t] call comspec_overwatch_connect_fnc_repairAtak; sleep 9; call _r; };
    };
    case "cert": {
        // Overwatch renouvelle le certificat pendant sa synchronisation du terminal (absent, expiré ou révoqué).
        if (isNil "comspec_overwatch_connect_fnc_syncAtakRealism") exitWith {};
        [_render] spawn {
            params ["_r"];
            [] call comspec_overwatch_connect_fnc_syncAtakRealism;
            private _st = missionNamespace getVariable ["COMSPEC_CertStatus", ""];
            if (_st in ["active", "issued"]) then { ["SUCCESS", "Certificat délivré", 5, 30] call comspec_atak_native_fnc_notify; } else { ["WARNING", format ["Certificat : %1", [missionNamespace getVariable ["COMSPEC_AtakRealismLastError", "refusé par le poste"], "refusé par le poste"] select ((missionNamespace getVariable ["COMSPEC_AtakRealismLastError", ""]) isEqualTo "")], 5, 30] call comspec_atak_native_fnc_notify; };
            call _r;
        };
    };
};
true
