/*
    Panneau de détail du tableau des liens pour le nœud choisi.
    [_nodeId] call comspec_sse_fnc_chartDetail
*/
params [["_id", "", [""]]];
private _disp = uiNamespace getVariable ["comspec_sse_chartDisplay", displayNull];
if (isNull _disp) exitWith {};
private _st = uiNamespace getVariable ["comspec_sse_chartState", createHashMap];
private _zeus = _st getOrDefault ["zeus", false];
private _nodes = _st getOrDefault ["nodes", createHashMap];
private _ctrl = _disp displayCtrl 93530;
private _n = _nodes getOrDefault [_id, createHashMap];
if (count _n == 0) exitWith {
    _ctrl ctrlSetStructuredText parseText "<t color='#9aa'>Choisissez un nœud sur le tableau.</t><br/><br/><t color='#9aa' size='0.9'>Le tableau se remplit au fil de la mission : personnes contrôlées et identifiées, pièces saisies, appareils et documents exploités. Une appartenance d'abord soupçonnée devient confirmée par une identification biométrique.</t>";
};

private _orgs = missionNamespace getVariable ["comspec_sse_orgs", createHashMap];
private _mem = missionNamespace getVariable ["comspec_sse_orgMembers", createHashMap];
private _knownLbl = ([] call comspec_sse_fnc_orgTables) get "known";
private _row = { params ["_k", "_v"]; format ["<t color='#9aa'>%1</t>  %2", _k, _v] };
private _lines = [format ["<t color='#8f8' size='1.1'>%1</t>", _n get "label"], format ["<t color='#cfd8d8'>%1</t>", _n get "sub"], ""];

// Liens du nœud.
private _links = [];
{
    _x params ["_a", "_b", "_lbl"];
    private _other = "";
    if (_a isEqualTo _id) then { _other = _b; };
    if (_b isEqualTo _id) then { _other = _a; };
    if (_other isNotEqualTo "") then {
        _links pushBack format ["· %1%2", (_nodes get _other) get "label", ["", format [" <t color='#9aa'>(%1)</t>", _lbl]] select (_lbl isNotEqualTo "")];
    };
} forEach (_st getOrDefault ["edges", []]);

switch (_n get "kind") do {
    case "person": {
        private _m = _mem getOrDefault [_id select [2], createHashMap];
        if (count _m > 0 && {_zeus || {(_m getOrDefault ["known", 0]) >= 1}}) then {
            private _o = _orgs getOrDefault [_m get "org", createHashMap];
            _lines pushBack (["Organisation", _o getOrDefault ["name", "?"]] call _row);
            _lines pushBack (["Rôle", ["roleLabel", _m get "role"] call comspec_sse_fnc_orgTables] call _row);
            if ((_m getOrDefault ["alias", ""]) isNotEqualTo "") then { _lines pushBack (["Alias", _m get "alias"] call _row); };
            _lines pushBack (["Appartenance", _knownLbl select ((_m getOrDefault ["known", 0]) min 2)] call _row);
            if ((_m getOrDefault ["how", ""]) isNotEqualTo "") then {
                _lines pushBack (["Source", format ["%1%2", _m get "how", [" à " + (_m getOrDefault ["when", ""]), ""] select ((_m getOrDefault ["when", ""]) isEqualTo "")]] call _row);
            };
        } else {
            _lines pushBack "<t color='#9aa'>Aucune appartenance connue.</t>";
        };
        private _u = objectFromNetId (_n get "netId");
        if (!isNull _u) then {
            _lines pushBack (["État", ["vivant", "décédé"] select (!alive _u)] call _row);
            private _pid = _u getVariable ["COMSPEC_SSE_PieceId", ""];
            if (_pid isNotEqualTo "") then { _lines pushBack (["Effets saisis", _pid] call _row); };
            if (_u getVariable ["COMSPEC_SSE_HeadBagged", false]) then { _lines pushBack (["Capture", "sac sur la tête"] call _row); };
        };
    };
    case "org": {
        private _o = _orgs getOrDefault [_id select [2], createHashMap];
        _lines pushBack (["Nature", ["kindLabel", _o getOrDefault ["kind", ""]] call comspec_sse_fnc_orgTables] call _row);
        _lines pushBack (["Menace", ["threatLabel", _o getOrDefault ["threat", ""]] call comspec_sse_fnc_orgTables] call _row);
        private _p = _o getOrDefault ["parent", ""];
        if (_p isNotEqualTo "") then { _lines pushBack (["Rattachée à", (_orgs getOrDefault [_p, createHashMap]) getOrDefault ["name", "?"]] call _row); };
        if ((_o getOrDefault ["desc", ""]) isNotEqualTo "") then { _lines pushBack (_o get "desc"); };
        private _total = 0; private _knownN = 0;
        { if ((_y getOrDefault ["org", ""]) isEqualTo (_id select [2])) then { _total = _total + 1; if ((_y getOrDefault ["known", 0]) >= 1) then { _knownN = _knownN + 1; }; }; } forEach _mem;
        _lines pushBack (["Membres identifiés", [str _knownN, format ["%1 sur %2", _knownN, _total]] select _zeus] call _row);
        if ((_o getOrDefault ["how", ""]) isNotEqualTo "") then { _lines pushBack (["Repérée par", _o get "how"] call _row); };
    };
    default {
        private _o = objectFromNetId (_n get "netId");
        private _pid = if (isNull _o) then { "" } else { _o getVariable ["COMSPEC_SSE_PieceId", ""] };
        if (_pid isNotEqualTo "") then {
            _lines pushBack ([_pid] call comspec_sse_fnc_custodyText);
        };
        if (!isNull _o) then { _lines pushBack (["Position", mapGridPosition _o] call _row); };
    };
};
if (_links isNotEqualTo []) then {
    _lines pushBack "";
    _lines pushBack "<t color='#8f8'>LIENS</t>";
    _lines append (_links select [0, 14]);
};
_ctrl ctrlSetStructuredText parseText (_lines joinString "<br/>");
_ctrl ctrlSetPositionH ((ctrlTextHeight _ctrl) max ((ctrlPosition (ctrlParentControlsGroup _ctrl)) select 3));
_ctrl ctrlCommit 0;
