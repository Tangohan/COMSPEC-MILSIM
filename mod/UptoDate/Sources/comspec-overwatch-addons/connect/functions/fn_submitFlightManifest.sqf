/*
    Construit et envoie le manifeste de vol.
    Source de vérité = champs affichés (ce que le joueur voit / corrige).
*/
if (!hasInterface) exitWith {};

private _veh = vehicle player;
private _display = uiNamespace getVariable ["COMSPEC_FlightManifest_Display", displayNull];
private _atak = uiNamespace getVariable ["COMSPEC_ATAK_Manifest_group", controlNull];
if (isNull _display && {isNull _atak}) exitWith {};

private _inAir = uiNamespace getVariable ["COMSPEC_FlightManifest_InAir", false];
if (!(_inAir isEqualType true)) then { _inAir = false; };

private _txtOf = {
    params ["_idc"];
    private _c = [_idc] call comspec_overwatch_connect_fnc_manifestCtrl;
    if (isNull _c) then { "" } else { trim (ctrlText _c) };
};

private _callsign = [1501] call _txtOf;
if (_callsign isEqualTo "" || {(toLower _callsign) in ["unknown", "inconnu"]}) then {
    _callsign = _veh getVariable ["COMSPEC_Callsign", ""];
};
if (!(_callsign isEqualType "") || {_callsign isEqualTo ""}) then {
    _callsign = [] call comspec_overwatch_connect_fnc_getCallsign;
};
_callsign = trim _callsign;
if (_callsign isEqualTo "" || {(toLower _callsign) in ["unknown", "inconnu"]}) then {
    _callsign = trim (groupId (group player));
};
if (_callsign isEqualTo "") then { _callsign = "N-01"; };

private _model = [1502] call _txtOf;
if (_model isEqualTo "" || {_model find "À préciser" == 0} || {_model isEqualTo "Déclaration sol"}) then {
    if (_inAir || {(vehicle player) isKindOf "Air"}) then {
        _model = getText (configOf _veh >> "displayName");
        if (_model isEqualTo "") then { _model = typeOf _veh; };
    } else {
        _model = "";
    };
};

private _comboType = [1503] call comspec_overwatch_connect_fnc_manifestCtrl;
private _aircraftType = "";
if (!isNull _comboType) then { _aircraftType = _comboType lbData (lbCurSel _comboType); };
if (!(_aircraftType isEqualType "") || {_aircraftType isEqualTo ""}) then {
    _aircraftType = uiNamespace getVariable ["COMSPEC_FlightManifest_AircraftType", "helicopter"];
};
if ((toLower _aircraftType) in ["unknown", "ground", ""]) then { _aircraftType = "helicopter"; };

private _freq = [1504] call _txtOf;
if ((toLower _freq) in ["non détectée", "non detectee"]) then { _freq = ""; };

private _pos = getPosASL _veh;
private _heading = getDir _veh;
private _alt = _pos select 2;

private _laser = [1510] call _txtOf;
if (_laser isEqualTo "") then { _laser = "1688"; };
private _auth = [1511] call _txtOf;
private _countStr = [1512] call _txtOf;
private _count = 1;
if (_countStr != "" && {parseNumber _countStr >= 1}) then { _count = round (parseNumber _countStr); };
private _paxStr = [1513] call _txtOf;
private _pax = 1;
if (_paxStr != "" && {parseNumber _paxStr >= 0}) then { _pax = round (parseNumber _paxStr); };

private _comboRole = [1507] call comspec_overwatch_connect_fnc_manifestCtrl;
private _role = "";
if (!isNull _comboRole) then { _role = _comboRole lbData (lbCurSel _comboRole); };
if (!(_role isEqualType "") || {_role isEqualTo ""}) then { _role = "transport"; };
private _dest = [1508] call _txtOf;
private _notes = [1509] call _txtOf;
private _ordnance = [1517] call _txtOf;
private _sensors = [1518] call _txtOf;
private _playTxt = [1519] call _txtOf;
private _abort = [1522] call _txtOf;
private _ato = [1523] call _txtOf;
private _crewTxt = [1516] call _txtOf;

