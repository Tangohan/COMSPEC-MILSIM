/*
    Lit les wardrobes ACE Arsenal locales (profileNamespace).
    Retourne : [["name", loadoutArrayOrWrapped], ...]
*/
private _saved = profileNamespace getVariable ["ace_arsenal_saved_loadouts", []];
if (!(_saved isEqualType [])) exitWith { [] };

private _out = [];
{
    if (!(_x isEqualType []) || {count _x < 2}) then { continue };
    private _name = _x select 0;
    private _data = _x select 1;
    if (!(_name isEqualType "") || {_name isEqualTo ""}) then { continue };
    _out pushBack [_name, _data];
} forEach _saved;

_out
