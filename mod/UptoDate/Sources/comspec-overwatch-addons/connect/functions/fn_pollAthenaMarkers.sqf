/*
    Tire les marqueurs posés sur l’ATAK web vers la carte Arma (sens inverse de SendMarker).
    Ignore les repères déjà originaires du jeu (arma / cTab / BCE) pour éviter les doublons.
*/
if (!hasInterface) exitWith {};
if (isNull player || {!([player] call comspec_overwatch_connect_fnc_hasTerminal)}) exitWith {};
private _txGate = [true] call comspec_overwatch_connect_fnc_canTransmit;
if !(_txGate getOrDefault ["can_transmit", true]) exitWith {};
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
if (
    !(missionNamespace getVariable ["COMSPEC_DiagIsolateActive", false])
    && {!isNil "comspec_overwatch_connect_fnc_uplinkQuiet"}
    && {[] call comspec_overwatch_connect_fnc_uplinkQuiet}
) exitWith {};

private _raw = ["COMSPECExtension" callExtension ["GetMarkers", ["world:" + worldName]]] call comspec_overwatch_connect_fnc_extResult;
if (_raw isEqualTo "" || {(_raw select [0, 3]) != "OK|"}) exitWith {
    ["marqueurs", 0, " (vide ou erreur)"] call comspec_overwatch_connect_fnc_noteUplinkReturn;
};
private _body = _raw select [3, (count _raw) - 3];
if (_body isEqualTo "" || {_body isEqualTo "[]"}) exitWith {
    ["marqueurs", 0, " (aucun)"] call comspec_overwatch_connect_fnc_noteUplinkReturn;
};

private _seen = [];
private _created = 0;
private _nl = toString [10];
private _tab = toString [9];
private _lines = _body splitString _nl;
private _bootMk = missionNamespace getVariable ["COMSPEC_WebMarkersBootstrapped", false];
if (!_bootMk) then {
    missionNamespace setVariable ["COMSPEC_WebMarkersBootstrapped", true, false];
};
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
    if (_text isEqualTo "") then { _text = "Repère poste"; };

    if (!(_name in allMapMarkers) && {_created >= 25}) then { continue };

    private _muted = (missionNamespace getVariable ["COMSPEC_MarkerEhMuted", 0]) + 1;
    missionNamespace setVariable ["COMSPEC_MarkerEhMuted", _muted, false];
    if (_name in allMapMarkers) then {
        _name setMarkerPos [_xPos, _yPos];
        _name setMarkerText _text;
        _name setMarkerType _type;
        _name setMarkerColor _color;
    } else {
        private _mk = createMarker [_name, [_xPos, _yPos]];
        if (_mk isEqualTo "") then { _mk = _name; };
        _mk setMarkerType _type;
        _mk setMarkerColor _color;
        _mk setMarkerText _text;
        _mk setMarkerSize [0.85, 0.85];
        _mk setMarkerAlpha 1;
        _created = _created + 1;
    };
    private _unmute = (missionNamespace getVariable ["COMSPEC_MarkerEhMuted", 1]) - 1;
    if (_unmute < 0) then { _unmute = 0; };
    missionNamespace setVariable ["COMSPEC_MarkerEhMuted", _unmute, false];
} forEach _lines;

["marqueurs", count _seen, format [
    " · lignes %1%2",
    count _lines,
    if (_bootMk) then { "" } else { " · première lecture" }
]] call comspec_overwatch_connect_fnc_noteUplinkReturn;

private _prev = missionNamespace getVariable ["COMSPEC_WebMarkerIds", []];
if (!(_prev isEqualType [])) then { _prev = []; };
if ((count _raw) < 7990) then {
    {
        if (!(_x in _seen)) then {
            private _n = format ["comspec_webmk_%1", _x];
            if (_n in allMapMarkers) then {
                private _muted = (missionNamespace getVariable ["COMSPEC_MarkerEhMuted", 0]) + 1;
                missionNamespace setVariable ["COMSPEC_MarkerEhMuted", _muted, false];
                deleteMarker _n;
                private _unmute = (missionNamespace getVariable ["COMSPEC_MarkerEhMuted", 1]) - 1;
                if (_unmute < 0) then { _unmute = 0; };
                missionNamespace setVariable ["COMSPEC_MarkerEhMuted", _unmute, false];
            };
        };
    } forEach _prev;
    missionNamespace setVariable ["COMSPEC_WebMarkerIds", _seen, false];
};
