if (!hasInterface) exitWith {};

// Restaure une liaison précédente (code Athena) si les réglages CBA sont encore vides
private _url = missionNamespace getVariable ["comspec_overwatch_api_url", ""];
if (_url isEqualTo "") then {
    _url = profileNamespace getVariable ["comspec_overwatch_saved_api_url", ""];
    if (!(_url isEqualTo "")) then {
        missionNamespace setVariable ["comspec_overwatch_api_url", _url];
    };
};
private _key = missionNamespace getVariable ["comspec_overwatch_api_key", ""];
if (_key isEqualTo "") then {
    _key = profileNamespace getVariable ["comspec_overwatch_saved_api_key", ""];
    if (!(_key isEqualTo "")) then {
        missionNamespace setVariable ["comspec_overwatch_api_key", _key];
    };
};
private _tenant = missionNamespace getVariable ["comspec_overwatch_tenant_id", ""];
if (_tenant isEqualTo "") then {
    _tenant = profileNamespace getVariable ["comspec_overwatch_saved_tenant_id", ""];
    if (!(_tenant isEqualTo "")) then {
        missionNamespace setVariable ["comspec_overwatch_tenant_id", _tenant];
    };
};

_url = missionNamespace getVariable ["comspec_overwatch_api_url", ""];
if (_url == "") exitWith {
    missionNamespace setVariable ["COMSPEC_LinkState", "offline", false];
    missionNamespace setVariable ["COMSPEC_LinkDetail", "Adresse Athena non renseignée", false];
    [] call comspec_overwatch_connect_fnc_updateStatusBadges;
};

missionNamespace setVariable ["COMSPEC_LinkState", "connecting", false];
missionNamespace setVariable ["COMSPEC_LinkDetail", "", false];
[] call comspec_overwatch_connect_fnc_updateStatusBadges;

_key = missionNamespace getVariable ["comspec_overwatch_api_key", ""];
private _result = ["COMSPECExtension" callExtension ["Connect", [_url, _key]]] call comspec_overwatch_connect_fnc_extResult;
private _parts = _result splitString "|";
private _prefix = if (count _parts >= 1) then { _parts select 0 } else { "" };
private _payload = if (count _parts >= 2) then { _parts select 1 } else { _result };

private _log = missionNamespace getVariable ["COMSPEC_Log", ""];
if (_prefix == "OK") then {
    _log = _log + "[Athena] Liaison établie.\n";
    missionNamespace setVariable ["COMSPEC_LinkState", "linked", false];
    missionNamespace setVariable ["COMSPEC_LinkDetail", "", false];
    private _ipResult = ["COMSPECExtension" callExtension ["GetClientIp", []]] call comspec_overwatch_connect_fnc_extResult;
    private _ipParts = _ipResult splitString "|";
    private _ipPrefix = if (count _ipParts >= 1) then { _ipParts select 0 } else { "" };
    private _userIp = if (count _ipParts >= 2) then { _ipParts select 1 } else { "—" };
    if (_ipPrefix == "OK") then {
        missionNamespace setVariable ["COMSPEC_userIp", _userIp, true];
        missionNamespace setVariable ["COMSPEC_LastHealthOk", diag_tickTime, false];
    } else {
        missionNamespace setVariable ["COMSPEC_userIp", "—", true];
        // Connect OK localement mais le portail ne répond pas encore
        missionNamespace setVariable ["COMSPEC_LinkState", "offline", false];
        missionNamespace setVariable ["COMSPEC_LinkDetail", "Portail injoignable", false];
        _log = _log + "[Athena] Portail injoignable après connexion.\n";
    };
} else {
    missionNamespace setVariable ["COMSPEC_LinkState", "offline", false];
    missionNamespace setVariable ["COMSPEC_LinkDetail", "Échec de liaison", false];
    if (_prefix == "ERR") then {
        _log = _log + "[Athena] Échec : " + _payload + "\n";
    };
};
missionNamespace setVariable ["COMSPEC_Log", _log, true];
[] call comspec_overwatch_connect_fnc_updateStatusBadges;

private _display = uiNamespace getVariable ["COMSPEC_Chat_Display", displayNull];
if (!isNull _display) then {
    private _ctrl = _display displayCtrl 1402;
    if (!isNull _ctrl) then { _ctrl ctrlSetText _log; };
    private _ipCtrl = _display displayCtrl 1398;
    private _ip = missionNamespace getVariable ["COMSPEC_userIp", "—"];
    if (!isNull _ipCtrl) then { _ipCtrl ctrlSetText ("Votre adresse : " + _ip); };
};
