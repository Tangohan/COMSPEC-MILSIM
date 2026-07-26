/*
    Vérifie si l’unité possède l’équipement requis pour sync / interface Overwatch.
    Si « Exiger un équipement » est désactivé → toujours vrai.
*/
params [["_unit", player]];
if (isNull _unit) exitWith { false };

if (!(missionNamespace getVariable ["comspec_overwatch_require_item", false])) exitWith { true };

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
