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
if (_terminalUid isEqualTo "") exitWith { false };

// Version minimale du mod (réglage serveur ATAK client non vérifiable en jeu)
private _modVersion = [] call comspec_overwatch_connect_fnc_getModVersion;
if ((parseNumber (_modVersion select [0, 1])) < 1) exitWith { false };

private _reg = [_pairingToken] call comspec_overwatch_connect_fnc_registerAtakTerminal;
if (count _reg < 1) exitWith { false };
_reg params ["_terminalId", "_uid", "_termStatus"];

private _raw = ["COMSPECExtension" callExtension ["GetTerminalRealism", [_terminalUid]]] call comspec_overwatch_connect_fnc_extResult;
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
};

private _autoPairing = (_map getOrDefault ["auto_pairing", "1"]) isEqualTo "1";
private _certStatus = toLower (_map getOrDefault ["cert_status", "missing"]);
private _certExpires = _map getOrDefault ["cert_expires", ""];

missionNamespace setVariable ["COMSPEC_CertStatus", _certStatus, false];
missionNamespace setVariable ["COMSPEC_CertExpires", _certExpires, false];

private _needCert = _autoPairing && {
    _certStatus in ["", "missing", "expired", "revoked", "issued"]
};

if (_needCert) then {
    private _cert = [_terminalId, "", ""] call comspec_overwatch_connect_fnc_registerAtakCertificate;
    if (count _cert >= 2) then {
        _cert params ["_ref", "_newStatus", "_newExpires"];
        _certStatus = toLower _newStatus;
        if (_newExpires isNotEqualTo "") then { _certExpires = _newExpires; };
        missionNamespace setVariable ["COMSPEC_CertStatus", _certStatus, false];
        missionNamespace setVariable ["COMSPEC_CertExpires", _certExpires, false];
        if (!_force && {_pairingToken isEqualTo ""}) then {
            ["Certificat du terminal activé.", "link", "info"] call comspec_overwatch_connect_fnc_announce;
        };
    };
} else {
    if (_certStatus in ["active", "issued"]) then {
        missionNamespace setVariable ["COMSPEC_CertRef", _map getOrDefault ["certificate_ref", ""], false];
    };
};

missionNamespace setVariable ["COMSPEC_AtakRealismLastSync", diag_tickTime, false];
missionNamespace setVariable ["COMSPEC_AtakRealismReady", true, false];

[format [
    "[Athena] Terminal %1 enregistré — certificat : %2",
    _uid,
    switch (toLower _certStatus) do {
        case "active": { "actif" };
        case "issued": { "émis" };
        case "expired": { "expiré" };
        case "revoked": { "révoqué" };
        case "missing": { "en attente" };
        default { _certStatus };
    }
]] call comspec_overwatch_connect_fnc_appendLinkLog;

true
