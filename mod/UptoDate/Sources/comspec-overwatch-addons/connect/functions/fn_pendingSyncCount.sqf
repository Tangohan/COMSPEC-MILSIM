/*
    Nombre de transmissions encore en attente (tampon jeu + file de liaison).
    Lecture seule, pour le badge téléphone. Ne déclenche pas d’envoi.
*/
private _n = 0;
if (!hasInterface) exitWith { _n };

private _cachedAt = missionNamespace getVariable ["COMSPEC_PendingSyncAt", -1e9];
if ((diag_tickTime - _cachedAt) < 1.2) exitWith {
    missionNamespace getVariable ["COMSPEC_PendingSyncCount", 0]
};

if (!isNil "comspec_overwatch_connect_fnc_outboxState") then {
    private _ob = ["get"] call comspec_overwatch_connect_fnc_outboxState;
    if (_ob isEqualType createHashMap) then {
        private _c = _ob getOrDefault ["count", 0];
        if (_c isEqualType 0) then { _n = _n + _c; };
    };
};

private _raw = "COMSPECExtension" callExtension ["GetPendingQueueCount", []];
private _s = if (_raw isEqualType []) then {
    if ((count _raw) > 0) then { _raw select 0 } else { "" }
} else {
    if (_raw isEqualType "") then { _raw } else { str _raw }
};
if (_s isEqualType "" && {_s select [0, 3] isEqualTo "OK|"}) then {
    private _dll = parseNumber (_s select [3]);
    if (_dll > 0) then { _n = _n + _dll; };
};

_n = (round _n) max 0;
missionNamespace setVariable ["COMSPEC_PendingSyncCount", _n, false];
missionNamespace setVariable ["COMSPEC_PendingSyncAt", diag_tickTime, false];
_n
