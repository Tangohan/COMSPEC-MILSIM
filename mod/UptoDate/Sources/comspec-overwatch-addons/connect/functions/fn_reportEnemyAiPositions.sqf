/*
    Remonte les chefs de groupe IA ennemis (camp est) vers le poste.
    Plafonné pour ne pas saturer la liaison.
*/
if (!hasInterface) exitWith { false };
if (!(missionNamespace getVariable ["COMSPEC_AtakShowEnemyAi", false])) exitWith { false };
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith { false };
if (missionNamespace getVariable ["COMSPEC_DisconnectSent", false]) exitWith { false };

private _sent = 0;
{
    if (_sent >= 24) then { break };
    private _side = side _x;
    if (_side isNotEqualTo east) then { continue };
    private _leader = leader _x;
    if (isNull _leader || {!alive _leader}) then { continue };
    if (isPlayer _leader) then { continue };
    if (!(_leader isKindOf "CAManBase")) then { continue };
    if ([_leader] call comspec_overwatch_connect_fnc_reportEnemyPosition) then {
        _sent = _sent + 1;
    };
} forEach allGroups;

_sent > 0
