/*
    Bascule le masquage d'une catégorie d'alertes dans le journal de liaison (persistant, par
    profil Windows). Rafraîchit le libellé du bouton correspondant si le dialog messagerie est
    ouvert.
    Params: [_category, _idc] où _category ∈ "liaison" | "cas" | "medical", _idc = idc du bouton.
*/
params [["_category", "", [""]], ["_idc", -1, [0]]];
if (_category isEqualTo "") exitWith {};

private _key = format ["comspec_overwatch_mute_%1", _category];
private _muted = profileNamespace getVariable [_key, false];
_muted = !_muted;
profileNamespace setVariable [_key, _muted];
saveProfileNamespace;

if (_idc < 0) exitWith {};
private _display = uiNamespace getVariable ["COMSPEC_Chat_Display", displayNull];
if (isNull _display) exitWith {};
private _ctrl = _display displayCtrl _idc;
if (isNull _ctrl) exitWith {};

private _labels = createHashMapFromArray [
    ["liaison", "Liaison"],
    ["cas", "CAS"],
    ["medical", "Médical"]
];
private _label = _labels getOrDefault [_category, _category];
_ctrl ctrlSetText format ["%1 : %2", _label, if (_muted) then { "masqué" } else { "affiché" }];
