/*
    Compte rendu du serveur après une demande « sites et kits » (comspec_sse_fnc_siteRequest).
    [_template, _values, _kind] call comspec_sse_fnc_siteNotify
    Le modèle et les valeurs texte (noms de sites) sont traduits ici, dans la langue du demandeur,
    avant d'être assemblés : le serveur ne connaît pas la langue du téléphone du Zeus.
*/
params [
    ["_template", "", [""]],
    ["_values", [], [[]]],
    ["_kind", "info", [""]]
];

if (!hasInterface || {_template isEqualTo ""}) exitWith { false };

private _vals = _values apply { if (_x isEqualType "") then { _x call comspec_sse_fnc_tr } else { _x } };
[format ([_template call comspec_sse_fnc_tr] + _vals), _kind] call comspec_sse_fnc_zeusNotify
