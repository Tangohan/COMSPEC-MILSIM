/*
    Compare l’empreinte courante et déclenche un sync si un champ significatif a changé.
    Params: [_reason]
*/
params [["_reason", "profile_sync", [""]]];

if (!hasInterface) exitWith { false };
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith { false };
if (isNull player || {!alive player}) exitWith { false };
if (missionNamespace getVariable ["COMSPEC_DisconnectSent", false]) exitWith { false };
if (diag_tickTime < (missionNamespace getVariable ["COMSPEC_RespawnGraceUntil", -1e9])) exitWith { false };

private _registered = missionNamespace getVariable ["COMSPEC_OperatorRegistered", false];
if (!_registered) exitWith {
    ["register", "first_connect"] call comspec_overwatch_connect_fnc_syncOperatorProfile
};

private _payload = [player, "sync", _reason] call comspec_overwatch_connect_fnc_buildOperatorProfile;
private _fp = _payload getOrDefault ["fingerprint", ""];
private _lastFp = missionNamespace getVariable ["COMSPEC_OperatorFingerprint", ""];
if (_fp isEqualTo _lastFp) exitWith { false };

["sync", _reason] call comspec_overwatch_connect_fnc_syncOperatorProfile
