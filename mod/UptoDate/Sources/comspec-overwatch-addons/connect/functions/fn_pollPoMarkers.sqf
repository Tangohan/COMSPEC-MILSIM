/*
    Points d’objectif carte : libellé PO (PO, PO 1, PO-2…).
    Anneau local 20 m. Si un opérateur ATAK entre dans le rayon, le point est confirmé
    par la position déjà transmise au poste (et visuellement ici).
    Les règles communautaires (libellé / symbole) ajoutent d’autres points suivis,
    avec le rayon choisi par la communauté.
*/
if (!hasInterface) exitWith { false };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };
if (!([player] call comspec_overwatch_connect_fnc_hasTerminal)) exitWith { false };

private _radius = 20;
private _fnc_isPoLabel = {
    params ["_txt"];
    _txt = toUpper (trim (str _txt));
    if (_txt isEqualTo "") exitWith { false };
    if (_txt isEqualTo "PO") exitWith { true };
    if ((count _txt) < 3) exitWith { false };
    if ((_txt select [0, 2]) isNotEqualTo "PO") exitWith { false };
    private _c = _txt select [2, 1];
    if (_c in [" ", "-", "_", ".", "#"]) exitWith { true };
    if (_c in ["0", "1", "2", "3", "4", "5", "6", "7", "8", "9"]) exitWith { true };
    false
};

private _fnc_ruleHits = {
    params ["_mode", "_needle", "_text", "_type"];
    _needle = toLower (trim (str _needle));
    if (_needle isEqualTo "") exitWith { false };
    private _hay = toLower (trim (str _text));
    private _mkType = toLower (trim (str _type));
    if (_mode isEqualTo "label_equals") exitWith { _hay isEqualTo _needle };
    if (_mode isEqualTo "label_contains") exitWith { (_hay find _needle) >= 0 };
    if (_mode isEqualTo "marker_type") exitWith {
        private _norm = (_mkType splitString "- " joinString "_");
        private _want = (_needle splitString "- " joinString "_");
        _norm isEqualTo _want
    };
    if (_hay isEqualTo "") exitWith { false };
    (_hay find _needle) == 0
};

private _fnc_matchDetection = {
    params ["_text", "_name", "_type"];
    private _rules = missionNamespace getVariable ["COMSPEC_MarkerDetectionRules", []];
    if (!(_rules isEqualType [])) exitWith { [] };
    private _hay = if ((trim (str _text)) isEqualTo "") then { _name } else { _text };
    private _hit = [];
    {
        if (!(_x isEqualType []) || {(count _x) < 4}) then { continue };
        if (_hit isNotEqualTo []) then { continue };
        private _mode = toLower (str (_x select 0));
        private _needle = _x select 1;
        if ([_mode, _needle, _hay, _type] call _fnc_ruleHits) then {
            private _r = _x select 2;
            if (_r isEqualType "") then { _r = parseNumber _r; };
            if (!(_r isEqualType 0) || {_r < 1}) then { _r = 20; };
            private _c = _x select 3;
            private _lbl = if ((count _x) > 4 && {(_x select 4) isNotEqualTo ""}) then { str (_x select 4) } else { str _hay };
            private _confirm = (_c isEqualTo true) || {_c isEqualTo 1} || {_c isEqualTo "1"};
            _hit = [_r, _confirm, _lbl];
        };
    } forEach _rules;
    _hit
};

private _fnc_clearRings = {
    params ["_keep"];
    private _prev = missionNamespace getVariable ["COMSPEC_PoRingIds", []];
    if (!(_prev isEqualType [])) then { _prev = []; };
    private _muted = (missionNamespace getVariable ["COMSPEC_MarkerEhMuted", 0]) + 1;
    missionNamespace setVariable ["COMSPEC_MarkerEhMuted", _muted, false];
    {
        if (!(_x in _keep)) then {
            if (_x in allMapMarkers) then { deleteMarkerLocal _x; };
        };
    } forEach _prev;
    private _unmute = (missionNamespace getVariable ["COMSPEC_MarkerEhMuted", 1]) - 1;
    if (_unmute < 0) then { _unmute = 0; };
    missionNamespace setVariable ["COMSPEC_MarkerEhMuted", _unmute, false];
};

private _unit = player;
if (isNull _unit || {!alive _unit}) exitWith { false };
private _pos = getPosASL _unit;
private _seen = [];
private _synced = missionNamespace getVariable ["COMSPEC_PoSyncedAt", createHashMap];
if (!(_synced isEqualType createHashMap)) then { _synced = createHashMap; };
private _announced = missionNamespace getVariable ["COMSPEC_PoReachedNames", []];
if (!(_announced isEqualType [])) then { _announced = []; };

