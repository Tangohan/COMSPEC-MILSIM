/*
    Vérifie si l’unité possède le terminal requis pour sync / interface ATAK.
    Si « Exiger un terminal ATAK » est désactivé → toujours vrai.

    Respecte comspec_overwatch_terminal_mode :
      0 = slot objet (ItemAndroid équipé comme GPS)
      1 = inventaire (ItemAndroidMisc porté)
      2 = les deux (défaut)
*/
params [["_unit", player]];
if (isNull _unit) exitWith { false };

if (!(missionNamespace getVariable ["comspec_overwatch_require_item", true])) exitWith { true };

private _custom = trim (missionNamespace getVariable ["comspec_overwatch_required_item_custom", ""]);
if (_custom isNotEqualTo "") exitWith {
    if (_custom in (assignedItems _unit)) exitWith { true };
    if (_custom in (items _unit)) exitWith { true };
    false
};

private _mode = missionNamespace getVariable ["comspec_overwatch_terminal_mode", 2];
if (!(_mode isEqualType 0)) then { _mode = 2; };
_mode = (_mode max 0) min 2;

private _assigned = assignedItems _unit;
private _inv = items _unit;
private _hasSlot = "ItemAndroid" in _assigned;
private _hasInv = ("ItemAndroidMisc" in _inv)
    || {"ItemAndroidMisc" in _assigned}
    || {"ItemAndroid" in _inv};

switch (_mode) do {
    case 0: { _hasSlot };
    case 1: { _hasInv };
    default { _hasSlot || _hasInv };
};
