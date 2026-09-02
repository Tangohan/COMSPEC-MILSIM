/*
    Retire une tenue de l’arsenal ACE local (cet ordinateur).
*/
params [["_name", "", [""]]];

if (!hasInterface) exitWith { false };
if (_name isEqualTo "") exitWith { false };

private _saved = profileNamespace getVariable ["ace_arsenal_saved_loadouts", []];
if (!(_saved isEqualType [])) exitWith { false };

private _want = toLower _name;
private _kept = [];
private _found = false;
{
    if (!(_x isEqualType []) || {count _x < 1}) then { continue };
    private _entryName = _x select 0;
    if ((_entryName isEqualType "") && {toLower _entryName isEqualTo _want}) then {
        _found = true;
        continue;
    };
    _kept pushBack _x;
} forEach _saved;

if (!_found) exitWith { false };

profileNamespace setVariable ["ace_arsenal_saved_loadouts", _kept];
saveProfileNamespace;
true
