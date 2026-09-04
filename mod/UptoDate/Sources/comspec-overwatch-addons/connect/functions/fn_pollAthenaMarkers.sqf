/*
    Tire les marqueurs posés sur l’ATAK web vers la carte Arma (sens inverse de SendMarker).
    Ignore les repères déjà originaires du jeu (arma / cTab / BCE) pour éviter les doublons.
*/
if (!hasInterface) exitWith {};
private _txGate = [true] call comspec_overwatch_connect_fnc_canTransmit;
if !(_txGate getOrDefault ["can_transmit", true]) exitWith {};
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};

private _raw = ["COMSPECExtension" callExtension ["GetMarkers", ["world:" + worldName]]] call comspec_overwatch_connect_fnc_extResult;
if (_raw isEqualTo "" || {(_raw select [0, 3]) != "OK|"}) exitWith {};
private _body = _raw select [3, (count _raw) - 3];
if (_body isEqualTo "" || {_body isEqualTo "[]"}) exitWith {};

private _seen = [];
private _nl = toString [10];
private _tab = toString [9];
private _lines = _body splitString _nl;
{
    private _cols = _x splitString _tab;
    if ((count _cols) < 6) then { continue };
    if ((_cols select 0) != "M") then { continue };

    private _id = _cols select 1;
    private _xPos = parseNumber (_cols select 3);
    private _yPos = parseNumber (_cols select 4);
    private _type = _cols select 5;
    private _text = if ((count _cols) > 6) then { _cols select 6 } else { "" };
    private _color = if ((count _cols) > 7) then { _cols select 7 } else { "" };
    private _source = if ((count _cols) > 8) then { toLower (_cols select 8) } else { "" };

    if (_source in ["arma", "bce_widget", "ctab_user"]) then { continue };
    private _typeL = toLower _type;
    private _fromWeb = (_source in ["web", "manual"]) || {_typeL isEqualTo "manual"};
    if (!_fromWeb) then { continue };
    if ((abs _xPos) < 0.5 && {(abs _yPos) < 0.5}) then { continue };

    _seen pushBack _id;
    private _name = format ["comspec_webmk_%1", _id];
    if (_typeL isEqualTo "manual" || {!(isClass (configFile >> "CfgMarkers" >> _type))}) then {
        _type = "mil_dot";
    };
    if (_color isEqualTo "" || {(_color select [0, 1]) isEqualTo "#"}) then {
        _color = "ColorGreen";
    };

    if (_name in allMapMarkers) then {
        _name setMarkerPosLocal [_xPos, _yPos];
        _name setMarkerTextLocal _text;
        _name setMarkerTypeLocal _type;
        _name setMarkerColorLocal _color;
    } else {
        private _mk = createMarkerLocal [_name, [_xPos, _yPos]];
        _mk setMarkerTypeLocal _type;
        _mk setMarkerColorLocal _color;
        _mk setMarkerTextLocal _text;
        _mk setMarkerAlphaLocal 1;
    };
} forEach _lines;

private _prev = missionNamespace getVariable ["COMSPEC_WebMarkerIds", []];
if (!(_prev isEqualType [])) then { _prev = []; };
{
    if (!(_x in _seen)) then {
        private _n = format ["comspec_webmk_%1", _x];
        if (_n in allMapMarkers) then { deleteMarkerLocal _n; };
    };
} forEach _prev;
missionNamespace setVariable ["COMSPEC_WebMarkerIds", _seen, false];
