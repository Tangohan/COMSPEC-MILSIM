/*
    Envoie la fiche personne vers Athena, puis la biométrie si l’id est connu.
    [_entity, _includeDigital, _announce] call comspec_sse_fnc_transmitEntity
*/
params [
    ["_entity", objNull, [objNull]],
    ["_includeDigital", false, [true]],
    ["_announce", true, [true]]
];

// Appel non planifié (bouton d'interface, app SSE terrain) : relancé en spawn, sinon callExtension bloque le jeu
// pendant la requête HTTP (fn_extensionCall n'attend via extAwait qu'en contexte suspendable).
if (!canSuspend) exitWith { _this spawn comspec_sse_fnc_transmitEntity; true };

if (isNull _entity) exitWith {
    ["TX abandon — aucune cible", "WARN"] call comspec_sse_fnc_log;
    if (_announce) then { ["Aucun sujet à transmettre.", "WARNING"] call comspec_sse_fnc_notify; };
    false
};

[format ["TX start netId=%1", netId _entity]] call comspec_sse_fnc_log;

private _okP = false;
if (!isNil "comspec_sse_fnc_submitPersonRecord") then {
    _okP = [_entity, createHashMap, false] call comspec_sse_fnc_submitPersonRecord;
};

private _okB = false;
private _athenaId = _entity getVariable ["comspec_sse_athenaPersonId", ""];
if (!(_athenaId isEqualType "")) then { _athenaId = str _athenaId; };
if (
    !isNil "comspec_sse_fnc_submitBiometricsSim"
    && {_okP || {_athenaId isNotEqualTo "" && {_athenaId isNotEqualTo "0"}}}
) then {
    _okB = [_entity, createHashMap, false] call comspec_sse_fnc_submitBiometricsSim;
};

private _okD = false;
if (_includeDigital && {!isNil "comspec_sse_fnc_submitDigitalAcquisition"}) then {
    private _devs = [_entity, "digitalDevices"] call comspec_sse_fnc_getSection;
    if (!isNil "_devs" && {_devs isEqualType []} && {(count _devs) > 0}) then {
        _okD = [_entity, createHashMap, false] call comspec_sse_fnc_submitDigitalAcquisition;
    };
};

[format ["TX done person=%1 bio=%2 digital=%3 raw=%4", _okP, _okB, _okD, missionNamespace getVariable ["comspec_sse_lastExtRaw", ""]]] call comspec_sse_fnc_log;

if (_announce) then {
    if (_okP) then {
        ["Fiche d’identité envoyée au registre.", "SUCCESS"] call comspec_sse_fnc_notify;
    } else {
        ["La fiche n’est pas encore arrivée au registre. Elle est mise en attente — le détail est dans le journal COMSPEC.", "WARNING"] call comspec_sse_fnc_notify;
    };
};

_okP
