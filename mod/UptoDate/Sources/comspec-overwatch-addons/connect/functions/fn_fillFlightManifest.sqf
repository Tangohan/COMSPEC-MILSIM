/*
    Remplit le manifeste de vol (identité, emport, sécurité, canevas d'attaque).
    Au sol : champs vides utiles. En vol : appareil, occupants, munitions et capteurs détectés.
*/
if (!hasInterface) exitWith {};

private _veh = vehicle player;
private _display = uiNamespace getVariable ["COMSPEC_FlightManifest_Display", displayNull];
private _atak = uiNamespace getVariable ["COMSPEC_ATAK_Manifest_group", controlNull];
if (isNull _display && {isNull _atak}) exitWith {};

private _inAir =
    (_veh isKindOf "Air")
    || {_veh isKindOf "Plane"}
    || {_veh isKindOf "Helicopter"}
    || {_veh isKindOf "UAV"};

private _csCtrl = [1501] call comspec_overwatch_connect_fnc_manifestCtrl;
private _callsign = if (isNull _csCtrl) then { "" } else { ctrlText _csCtrl };
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
private _aircraftType = "helicopter";
if (_inAir) then {
    _model = getText (configOf _veh >> "displayName");
    if (_model isEqualTo "") then {
        _model = getText (configFile >> "CfgVehicles" >> typeOf _veh >> "displayName");
    };
    if (_model isEqualTo "") then { _model = typeOf _veh; };
    _aircraftType = [_veh] call comspec_overwatch_connect_fnc_getAircraftType;
    if ((toLower _aircraftType) in ["", "unknown", "ground"]) then { _aircraftType = "helicopter"; };
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

private _grid = format ["Grille %1", mapGridPosition _veh];
private _altM = round ((getPosASL _veh) select 2);
private _altAgl = round ((getPosATL _veh) select 2);
private _altTxt = if (_inAir) then {
    format ["%1 m (sol %2 m)", _altM toFixed 0, _altAgl toFixed 0]
} else {
    ""
};
private _hdg = round (getDir _veh);
if (_hdg < 0) then { _hdg = _hdg + 360; };
private _hdgTxt = format ["%1°", _hdg toFixed 0];

private _fuelTxt = "";
private _fuelPct = 0;
if (_veh isKindOf "AllVehicles" && {!(_veh isKindOf "Man")}) then {
    _fuelPct = (round ((fuel _veh) * 100)) max 0 min 100;
    _fuelTxt = format ["%1 %%", _fuelPct];
};

private _playMin = 0;
if (_fuelPct > 0) then {
    _playMin = round (_fuelPct * 0.45);
};
private _playTxt = if (_playMin > 0) then {
    format ["%1 min", _playMin toFixed 0]
} else {
    ""
};

private _occ = [];
if (_inAir) then {
    _occ = [_veh] call comspec_overwatch_connect_fnc_collectVehicleOccupants;
};
private _pax = (count _occ) max (if (_inAir) then { (count (crew _veh)) max 1 } else { 1 });
private _crewBits = [];
{
    if (!(_x isEqualType createHashMap)) then { continue };
    private _seat = toLower (_x getOrDefault ["seat", "cargo"]);
    private _seatLbl = switch (_seat) do {
        case "driver": { "Pilote" };
        case "gunner": { "Tireur" };
        case "commander": { "Chef de bord" };
        default { "Passager" };
    };
    private _nm = trim (_x getOrDefault ["name", ""]);
    if (_nm isEqualTo "") then { continue };
    _crewBits pushBack (format ["%1 — %2", _seatLbl, _nm]);
} forEach _occ;
private _crewTxt = _crewBits joinString (toString [10]);
if (_crewTxt isEqualTo "" && {_inAir}) then { _crewTxt = "À préciser"; };

private _acCount = 1;
if (_inAir) then {
    private _seen = [];
    {
        private _v = vehicle _x;
        if (!isNull _v && {alive _v} && {_v isKindOf "Air"}) then { _seen pushBackUnique _v; };
    } forEach (units group player);
    _acCount = (count _seen) max 1;
};

private _load = if (_inAir) then {
    [_veh] call comspec_overwatch_connect_fnc_collectAircraftLoadout
} else {
    createHashMapFromArray [["ordnance", ""], ["sensors", ""], ["raw", []]]
};
private _ordnance = _load getOrDefault ["ordnance", ""];
private _sensors = _load getOrDefault ["sensors", ""];
if (!_inAir) then {
    if (_ordnance isEqualTo "") then { _ordnance = ""; };
    if (_sensors isEqualTo "") then { _sensors = ""; };
};

private _comboType = [1503] call comspec_overwatch_connect_fnc_manifestCtrl;
if (!isNull _comboType) then {
    lbClear _comboType;
    {
        _x params ["_label", "_code"];
        private _i = _comboType lbAdd _label;
        _comboType lbSetData [_i, _code];
    } forEach [
        ["Hélicoptère", "helicopter"],
        ["Avion", "plane"],
        ["Drone", "uav"],
        ["Autre", "other"]
    ];
    private _typeSel = switch (toLower _aircraftType) do {
        case "plane": { 1 };
        case "uav": { 2 };
        case "other": { 3 };
        default { 0 };
    };
    _comboType lbSetCurSel _typeSel;
};

private _comboRole = [1507] call comspec_overwatch_connect_fnc_manifestCtrl;
if (!isNull _comboRole) then {
    lbClear _comboRole;
    {
        _x params ["_label", "_code"];
        private _i = _comboRole lbAdd _label;
        _comboRole lbSetData [_i, _code];
    } forEach [
        ["Transport", "transport"],
        ["Appui aérien", "cas"],
        ["Reconnaissance", "recon"],
        ["Évacuation sanitaire", "medevac"],
        ["Ravitaillement", "resupply"],
        ["Escorte", "escort"],
        ["Autre", "other"]
    ];
    private _roleLast = missionNamespace getVariable ["COMSPEC_ManifestLastRole", 0];
    if (!(_roleLast isEqualType 0)) then { _roleLast = 0; };
    _comboRole lbSetCurSel ((_roleLast max 0) min 6);
};

private _laser = missionNamespace getVariable ["COMSPEC_ManifestLastLaser", "1688"];
if (!(_laser isEqualType "") || {_laser isEqualTo ""}) then { _laser = "1688"; };
private _auth = missionNamespace getVariable ["COMSPEC_ManifestLastAuth", "SIGMA-5"];
if (!(_auth isEqualType "") || {_auth isEqualTo ""}) then { _auth = "SIGMA-5"; };
private _dest = missionNamespace getVariable ["COMSPEC_ManifestLastDest", ""];
if (!(_dest isEqualType "")) then { _dest = ""; };
private _ato = missionNamespace getVariable ["COMSPEC_ManifestLastAto", ""];
if (!(_ato isEqualType "")) then { _ato = ""; };
private _abort = missionNamespace getVariable ["COMSPEC_ManifestLastAbort", ""];
if (!(_abort isEqualType "")) then { _abort = ""; };

private _setTxt = {
    params ["_idc", "_txt"];
    private _c = [_idc] call comspec_overwatch_connect_fnc_manifestCtrl;
    if (!isNull _c) then { _c ctrlSetText _txt; };
};
[1501, _callsign] call _setTxt;
[1502, _model] call _setTxt;
[1504, _freq] call _setTxt;
[1505, _grid] call _setTxt;
[1506, _fuelTxt] call _setTxt;
[1508, _dest] call _setTxt;
[1510, _laser] call _setTxt;
[1511, _auth] call _setTxt;
[1512, str _acCount] call _setTxt;
[1513, str _pax] call _setTxt;
[1514, _altTxt] call _setTxt;
[1515, if (_inAir) then { _hdgTxt } else { "" }] call _setTxt;
[1516, _crewTxt] call _setTxt;
[1517, _ordnance] call _setTxt;
[1518, _sensors] call _setTxt;
[1519, _playTxt] call _setTxt;
[1522, _abort] call _setTxt;
[1523, _ato] call _setTxt;

private _l6 = [1556] call comspec_overwatch_connect_fnc_manifestCtrl;
if (!isNull _l6 && {trim (ctrlText _l6) isEqualTo ""}) then {
    _l6 ctrlSetText (mapGridPosition _veh);
};
private _l2 = [1552] call comspec_overwatch_connect_fnc_manifestCtrl;
if (!isNull _l2 && {trim (ctrlText _l2) isEqualTo ""} && {_inAir}) then {
    _l2 ctrlSetText _hdgTxt;
};

private _hint = [1540] call comspec_overwatch_connect_fnc_manifestCtrl;
if (!isNull _hint) then {
    private _msg = if (_inAir) then {
        format [
            "Appareil détecté : %1 · %2 à bord. Vérifiez emport, codes et canevas avant transmission.",
            _model,
            _pax
        ]
    } else {
        "Déclaration depuis le sol : indiquez l'appareil, le rôle et les codes, puis transmettez."
    };
    _hint ctrlSetStructuredText parseText format ["<t color='#E8F2FA'>%1</t>", _msg];
};

uiNamespace setVariable ["COMSPEC_FlightManifest_AircraftType", _aircraftType];
uiNamespace setVariable ["COMSPEC_FlightManifest_InAir", _inAir];
uiNamespace setVariable ["COMSPEC_FlightManifest_FuelPct", _fuelPct];
uiNamespace setVariable ["COMSPEC_FlightManifest_PlayMin", _playMin];
uiNamespace setVariable ["COMSPEC_FlightManifest_Occupants", _occ];
