/*
    Vérifie si un ordre C2 concerne le joueur local.
    Params: [_order] hashmap
    Retour: BOOL
*/
params [["_order", createHashMap]];

if (!(_order isEqualType createHashMap)) exitWith { false };

private _target = trim (_order getOrDefault ["target", ""]);
private _targetType = toLower (_order getOrDefault ["targetType", _order getOrDefault ["target_type", ""]]);
private _targetRef = trim (_order getOrDefault ["targetRef", _order getOrDefault ["target_ref", ""]]);
private _aliasesRaw = _order getOrDefault ["aliases", _order getOrDefault ["match_aliases", ""]];

// Diffusion générale
if (_target isEqualTo "" || {_targetType in ["all", "team", ""]}) exitWith { true };

private _myCallsign = [] call comspec_overwatch_connect_fnc_getCallsign;
private _myGroup = groupId (group player);
private _myName = name player;
private _myMid = missionNamespace getVariable ["COMSPEC_MilitaryId", ""];
if (!(_myMid isEqualType "")) then { _myMid = str _myMid; };
_myMid = trim _myMid;

private _idents = [];
{
    private _v = trim _x;
    if (!(_v isEqualTo "")) then { _idents pushBackUnique (toLower _v); };
} forEach [_myCallsign, _myGroup, _myName, _myMid];

if (_myMid != "") then {
    _idents pushBackUnique (toLower ("mid:" + _myMid));
};

// Membres fire-team connus localement
private _myCsLower = toLower _myCallsign;
{
    _x params ["_tid", "_label", "", "", "", ["_members", []]];
    private _inTeam = false;
    {
        _x params ["_mcs"];
        if ((toLower _mcs) isEqualTo _myCsLower) exitWith { _inTeam = true; };
    } forEach _members;
    if (_inTeam) then {
        _idents pushBackUnique (toLower format ["ft:%1", _tid]);
        if (_label != "") then { _idents pushBackUnique (toLower _label); };
    };
} forEach (missionNamespace getVariable ["COMSPEC_FireTeams", []]);

private _candidates = [_target, _targetRef];
if (_aliasesRaw isEqualType []) then {
    { _candidates pushBack _x; } forEach _aliasesRaw;
} else {
    if (_aliasesRaw isEqualType "" && {_aliasesRaw != ""}) then {
        { _candidates pushBack _x; } forEach (_aliasesRaw splitString ",");
    };
};

private _hit = false;
{
    private _c = toLower (trim _x);
    if (_c isEqualTo "") then { continue };
    if (_c in _idents) exitWith { _hit = true; };
    // Comparaison souple callsign / groupe / nom
    if (
        _c isEqualTo (toLower _myCallsign)
        || {_c isEqualTo (toLower _myGroup)}
        || {_c isEqualTo (toLower _myName)}
        || {_myMid != "" && {_c isEqualTo (toLower _myMid)}}
    ) exitWith { _hit = true; };
} forEach _candidates;

_hit
