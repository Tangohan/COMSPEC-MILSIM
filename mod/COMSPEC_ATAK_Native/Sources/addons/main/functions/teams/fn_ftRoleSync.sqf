/*
    Rôles d'équipe de feu partagés avec Athena et rôle mémorisé du joueur (côté client).
    Params : [action, argument]
      "init"            : à l'arrivée (XEH client) : écouteurs, réapparition, puis "load"
      "load"            : relit sur Athena les fonctions de la communauté et les rôles créés en jeu, plus le rôle mémorisé
                          et la fonction principale du joueur (COMSPEC Link 2.0.63, GetFireTeams [tenant, "roles:<steam>"],
                          sans nouvelle commande DLL), les donne au serveur (fn_ftServer « roles ») puis lance "auto"
      "auto"            : sans rôle, applique le rôle mémorisé (profil, puis Athena), sinon celui de la fonction Athena
      "remember" [clé]  : mémorise le rôle choisi (profil COMSPEC_ATAK_MyRole, à vie ; Athena via Squad.Sync role_pref)
      "respawn" [id d'équipe, rôle] : retrouve son équipe et son rôle après réapparition
    État : missionNamespace COMSPEC_ATAK_RoleAthena = [état, rôle mémorisé Athena, rôle de la fonction, libellé fonction, nb de rôles].
*/
params [["_act", ""], ["_arg", []]];
if (!hasInterface) exitWith { false };
private _known = { params ["_k"]; _k isNotEqualTo "" && {(((([] call comspec_atak_native_fnc_ftCatalog) get "roles")) findIf { (_x select 0) isEqualTo _k }) >= 0} };
switch (_act) do {
    case "init": {
        ["comspec_atak_native_ftRemember", { ["remember", _this param [0, ""]] call comspec_atak_native_fnc_ftRoleSync; }] call CBA_fnc_addEventHandler;
        player addEventHandler ["Respawn", {
            params ["_new", "_old"];
            if (isNull _old) exitWith {};
            private _pref = _old getVariable ["COMSPEC_FTRolePref", ""];
            if (_pref isNotEqualTo "") then { _new setVariable ["COMSPEC_FTRolePref", _pref, true]; };
            [{ ["respawn", _this] call comspec_atak_native_fnc_ftRoleSync; }, [_old getVariable ["COMSPEC_FT", ""], _old getVariable ["COMSPEC_FTRole", ""]], 2] call CBA_fnc_waitAndExecute;
        }];
        private _p = profileNamespace getVariable ["COMSPEC_ATAK_MyRole", ""];
        if (_p isNotEqualTo "" && {_p isNotEqualTo "NONE"}) then { player setVariable ["COMSPEC_FTRolePref", _p, true]; };
        // Après le contrôle d'arrivée du serveur (ftCheck, 5 s).
        [{ ["load"] call comspec_atak_native_fnc_ftRoleSync; }, [], 8] call CBA_fnc_waitAndExecute;
    };
    case "load": {
        [] spawn {
            missionNamespace setVariable ["COMSPEC_ATAK_RoleAthena", ["CHARGEMENT", "", "", "", 0]];
            private _t0 = diag_tickTime;
            waitUntil { sleep 2; (missionNamespace getVariable ["COMSPEC_AthenaReady", false]) || {diag_tickTime - _t0 > 90} };
            if !([] call comspec_atak_native_fnc_bridge && {missionNamespace getVariable ["COMSPEC_AthenaReady", false]}) exitWith {
                missionNamespace setVariable ["COMSPEC_ATAK_RoleAthena", ["SANS LIAISON", "", "", "", 0]];
                ["auto"] call comspec_atak_native_fnc_ftRoleSync;
            };
            private _tn = missionNamespace getVariable ["comspec_overwatch_tenant_id", ""];
            if !(_tn isEqualType "") then { _tn = str _tn; };
            private _r = "COMSPECExtension" callExtension ["GetFireTeams", [_tn, "roles:" + getPlayerUID player]];
            if (_r isEqualType []) then { _r = _r param [0, ""]; };
            if !(_r isEqualType "") then { _r = ""; };
            if ((_r select [0, 3]) isNotEqualTo "OK|") exitWith {
                missionNamespace setVariable ["COMSPEC_ATAK_RoleAthena", [["ERREUR " + (_r select [4, 40]), "COMSPEC Link 2.0.63 requis"] select (_r isEqualTo ""), "", "", "", 0]];
                ["INFO", "SQUAD", format ["Rôles Athena : %1", _r]] call comspec_atak_native_fnc_log;
                ["auto"] call comspec_atak_native_fnc_ftRoleSync;
            };
            private _roles = [];
            private _pref = "";
            private _job = "";
            private _jobLabel = "";
            {
                private _f = _x splitString (toString [9]);
                if ((_f param [0, ""]) isEqualTo "M" && {(count _f) >= 4}) then {
                    _f params ["", "_team", "_key", "_val", ["_label", ""]];
                    if (_team isEqualTo "1") then {
                        (_val splitString "~") params [["_ik", "FUS"], ["_ab", ""], ["_o", "ATHENA"]];
                        _roles pushBack [_key, [_label, _key] select (_label isEqualTo ""), _ab, _ik, _o];
                    };
                    if (_team isEqualTo "2" && {_key isEqualTo "PREF"}) then { _pref = _val; };
                    if (_team isEqualTo "2" && {_key isEqualTo "JOB"}) then { _job = _val; _jobLabel = _label; };
                };
            } forEach ((_r select [3]) splitString (toString [10]));
            missionNamespace setVariable ["COMSPEC_ATAK_RoleAthena", ["OK", _pref, _job, _jobLabel, count _roles]];
            if ((count _roles) > 0) then {
                ["comspec_atak_native_ft", [player, "roles", [_roles]]] call CBA_fnc_serverEvent;
                // Liste publiée par le serveur avant de choisir le rôle.
                private _rev = missionNamespace getVariable ["COMSPEC_ATAK_FtRolesRev", 0];
                private _t1 = diag_tickTime;
                waitUntil { sleep 0.5; (missionNamespace getVariable ["COMSPEC_ATAK_FtRolesRev", 0]) isNotEqualTo _rev || {diag_tickTime - _t1 > 5} };
            };
            ["auto"] call comspec_atak_native_fnc_ftRoleSync;
        };
    };
    case "auto": {
        if (isNull player || {!alive player} || {(player getVariable ["COMSPEC_FTRole", ""]) isNotEqualTo ""}) exitWith {};
        private _p = profileNamespace getVariable ["COMSPEC_ATAK_MyRole", ""];
        if (_p isEqualTo "NONE") exitWith {};
        (missionNamespace getVariable ["COMSPEC_ATAK_RoleAthena", ["", "", "", "", 0]]) params ["", ["_ap", ""], ["_aj", ""]];
        private _k = ([_p, _ap, _aj] select { _x isNotEqualTo "CDE" && {[_x] call _known} }) param [0, ""];
        if (_k isEqualTo "") exitWith {};
        player setVariable ["COMSPEC_FTRolePref", _k, true];
        ["comspec_atak_native_ft", [player, "role", [netId player, _k]]] call CBA_fnc_serverEvent;
    };
    case "remember": {
        private _k = _arg;
        if !(_k isEqualType "") exitWith {};
        // Chef d'équipe : dépend de l'équipe, jamais mémorisé.
        if (_k isEqualTo "CDE") exitWith {};
        profileNamespace setVariable ["COMSPEC_ATAK_MyRole", [_k, "NONE"] select (_k isEqualTo "")];
        saveProfileNamespace;
        player setVariable ["COMSPEC_FTRolePref", _k, true];
    };
    case "respawn": {
        _arg params [["_tid", ""], ["_r", ""]];
        if (_tid isNotEqualTo "" || {_r isNotEqualTo ""}) then { ["comspec_atak_native_ft", [player, "restore", [_tid, _r]]] call CBA_fnc_serverEvent; };
        [{ ["auto"] call comspec_atak_native_fnc_ftRoleSync; }, [], 3] call CBA_fnc_waitAndExecute;
    };
};
true
