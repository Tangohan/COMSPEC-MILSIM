/*
    Vérifie si l’unité possède le terminal requis pour sync / interface ATAK.
    Si « Exiger un terminal ATAK » est désactivé → toujours vrai.

    Respecte comspec_overwatch_terminal_mode :
      0 = slot objet (ItemAndroid équipé comme GPS)
      1 = inventaire (ItemAndroidMisc porté)
      2 = les deux (défaut)

    Si le téléphone cTab est réellement ouvert pour le joueur local : considéré
    équipé (évite BFT coupé alors que l’UI Athena est visible).
    Attention : ne pas se fier à « !isNil cTabIfOpen » seul — la variable peut
    rester définie après fermeture / drop et continuerait le suivi sans téléphone.
*/
params [["_unit", player]];
if (isNull _unit) exitWith { false };

if (!(missionNamespace getVariable ["comspec_overwatch_require_item", true])) exitWith { true };

// Téléphone ATAK natif chargé : c'est lui qui décide (réglages serveur « COMSPEC ATAK natif »).
if (!isNil "comspec_atak_native_fnc_hasDevice") exitWith { [_unit] call comspec_atak_native_fnc_hasDevice };

private _fnc_isTerminalClass = {
    params ["_cls"];
    if (!(_cls isEqualType "") || {_cls isEqualTo ""}) exitWith { false };
    private _l = toLower _cls;
    // Caméra casque / accessoires photo : pas un terminal de liaison.
    if (
        (_l find "hcam") >= 0
        || {(_l find "helmetcam") >= 0}
        || {(_l find "helmet_cam") >= 0}
    ) exitWith { false };
    if ((_l find "itemandroid") >= 0) exitWith { true };
    // Tablette cTab (ItemcTab…), hors caméras déjà exclues.
    if (_l isEqualTo "itemctab") exitWith { true };
    if ((_l find "itemctab") == 0) exitWith { true };
    false
};

// UI réellement affichée (pas une variable cTabIfOpen orpheline).
if (_unit isEqualTo player) then {
    if (!isNull (uiNamespace getVariable ["cTab_Android_dlg", displayNull])) exitWith { true };
    if (!isNull (uiNamespace getVariable ["cTab_Android_dsp", displayNull])) exitWith { true };
    if (
        !isNil "cTabIfOpen"
        && {cTabIfOpen isEqualType []}
        && {(count cTabIfOpen) >= 2}
    ) then {
        private _dispName = cTabIfOpen select 1;
        if (_dispName isEqualType "" && {_dispName isNotEqualTo ""}) then {
            if (!isNull (uiNamespace getVariable [_dispName, displayNull])) exitWith { true };
        };
    };
};

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
private _hasSlot = false;
private _hasInv = false;
{
    if ([_x] call _fnc_isTerminalClass) then { _hasSlot = true; };
} forEach _assigned;
{
    if ([_x] call _fnc_isTerminalClass) then { _hasInv = true; };
} forEach (_inv + _assigned);

switch (_mode) do {
    case 0: { _hasSlot };
    case 1: { _hasInv };
    default { _hasSlot || _hasInv };
};
