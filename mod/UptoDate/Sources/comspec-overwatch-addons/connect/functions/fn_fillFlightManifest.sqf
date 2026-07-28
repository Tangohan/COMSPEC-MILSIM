/*
    Remplit le manifeste de vol (indicatif, modèle, type, fréquence).
    À pied / hors aéronef : libellés clairs + champs modifiables (FAC / TOC air).
*/
if (!hasInterface) exitWith {};

private _veh = vehicle player;
private _display = uiNamespace getVariable ["COMSPEC_FlightManifest_Display", displayNull];
if (isNull _display) exitWith {};

private _inAir =
    (_veh isKindOf "Air")
    || {_veh isKindOf "Plane"}
    || {_veh isKindOf "Helicopter"}
    || {_veh isKindOf "UAV"};

private _callsign = ctrlText (_display displayCtrl 1501);
_callsign = trim _callsign;
if (_callsign isEqualTo "") then {
    _callsign = _veh getVariable ["COMSPEC_Callsign", ""];
};
if (!(_callsign isEqualType "") || {_callsign isEqualTo ""}) then {
    _callsign = [] call comspec_overwatch_connect_fnc_getCallsign;
};
_callsign = trim _callsign;
if (_callsign isEqualTo "" || {(toLower _callsign) in ["unknown", "inconnu", "operateur", "opérateur"]}) then {
    private _g = trim (groupId (group player));
    if (_g isNotEqualTo "") then { _callsign = _g; };
};
if (_callsign isEqualTo "") then { _callsign = "N-01"; };

private _model = "";
private _typeLabel = "Non classé";
private _aircraftType = "unknown";

if (_inAir) then {
    _model = getText (configFile >> "CfgVehicles" >> typeOf _veh >> "displayName");
    if (_model isEqualTo "") then { _model = typeOf _veh; };
    _aircraftType = [_veh] call comspec_overwatch_connect_fnc_getAircraftType;
    _typeLabel = switch (toLower _aircraftType) do {
        case "plane": { "Avion" };
        case "helicopter": { "Hélicoptère" };
        case "uav": { "Drone" };
        default { "Aéronef" };
    };
} else {
    _model = "À préciser (déclaration sol)";
    _typeLabel = "Déclaration sol";
    _aircraftType = "unknown";
};

private _freq = "Non détectée";
private _radioState = player call comspec_overwatch_connect_fnc_getRadioState;
if (_radioState != "" && {_radioState != "N/A|N/A|N/A"}) then {
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

// Mémoriser le type technique pour l’envoi (le libellé FR reste dans 1503)
uiNamespace setVariable ["COMSPEC_FlightManifest_AircraftType", _aircraftType];
uiNamespace setVariable ["COMSPEC_FlightManifest_InAir", _inAir];
