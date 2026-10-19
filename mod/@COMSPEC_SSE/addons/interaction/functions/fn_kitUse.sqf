/*
    Consommables des kits SSE : chaque kit porté contient un nombre d'usages (poudre et rubans,
    écouvillons, cônes…). Un usage est retiré à chaque geste ; le kit vide disparaît de l'inventaire.
    [_unit, _class, _perKit, _dryRun] call comspec_sse_fnc_kitUse -> NUMBER usages restants avant ce geste (0 = pas de kit)
      _dryRun = true : lecture seule (condition d'une action)
    Compteur sur l'unité (un objet d'inventaire ne porte pas de variables) : le kit entamé est celui en cours.
*/
params [
    ["_unit", objNull, [objNull]],
    ["_class", "", [""]],
    ["_perKit", 10, [0]],
    ["_dryRun", false, [false]]
];

if (isNull _unit || {_class isEqualTo ""}) exitWith { 0 };
private _owned = { _x == _class } count ((items _unit) + (assignedItems _unit));
if (_owned isEqualTo 0) exitWith { 0 };

private _uses = _unit getVariable ["comspec_sse_kitUses", createHashMap];
private _left = _uses getOrDefault [_class, _perKit];
// Total disponible : le kit entamé plus les kits neufs de l'inventaire.
private _total = _left + ((_owned - 1) * _perKit);
if (_dryRun) exitWith { _total };

_left = _left - 1;
if (_left <= 0) then {
    _unit removeItem _class;
    _left = _perKit;
    ["Kit épuisé : il a été retiré de votre inventaire.", "WARNING"] call comspec_sse_fnc_notify;
};
_uses set [_class, _left];
_unit setVariable ["comspec_sse_kitUses", _uses];
_total
