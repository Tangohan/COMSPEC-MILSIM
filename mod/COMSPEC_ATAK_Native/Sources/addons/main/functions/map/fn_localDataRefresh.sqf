if (!hasInterface || {isNull player}) exitWith {};

private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data",createHashMap];
private _units = createHashMap;
{
    private _remote = _y;
    private _age = diag_tickTime - (_remote getOrDefault ["updated",diag_tickTime]);
    private _freshness = switch (true) do {
        case (_age < 5): {"LIVE"};
        case (_age < 20): {"STALE"};
        case (_age < 60): {"LOST"};
        default {"OFFLINE"};
    };
    _remote set ["freshness",_freshness];
    _units set [_x,_remote];
} forEach (_data getOrDefault ["remoteUnits",createHashMap]);
private _athena = +_units;

{
    private _obj = _x;
    if (isNull _obj) then { continue };
    private _id = netId _obj;
    if (_id isEqualTo "0:0") then { _id = str _obj; };
    private _affiliation = if (side group _obj isEqualTo side group player) then {
        "friend"
    } else {
        switch (side group _obj) do {
            case east: {"hostile"};
            case civilian: {"neutral"};
            default {"unknown"};
        }
    };
    private _type = if (_obj isKindOf "CAManBase") then {
        "infantry"
    } else {
        if (_obj isKindOf "Air") then {"air"} else {if (_obj isKindOf "Tank") then {"armor"} else {"vehicle"}}
    };
    // Indicatif d'abord ; sinon celui de l'unité Athena au même endroit (qu'on masque pour ne pas doubler) ;
    // le pseudo ne sert que sans indicatif ni liaison.
    private _callsign = [_obj,true] call comspec_atak_native_fnc_unitCallsign;
    private _near = "";
    private _best = 30;
    {
        private _d = (_y getOrDefault ["position",[0,0,0]]) distance2D _obj;
        if (_d < _best) then { _best = _d; _near = _x; };
    } forEach _athena;
    if (_near isNotEqualTo "") then {
        if (_callsign isEqualTo "") then { _callsign = (_athena get _near) getOrDefault ["callsign",""]; };
        _athena deleteAt _near;
        _units deleteAt _near;
    };
    if (_callsign isEqualTo "") then { _callsign = [_obj] call comspec_atak_native_fnc_unitCallsign; };
    _units set [_id,createHashMapFromArray [
        ["id",_id],["object",_obj],["self",_obj isEqualTo player],
        ["callsign",_callsign],
        ["position",getPosASL _obj],["heading",getDir _obj],
        ["affiliation",_affiliation],["type",_type],["freshness","LIVE"],["updated",diag_tickTime]
    ]];
} forEach (allUnits select {
    alive _x && {(side group _x isEqualTo side group player) || {profileNamespace getVariable ["COMSPEC_ATAK_ShowHostile",false]}}
});
["units",_units] call comspec_atak_native_fnc_storeSet;

// Les marqueurs de la mission sont déjà dessinés par la carte Arma : on les garde pour la sélection
// et l'inspecteur (local = true) et on n'ajoute que les marqueurs Athena absents du jeu.
private _markers = createHashMap;
{
    private _name = _x;
    _markers set [_name,createHashMapFromArray [
        ["id",_name],["position",getMarkerPos _name],["text",markerText _name],
        ["type",markerType _name],["color",markerColor _name],["shape",markerShape _name],
        ["size",markerSize _name],["dir",markerDir _name],["alpha",markerAlpha _name],["local",true]
    ]];
} forEach allMapMarkers;
private _local = values _markers;
{
    private _r = _y;
    private _rp = _r getOrDefault ["position",[0,0,0]];
    private _rt = toLower (_r getOrDefault ["text",""]);
    if ((_local findIf { ((_x get "position") distance2D _rp) < 15 && {(toLower (_x get "text")) isEqualTo _rt || {_rt isEqualTo ""}} }) < 0) then {
        _markers set [_x,_r];
    };
} forEach (_data getOrDefault ["remoteMarkers",createHashMap]);
["markers",_markers] call comspec_atak_native_fnc_storeSet;
