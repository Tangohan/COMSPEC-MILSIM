/*
    callExtension Athena avec journalisation tentative + résultat.

    Params:
        0: STRING — commande extension
        1: ARRAY  — arguments
        2: STRING — libellé métier court (optionnel, défaut = commande)
        3: BOOL   — journal modules (défaut false)
        4: BOOL   — journaliser aussi la tentative (défaut true)
        5: STRING — catégorie liaison (défaut "system")

    Retour: [_ok, _status, _detail] — même format que parseAtakExtResponse
*/
params [
    ["_cmd", "", [""]],
    ["_args", [], [[]]],
    ["_label", "", [""]],
    ["_moduleLog", false, [true]],
    ["_logAttempt", true, [true]],
    ["_linkCat", "system", [""]]
];

if (_cmd isEqualTo "") exitWith { [false, "ERR", "empty_cmd"] };
if (_label isEqualTo "") then { _label = _cmd; };

if (_logAttempt) then {
    [_label, "attempt", "", nil, _moduleLog, _linkCat] call comspec_overwatch_connect_fnc_logTransmission;
};

private _raw = "COMSPECExtension" callExtension [_cmd, _args];
private _parsed = [_raw] call comspec_overwatch_connect_fnc_parseAtakExtResponse;
_parsed params ["_ok", "_status", "_detail"];

private _extErr = missionNamespace getVariable ["COMSPEC_LastExtError", 0];
if (!_ok) then {
    private _why = _detail;
    if (_why isEqualTo "" && {_status isNotEqualTo ""}) then { _why = _status; };
    if (_extErr isEqualType 0 && {_extErr != 0}) then {
        _why = format ["%1 (ext %2)", _why, _extErr];
    };
    [_label, "fail", _why, _raw, _moduleLog, _linkCat] call comspec_overwatch_connect_fnc_logTransmission;
} else {
    [_label, "ok", if (_detail isEqualTo "") then { "OK" } else { _detail }, nil, _moduleLog, _linkCat] call comspec_overwatch_connect_fnc_logTransmission;
};

_parsed
