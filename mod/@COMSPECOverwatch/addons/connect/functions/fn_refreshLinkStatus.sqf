/*
    Interroge la liaison Athena (extension) et met à jour l’état pour les badges UI.
    Utilise un contrôle léger déjà présent côté extension (whoami).
    Retourne l’état : "linked" | "offline" | "connecting" | "disabled"
*/
if (!hasInterface) exitWith { "disabled" };

if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {
    missionNamespace setVariable ["COMSPEC_LinkState", "disabled", false];
    [] call comspec_overwatch_connect_fnc_updateStatusBadges;
    "disabled"
};

private _url = missionNamespace getVariable ["comspec_overwatch_api_url", ""];
if (_url isEqualTo "") exitWith {
    missionNamespace setVariable ["COMSPEC_LinkState", "offline", false];
    missionNamespace setVariable ["COMSPEC_LinkDetail", "Adresse Athena non renseignée", false];
    [] call comspec_overwatch_connect_fnc_updateStatusBadges;
    "offline"
};

missionNamespace setVariable ["COMSPEC_LinkState", "connecting", false];
[] call comspec_overwatch_connect_fnc_updateStatusBadges;

private _raw = ["COMSPECExtension" callExtension ["GetClientIp", []]] call comspec_overwatch_connect_fnc_extResult;
private _parts = _raw splitString "|";
private _prefix = if (count _parts >= 1) then { _parts select 0 } else { "" };
private _payload = if (count _parts >= 2) then { _parts select 1 } else { _raw };

private _state = "offline";
private _detail = "";

if (_prefix == "OK") then {
    _state = "linked";
    _detail = "";
    if (!(_payload isEqualTo "") && {!(_payload isEqualTo "—")}) then {
        missionNamespace setVariable ["COMSPEC_userIp", _payload, true];
    };
    missionNamespace setVariable ["COMSPEC_LastHealthOk", diag_tickTime, false];
} else {
    _state = "offline";
    _detail = switch (_payload) do {
        case "not_connected": { "Liaison non établie" };
        case "invalid": { "Extension indisponible" };
        default {
            if (_payload isEqualTo "") then { "Hors liaison" } else { "Hors liaison" };
        };
    };
};

missionNamespace setVariable ["COMSPEC_LinkState", _state, false];
missionNamespace setVariable ["COMSPEC_LinkDetail", _detail, false];
[] call comspec_overwatch_connect_fnc_updateStatusBadges;
_state
