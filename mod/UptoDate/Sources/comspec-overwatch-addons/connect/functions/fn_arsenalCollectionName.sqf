/*
    Nom de collection pour regrouper une tenue.
    Priorité : collection Athena, sinon le préfixe avant « - ».
*/
params [["_name", "", [""]], ["_coll", "", [""]]];

private _c = trim _coll;
if (_c isNotEqualTo "") exitWith { _c };

private _n = trim _name;
if (_n isEqualTo "") exitWith { "Autres" };

private _sep = _n find " - ";
if (_sep < 1) exitWith { "Autres" };

private _prefix = trim (_n select [0, _sep]);
if (_prefix isEqualTo "") exitWith { "Autres" };
_prefix
