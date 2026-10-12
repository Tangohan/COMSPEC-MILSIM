private _ctx = ["graph"] call comspec_sse_fnc_uiDisplayCtx;
_ctx params ["_disp", "_idcEdges", "_idcList"];
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
                private _dn = _x getOrDefault ["phoneNumber", ""];
                if (_dn isEqualTo "") then { _dn = _x getOrDefault ["uid", "DEV"]; };
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

    // Appartenance connue des joueurs (organisations SSE).
    private _mem = missionNamespace getVariable ["comspec_sse_orgMembers", createHashMap];
    private _m = _mem getOrDefault [_rec getVariable ["COMSPEC_SSE_BioKey", ""], createHashMap];
    if (count _m > 0 && {(_m getOrDefault ["known", 0]) >= 1}) then {
        private _o = (missionNamespace getVariable ["comspec_sse_orgs", createHashMap]) getOrDefault [_m get "org", createHashMap];
        private _role = if (isNil "comspec_sse_fnc_orgTables") then { _m get "role" } else { ["roleLabel", _m get "role"] call comspec_sse_fnc_orgTables };
        _nodes pushBack format ["◆ %1 (%2)%3", _o getOrDefault ["name", "?"], _role, ["", " ?"] select ((_m get "known") < 2)];
        _edges pushBack format ["%1 —%2→ %3", _uid, toLower _role, _o getOrDefault ["name", "?"]];
    };
    private _piece = _rec getVariable ["COMSPEC_SSE_PieceId", ""];
    if (_piece isNotEqualTo "") then { _nodes pushBack format ["▤ PIÈCE %1", _piece]; };

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

private _lb = _disp displayCtrl _idcList;
lbClear _lb;
{ _lb lbAdd _x; } forEach _nodes;
if (_nodes isEqualTo []) then { _lb lbAdd "(graphe vide — lier un record)"; };

if (!isNil "comspec_sse_fnc_chartOpen") then { _edges pushBack "<br/><t color='#9aa'>Vue complète : ACE, COMSPEC SSE, Tableau des liens.</t>"; };
(_disp displayCtrl _idcEdges) ctrlSetStructuredText parseText (_edges joinString "<br/>");
true
