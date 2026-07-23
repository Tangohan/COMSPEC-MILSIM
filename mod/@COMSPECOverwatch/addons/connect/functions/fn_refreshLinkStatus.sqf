/*
    Interroge la liaison Athena (extension) et met à jour l’état pour les badges UI.
    whoami seul ne prouve PAS une liaison authentifiée — une clé d’accès est requise.
    Retourne l’état : "linked" | "offline" | "connecting" | "disabled"
*/
if (!hasInterface) exitWith { "disabled" };

if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {
    missionNamespace setVariable ["COMSPEC_LinkState", "disabled", false];
    [] call comspec_overwatch_connect_fnc_updateStatusBadges;
    "disabled"
};

private _url = missionNamespace getVariable ["comspec_overwatch_api_url", ""];
if (!(_url isEqualType "")) then { _url = format ["%1", _url]; };
_url = trim _url;
if (_url isEqualTo "") exitWith {
    missionNamespace setVariable ["COMSPEC_LinkState", "offline", false];
    missionNamespace setVariable ["COMSPEC_LinkDetail", "Adresse Athena non renseignée", false];
    [] call comspec_overwatch_connect_fnc_updateStatusBadges;
    "offline"
};

private _key = missionNamespace getVariable ["comspec_overwatch_api_key", ""];
if (!(_key isEqualType "")) then { _key = format ["%1", _key]; };
_key = trim _key;

missionNamespace setVariable ["COMSPEC_LinkState", "connecting", false];
[] call comspec_overwatch_connect_fnc_updateStatusBadges;

private _raw = ["COMSPECExtension" callExtension ["GetClientIp", []]] call comspec_overwatch_connect_fnc_extResult;
private _parts = _raw splitString "|";
private _prefix = if (count _parts >= 1) then { _parts select 0 } else { "" };
private _payload = if (count _parts >= 2) then { _parts select 1 } else { _raw };

private _state = "offline";
private _detail = "";

if (_prefix == "OK") then {
    if (!(_payload isEqualTo "") && {!(_payload isEqualTo "—")}) then {
        missionNamespace setVariable ["COMSPEC_userIp", _payload, true];
    };
    // whoami public ≠ auth : sans clé, portail joignable seulement.
    if ((count _key) < 4) then {
        _state = "offline";
        _detail = "Compte non lié";
        [format ["[Athena] Portail joignable (adresse %1) mais compte non lié.", missionNamespace getVariable ["COMSPEC_userIp", "—"]]] call comspec_overwatch_connect_fnc_appendLinkLog;
    } else {
        _state = "linked";
        _detail = "";
        missionNamespace setVariable ["COMSPEC_LastHealthOk", diag_tickTime, false];
        [format ["[Athena] Contrôle liaison OK (adresse %1).", missionNamespace getVariable ["COMSPEC_userIp", "—"]]] call comspec_overwatch_connect_fnc_appendLinkLog;
    };
} else {
    _state = "offline";
    _detail = switch (_payload) do {
        case "not_connected": { "Liaison non établie" };
        case "invalid": { "Extension indisponible" };
        default {
            if (_payload isEqualTo "") then { "Hors liaison" } else { format ["Hors liaison (%1)", _payload] };
        };
    };
    [format ["[Athena] Contrôle liaison échoué : %1", _detail]] call comspec_overwatch_connect_fnc_appendLinkLog;
};

missionNamespace setVariable ["COMSPEC_LinkState", _state, false];
missionNamespace setVariable ["COMSPEC_LinkDetail", _detail, false];
if (_state isEqualTo "linked" || {_prefix == "OK"}) then {
    [] call comspec_overwatch_connect_fnc_measureLatency;
};
[] call comspec_overwatch_connect_fnc_updateStatusBadges;
_state
