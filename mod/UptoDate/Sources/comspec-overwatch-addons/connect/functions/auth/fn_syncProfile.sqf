if !([] call comspec_overwatch_connect_fnc_isReady) exitWith { false };
private _rev = missionNamespace getVariable ["comspec_profile_revision", 0];
// extAwait : non bloquant quand appelé en spawn (boucle de fn_startSyncLoops), direct sinon.
private _raw = [["COMSPECExtension", "SyncProfile", [str _rev]] call comspec_overwatch_connect_fnc_extAwait] call comspec_overwatch_connect_fnc_extResult;
if (_raw find "OK|changed" == 0) then {
    [] call comspec_overwatch_connect_fnc_applyBootstrap;
    true
} else {
    false
};
