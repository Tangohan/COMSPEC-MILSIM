/*
    Conserve le brouillon de la fiche en cours dans le profil du joueur.

    Une fiche est souvent interrompue : contact, déplacement, ordre radio. Perdre
    la saisie à chaque fermeture pousserait l'opérateur à ne plus rien rédiger.

    Args: [_clear]  true = efface le brouillon (fiche transmise)
*/
params [["_clear", false, [true]]];

if (!hasInterface) exitWith {};

if (_clear) exitWith {
    profileNamespace setVariable ["COMSPEC_IntelNote_Draft", nil];
    saveProfileNamespace;
};

private _disp = uiNamespace getVariable ["COMSPEC_IntelNote_Display", displayNull];
if (isNull _disp) then { _disp = findDisplay 9982; };
if (isNull _disp) exitWith {};

private _kindCombo = _disp displayCtrl 9656;
private _kindIdx = lbCurSel _kindCombo;
private _kind = if (_kindIdx >= 0) then { _kindCombo lbData _kindIdx } else { "FRM" };

private _urgencyCombo = _disp displayCtrl 9657;
private _urgencyIdx = lbCurSel _urgencyCombo;
private _urgency = if (_urgencyIdx >= 0) then { _urgencyCombo lbData _urgencyIdx } else { "routine" };

private _themes = uiNamespace getVariable ["COMSPEC_IntelNote_Themes", []];
if (!(_themes isEqualType [])) then { _themes = []; };

// Par la mémoire des champs : le brouillon est enregistré à la fermeture, donc
// souvent depuis un volet où le cadre de rédaction ou le lieu sont masqués.
profileNamespace setVariable ["COMSPEC_IntelNote_Draft", [
    ["value", "body"] call comspec_overwatch_connect_fnc_intelNoteCache,
    trim (["value", "place"] call comspec_overwatch_connect_fnc_intelNoteCache),
    _kind,
    _urgency,
    _themes
]];
saveProfileNamespace;
