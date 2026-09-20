/*
    Envoie un statut pilote court vers le poste (sans fermer le manifeste).
    params: [_status] ROGER, INBOUND, ONSTA, ENGAGED, RTB
*/
params ["_status"];
if (!hasInterface) exitWith {};

private _display = uiNamespace getVariable ["COMSPEC_FlightManifest_Display", displayNull];
private _csCtrl = [1501] call comspec_overwatch_connect_fnc_manifestCtrl;
private _callsign = "";
if (!isNull _csCtrl) then {
    _callsign = trim (ctrlText _csCtrl);
};
if (_callsign isEqualTo "") then {
    private _veh = vehicle player;
    _callsign = _veh getVariable ["COMSPEC_Callsign", ""];
};
if (!(_callsign isEqualType "") || {_callsign isEqualTo ""}) then {
    _callsign = [] call comspec_overwatch_connect_fnc_getCallsign;
};
if (_callsign isEqualTo "") then { _callsign = trim (groupId (group player)); };
if (_callsign isEqualTo "") then { _callsign = "PILOT"; };

private _s = toUpper (_status param [0, ""]);
if (!(_s in ["ROGER", "INBOUND", "ONSTA", "ENGAGED", "RTB"])) exitWith {};

"COMSPECExtension" callExtension ["PilotResponse", [_callsign, _s]];
[format ["[PILOT] %1 -> %2", _callsign, _s]] call comspec_overwatch_connect_fnc_appendLinkLog;

private _lab = switch _s do {
    case "ROGER": { "Reçu" };
    case "INBOUND": { "En approche" };
    case "ONSTA": { "À poste" };
    case "ENGAGED": { "Engagé" };
    case "RTB": { "Retour" };
    default { _s };
};
[format ["Réponse transmise au poste : %1.", _lab], "system", "info"] call comspec_overwatch_connect_fnc_announce;
