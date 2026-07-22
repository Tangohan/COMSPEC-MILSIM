/*
    Remplit la liste des alertes médicales actives (fenêtre 30 min, côté Athena).
*/
if (!hasInterface) exitWith {};

private _display = uiNamespace getVariable ["COMSPEC_MedicalInbox_Display", displayNull];
if (isNull _display) exitWith {};

private _list = _display displayCtrl 9501;
if (isNull _list) exitWith {};
lbClear _list;

private _hint = _display displayCtrl 9502;

if !([] call comspec_overwatch_connect_fnc_canTriageMedical) exitWith {
    if (!isNull _hint) then {
        _hint ctrlSetStructuredText parseText "<t align='center' size='0.55' color='#fca5a5'>Accès réservé aux médecins et chefs d’équipe.</t>";
    };
};

// Rafraîchir depuis Athena si possible
[] call comspec_overwatch_connect_fnc_pollMedicalAlerts;

private _alerts = missionNamespace getVariable ["COMSPEC_MedicalAlerts", []];
private _shown = [];

{
    if (!(_x isEqualType createHashMap)) then { continue };
    private _id = str (_x getOrDefault ["id", ""]);
    if (_id isEqualTo "" || {_id isEqualTo "0"}) then { continue };

    private _kind = _x getOrDefault ["kind", "medical_alert"];
    private _callSign = _x getOrDefault ["call_sign", ""];
    private _label = _x getOrDefault ["label", "Assistance médicale"];
    private _grid = _x getOrDefault ["grid", ""];
    private _status = _x getOrDefault ["triage_status", "a_secourir"];
    private _statusLabel = _x getOrDefault ["triage_label", "À secourir"];
    private _created = _x getOrDefault ["created_at", ""];

    private _kindLabel = switch (toLower _kind) do {
        case "cardiac_arrest": { "Arrêt cardiaque" };
        case "unconscious": { "Inconscient" };
        case "wia_report": { "Bilan santé" };
        default { "Assistance" };
    };

    private _line = format ["[%1] %2 — %3", _statusLabel, _kindLabel, if (_callSign isEqualTo "") then { _label } else { _callSign }];
    if (_grid != "") then { _line = _line + format [" · Grille %1", _grid]; };
    if (_created != "") then { _line = _line + format [" · %1", _created]; };

    private _idx = _list lbAdd _line;
    _list lbSetData [_idx, _id];
    _shown pushBack _id;
} forEach _alerts;

uiNamespace setVariable ["COMSPEC_MedicalInbox_Ids", _shown];

if (!isNull _hint) then {
    private _txt = if ((count _shown) == 0) then {
        "Aucune alerte active (fenêtre de 30 minutes)."
    } else {
        format ["%1 alerte(s) — sélectionnez puis mettez à jour le statut.", count _shown]
    };
    _hint ctrlSetStructuredText parseText format ["<t align='center' size='0.55' color='#8aa0b4'>%1</t>", _txt];
};
