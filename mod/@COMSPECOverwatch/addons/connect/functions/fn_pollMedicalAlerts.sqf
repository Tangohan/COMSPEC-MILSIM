/*
    Interroge Athena pour les alertes médicales actives (≤ 30 min) et met à jour le cache local.
    Notifie en cas de nouvelle alerte critique non encore vue.
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

private _raw = ["COMSPECExtension" callExtension ["GetMedicalAlerts", ["1", "25"]]] call comspec_overwatch_connect_fnc_extResult;
if (!(_raw isEqualType "") || {_raw isEqualTo ""}) exitWith { false };
private _parts = _raw splitString "|";
private _prefix = if ((count _parts) >= 1) then { _parts select 0 } else { "" };
if (_prefix != "OK") exitWith { false };

private _body = if ((count _parts) >= 2) then { _raw select [3] } else { "" };
// Si le payload contient des | (rare), reconstituer après le premier "OK|"
if ((_raw select [0, 3]) isEqualTo "OK|") then { _body = _raw select [3]; };

private _lines = _body splitString (toString [10]);
private _alerts = [];

{
    private _line = _x;
    if (_line isEqualTo "") then { continue };
    private _cols = _line splitString (toString [9]);
    if ((count _cols) < 6) then { continue };

    private _id = _cols select 0;
    private _kind = _cols select 1;
    private _callSign = _cols select 2;
    private _label = _cols select 3;
    private _grid = _cols select 4;
    private _created = _cols select 5;
    private _triageStatus = if ((count _cols) > 6) then { _cols select 6 } else { "a_secourir" };
    private _triageLabel = if ((count _cols) > 7) then { _cols select 7 } else { "À secourir" };
    private _severity = if ((count _cols) > 8) then { _cols select 8 } else { "urgent" };

    private _hm = createHashMapFromArray [
        ["id", _id],
        ["kind", _kind],
        ["call_sign", _callSign],
        ["label", _label],
        ["grid", _grid],
        ["created_at", _created],
        ["triage_status", _triageStatus],
        ["triage_label", _triageLabel],
        ["severity", _severity]
    ];
    _alerts pushBack _hm;
} forEach _lines;

missionNamespace setVariable ["COMSPEC_MedicalAlerts", _alerts, false];

private _seen = missionNamespace getVariable ["COMSPEC_MedicalAlertsSeen", []];
{
    private _a = _x;
    private _id = _a getOrDefault ["id", ""];
    if (_id isEqualTo "" || {_id in _seen}) then { continue };
    _seen pushBack _id;
    if ((_a getOrDefault ["severity", ""]) isEqualTo "critical") then {
        private _cs = _a getOrDefault ["call_sign", ""];
        private _lb = _a getOrDefault ["label", "Assistance médicale"];
        private _kind = toLower (_a getOrDefault ["kind", ""]);
        private _msg = if (_cs isEqualTo "") then { _lb } else { format ["%1 — %2", _cs, _lb] };
        // Message enrichi du kind pour que showNotification choisisse death / unconscious
        private _toast = switch (_kind) do {
            case "cardiac_arrest";
            case "cardiac-arrest";
            case "death";
            case "dead";
            case "kia": { format ["Alerte médicale — arrêt cardiaque — %1", _msg] };
            case "unconscious": { format ["Alerte médicale — inconscient — %1", _msg] };
            default { format ["Alerte médicale — %1", _msg] };
        };
        ["COMSPEC_Warning", [_toast]] call comspec_overwatch_connect_fnc_showNotification;
        [format ["[Médical] %1", _msg], "medical"] call comspec_overwatch_connect_fnc_appendLinkLog;
    };
} forEach _alerts;

if (count _seen > 100) then { _seen deleteRange [0, (count _seen) - 100]; };
missionNamespace setVariable ["COMSPEC_MedicalAlertsSeen", _seen, false];

true
