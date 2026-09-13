/*
    Chef d’unité (leader de groupe), joueur solo, ou rôle « chef » / « commandant ».
*/
if (!hasInterface) exitWith { false };
if (isNull player || {!alive player}) exitWith { false };

private _g = group player;
if (leader _g isEqualTo player) exitWith { true };
if ((count (units _g)) <= 1) exitWith { true };

private _role = toLower (roleDescription player);
if (
    (_role find "chef" >= 0)
    || {_role find "command" >= 0}
    || {_role find "leader" >= 0}
    || {_role find "sl " >= 0}
    || {_role find "tl " >= 0}
) exitWith { true };

private _unitRole = toLower (missionNamespace getVariable ["COMSPEC_UnitRole", ""]);
if (
    (_unitRole find "chef" >= 0)
    || {_unitRole find "command" >= 0}
    || {_unitRole find "leader" >= 0}
) exitWith { true };

false
