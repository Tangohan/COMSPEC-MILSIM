/*

    Envoie un marqueur carte Arma vers Athena (création / maj / suppression).

    Gère ICON / ELLIPSE / RECTANGLE et POLYLINE (type souvent vide en Arma).

    Couvre Marker Widget BCE (`_USER_DEFINED` / `_IcTab_DEFINED #…`).

    Params: [_markerName, _deleted, _force]

      _force : ignore hasTerminal / canTransmit (resync manuelle / widget ouvert)

*/

params [

    ["_markerName", "", [""]],

    ["_deleted", false, [true]],

    ["_force", false, [true]]

];

if (_markerName isEqualTo "") exitWith { false };

if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };

if (!(missionNamespace getVariable ["comspec_overwatch_sync_map_markers", true])) exitWith { false };



if (!_force) then {

    if !([player] call comspec_overwatch_connect_fnc_hasTerminal) then {

        missionNamespace setVariable ["COMSPEC_LastMarkerSyncSkip", "no_terminal", false];

    };

};

if (

    !_force

    && {!([player] call comspec_overwatch_connect_fnc_hasTerminal)}

) exitWith { false };



if (!_force) then {

    private _txGate = [true] call comspec_overwatch_connect_fnc_canTransmit;

    if !(_txGate getOrDefault ["can_transmit", true]) then {

        missionNamespace setVariable ["COMSPEC_LastMarkerSyncSkip", _txGate getOrDefault ["reason", "tx_blocked"], false];

    };

};

if (

    !_force

    && {

        private _g = [true] call comspec_overwatch_connect_fnc_canTransmit;

        !(_g getOrDefault ["can_transmit", true])

    }

) exitWith { false };



// Joueur Arma / Marker Widget BCE / Dropper TAD

private _ulCheck = toLower _markerName;

private _isUnderscore = ((_markerName select [0, 1]) == "_");

private _underscoreOk = true;

if (_isUnderscore) then {

    if (!isNil "comspec_overwatch_connect_fnc_isSyncableMapMarker") then {

        _underscoreOk = [_markerName] call comspec_overwatch_connect_fnc_isSyncableMapMarker;

    } else {

        _underscoreOk = (

            (_ulCheck find "_user_defined") >= 0

            || {(_ulCheck find "user_defined") >= 0}

            || {(_ulCheck find "_defined #") >= 0}

            || {(_ulCheck find "_ictab_defined") >= 0}

        );

    };

};

if (_isUnderscore && {!_underscoreOk}) exitWith { false };



// Marqueurs déjà transmis via leur propre appel structuré

private _mirroredElsewherePrefixes = [

    "poi_local_", "qrf_contact_", "medevac_lz_", "vehicle_service_",

    "comspec_roleplay_zone_", "comspec_tabletmk_", "comspec_webmk_", "comspec_shape_", "ctab_u_"

];

private _nameLower = toLower _markerName;

if (({ (_nameLower find _x) == 0 } count _mirroredElsewherePrefixes) > 0) exitWith { false };



if (_deleted) exitWith {

    if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) then {

        [_markerName, "{}", true] call comspec_overwatch_connect_fnc_queueMapMarker;

    } else {

        "COMSPECExtension" callExtension ["SendMarker", [_markerName, "{}", "1", "1"]];

    };

    true

};



private _shape = toUpper (markerShape _markerName);

private _type = markerType _markerName;

if (_type isEqualTo "" && {!(_shape isEqualTo "POLYLINE")}) then {

    if (_shape isEqualTo "ELLIPSE" || {_shape isEqualTo "RECTANGLE"}) then {

        _type = "mil_circle";

    } else {

        _type = "mil_dot";

    };

};

if (!(_shape isEqualTo "POLYLINE") && {_type isEqualTo ""}) exitWith { false };



private _pos = markerPos _markerName;

if ((abs (_pos select 0) < 0.1) && {(abs (_pos select 1) < 0.1)}) exitWith { false };



private _text = markerText _markerName;
if (_text isEqualTo "" && {_isUnderscore}) then {
    _text = [_markerName] call comspec_overwatch_connect_fnc_resolveBceMarkerText;
};

private _color = markerColor _markerName;

if (_color isEqualTo "" || {_color isEqualTo "Default"} || {_color isEqualTo "ColorDefault"}) then {

    _color = "ColorYellow";

};

private _cl = toLower _color;

if (_cl isEqualTo "coloreast") then { _color = "ColorRed"; };

if (_cl isEqualTo "colorwest") then { _color = "ColorBlue"; };

if (_cl isEqualTo "colorblack") then { _color = "ColorBlack"; };



private _dir = markerDir _markerName;

private _alpha = markerAlpha _markerName;

if (_alpha < 0.05) then { _alpha = 1; };

private _size = markerSize _markerName;

if (!(_size isEqualType []) || {(count _size) < 2}) then { _size = [1, 1]; };

if (((_size select 0) max (_size select 1)) < 0.05) then { _size = [0.8, 0.8]; };

private _brush = markerBrush _markerName;

private _polyJson = "[]";



if (_shape isEqualTo "POLYLINE") then {

    private _pts = markerPolyline _markerName;

    if (!(_pts isEqualType []) || {(count _pts) < 4}) exitWith { false };

    private _parts = [];

    { _parts pushBack (str _x); } forEach _pts;

    _polyJson = "[" + (_parts joinString ",") + "]";

    _pos = [_pts select 0, _pts select 1, 0];

};



private _texture = "";

if (_type isNotEqualTo "" && {!(_shape isEqualTo "POLYLINE")}) then {

    _texture = getText (configFile >> "CfgMarkers" >> _type >> "icon");

};

private _bs = toString [92];

private _texForJson = (_texture splitString _bs joinString "/");

_texForJson = (_texForJson splitString """" joinString "'");



private _src = if (_isUnderscore) then { "bce_widget" } else { "arma" };

private _json = format [

    "{""pos"":[%1,%2,%3],""type"":""%4"",""text"":""%5"",""color"":""%6"",""dir"":%7,""alpha"":%8,""shape"":""%9"",""size"":[%10,%11],""brush"":""%12"",""polyline"":%13,""source"":""%14"",""callsign"":""%15"",""grid"":""%16"",""texture"":""%17""}",

    (_pos select 0) toFixed 2,

    (_pos select 1) toFixed 2,

    (if (count _pos > 2) then { _pos select 2 } else { 0 }) toFixed 2,

    _type,

    (_text splitString """" joinString "'"),

    _color,

    _dir toFixed 2,

    _alpha toFixed 2,

    _shape,

    (_size select 0) toFixed 2,

    (_size select 1) toFixed 2,

    _brush,

    _polyJson,

    _src,

    (([] call comspec_overwatch_connect_fnc_getCallsign) splitString """" joinString "'"),

    mapGridPosition _pos,

    _texForJson

];



if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {

    [_markerName, _json, false] call comspec_overwatch_connect_fnc_queueMapMarker;

    true

};



"COMSPECExtension" callExtension ["SendMarker", [_markerName, _json, "1", "0"]];

missionNamespace setVariable ["COMSPEC_LastMarkerSyncName", _markerName, false];

missionNamespace setVariable ["COMSPEC_LastMarkerSyncAt", diag_tickTime, false];

true

