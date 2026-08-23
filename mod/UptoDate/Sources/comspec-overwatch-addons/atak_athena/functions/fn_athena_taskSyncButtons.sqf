/*
    Boutons TASK selon l’état de l’ordre sélectionné.
    À traiter : Accepter + Refuser
    Accepté : En cours + Abort
    En cours : Abort
    Terminé / refusé / annulé / aucun : aucun bouton d’action
*/
if (!hasInterface) exitWith {};

private _group = uiNamespace getVariable ["COMSPEC_ATAK_Task_group", controlNull];
if (isNull _group) exitWith {};

private _btnAccept = _group controlsGroupCtrl 9904;
private _btnExec = _group controlsGroupCtrl 9905;
private _btnRefuse = _group controlsGroupCtrl 9906;
private _btnAbort = _group controlsGroupCtrl 9908;

private _showAccept = false;
private _showExec = false;
private _showRefuse = false;
private _showAbort = false;

private _id = uiNamespace getVariable ["COMSPEC_ATAK_Task_selectedId", ""];
if (_id isNotEqualTo "") then {
    private _status = "";
    {
        if ((_x getOrDefault ["id", ""]) isEqualTo _id) exitWith {
            _status = toUpper (_x getOrDefault ["status", "PENDING"]);
        };
    } forEach (missionNamespace getVariable ["COMSPEC_Orders", []]);

    switch (_status) do {
        case "ACK": {
            _showExec = true;
            _showAbort = true;
        };
        case "EXEC": {
            _showAbort = true;
        };
        case "FAILED";
        case "CANCELLED";
        case "DONE";
        case "CLOSED": {};
        default {
            // PENDING, DELIVERED, ou état inconnu encore à traiter
            _showAccept = true;
            _showRefuse = true;
        };
    };
};

if (!isNull _btnAccept) then { _btnAccept ctrlShow _showAccept; };
if (!isNull _btnExec) then { _btnExec ctrlShow _showExec; };
if (!isNull _btnRefuse) then { _btnRefuse ctrlShow _showRefuse; };
if (!isNull _btnAbort) then { _btnAbort ctrlShow _showAbort; };
