/*
    Module Eden « Organisation » : déclare un réseau, une cellule, un clan...
    Les personnes synchronisées en deviennent membres ; les objets synchronisés
    lui sont rattachés (les exploiter révèle l'organisation).

    Répartition :
      « manual » chaque personne garde le rôle saisi dans ses attributs (sinon membre) ;
      « auto »   cellules et rôles générés depuis la graine.
*/
params [["_logic", objNull, [objNull]], ["_units", [], [[]]], ["_activated", true, [true]]];
if (!isServer || {isNull _logic}) exitWith { false };

// Après les clés biométriques et les attributs Eden des personnes.
[{
    params ["_logic"];
    if (isNull _logic) exitWith {};
    private _g = { params ["_k", "_d"]; private _v = _logic getVariable [_k, _d]; if (isNil "_v") then { _d } else { _v } };
    private _name = trim (["Name", ""] call _g);
    private _kind = ["Kind", "reseau"] call _g;
    private _parent = ["Parent", ""] call _g;
    private _threat = ["Threat", "moyenne"] call _g;
    private _desc = ["Description", ""] call _g;
    private _mode = ["Distribution", "manual"] call _g;
    private _seed = ["Seed", -1] call _g;
    private _known = ["Known", 0] call _g;
    if (_known isEqualType "") then { _known = parseNumber _known; };
    if (_seed isEqualType "") then { _seed = parseNumber _seed; };
    if (_seed <= 0) then { _seed = -1; };

    private _synced = synchronizedObjects _logic;
    private _people = _synced select { _x isKindOf "CAManBase" };
    { _people append (units group _x); } forEach (_synced select { _x isKindOf "CAManBase" && {count units group _x > 1} && {(leader group _x) isEqualTo _x} });
    _people = _people arrayIntersect _people;
    private _objects = _synced select { !(_x isKindOf "CAManBase") && {!(_x isKindOf "Logic")} };

    if (_name isEqualTo "") then {
        _name = format ["%1 %2", ["kindLabel", _kind] call comspec_sse_fnc_orgTables, (([] call comspec_sse_fnc_orgTables) get "codes") select (floor (random 20))];
    };
    private _id = ["ensure", [_name, _kind, _parent, _threat, _desc]] call comspec_sse_fnc_orgOp;
    if (_mode isEqualTo "auto" && {_people isNotEqualTo []}) then {
        [_people, _name, _kind, _seed, _known, _threat] call comspec_sse_fnc_orgGenerate;
    } else {
        {
            private _role = _x getVariable ["COMSPEC_SSE_Org_Role", "membre"];
            if (!(_role isEqualType "") || {_role in ["", "auto"]}) then { _role = ["membre", "chef"] select (_forEachIndex isEqualTo 0 && {(count _people) > 1}); };
            ["member", [_x, _name, _role, _x getVariable ["COMSPEC_SSE_Org_Alias", ""], _x getVariable ["COMSPEC_SSE_Org_Cell", ""], [-1, 1] select (_known >= 1)]] call comspec_sse_fnc_orgOp;
        } forEach _people;
        if (_known >= 2) then { ["revealOrg", [_id, 1, "connue des services"]] call comspec_sse_fnc_orgOp; };
    };
    { _x setVariable ["COMSPEC_SSE_OrgLinked", _name, true]; } forEach _objects;
}, [_logic], 2] call CBA_fnc_waitAndExecute;
true