{
    private _name = _x;
    if (_name isEqualTo "") then { continue };
    private _ul = toLower _name;
    if ((_ul find "_comspec_po_ring_") == 0) then { continue };
    if ((_ul find "_comspec_det_ring_") == 0) then { continue };
    if ((_ul find "comspec_gps_wp_") == 0) then { continue };
    if ((_ul find "comspec_gps_rt_") == 0) then { continue };
    if ((_ul find "comspec_webmk_") == 0) then { continue };

    private _text = markerText _name;
    if (_text isEqualTo "" && {!isNil "comspec_overwatch_connect_fnc_resolveBceMarkerText"}) then {
        _text = [_name] call comspec_overwatch_connect_fnc_resolveBceMarkerText;
    };
    private _isPo = [_text] call _fnc_isPoLabel;
    if (!_isPo) then { _isPo = [_name] call _fnc_isPoLabel; };
    private _det = [];
    if (!_isPo) then {
        _det = [_text, _name, getMarkerType _name] call _fnc_matchDetection;
        if (_det isEqualTo []) then { continue };
    };

    private _mkPos = markerPos _name;
    if ((abs (_mkPos select 0) < 0.5) && {(abs (_mkPos select 1) < 0.5)}) then { continue };

    private _safe = (_name splitString " #" joinString "_");
    private _thisRadius = if (_isPo) then { _radius } else { _det select 0 };
    private _confirm = if (_isPo) then { true } else { _det select 1 };
    private _ring = if (_isPo) then {
        format ["_COMSPEC_PO_RING_%1", _safe]
    } else {
        format ["_COMSPEC_DET_RING_%1", _safe]
    };
    _seen pushBack _ring;
    private _dist = [_pos select 0, _pos select 1, 0] distance2D [_mkPos select 0, _mkPos select 1, 0];
    private _inside = _dist <= _thisRadius;
    private _wasReached = _name in _announced;
    private _color = if (_wasReached) then { "ColorGrey" } else { if (_inside) then { "ColorGreen" } else { "ColorYellow" } };
    private _alpha = if (_wasReached) then { 0.35 } else { 0.7 };

    private _muted = (missionNamespace getVariable ["COMSPEC_MarkerEhMuted", 0]) + 1;
    missionNamespace setVariable ["COMSPEC_MarkerEhMuted", _muted, false];
    if (_ring in allMapMarkers) then {
        _ring setMarkerPosLocal _mkPos;
        _ring setMarkerColorLocal _color;
        _ring setMarkerAlphaLocal _alpha;
        _ring setMarkerSizeLocal [_thisRadius, _thisRadius];
    } else {
        private _el = createMarkerLocal [_ring, _mkPos];
        _el setMarkerShapeLocal "ELLIPSE";
        _el setMarkerSizeLocal [_thisRadius, _thisRadius];
        _el setMarkerBrushLocal "Border";
        _el setMarkerColorLocal _color;
        _el setMarkerAlphaLocal _alpha;
    };
    private _unmute = (missionNamespace getVariable ["COMSPEC_MarkerEhMuted", 1]) - 1;
    if (_unmute < 0) then { _unmute = 0; };
    missionNamespace setVariable ["COMSPEC_MarkerEhMuted", _unmute, false];

    private _lastSync = _synced getOrDefault [_name, -1e9];
    if ((diag_tickTime - _lastSync) > 45 && {missionNamespace getVariable ["COMSPEC_AthenaReady", false]}) then {
        if (!isNil "comspec_overwatch_connect_fnc_syncMapMarker") then {
            [_name, false, true] call comspec_overwatch_connect_fnc_syncMapMarker;
        };
        _synced set [_name, diag_tickTime];
    };

    if (_confirm && {_inside} && {!_wasReached}) then {
        _announced pushBack _name;
        private _lbl = if (_text isEqualTo "") then {
            if (_isPo) then { "PO" } else { _det select 2 }
        } else { _text };
        private _msg = if (_isPo) then { "Point d’objectif atteint — " } else { "Point suivi atteint — " };
        [_msg + _lbl, "gps", "info"] call comspec_overwatch_connect_fnc_announce;
        if (_name in allMapMarkers) then {
            _name setMarkerColorLocal "ColorGrey";
        };
    };
} forEach (+allMapMarkers);

[_seen] call _fnc_clearRings;
missionNamespace setVariable ["COMSPEC_PoRingIds", _seen, false];
missionNamespace setVariable ["COMSPEC_PoSyncedAt", _synced, false];
missionNamespace setVariable ["COMSPEC_PoReachedNames", _announced, false];
true
