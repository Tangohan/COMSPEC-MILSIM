/*

    Demande l’émission / renouvellement du certificat métier pour un terminal.

    Params: [_terminalId, _certificateRef, _commonName]

    Retour : [certificateRef, status, expiresAt] ou []

*/

params [

    ["_terminalId", "", ["", 0]],

    ["_certificateRef", "", [""]],

    ["_commonName", "", [""]]

];



if (!hasInterface) exitWith { [] };



if (_terminalId isEqualType 0) then { _terminalId = str _terminalId; };

if (!(_terminalId isEqualType "") || {_terminalId isEqualTo ""} || {(toLower _terminalId) in ["0", "null", "<null>"]}) exitWith { [] };



private _terminalUid = [] call comspec_overwatch_connect_fnc_getTerminalUid;

if (!(_terminalUid isEqualType "") || {_terminalUid isEqualTo ""}) exitWith { [] };



private _isBad = {

    params ["_v"];

    if (!(_v isEqualType "")) exitWith { true };

    private _t = toLower (trim _v);

    if (_t isEqualTo "") exitWith { true };

    if (_t in ["null", "<null>", "<nul>", "nil"]) exitWith { true };

    if ((count _t) >= 6 && {(_t select [0, 6]) isEqualTo "<null"}) exitWith { true };

    if (_t find "<null" >= 0) exitWith { true };

    false

};



if ([_certificateRef] call _isBad) then { _certificateRef = ""; };

if (_certificateRef isEqualTo "") then {

    _certificateRef = format ["OW-CERT-%1", _terminalUid];

};

// Jamais OW-CERT-<null>

if ([_certificateRef] call _isBad || {_certificateRef find "<null" >= 0} || {_certificateRef find "<nul" >= 0}) then {

    _certificateRef = format ["OW-CERT-%1", _terminalUid];

};



if (!(_commonName isEqualType "") || {_commonName isEqualTo ""}) then {

    _commonName = [] call comspec_overwatch_connect_fnc_getCallsign;

};

if (!(_commonName isEqualType "") || {_commonName isEqualTo ""}) then {

    _commonName = name player;

};

if (!(_commonName isEqualType "")) then { _commonName = "Operateur"; };



private _serialKey = format ["comspec_cert_serial_%1", _terminalUid];

private _serial = profileNamespace getVariable [_serialKey, ""];

if (!(_serial isEqualType "") || {_serial isEqualTo ""} || {[_serial] call _isBad}) then {

    private _uidPart = if ((count _terminalUid) >= 9) then { _terminalUid select [3, 6] } else { _terminalUid };

    private _steam = getPlayerUID player;

    if (!(_steam isEqualType "")) then { _steam = "000000"; };

    private _steamPart = if ((count _steam) >= 6) then { _steam select [0, 6] } else { _steam };

    _serial = format ["SN-%1-%2", _uidPart, _steamPart];

    profileNamespace setVariable [_serialKey, _serial];

    saveProfileNamespace;

};



private _fpUid = if ((count _terminalUid) >= 15) then { _terminalUid select [3, 12] } else { _terminalUid };

private _fpSerial = if ((count _serial) >= 16) then { _serial select [0, 16] } else { _serial };

private _fingerprint = toLower format ["ow%1%2", _fpUid, _fpSerial];



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



if ([_ref] call _isBad || {_ref find "<null" >= 0}) then {

    _ref = _certificateRef;

};



missionNamespace setVariable ["COMSPEC_CertRef", _ref, false];

missionNamespace setVariable ["COMSPEC_CertStatus", _status, false];

missionNamespace setVariable ["COMSPEC_CertExpires", _expires, false];



[_ref, _status, _expires]

