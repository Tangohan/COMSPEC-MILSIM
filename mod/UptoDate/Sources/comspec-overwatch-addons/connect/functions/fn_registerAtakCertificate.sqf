/*
    Demande l’émission / renouvellement du certificat métier pour un terminal.
    Params: [_terminalId, _certificateRef, _commonName]
    Retour : [certificateRef, status, expiresAt] ou []
*/
params [
    ["_terminalId", "", [""]],
    ["_certificateRef", "", [""]],
    ["_commonName", "", [""]]
];

if (!hasInterface) exitWith { [] };
if (_terminalId isEqualTo "") exitWith { [] };

private _terminalUid = missionNamespace getVariable ["COMSPEC_TerminalUid", ""];
if (_certificateRef isEqualTo "") then {
    _certificateRef = format ["OW-CERT-%1", _terminalUid];
};
if (_commonName isEqualTo "") then {
    _commonName = [] call comspec_overwatch_connect_fnc_getCallsign;
    if (_commonName isEqualTo "") then { _commonName = name player; };
};

private _serial = profileNamespace getVariable [format ["comspec_cert_serial_%1", _terminalUid], ""];
if (_serial isEqualTo "") then {
    _serial = format ["SN-%1-%2", [_terminalUid select [3, 6], getPlayerUID player select [0, 6]] joinString ""];
    profileNamespace setVariable [format ["comspec_cert_serial_%1", _terminalUid], _serial];
    saveProfileNamespace;
};

private _fingerprint = toLower format [
    "ow%1%2",
    _terminalUid select [3, 12],
    _serial select [0, 16]
];

private _raw = ["COMSPECExtension" callExtension [
    "RegisterCertificate",
    [_terminalId, _certificateRef, _commonName, _serial, _fingerprint]
]] call comspec_overwatch_connect_fnc_extResult;

private _parts = _raw splitString "|";
if ((count _parts) < 2 || {(_parts select 0) isNotEqualTo "OK"}) exitWith {
    private _code = if (count _parts >= 2) then { _parts select 1 } else { _raw };
    missionNamespace setVariable [
        "COMSPEC_AtakRealismLastError",
        [_code, "Impossible d’obtenir le certificat du terminal."] call comspec_overwatch_connect_fnc_realismErrorMessage,
        false
    ];
    diag_log format ["[COMSPEC] RegisterCertificate échec : %1", _raw];
    []
};

private _cols = (_parts select 1) splitString (toString [9]);
if (count _cols < 2) exitWith { [] };

private _ref = _cols select 0;
private _status = _cols select 1;
private _expires = if (count _cols >= 3) then { _cols select 2 } else { "" };

missionNamespace setVariable ["COMSPEC_CertRef", _ref, false];
missionNamespace setVariable ["COMSPEC_CertStatus", _status, false];
missionNamespace setVariable ["COMSPEC_CertExpires", _expires, false];

[_ref, _status, _expires]
