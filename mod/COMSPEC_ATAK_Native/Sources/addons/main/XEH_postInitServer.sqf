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
["comspec_atak_native_syncReq", {
    params ["_unit"];
    if (isNull _unit) exitWith {};
    private _side = str side group _unit;
    ["comspec_atak_native_syncData", [COMSPEC_ATAK_SrvLog getOrDefault [_side, []], COMSPEC_ATAK_SrvRoutes getOrDefault [netId group _unit, []]], _unit] call CBA_fnc_targetEvent;
}] call CBA_fnc_addEventHandler;
