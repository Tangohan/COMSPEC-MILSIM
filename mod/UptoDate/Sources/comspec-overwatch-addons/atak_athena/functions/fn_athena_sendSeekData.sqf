/*
    Transmet vers Athena toutes les données SEEK / SSE disponibles :
    - fiche(s) personne + biométrie (cible SEEK, record UI, alentours)
    - file d’attente SSE (flush)
    Params : [_silent] — true = pas de bandeau panneau (Resynch global).
*/
params [["_silent", false, [true]]];

if (!hasInterface) exitWith {};

if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {
    [
        "Overwatch est désactivé — impossible de transmettre.",
        "error",
        6
    ] call comspec_overwatch_atak_athena_fnc_athena_setPanelFeedback;
};

private _setFb = {
    params ["_msg", ["_kind", "info"], ["_dur", 6]];
    if (!isNil "comspec_overwatch_atak_athena_fnc_athena_setPanelFeedback") then {
        [_msg, _kind, _dur] call comspec_overwatch_atak_athena_fnc_athena_setPanelFeedback;
    } else {
        systemChat _msg;
    };
};

private _targets = [];
private _push = {
    params ["_obj"];
    if (isNull _obj) exitWith {};
    if !(_obj isKindOf "CAManBase") exitWith {};
    if (_obj in _targets) exitWith {};
    _targets pushBack _obj;
};

[missionNamespace getVariable ["comspec_sse_seekTarget", objNull]] call _push;

if (!isNil "comspec_sse_fnc_uiGetRecord") then {
    private _rec = [] call comspec_sse_fnc_uiGetRecord;
    [_rec] call _push;
};

[cursorObject] call _push;
[cursorTarget] call _push;

// Alentours avec données SSE déjà générées
private _near = nearestObjects [player, ["CAManBase"], 80];
{
    if (!isNil { _x getVariable "comspec_sse_data" }) then {
        [_x] call _push;
    };
} forEach _near;

private _txCount = 0;
{
    private _ent = _x;
    if (!isNil "comspec_sse_fnc_ensureGenerated") then {
        [_ent] call comspec_sse_fnc_ensureGenerated;
    };
    if (!isNil "comspec_sse_fnc_submitPersonRecord") then {
        [_ent] call comspec_sse_fnc_submitPersonRecord;
        _txCount = _txCount + 1;
    };
    if (!isNil "comspec_sse_fnc_submitBiometricsSim") then {
        [_ent] call comspec_sse_fnc_submitBiometricsSim;
    };
    if (!isNil "comspec_sse_fnc_submitDigitalAcquisition") then {
        [_ent] call comspec_sse_fnc_submitDigitalAcquisition;
    };
} forEach _targets;

// Vider la file SSE (plusieurs lots si besoin)
private _flushed = 0;
if (!isNil "comspec_sse_fnc_flushQueue") then {
    for "_i" from 1 to 6 do {
        private _n = [] call comspec_sse_fnc_flushQueue;
        if (!(_n isEqualType 0)) then { _n = 0; };
        _flushed = _flushed + _n;
        private _q = missionNamespace getVariable ["comspec_sse_txQueue", []];
        if (!(_q isEqualType []) || {(count _q) < 1}) exitWith {};
    };
};

private _pending = 0;
private _qLeft = missionNamespace getVariable ["comspec_sse_txQueue", []];
if (_qLeft isEqualType []) then { _pending = count _qLeft; };

private _msg = switch (true) do {
    case (_txCount < 1 && {_flushed < 1}): {
        "Aucune donnée SEEK à transmettre — scannez une personne ou ouvrez une fiche."
    };
    case (_pending > 0): {
        format [
            "SEEK : %1 fiche(s) mise(s) en file, %2 déjà envoyée(s). Il reste %3 en attente.",
            _txCount,
            _flushed,
            _pending
        ]
    };
    default {
        format [
            "SEEK : %1 fiche(s) traitée(s), %2 élément(s) transmis vers Athena.",
            _txCount,
            _flushed max _txCount
        ]
    };
};

if (!_silent) then {
    [_msg, if (_txCount < 1 && {_flushed < 1}) then { "warn" } else { "ok" }, 8] call _setFb;
};

if (!isNil "comspec_overwatch_connect_fnc_appendModuleLog") then {
    [format ["[SEEK] Tx all — fiches=%1 flush=%2 pending=%3", _txCount, _flushed, _pending]] call comspec_overwatch_connect_fnc_appendModuleLog;
};

if (!isNil "comspec_overwatch_atak_athena_fnc_athena_updatePanel") then {
    [] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
};

true
