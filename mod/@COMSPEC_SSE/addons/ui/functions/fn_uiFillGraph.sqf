private _disp = findDisplay 93350;
if (isNull _disp) exitWith { false };

private _rec = [] call comspec_sse_fnc_uiGetRecord;
private _nodes = [];
private _edges = ["<t color='#8f8'>RELATIONS</t>"];

if (!isNull _rec) then {
    private _data = [_rec] call comspec_sse_fnc_getData;
    private _uid = if (isNil "_data") then {"?"} else {[_data, "uid", "?"] call BIS_fnc_getFromPairs};
    private _type = if (isNil "_data") then {"?"} else {[_data, "type", "?"] call BIS_fnc_getFromPairs};
    _nodes pushBack format ["● %1 (%2)", _uid, _type];

    private _id = [_rec, "identity"] call comspec_sse_fnc_getSection;
    if (_id isEqualType createHashMap) then {
        private _alias = _id getOrDefault ["alias", ""];
        if (_alias != "") then {
            _nodes pushBack format ["◇ ALIAS %1", _alias];
            _edges pushBack format ["%1 —alias→ %2", _uid, _alias];
        };
        private _phone = _id getOrDefault ["phone", ""];
        if (_phone != "") then {
            _nodes pushBack format ["☎ %1", _phone];
            _edges pushBack format ["%1 —comms→ %2", _uid, _phone];
        };
    };

    private _devices = [_rec, "digitalDevices"] call comspec_sse_fnc_getSection;
    if (_devices isEqualType []) then {
        {
            if (_x isEqualType createHashMap) then {
                private _dn = _x getOrDefault ["phoneNumber", _x getOrDefault ["uid", "DEV"]];
                _nodes pushBack format ["▣ DEVICE %1", _dn];
                _edges pushBack format ["%1 —owns→ %2", _uid, _dn];
            };
        } forEach _devices;
    };

    private _docs = [_rec, "documents"] call comspec_sse_fnc_getSection;
    if (_docs isEqualType []) then {
        {
            if (_x isEqualType createHashMap) then {
                _nodes pushBack format ["▤ %1", _x getOrDefault ["title", "DOC"]];
                private _g = _x getOrDefault ["grid", ""];
                if (_g != "") then {
                    _nodes pushBack format ["⌖ GRID %1", _g];
                    _edges pushBack format ["DOC —loc→ %1", _g];
                };
            };
        } forEach _docs;
    };

    if (!isNil "comspec_sse_fnc_pivotSearch") then {
        private _piv = [_rec] call comspec_sse_fnc_pivotSearch;
        {
            _nodes pushBack format ["↔ PIVOT %1", netId _x];
            _edges pushBack format ["%1 —pivot→ %2", _uid, typeOf _x];
        } forEach (_piv select [0, 8]);
    };
};

private _logical = if (!isNil "comspec_sse_fnc_listLogicalEntities") then { [] call comspec_sse_fnc_listLogicalEntities } else { [] };
{
    if (_x isEqualType createHashMap) then {
        _nodes pushBack format ["○ %1 [%2]", _x getOrDefault ["label", "?"], _x getOrDefault ["kind", "?"]];
    };
} forEach (_logical select [0, 12]);

private _lb = _disp displayCtrl 93360;
lbClear _lb;
{ _lb lbAdd _x; } forEach _nodes;
if (_nodes isEqualTo []) then { _lb lbAdd "(graphe vide — lier un record)"; };

(_disp displayCtrl 93361) ctrlSetStructuredText parseText (_edges joinString "<br/>");
true
