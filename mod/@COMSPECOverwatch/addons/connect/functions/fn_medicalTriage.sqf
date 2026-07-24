/*
    Applique un statut de triage sur l’alerte sélectionnée.
    Params: [_status] — a_secourir | en_cours | traite | kia | annule
*/
params [["_status", "traite", [""]]];

if (!hasInterface) exitWith {};

if !([] call comspec_overwatch_connect_fnc_canTriageMedical) exitWith {
    ["COMSPEC_Warning", ["Seul un médecin ou un chef d’équipe peut faire le triage des alertes."]] call comspec_overwatch_connect_fnc_showNotification;
};

private _display = uiNamespace getVariable ["COMSPEC_MedicalInbox_Display", displayNull];
if (isNull _display) exitWith {};

private _list = _display displayCtrl 9501;
if (isNull _list) exitWith {};

private _idx = lbCurSel _list;
if (_idx < 0) exitWith {
    ["COMSPEC_Warning", ["Sélectionnez d’abord une alerte."]] call comspec_overwatch_connect_fnc_showNotification;
};

private _alertId = _list lbData _idx;
if (_alertId isEqualTo "") exitWith {};

private _statusNorm = toLower _status;
if !(_statusNorm in ["a_secourir", "en_cours", "traite", "kia", "annule"]) exitWith {
    ["COMSPEC_Warning", ["Statut de triage non reconnu."]] call comspec_overwatch_connect_fnc_showNotification;
};

private _by = [] call comspec_overwatch_connect_fnc_getCallsign;
if (_by isEqualTo "") then { _by = name player; };

private _raw = ["COMSPECExtension" callExtension ["TriageMedicalAlert", [_alertId, _statusNorm, _by]]] call comspec_overwatch_connect_fnc_extResult;
if (!(_raw isEqualType "") || {_raw isEqualTo ""}) exitWith {
    ["COMSPEC_Warning", ["Impossible de joindre Athena pour le triage."]] call comspec_overwatch_connect_fnc_showNotification;
};

private _parts = _raw splitString "|";
private _prefix = if ((count _parts) >= 1) then { _parts select 0 } else { "" };
if (_prefix == "ERR") exitWith {
    private _err = if ((count _parts) >= 2) then { _parts select 1 } else { "" };
    private _msg = switch (_err) do {
        case "forbidden": { "Seul un médecin ou un chef d’équipe peut faire le triage." };
        case "not_found": { "Alerte introuvable." };
        case "not_migrated";
        case "unavailable": { "Triage indisponible sur ce serveur pour le moment." };
        default { "Impossible de mettre à jour le triage." };
    };
    ["COMSPEC_Warning", [_msg]] call comspec_overwatch_connect_fnc_showNotification;
};
if (_prefix != "OK") exitWith {
    ["COMSPEC_Warning", ["Impossible de mettre à jour le triage."]] call comspec_overwatch_connect_fnc_showNotification;
};

private _label = switch (_statusNorm) do {
    case "en_cours": { "In progress" };
    case "traite": { "Treated" };
    case "kia": { "KIA" };
    case "annule": { "Cancelled" };
    default { "To rescue" };
};

private _alerts = missionNamespace getVariable ["COMSPEC_MedicalAlerts", []];
{
    if (!(_x isEqualType createHashMap)) then { continue };
    if ((str (_x getOrDefault ["id", ""])) isEqualTo _alertId) exitWith {
        _x set ["triage_status", _statusNorm];
        _x set ["triage_label", _label];
    };
} forEach _alerts;
missionNamespace setVariable ["COMSPEC_MedicalAlerts", _alerts, false];

["COMSPEC_Info", [format ["Triage mis à jour — %1", _label]]] call comspec_overwatch_connect_fnc_showNotification;
[format ["[Médical] Triage %1 — alerte %2", _label, _alertId], "medical"] call comspec_overwatch_connect_fnc_appendLinkLog;
[] call comspec_overwatch_connect_fnc_medicalInboxOnLoad;
