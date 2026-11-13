/*
    comspec_fb_fnc_targets (serveur) : machines qui doivent jouer une détonation à _posASL.
    Serveur (IA locales), clients headless (IA), joueurs à moins de 2,5 km (le flash se voit de loin la nuit),
    joueurs morts ou spectateurs et Zeus (leur caméra peut être n'importe où). Identifiants « owner » sans doublon.
    [_posASL] call comspec_fb_fnc_targets -> [2, 5, 7, ...]
*/
params [["_posASL", [0, 0, 0], [[]], 3]];
private _owners = [2];
private _pos = ASLToAGL _posASL;
{
    if (_x isKindOf "HeadlessClient_F" || {!alive _x} || {(_x distance2D _pos) < 2500} || {!isNull (getAssignedCuratorLogic _x)}) then {
        _owners pushBackUnique (owner _x);
    };
} forEach allPlayers;
{ _owners pushBackUnique (owner _x) } forEach (entities "HeadlessClient_F");
_owners select { _x >= 2 }
