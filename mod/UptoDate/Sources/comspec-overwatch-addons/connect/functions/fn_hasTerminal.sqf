/*
    Vérifie si l’unité possède le terminal requis pour sync / interface ATAK.
    Si « Exiger un terminal ATAK » est désactivé → toujours vrai.
*/
params [["_unit", player]];
if (isNull _unit) exitWith { false };

if (!(missionNamespace getVariable ["comspec_overwatch_require_item", true])) exitWith { true };

private _custom = trim (missionNamespace getVariable ["comspec_overwatch_required_item_custom", ""]);
private _cls = if (_custom isNotEqualTo "") then {
    _custom
} else {
    missionNamespace getVariable ["comspec_overwatch_required_item", "ItemAndroid"]
};
if (!(_cls isEqualType "") || {_cls isEqualTo ""}) exitWith { true };

if (_cls in (assignedItems _unit)) exitWith { true };
if (_cls in (items _unit)) exitWith { true };
false
