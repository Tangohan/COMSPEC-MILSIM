/*
    Remonte les chefs de groupe IA ennemis (camp est) vers le poste.
    Plafonné pour ne pas saturer la liaison.
*/
if (!hasInterface) exitWith { false };
if (!(missionNamespace getVariable ["COMSPEC_AtakShowEnemyAi", false])) exitWith { false };
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith { false };
if (missionNamespace getVariable ["COMSPEC_DisconnectSent", false]) exitWith { false };

// Passage manqué (affichage masqué puis réaffiché, liaison coupée) : nouvelle époque de signature,
// les contacts ennemis immobiles repartent tout de suite au lieu d'attendre le battement de 30 s.
private _prevPass = missionNamespace getVariable ["COMSPEC_EnemyAiPassAt", -1e9];
missionNamespace setVariable ["COMSPEC_EnemyAiPassAt", diag_tickTime, false];
if ((diag_tickTime - _prevPass) > 6) then {
    missionNamespace setVariable ["COMSPEC_TrackSigEpoch", (missionNamespace getVariable ["COMSPEC_TrackSigEpoch", 0]) + 1, false];
};

// Groupes ennemis filtrés une fois, joueurs vivants pris une fois par passage (au lieu de allPlayers par chef).
private _groups = allGroups select { (side _x) isEqualTo east };
if (_groups isEqualTo []) exitWith { false };
private _others = allPlayers select { alive _x && {isPlayer _x} && {_x isNotEqualTo player} };

private _sent = 0;
// Plafond de 24 contacts par passage compté sur les contacts suivis (envoyés ou inchangés), pas sur les envois :
// sinon les contacts immobiles, sautés par la signature, laissaient la place à d'autres et la carte en montrait plus.
private _tracked = 0;
{
    if (_tracked >= 24) then { break };
    private _leader = leader _x;
    if (isNull _leader || {!alive _leader}) then { continue };
    if (isPlayer _leader) then { continue };
    if (!(_leader isKindOf "CAManBase")) then { continue };
    if !([_leader, _others] call comspec_overwatch_connect_fnc_isNearestAtakReporter) then { continue };
    _tracked = _tracked + 1;
    if ([_leader] call comspec_overwatch_connect_fnc_reportEnemyPosition) then {
        _sent = _sent + 1;
    };
} forEach _groups;

_sent > 0
