/*
    Ajoute une ligne au journal de liaison et rafraîchit le dialog messagerie s’il est ouvert.
    Params: [_line, _category] où _category ∈ "liaison" (défaut) | "cas" | "medical" | "system".
    Une catégorie hiddene par le joueur (réglage persistant, voir fn_toggleLogCategory) est
    silencieusement ignorée : rien n’est ajouté au journal ni shown.

    IMPORTANT SQF : "\n" n’est PAS un saut de ligne (c’est \ + n). splitString "\n" coupe
    donc chaque lettre « n » (ex. Athena → Athe + a). Toujours utiliser toString [10].
*/
params [["_line", ""], ["_category", "liaison", [""]]];
if (_line isEqualTo "") exitWith {};

private _muted = profileNamespace getVariable [format ["comspec_overwatch_mute_%1", _category], false];
if (_muted) exitWith {};

private _nl = toString [10];
private _log = missionNamespace getVariable ["COMSPEC_Log", ""];
_log = _log + _line + _nl;
// Garde les ~40 dernières lignes pour rester lisible dans le RscEdit
private _lines = _log splitString _nl;
if (count _lines > 40) then {
    _lines = _lines select [(count _lines) - 40, 40];
    _log = (_lines joinString _nl) + _nl;
};
missionNamespace setVariable ["COMSPEC_Log", _log, true];

private _display = uiNamespace getVariable ["COMSPEC_Chat_Display", displayNull];
if (!isNull _display) then {
    private _ctrl = _display displayCtrl 1402;
    if (!isNull _ctrl) then { _ctrl ctrlSetText _log; };
};
