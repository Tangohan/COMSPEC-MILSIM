/*
    Contexte commun des modules SSE (Zeus et Eden).

    [_logic, _units, _activated] call comspec_sse_fnc_moduleContext
    → HashMap :
        run     : true si CE poste doit exécuter le module
        zeus    : true si posé par un Zeus (interface curateur ouverte ici)
        logic   : la logique du module
        pos     : position ATL de la logique
        targets : objets visés (attaché en Zeus, synchronisés en Eden, unités)
        target  : premier objet visé (ou objNull)

    Un seul exécutant : le poste où la logique est locale. En Zeus, c'est le
    curateur qui a posé le module ; en Eden, le serveur. Les modules étant
    isGlobal = 1, sans ce filtre chaque client relançait la génération (graines
    aléatoires différentes, données écrasées) et les dialogues s'ouvraient
    chez tous les joueurs.
*/
params [
    ["_logic", objNull, [objNull]],
    ["_units", [], [[]]],
    ["_activated", true, [true]]
];

private _out = createHashMapFromArray [["run", false]];
if (isNull _logic || {!_activated}) exitWith { _out };
if (!local _logic) exitWith { _out };

private _targets = [];
private _add = {
    params ["_o"];
    if (_o isEqualType objNull && {!isNull _o} && {!(_o isKindOf "Logic")}) then {
        _targets pushBackUnique _o;
    };
};

[_logic getVariable ["bis_fnc_curatorAttachObject_object", objNull]] call _add;
[attachedTo _logic] call _add;
{ [_x] call _add; } forEach (synchronizedObjects _logic);
{ [_x] call _add; } forEach _units;

private _zeus = hasInterface && {!isNull curatorCamera || {!isNull (findDisplay 312)}};

_out set ["run", true];
_out set ["zeus", _zeus];
_out set ["logic", _logic];
_out set ["pos", getPosATL _logic];
_out set ["targets", _targets];
_out set ["target", _targets param [0, objNull]];
_out
