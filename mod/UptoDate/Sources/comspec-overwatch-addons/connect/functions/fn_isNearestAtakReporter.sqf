/*
    True si ce poste est le plus proche de l’objet (parmi les joueurs).
    Évite qu’un seul relais (Steam le plus petit) perde l’IA dès qu’elle s’éloigne.
    _others (facultatif) : autres joueurs vivants déjà filtrés par l’appelant, pour ne pas
    refiltrer allPlayers à chaque objet d’un même passage.
*/
params [
    ["_obj", objNull, [objNull]],
    ["_others", objNull, [[], objNull]]
];
if (!hasInterface) exitWith { false };
if (isNull player) exitWith { false };
if (isNull _obj) exitWith { false };
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith { false };
if (missionNamespace getVariable ["COMSPEC_DisconnectSent", false]) exitWith { false };

private _myUid = getPlayerUID player;
private _myDist = player distance2D _obj;
if (!(_others isEqualType [])) then {
    _others = allPlayers select { alive _x && {isPlayer _x} && {_x isNotEqualTo player} };
};
private _ok = true;
{
    // Joueur non lié à Athena : il ne remonte rien, il ne doit pas gagner l’élection.
    if !(_x getVariable ["COMSPEC_AtakReporterLinked", false]) then { continue };
    private _d = _x distance2D _obj;
    if ((_d + 20) < _myDist) then { _ok = false; break; };
    if ((abs (_d - _myDist)) <= 20 && {(getPlayerUID _x) < _myUid}) then { _ok = false; break; };
} forEach _others;
_ok
