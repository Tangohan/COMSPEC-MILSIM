/*
    True = IA ennemie (camp est, pas un joueur) et suivi non demandé :
    ne rien envoyer (position, occupancy, journal).
    False = joueur, autre camp, ou Zeus / Eden a demandé les contacts ennemis.
*/
params [
    ["_obj", objNull, [objNull]]
];
if (isNull _obj) exitWith { false };
if (missionNamespace getVariable ["COMSPEC_AtakShowEnemyAi", false]) exitWith { false };
if (isPlayer _obj) exitWith { false };

if (_obj isKindOf "CAManBase") exitWith {
    (side group _obj) isEqualTo east
};

private _crew = (crew _obj) select { alive _x && {_x isKindOf "CAManBase"} };
if (({ isPlayer _x } count _crew) > 0) exitWith { false };

private _side = side _obj;
if ((_side isEqualTo sideUnknown || {_side isEqualTo civilian}) && {_crew isNotEqualTo []}) then {
    _side = side group (_crew select 0);
};

_side isEqualTo east
