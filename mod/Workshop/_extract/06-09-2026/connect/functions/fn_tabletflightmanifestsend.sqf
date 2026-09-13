/*
    Envoie le Flight Manifest depuis la tablette (champs laser / auth / pax).
*/
if (!hasInterface) exitWith {};

private _veh = vehicle player;
private _inAir = (_veh isKindOf "Air") || {_veh isKindOf "Plane"} || {_veh isKindOf "Helicopter"} || {_veh isKindOf "UAV"};

private _callsign = _veh getVariable ["COMSPEC_Callsign", ""];
if (!(_callsign isEqualType "") || {_callsign isEqualTo ""}) then {
    _callsign = [] call comspec_overwatch_connect_fnc_getCallsign;
};
_callsign = trim _callsign;
if (_callsign isEqualTo "" || {(toLower _callsign) in ["unknown", "inconnu"]}) then {
    _callsign = trim (groupId (group player));
};
if (_callsign isEqualTo "") then { _callsign = "N-01"; };

private _model = "";
private _aircraftType = "ground";
if (_inAir) then {
    _model = getText (configFile >> "CfgVehicles" >> typeOf _veh >> "displayName");
    if (_model isEqualTo "") then { _model = typeOf _veh; };
    _aircraftType = [_veh] call comspec_overwatch_connect_fnc_getAircraftType;
    if ((toLower _aircraftType) isEqualTo "unknown") then { _aircraftType = "ground"; };
} else {
    _model = "Déclaration sol";
};

private _freq = "";
private _radioState = player call comspec_overwatch_connect_fnc_getRadioState;
if (_radioState != "" && {_radioState != "N/A|N/A|N/A"}) then {
    private _parts = _radioState splitString "|";
    if (count _parts >= 2) then {
        private _f = _parts select 1;
        if (_f != "" && {_f != "N/A"}) then { _freq = _f; };
    };
};

private _pos = getPosASL _veh;
private _heading = getDir _veh;
private _alt = _pos select 2;

private _laser = missionNamespace getVariable ["COMSPEC_TabletManifestLaser", "1688"];
if (!(_laser isEqualType "") || {_laser isEqualTo ""}) then { _laser = "1688"; };
private _auth = missionNamespace getVariable ["COMSPEC_TabletManifestAuth", ""];
if (!(_auth isEqualType "")) then { _auth = ""; };
private _countStr = missionNamespace getVariable ["COMSPEC_TabletManifestPax", "1"];
private _count = 1;
if (_countStr isEqualType "" && {_countStr != ""} && {parseNumber _countStr >= 1}) then {
    _count = round (parseNumber _countStr);
};

private _sideStr = "WEST";
switch (side player) do {
    case east: { _sideStr = "EAST"; };
    case independent: { _sideStr = "GUER"; };
    case civilian: { _sideStr = "CIV"; };
    default { _sideStr = "WEST"; };
};

private _posX = _pos select 0;
private _posY = _pos select 1;
private _fuelPct = 0;
if (_veh isKindOf "AllVehicles" && {!(_veh isKindOf "Man")}) then {
    _fuelPct = (round ((fuel _veh) * 100)) max 0 min 100;
};

private _payload = createHashMapFromArray [
    ["mapId", 1],
    ["callsign", _callsign],
    ["call_sign", _callsign],
    ["model", _model],
    ["aircraft_type", _aircraftType],
    ["freq", _freq],
    ["radio_main", _freq],
    ["laser", _laser],
    ["auth", _auth],
    ["auth_code", _auth],
    ["alt", _alt],
    ["altitude", _alt],
    ["heading", _heading],
    ["pos", [_posX, _posY]],
    ["pos_x", _posX],
    ["pos_y", _posY],
    ["side", _sideStr],
    ["aircraft_count", _count],
    ["status", "AVAILABLE"],
    ["fuel_pct", _fuelPct],
    ["lastUpdate", floor time],
    ["pilot", [] call comspec_overwatch_connect_fnc_getCallsign]
];

private _json = [_payload] call comspec_overwatch_connect_fnc_hashMapToJson;
if (!(_json isEqualType "") || {_json isEqualTo ""}) exitWith {
    ["Impossible de préparer le manifeste de vol.", "system", "warn"] call comspec_overwatch_connect_fnc_announce;
};

["SendFlightManifest", "attempt", format ["tablette %1", _callsign], nil, true, "system"] call comspec_overwatch_connect_fnc_logTransmission;
"COMSPECExtension" callExtension ["SendFlightManifest", [_json]];
[format ["[FLIGHT MANIFEST] Envoi (tablette) : %1", _callsign]] call comspec_overwatch_connect_fnc_appendLinkLog;
["Manifeste de vol transmis (file d’attente).", "system", "info"] call comspec_overwatch_connect_fnc_announce;
