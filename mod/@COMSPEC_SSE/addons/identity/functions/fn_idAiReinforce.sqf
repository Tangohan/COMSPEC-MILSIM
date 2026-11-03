/*
    Appel de renforts (SERVEUR, état « renforts » de l'automate) : jusqu'à 6 IA proches (300 m) du même camp
    convergent vers la personne contrôlée et repèrent le contrôleur ; armées, elles deviennent hostiles.
    Civil : seuls les membres de sa cellule (civils au profil insurgé ou HVT : faux papiers, fichés) répondent ; armés,
    ils passent dans un groupe du camp hostile au contrôleur (joinSilent), sinon ils s'approchent (attroupement).
    [ia, contrôleur] call comspec_sse_fnc_idAiReinforce -> nombre d'IA appelées
*/
params [["_u", objNull, [objNull]], ["_c", objNull, [objNull]]];
if (!isServer || {isNull _u}) exitWith { 0 };
private _side = side group _u;
private _civ = _side isEqualTo civilian;
private _cs = [side group _c, west] select (isNull _c);
private _hs = ([east, independent, west] select { [_x, _cs] call BIS_fnc_sideIsEnemy }) param [0, east];
private _cands = ((_u nearEntities ["CAManBase", 300]) select {
    alive _x && {!isPlayer _x} && {_x isNotEqualTo _u} && {!(_x in (missionNamespace getVariable ["comspec_sse_idFsmList", []]))}
    && {!(_x getVariable ["ace_captives_isHandcuffed", false])} && {(side group _x) isEqualTo _side}
}) apply { [_x distance _u, _x] };
_cands sort true;
_cands = _cands apply { _x select 1 };
if (_civ) then {
    _cands = (_cands select [0, 12]) select { (["profile", [_x, _c]] call comspec_sse_fnc_idAiBrain) in ["insurge", "hvt"] };
};
_cands = _cands select [0, 6];
private _pos = getPosATL _u;
private _targets = [[_c], []] select (isNull _c);
{
    private _armed = (primaryWeapon _x) isNotEqualTo "" || {(handgunWeapon _x) isNotEqualTo ""};
    if (_armed && {_civ || {!([_side, _cs] call BIS_fnc_sideIsEnemy)}}) then {
        ["comspec_sse_idAiDo", [_x, "converge", [_pos getPos [10 + random 15, random 360], _targets]], _x] call CBA_fnc_targetEvent;
        ["comspec_sse_idAiDo", [_x, "hostile", [_hs, _targets]], _x] call CBA_fnc_targetEvent;
    } else {
        ["comspec_sse_idAiDo", [_x, "converge", [_pos getPos [6 + random 10, random 360], _targets]], _x] call CBA_fnc_targetEvent;
    };
} forEach _cands;
count _cands
