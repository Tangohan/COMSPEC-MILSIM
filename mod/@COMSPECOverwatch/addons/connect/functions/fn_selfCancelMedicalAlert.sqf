/*
    « Je vais bien » : le joueur annule lui-même sa propre alerte médicale active la plus
    récente, sans passer par un médecin/chef d'équipe. Réutilise TriageMedicalAlert (même
    voie que fn_medicalTriage) — le serveur autorise déjà toute écriture jeu authentifiée ;
    on se restreint ici côté client à SA PROPRE alerte (résolue par indicatif), jamais celle
    d'un autre joueur.
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

private _alert = [] call comspec_overwatch_connect_fnc_hasOwnActiveMedicalAlert;
private _alertId = if (_alert isEqualType createHashMap) then { _alert getOrDefault ["id", ""] } else { "" };
if (_alertId isEqualTo "") exitWith {
    ["COMSPEC_Info", ["Aucune alerte médicale active à annuler."]] call comspec_overwatch_connect_fnc_showNotification;
};

private _by = [] call comspec_overwatch_connect_fnc_getCallsign;
if (_by isEqualTo "") then { _by = name player; };

private _raw = ["COMSPECExtension" callExtension ["TriageMedicalAlert", [_alertId, "annule", _by]]] call comspec_overwatch_connect_fnc_extResult;
if (!(_raw isEqualType "") || {_raw isEqualTo ""}) exitWith {
    ["COMSPEC_Warning", ["Impossible de joindre Athena pour annuler l’alerte."]] call comspec_overwatch_connect_fnc_showNotification;
};

private _parts = _raw splitString "|";
private _prefix = if ((count _parts) >= 1) then { _parts select 0 } else { "" };
if (_prefix != "OK") exitWith {
    private _err = if ((count _parts) >= 2) then { _parts select 1 } else { "" };
    private _msg = switch (_err) do {
        case "not_found": { "Alerte introuvable — elle a peut-être déjà expiré." };
        case "not_migrated": { "Annulation indisponible sur ce serveur pour le moment." };
        default { "Impossible d’annuler l’alerte pour le moment." };
    };
    ["COMSPEC_Warning", [_msg]] call comspec_overwatch_connect_fnc_showNotification;
};

{
    if (!(_x isEqualType createHashMap)) then { continue };
    if ((str (_x getOrDefault ["id", ""])) isEqualTo _alertId) then {
        _x set ["triage_status", "annule"];
        _x set ["triage_label", "Annulé"];
    };
} forEach (missionNamespace getVariable ["COMSPEC_MedicalAlerts", []]);

missionNamespace setVariable ["COMSPEC_lastMedicalAlertKind", "", false];
["COMSPEC_Info", ["Alerte médicale annulée — vous avez signalé aller bien."]] call comspec_overwatch_connect_fnc_showNotification;
[format ["[Médical] %1 a annulé sa propre alerte (« je vais bien »)", _by], "medical"] call comspec_overwatch_connect_fnc_appendLinkLog;
