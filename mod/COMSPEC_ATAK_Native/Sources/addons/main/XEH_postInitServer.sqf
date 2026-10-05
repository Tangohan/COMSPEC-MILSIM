/*
    Mémoire serveur de l'état partagé du téléphone, pour les joueurs qui rejoignent en cours de partie :
    demandes logistiques, MEDEVAC et comptes rendus (journal des événements par camp, rejoué chez le nouveau venu)
    et itinéraires de groupe partagés. Les brouilleurs sont déjà une variable publique (COMSPEC_ATAK_Jammers).
    Un client demande la synchro à son arrivée (comspec_atak_native_syncReq) ; le serveur répond par comspec_atak_native_syncData.
*/
if (!isServer) exitWith {};
COMSPEC_ATAK_SrvLog = createHashMap;      // camp -> [[événement, arguments]...] (200 au plus)
COMSPEC_ATAK_SrvRoutes = createHashMap;   // netId du groupe -> [points, auteur]
private _keep = {
    params ["_side", "_ev", "_args"];
    private _l = COMSPEC_ATAK_SrvLog getOrDefault [_side, []];
    _l pushBack [_ev, _args];
    if ((count _l) > 200) then { _l deleteAt 0; };
    COMSPEC_ATAK_SrvLog set [_side, _l];
};
missionNamespace setVariable ["COMSPEC_ATAK_SrvKeep", _keep];
["comspec_atak_native_logi", { [_this param [1, ""], "comspec_atak_native_logi", _this] call COMSPEC_ATAK_SrvKeep; }] call CBA_fnc_addEventHandler;
["comspec_atak_native_medevac", { [_this param [1, ""], "comspec_atak_native_medevac", _this] call COMSPEC_ATAK_SrvKeep; }] call CBA_fnc_addEventHandler;
["comspec_atak_native_bda", { [_this param [1, ""], "comspec_atak_native_bda", _this] call COMSPEC_ATAK_SrvKeep; }] call CBA_fnc_addEventHandler;
["comspec_atak_native_report", { [_this param [1, ""], "comspec_atak_native_report", _this] call COMSPEC_ATAK_SrvKeep; }] call CBA_fnc_addEventHandler;
["comspec_atak_native_medevacStatus", { [_this param [3, ""], "comspec_atak_native_medevacStatus", _this] call COMSPEC_ATAK_SrvKeep; }] call CBA_fnc_addEventHandler;
["comspec_atak_native_wpStore", {
    params ["_grp", "_pts", "_who"];
    COMSPEC_ATAK_SrvRoutes set [_grp, [_pts, _who]];
}] call CBA_fnc_addEventHandler;
// Équipes de feu : seul le serveur écrit l'état partagé (fn_ftServer), puis le diffuse au camp.
["comspec_atak_native_ft", { _this call comspec_atak_native_fnc_ftServer; }] call CBA_fnc_addEventHandler;
// Clé de la session (Athena range les escouades et équipes envoyées par mission jouée).
missionNamespace setVariable ["COMSPEC_ATAK_MissionKey", format ["%1@%2#%3", missionName, worldName, (systemTimeUTC select [0, 5]) joinString ""], true];
// Contrôle de cohérence : unités dont l'équipe n'existe plus dans leur groupe (changement de groupe hors téléphone,
// équipe dissoute) remises sans équipe. Toutes les 10 s, et à la demande (Inter-team > RESYNCHRONISER, arrivée d'un joueur).
COMSPEC_ATAK_FtCheck = {
    params [["_by", objNull]];
    private _fixed = 0;
    {
        private _u = _x;
        private _tid = _u getVariable ["COMSPEC_FT", ""];
        if (_tid isNotEqualTo "" && {((group _u getVariable ["COMSPEC_FireTeams", []]) findIf { (_x select 0) isEqualTo _tid }) < 0}) then {
            _u setVariable ["COMSPEC_FT", "", true];
            if ((_u getVariable ["COMSPEC_FTRole", ""]) isEqualTo "CDE") then { _u setVariable ["COMSPEC_FTRole", "FUS", true]; };
            [_u, "MAIN"] remoteExecCall ["assignTeam", 0];
            _fixed = _fixed + 1;
        };
    } forEach allUnits;
    if (_fixed > 0) then { missionNamespace setVariable ["COMSPEC_ATAK_SquadRev", (missionNamespace getVariable ["COMSPEC_ATAK_SquadRev", 0]) + 1, true]; };
    if (!isNull _by) then { ["comspec_atak_native_ftChanged", [group _by, format ["Synchronisation : %1 correction(s), révision %2", _fixed, missionNamespace getVariable ["COMSPEC_ATAK_SquadRev", 0]]], _by] call CBA_fnc_targetEvent; };
    _fixed
};
["comspec_atak_native_ftCheck", { _this call COMSPEC_ATAK_FtCheck; }] call CBA_fnc_addEventHandler;
[{ [] call COMSPEC_ATAK_FtCheck; }, 10] call CBA_fnc_addPerFrameHandler;
["comspec_atak_native_syncReq", {
    params ["_unit"];
    if (isNull _unit) exitWith {};
    private _side = str side group _unit;
    ["comspec_atak_native_syncData", [COMSPEC_ATAK_SrvLog getOrDefault [_side, []], COMSPEC_ATAK_SrvRoutes getOrDefault [netId group _unit, []]], _unit] call CBA_fnc_targetEvent;
}] call CBA_fnc_addEventHandler;
