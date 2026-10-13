/*
    Module « Relever routes et villes » (Eden et Zeus).
      Eden (mission lancée) : si « Au début de la mission », le Zeus ou le chef de mission envoie routes et villes
                              dès qu'Athena est liée (attente 3 min max).
      Zeus (ZEN / ACE Zeus)  : ["zeus"] → envoi immédiat, même si déjà transmis.
    Le relevé lui-même : fn_sampleGeoNetwork (lieux Arma + segments routiers, Geo.Ingest).
*/
if ((_this param [0, ""]) isEqualTo "zeus") exitWith {
    if (!hasInterface) exitWith { false };
    if ((missionNamespace getVariable ["COMSPEC_LinkState", "offline"]) isNotEqualTo "linked") exitWith {
        ["COMSPEC_Warning", ["Athena n'est pas liée : routes et villes ne peuvent pas partir." call comspec_overwatch_connect_fnc_tr]] call comspec_overwatch_connect_fnc_showNotification;
        false
    };
    ["INFO", "Geo", "Relevé routes et villes demandé depuis Zeus"] call comspec_overwatch_connect_fnc_log;
    [0, true] call comspec_overwatch_connect_fnc_sampleGeoNetwork;
    true
};

params [["_logic", objNull], ["_units", []], ["_activated", true]];
if (!(_logic isEqualType objNull) || {isNull _logic}) exitWith { false };
if (!(_activated isEqualType true) || {!_activated}) exitWith { false };
if (is3DEN) exitWith { true };
if (!hasInterface) exitWith { false };

private _mode = _logic getVariable ["RunAtStart", "start"];
private _resend = _logic getVariable ["Resend", false];
if (!(_resend isEqualType true)) then { _resend = false; };
private _run = if (_mode isEqualType "") then { (toLower (trim _mode)) in ["start", "1", "true", "oui", "auto"] } else { _mode isEqualTo true };

private _isZeus = !isNull player && {!isNull (getAssignedCuratorLogic player)};
private _isAdmin = (!isMultiplayer) || {serverCommandAvailable "#kick"};
if (!_run || {!(_isZeus || _isAdmin)}) exitWith { true };

[_resend] spawn {
    params ["_resend"];
    private _t0 = diag_tickTime;
    waitUntil {
        uiSleep 5;
        ((missionNamespace getVariable ["COMSPEC_LinkState", "offline"]) isEqualTo "linked") || {(diag_tickTime - _t0) > 180}
    };
    if ((missionNamespace getVariable ["COMSPEC_LinkState", "offline"]) isNotEqualTo "linked") exitWith {
        ["WARN", "Geo", "Relevé routes et villes abandonné : Athena non liée après 3 min"] call comspec_overwatch_connect_fnc_log;
    };
    ["INFO", "Geo", format ["Relevé routes et villes au début de la mission (renvoi : %1)", _resend]] call comspec_overwatch_connect_fnc_log;
    [0, _resend] call comspec_overwatch_connect_fnc_sampleGeoNetwork;
};
true
