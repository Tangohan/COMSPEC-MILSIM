/*

    Synchronise l’identité terminal + certificat métier avec Athena.

    Params: [_pairingToken, _force]

    Retour : true si le terminal est enregistré.

*/

params [

    ["_pairingToken", "", [""]],

    ["_force", false, [true]]

];



if (!hasInterface) exitWith { false };

if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };

if ((missionNamespace getVariable ["COMSPEC_LinkState", "offline"]) isNotEqualTo "linked") exitWith { false };



private _last = missionNamespace getVariable ["COMSPEC_AtakRealismLastSync", -1e9];

if (!_force && {_pairingToken isEqualTo ""} && {(diag_tickTime - _last) < 180}) exitWith { false };



missionNamespace setVariable ["COMSPEC_AtakRealismLastError", "", false];



private _terminalUid = [] call comspec_overwatch_connect_fnc_getTerminalUid;

if (!(_terminalUid isEqualType "") || {_terminalUid isEqualTo ""}) exitWith {

    missionNamespace setVariable ["COMSPEC_AtakRealismLastError", "Identifiant terminal manquant.", false];

    false

};



private _modVersion = [] call comspec_overwatch_connect_fnc_getModVersion;

if ((parseNumber (_modVersion select [0, 1])) < 1) exitWith { false };



private _reg = [_pairingToken] call comspec_overwatch_connect_fnc_registerAtakTerminal;

if (count _reg < 1) exitWith {

    private _err = missionNamespace getVariable ["COMSPEC_AtakRealismLastError", ""];

    if (_err isEqualTo "") then {

        missionNamespace setVariable ["COMSPEC_AtakRealismLastError", "Échec d’enregistrement du terminal.", false];

    };

    false

};

_reg params [["_terminalId", "", [""]], ["_uid", "", [""]], ["_termStatus", "", [""]]];

if (_terminalId isEqualType 0) then { _terminalId = str _terminalId; };



private _uidForApi = _terminalUid;

if (_uid isEqualType "" && {_uid isNotEqualTo ""} && {(toLower _uid) find "<null" < 0}) then {

    _uidForApi = _uid;

};

if (!(_uidForApi isEqualType "") || {_uidForApi isEqualTo ""}) exitWith {

    missionNamespace setVariable ["COMSPEC_AtakRealismLastError", "Identifiant terminal vide.", false];

    false

};



private _extRet = "COMSPECExtension" callExtension ["GetTerminalRealism", [_uidForApi]];

private _raw = [_extRet] call comspec_overwatch_connect_fnc_extResult;

private _map = createHashMap;

private _parts = _raw splitString "|";

if ((count _parts) >= 2 && {(_parts select 0) isEqualTo "OK"}) then {

    private _payload = _raw select [3, count _raw - 3];

    private _nl = toString [10];

    {

        private _line = trim _x;

        if (_line isEqualTo "") then { continue };

        private _cols = _line splitString (toString [9]);

        if (count _cols >= 2) then {

            _map set [_cols select 0, _cols select 1];

        };

    } forEach (_payload splitString _nl);

} else {

    private _code = if (count _parts >= 2) then { _parts select 1 } else { _raw };

    diag_log format ["[COMSPEC] GetTerminalRealism : %1", _raw];

    missionNamespace setVariable [

        "COMSPEC_AtakRealismLastError",

        format ["Lecture terminal : %1", _code],

        false

    ];

};



private _isBadStr = {

    params ["_v"];

    if (!(_v isEqualType "")) exitWith { true };

    private _t = toLower (trim _v);

    if (_t isEqualTo "") exitWith { true };

    if (_t in ["null", "<null>", "<nul>", "nil"]) exitWith { true };

    if (_t find "<null" >= 0 || {_t find "<nul>" >= 0}) exitWith { true };

    false

};



private _autoPairing = (_map getOrDefault ["auto_pairing", "1"]) isEqualTo "1";

private _certStatus = toLower (_map getOrDefault ["cert_status", "missing"]);

private _certExpires = _map getOrDefault ["cert_expires", ""];

private _certRef = _map getOrDefault ["certificate_ref", ""];

private _certDurationDays = _map getOrDefault ["cert_duration_days", ""];
if (_certDurationDays isNotEqualTo "") then {
    missionNamespace setVariable ["COMSPEC_CertDurationDays", _certDurationDays, false];
};

if ([_certRef] call _isBadStr) then {

    _certRef = "";

    if (_certStatus in ["active", "issued"]) then { _certStatus = "missing"; };

};



private _apiTermStatus = _map getOrDefault ["terminal_status", _termStatus];

if (!([_apiTermStatus] call _isBadStr)) then {

    missionNamespace setVariable ["COMSPEC_TerminalStatus", _apiTermStatus, false];

};



missionNamespace setVariable ["COMSPEC_CertStatus", _certStatus, false];

missionNamespace setVariable ["COMSPEC_CertExpires", _certExpires, false];

if (!([_certRef] call _isBadStr)) then {

    missionNamespace setVariable ["COMSPEC_CertRef", _certRef, false];

};



// Délivrer / renouveler seulement si absent, expiré, révoqué ou référence corrompue

private _needCert = _autoPairing && {

    (_certStatus in ["", "missing", "expired", "revoked"]) || {[_certRef] call _isBadStr}

};



if (_needCert) then {

    private _cert = [_terminalId, "", ""] call comspec_overwatch_connect_fnc_registerAtakCertificate;

    if (count _cert >= 2) then {

        _cert params ["_ref", "_newStatus", ["_newExpires", ""]];

        if (!([_ref] call _isBadStr)) then {

            _certRef = _ref;

            missionNamespace setVariable ["COMSPEC_CertRef", _ref, false];

        };

        _certStatus = toLower _newStatus;

        if (_newExpires isNotEqualTo "") then { _certExpires = _newExpires; };

        missionNamespace setVariable ["COMSPEC_CertStatus", _certStatus, false];

        missionNamespace setVariable ["COMSPEC_CertExpires", _certExpires, false];

        if (_force || {_pairingToken isNotEqualTo ""}) then {

            ["Certificat du terminal synchronisé.", "link", "info"] call comspec_overwatch_connect_fnc_announce;

        };

    } else {

        private _cerr = missionNamespace getVariable ["COMSPEC_AtakRealismLastError", ""];

        if (_cerr isEqualTo "") then {

            missionNamespace setVariable ["COMSPEC_AtakRealismLastError", "Impossible d’obtenir le certificat.", false];

        };

    };

};



missionNamespace setVariable ["COMSPEC_AtakRealismLastSync", diag_tickTime, false];

missionNamespace setVariable ["COMSPEC_AtakRealismReady", true, false];

missionNamespace setVariable ["COMSPEC_TerminalUid", _uidForApi, false];



[] call comspec_overwatch_connect_fnc_syncPlayerAtakPublicVars;



private _certShow = [_certStatus, _certExpires] call comspec_overwatch_connect_fnc_certStatusLabel;

[format [

    "[Athena] Terminal %1 — %2",

    _uidForApi,

    _certShow

]] call comspec_overwatch_connect_fnc_appendLinkLog;



true

