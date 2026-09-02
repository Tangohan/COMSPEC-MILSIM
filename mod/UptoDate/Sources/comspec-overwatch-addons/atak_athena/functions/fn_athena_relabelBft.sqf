/*
    Remplace les libellés BFT cTab (slot de groupe, nom de groupe Arma)
    par l’indicatif Athena de l’opérateur.
*/
if (!hasInterface) exitWith {};
if (isNil "cTabBFTmembers" && {isNil "cTabBFTgroups"}) exitWith {};

private _fncLabel = {
    params ["_u"];
    if (isNull _u) exitWith { "" };
    [_u] call comspec_overwatch_atak_athena_fnc_athena_bftUnitLabel
};

private _fncLooksLikeSlot = {
    params ["_s"];
    if (!(_s isEqualType "")) then { _s = str _s; };
    _s = trim _s;
    if (_s isEqualTo "") exitWith { true };
    private _low = toLower _s;
    if (_low regexMatch "^[0-9]{1,3}$") exitWith { true };
    if (_low regexMatch "^[0-9]+-[0-9]+$") exitWith { true };
    if (_s regexMatch "^[A-Z] [0-9]+-[0-9]+$") exitWith { true };
    false
};

if (!isNil "cTabBFTmembers" && {cTabBFTmembers isEqualType []}) then {
    {
        if (!(_x isEqualType []) || {(count _x) < 5}) then { continue };
        private _u = _x select 0;
        if (!(_u isEqualType objNull) || {isNull _u}) then { continue };
        private _label = [_u] call _fncLabel;
        if (_label isEqualTo "") then { continue };
        _x set [3, _label];
        _x set [4, _label];
    } forEach cTabBFTmembers;
};

if (!isNil "cTabBFTgroups" && {cTabBFTgroups isEqualType []}) then {
    {
        if (!(_x isEqualType []) || {(count _x) < 4}) then { continue };
        private _u = _x select 0;
        if (!(_u isEqualType objNull) || {isNull _u}) then { continue };
        private _label = [_u] call _fncLabel;
        if (_label isEqualTo "") then {
            private _grp = group _u;
            if (!isNull _grp) then {
                {
                    _label = [_x] call _fncLabel;
                    if (_label isNotEqualTo "") exitWith {};
                } forEach (units _grp);
            };
        };
        if (_label isEqualTo "") then { continue };
        _x set [3, _label];
    } forEach cTabBFTgroups;
};

if (!isNil "cTabBFTvehicles" && {cTabBFTvehicles isEqualType []}) then {
    {
        if (!(_x isEqualType []) || {(count _x) < 4}) then { continue };
        private _veh = _x select 0;
        if (!(_veh isEqualType objNull) || {isNull _veh}) then { continue };
        private _crew = crew _veh;
        if (_crew isEqualTo []) then { continue };
        private _label = [_crew select 0] call _fncLabel;
        if (_label isEqualTo "") then {
            {
                _label = [_x] call _fncLabel;
                if (_label isNotEqualTo "") exitWith {};
            } forEach _crew;
        };
        if (_label isEqualTo "") then { continue };
        _x set [3, _label];
        if ((count _x) > 4 && {[_x select 4] call _fncLooksLikeSlot}) then {
            _x set [4, _label];
        };
    } forEach cTabBFTvehicles;
};

{
    private _type = toLower (markerType _x);
    if ((_type find "b_") != 0 && {(_type find "n_") != 0} && {(_type find "g_") != 0}) then { continue };
    if ((toLower (markerShape _x)) isNotEqualTo "icon") then { continue };
    private _txt = markerText _x;
    private _pos = markerPos _x;
    if ((_pos select 0) == 0 && {(_pos select 1) == 0}) then { continue };

    private _best = objNull;
    private _bestD = 80;
    {
        if (!alive _x || {!isPlayer _x}) then { continue };
        private _d = _x distance2D _pos;
        if (_d >= _bestD) then { continue };
        private _gid = trim (groupId (group _x));
        private _idx = "";
        if (!isNil "CBA_fnc_getGroupIndex") then {
            _idx = str ([_x] call CBA_fnc_getGroupIndex);
        };
        private _nm = name _x;
        if (
            _txt isEqualTo _gid
            || {_txt isEqualTo _idx}
            || {_txt isEqualTo _nm}
            || {[_txt] call _fncLooksLikeSlot}
        ) then {
            _best = _x;
            _bestD = _d;
        };
    } forEach allPlayers;

    if (isNull _best) then { continue };
    private _label = [_best] call _fncLabel;
    if (_label isEqualTo "" || {_label isEqualTo _txt}) then { continue };
    _x setMarkerTextLocal _label;
} forEach allMapMarkers;
