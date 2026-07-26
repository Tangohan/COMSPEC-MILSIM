/*
    Fills Flight Manifest dialog with auto-detected data (callsign, model, type, freq).
    Called from dialog onLoad.
*/
if (!hasInterface) exitWith {};

private _veh = vehicle player;
private _display = uiNamespace getVariable ["COMSPEC_FlightManifest_Display", displayNull];
if (isNull _display) exitWith {};

private _callsign = _veh getVariable ["COMSPEC_Callsign", ""];
if (_callsign isEqualTo "") then { _callsign = [] call comspec_overwatch_connect_fnc_getCallsign; };
if (_callsign == "") then { _callsign = groupId (group player); };
if (_callsign == "") then { _callsign = "PILOT"; };

private _model = getText (configFile >> "CfgVehicles" >> typeOf _veh >> "displayName");
if (_model == "") then { _model = typeOf _veh; };

private _aircraftType = [_veh] call comspec_overwatch_connect_fnc_getAircraftType;
private _typeLabel = switch (toLower _aircraftType) do {
    case "plane": { "Avion" };
    case "helicopter": { "Hélicoptère" };
    case "uav": { "Drone" };
    default { "Non classé" };
};

private _freq = "Non détectée";
private _radioState = player call comspec_overwatch_connect_fnc_getRadioState;
if (_radioState != "" && _radioState != "N/A|N/A|N/A") then {
    private _parts = _radioState splitString "|";
    if (count _parts >= 2) then {
        private _f = _parts select 1;
        if (_f != "" && {_f != "N/A"}) then { _freq = _f; };
    };
};

(_display displayCtrl 1501) ctrlSetText _callsign;
(_display displayCtrl 1502) ctrlSetText _model;
(_display displayCtrl 1503) ctrlSetText _typeLabel;
(_display displayCtrl 1504) ctrlSetText _freq;
