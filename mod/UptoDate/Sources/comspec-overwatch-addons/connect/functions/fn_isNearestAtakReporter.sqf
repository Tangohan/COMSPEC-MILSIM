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
// Joueur mort (écran de réapparition / spectateur) : les autres l’ignorent déjà, il ne doit pas s’élire lui-même.
if (!alive player) exitWith { false };
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
    // Départage à distance égale : UID le plus petit (ordre lexical via sort, '<' n'accepte pas les String).
    if ((abs (_d - _myDist)) <= 20) then {
        private _uid = getPlayerUID _x;
        if (_uid isNotEqualTo _myUid) then {
            private _pair = [_uid, _myUid];
            _pair sort true;
            if ((_pair select 0) isNotEqualTo _myUid) then { _ok = false; };
        };
    };
    if (!_ok) then { break; };
} forEach _others;
_ok
