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
// Filtre des alliés (réglage) : tous, mon groupe, ou mon rattachement ORBAT Athena (repli : groupe).
private _filter = profileNamespace getVariable ["COMSPEC_ATAK_AllyFilter", "ALL"];
private _myOrbat = player getVariable ["COMSPEC_ATAK_Orbat", ""];
private _hideAi = profileNamespace getVariable ["COMSPEC_ATAK_HideAllyAi", false];
private _keepFriend = {
    params ["_obj"];
    if (_obj isEqualTo player) exitWith { true };
    if (_hideAi && {!isPlayer _obj}) exitWith { false };
    if (_filter isEqualTo "ALL") exitWith { true };
    if (_filter isEqualTo "ORBAT" && {_myOrbat isNotEqualTo ""}) exitWith { (_obj getVariable ["COMSPEC_ATAK_Orbat", ""]) isEqualTo _myOrbat || {group _obj isEqualTo group player} };
    group _obj isEqualTo group player
};
// Unités Athena sans objet en jeu : on ne peut pas les filtrer, elles ne restent qu'en mode TOUS.
if (_filter isNotEqualTo "ALL") then { _units = createHashMap; };
private _athena = +_units;
// Liaison BFT : sans réseau je ne reçois plus rien ; un allié dont le téléphone est éteint, cassé ou sans signal
// (balise COMSPEC_ATAK_Beacon publiée par son téléphone) reste figé à sa dernière position connue.
private _last = uiNamespace getVariable ["COMSPEC_ATAK_BftLast", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_BftLast", _last];
private _meOn = (([] call comspec_atak_native_fnc_linkQuality) getOrDefault ["bars", 1]) > 0;

{
    private _obj = _x;
    if (isNull _obj) then { continue };
    if (side group _obj isEqualTo side group player && {!([_obj] call _keepFriend)}) then { continue };
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
    private _pos = getPosASL _obj; private _hd = getDir _obj; private _upd = diag_tickTime; private _fr = "LIVE";
    if (_obj isNotEqualTo player && {_affiliation isEqualTo "friend"}) then {
        private _on = !isPlayer _obj || {_obj getVariable ["COMSPEC_ATAK_Beacon", true]};
        if (_meOn && _on) then { _last set [_id, [_pos, _hd, _upd]]; } else {
            if !(_id in _last) then { _last set [_id, [_pos, _hd, _upd]]; };
            (_last get _id) params ["_lp", "_lh", "_lt"];
            _pos = _lp; _hd = _lh; _upd = _lt;
            private _age = diag_tickTime - _lt;
            _fr = if (!_on) then { "OFFLINE" } else { switch (true) do { case (_age < 5): { "LIVE" }; case (_age < 20): { "STALE" }; default { "LOST" }; } };
        };
    };
    _units set [_id,createHashMapFromArray [
        ["id",_id],["object",_obj],["self",_obj isEqualTo player],
        ["callsign",_callsign],
        ["position",_pos],["heading",_hd],
        ["affiliation",_affiliation],["type",_type],["freshness",_fr],["updated",_upd],
        ["icon",_obj getVariable ["COMSPEC_ATAK_Icon",""]],["orbat",_obj getVariable ["COMSPEC_ATAK_Orbat",""]]
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