private _nine = [];
{
    _x params ["_idc", "_title"];
    private _v = [_idc] call _txtOf;
    if (_v isNotEqualTo "") then {
        _nine pushBack (format ["%1 : %2", _title, _v]);
    };
} forEach [
    [1551, "1 Point de départ"],
    [1552, "2 Cap"],
    [1553, "3 Distance"],
    [1554, "4 Altitude cible"],
    [1555, "5 Description"],
    [1556, "6 Position cible"],
    [1557, "7 Marquage"],
    [1558, "8 Alliés proches"],
    [1559, "9 Sortie"]
];

private _fuelTxt = [1506] call _txtOf;
private _fuelPct = uiNamespace getVariable ["COMSPEC_FlightManifest_FuelPct", 0];
if (!(_fuelPct isEqualType 0)) then { _fuelPct = 0; };
if (_fuelTxt != "") then {
    private _n = parseNumber ((_fuelTxt splitString "%") select 0);
    if (_n >= 0) then { _fuelPct = (round _n) max 0 min 100; };
};

private _checkBits = [];
if (_notes isNotEqualTo "") then { _checkBits pushBack _notes; };
if (_sensors isNotEqualTo "") then { _checkBits pushBack (format ["Capteurs : %1", _sensors]); };
if (_abort isNotEqualTo "") then { _checkBits pushBack (format ["Annulation : %1", _abort]); };
if (_ato isNotEqualTo "") then { _checkBits pushBack (format ["Mission : %1", _ato]); };
if (_nine isNotEqualTo []) then {
    _checkBits pushBack ("Canevas d'attaque — " + (_nine joinString " · "));
};
private _checklist = _checkBits joinString (toString [10]);

private _occ = uiNamespace getVariable ["COMSPEC_FlightManifest_Occupants", []];
if (!(_occ isEqualType [])) then { _occ = []; };
if (_occ isEqualTo [] && {_crewTxt isNotEqualTo ""}) then {
    {
        private _line = trim _x;
        if (_line isEqualTo "") then { continue };
        private _row = createHashMapFromArray [["name", _line], ["seat", "cargo"], ["role", ""], ["player", true]];
        _occ pushBack _row;
    } forEach (_crewTxt splitString (toString [10]));
};

private _sideStr = "WEST";
switch (side player) do {
    case east: { _sideStr = "EAST"; };
    case independent: { _sideStr = "GUER"; };
    case civilian: { _sideStr = "CIV"; };
    default { _sideStr = "WEST"; };
};

private _status = if (_inAir) then { "IN-FLIGHT" } else { "AVAILABLE" };

private _posX = _pos select 0;
private _posY = _pos select 1;

missionNamespace setVariable ["COMSPEC_ManifestLastLaser", _laser, false];
missionNamespace setVariable ["COMSPEC_ManifestLastAuth", _auth, false];
missionNamespace setVariable ["COMSPEC_ManifestLastDest", _dest, false];
missionNamespace setVariable ["COMSPEC_ManifestLastAbort", _abort, false];
missionNamespace setVariable ["COMSPEC_ManifestLastAto", _ato, false];
missionNamespace setVariable ["COMSPEC_ManifestLastRole", if (isNull _comboRole) then { 0 } else { lbCurSel _comboRole }, false];

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
    ["status", _status],
    ["fuel_pct", _fuelPct],
    ["lastUpdate", floor time],
    ["pilot", [] call comspec_overwatch_connect_fnc_orderIssuerLabel],
    ["mission_id", _role],
    ["station", _dest],
    ["checklist", _checklist],
    ["crew", _occ],
    ["occupants", _occ],
    ["pax", _pax],
    ["ordnance", _ordnance],
    ["bingo_fuel", _playTxt]
];

private _json = [_payload] call comspec_overwatch_connect_fnc_hashMapToJson;
if (!(_json isEqualType "") || {_json isEqualTo ""}) exitWith {
    ["Impossible de préparer le manifeste de vol.", "system", "warn"] call comspec_overwatch_connect_fnc_announce;
};

["SendFlightManifest", "attempt", _callsign, nil, true, "system"] call comspec_overwatch_connect_fnc_logTransmission;
"COMSPECExtension" callExtension ["SendFlightManifest", [_json]];
[format ["Manifeste de vol transmis au poste - %1.", _callsign], "system", "info"] call comspec_overwatch_connect_fnc_announce;

if (!isNull _display) then {
    _display closeDisplay 1;
};
