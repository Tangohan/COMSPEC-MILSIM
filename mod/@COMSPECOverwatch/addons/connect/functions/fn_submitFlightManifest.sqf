/*
    Builds JSON Flight Manifest from dialog and current vehicle state, sends via extension.
*/
if (!hasInterface) exitWith {};

private _veh = vehicle player;
private _display = uiNamespace getVariable ["COMSPEC_FlightManifest_Display", displayNull];
if (isNull _display) exitWith {};

private _callsign = _veh getVariable ["COMSPEC_Callsign", groupId (group player)];
if (_callsign == "") then { _callsign = groupId (group player); };
if (_callsign == "") then { _callsign = "PILOT"; };

private _model = getText (configFile >> "CfgVehicles" >> typeOf _veh >> "displayName");
if (_model == "") then { _model = typeOf _veh; };

private _aircraftType = [_veh] call comspec_overwatch_connect_fnc_getAircraftType;

private _freq = "UNKNOWN";
private _radioState = player call comspec_overwatch_connect_fnc_getRadioState;
if (_radioState != "" && _radioState != "N/A|N/A|N/A") then {
    private _parts = _radioState splitString "|";
    if (count _parts >= 2) then { _freq = _parts select 1; };
};

private _pos = getPosASL _veh;
private _heading = getDir _veh;
private _alt = _pos select 2;

private _laser = ctrlText (_display displayCtrl 1510);
if (_laser == "") then { _laser = "1688"; };
private _auth = ctrlText (_display displayCtrl 1511);
private _countStr = ctrlText (_display displayCtrl 1512);
private _count = 1;
if (_countStr != "" && { parseNumber _countStr >= 1 }) then { _count = parseNumber _countStr; };

private _sideStr = "WEST";
switch (side player) do {
    case east: { _sideStr = "EAST"; };
    case independent: { _sideStr = "GUER"; };
    case civilian: { _sideStr = "CIV"; };
    default { _sideStr = "WEST"; };
};

private _posX = _pos select 0;
private _posY = _pos select 1;
private _fuelPct = round ((fuel _veh) * 100);
private _status = "AVAILABLE";

private _escape = {
    params ["_s"];
    private _o = "";
    if (isNil "_s" || { _s isEqualTo "" }) exitWith { "" };
    _s = str _s;
    if (count _s > 1) then { _s = _s select [1, count _s - 2]; };
    {
        if (_x == 34) then { _o = _o + "\""; }
        else { if (_x == 92) then { _o = _o + "\\"; } else { _o = _o + toString [_x]; }; };
    } forEach toArray _s;
    _o
};

private _callsignEsc = [_callsign] call _escape;
private _modelEsc = [_model] call _escape;
private _freqEsc = [_freq] call _escape;
private _laserEsc = [_laser] call _escape;
private _authEsc = [_auth] call _escape;

private _json = format [
    '{"mapId":1,"callsign":"%1","model":"%2","aircraft_type":"%3","freq":"%4","radio_main":"%4","laser":"%5","auth":"%6","auth_code":"%6","alt":%7,"altitude":%7,"heading":%8,"pos":[%9,%10],"pos_x":%9,"pos_y":%10,"side":"%11","aircraft_count":%12,"status":"%13","fuel_pct":%14,"lastUpdate":%15}',
    _callsignEsc, _modelEsc, _aircraftType, _freqEsc, _laserEsc, _authEsc,
    _alt, _heading, _posX, _posY, _sideStr, _count, _status, _fuelPct, floor time
];

"COMSPECExtension" callExtension ["SendFlightManifest", [_json]];

private _log = missionNamespace getVariable ["COMSPEC_Log", ""];
_log = _log + "[FLIGHT MANIFEST] Sent: " + _callsign + "\n";
missionNamespace setVariable ["COMSPEC_Log", _log, true];

closeDialog 0;
