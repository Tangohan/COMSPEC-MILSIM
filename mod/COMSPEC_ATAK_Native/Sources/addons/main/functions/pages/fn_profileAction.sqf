/* Actions de l'app Profil. Params : [action, argument] */
params ["_action", ["_arg", ""]];
private _render = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "PROFILE") then { ["PROFILE"] call comspec_atak_native_fnc_pageRender; }; }, [], 0.5] call comspec_atak_native_fnc_waitAndExec; };
// Rôle affiché changé : publié tout de suite (fn_displayRole) et renvoyé à Athena au prochain envoi de position.
private _applied = {
    private _r = [player] call comspec_atak_native_fnc_displayRole;
    missionNamespace setVariable ["COMSPEC_lastRole", "", false];
    [format ["Rôle affiché : %1", [_r, "aucun"] select (_r isEqualTo "")], 2] call comspec_atak_native_fnc_toast;
};
switch (_action) do {
    // Rôle affiché (fn_displayRole) : mode "athena" | "slot" | "custom", puis texte du rôle personnalisé.
    case "roleMode": {
        if !(_arg in ["athena", "slot", "custom"]) exitWith {};
        profileNamespace setVariable ["COMSPEC_ATAK_RoleMode", _arg];
        if (_arg isNotEqualTo "custom" || {(profileNamespace getVariable ["COMSPEC_ATAK_RoleCustom", ""]) isNotEqualTo ""}) then { [_arg] call _applied; };
        [] call comspec_atak_native_fnc_saveProfile;
        ["PROFILE"] call comspec_atak_native_fnc_pageRender;
    };
    case "roleCustom": {
        if !(_arg isEqualType "") exitWith {};
        _arg = trim _arg;
        if ((count _arg) > 40) then { _arg = _arg select [0, 40]; };
        if (_arg isEqualTo "") exitWith { ["Choisissez ou saisissez un rôle", 2] call comspec_atak_native_fnc_toast; };
        profileNamespace setVariable ["COMSPEC_ATAK_RoleMode", "custom"];
        profileNamespace setVariable ["COMSPEC_ATAK_RoleCustom", _arg];
        ["custom"] call _applied;
        [] call comspec_atak_native_fnc_saveProfile;
        ["PROFILE"] call comspec_atak_native_fnc_pageRender;
    };
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
