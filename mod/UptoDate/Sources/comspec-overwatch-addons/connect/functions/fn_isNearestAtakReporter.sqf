/*
    True si ce poste est le plus proche de l’objet (parmi les joueurs).
    Évite qu’un seul relais (Steam le plus petit) perde l’IA dès qu’elle s’éloigne.
*/
params [
    ["_obj", objNull, [objNull]]
];
if (!hasInterface) exitWith { false };
if (isNull player) exitWith { false };
if (isNull _obj) exitWith { false };
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith { false };
if (missionNamespace getVariable ["COMSPEC_DisconnectSent", false]) exitWith { false };

private _myUid = getPlayerUID player;
private _myDist = player distance2D _obj;
private _ok = true;
{
    if (!_ok) then { break };
    if (!alive _x || {!isPlayer _x}) then { continue };
    if (_x isEqualTo player) then { continue };
    private _d = _x distance2D _obj;
    if ((_d + 20) < _myDist) then { _ok = false; break; };
    if ((abs (_d - _myDist)) <= 20 && {(getPlayerUID _x) < _myUid}) then { _ok = false; break; };
} forEach allPlayers;
_ok
