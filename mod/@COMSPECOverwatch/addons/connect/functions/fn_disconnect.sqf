/*
    Signale au portail Athena que le joueur quitte Arma / la mission.
    Appel synchrone extension (timeout court) — à brancher sur Ended / Unload display 46.
*/
if (!hasInterface) exitWith { false };

if (missionNamespace getVariable ["COMSPEC_DisconnectSent", false]) exitWith { false };
missionNamespace setVariable ["COMSPEC_DisconnectSent", true, false];

private _url = trim (missionNamespace getVariable ["comspec_overwatch_api_url", ""]);
private _key = trim (missionNamespace getVariable ["comspec_overwatch_api_key", ""]);
if (_url isEqualTo "" || {_key isEqualTo ""}) exitWith {
    missionNamespace setVariable ["COMSPEC_LinkState", "offline", false];
    ["disconnect"] call comspec_overwatch_connect_fnc_playAtakNotification;
    false
};

private _cs = [] call comspec_overwatch_connect_fnc_getCallsign;
private _modVersion = [] call comspec_overwatch_connect_fnc_getModVersion;
private _raw = ["COMSPECExtension" callExtension ["Disconnect", [_cs, _modVersion]]] call comspec_overwatch_connect_fnc_extResult;

missionNamespace setVariable ["COMSPEC_LinkState", "offline", false];
missionNamespace setVariable ["COMSPEC_LinkDetail", "", false];

private _parts = _raw splitString "|";
private _ok = (count _parts >= 1) && {(_parts select 0) isEqualTo "OK"};
if (_ok) then {
    ["[Athena] Déconnexion jeu signalée."] call comspec_overwatch_connect_fnc_appendLinkLog;
} else {
    private _why = if (count _parts >= 2) then { _parts select 1 } else { _raw };
    if (!(_why isEqualTo "")) then {
        [format ["[Athena] Déconnexion jeu non confirmée (%1).", _why]] call comspec_overwatch_connect_fnc_appendLinkLog;
    };
};

["disconnect"] call comspec_overwatch_connect_fnc_playAtakNotification;

_ok
