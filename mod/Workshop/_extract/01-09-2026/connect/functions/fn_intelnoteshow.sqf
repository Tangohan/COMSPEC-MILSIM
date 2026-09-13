/*
    Ouvre le rédacteur de fiche de renseignement, plein cadre sur l’ATAK.

    Args optionnels: [_kindCode]
      _kindCode  type de fiche présélectionné (FRM, FRO, FRC, FRA, FRT)
*/
params [["_kindCode", "", [""]]];

if (!hasInterface) exitWith { false };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };

// Référence orpheline (fermeture brutale) : ne pas bloquer une réouverture.
private _existing = uiNamespace getVariable ["COMSPEC_IntelNote_Display", displayNull];
private _live = findDisplay 9982;
if (!isNull _existing && {!isNull _live}) exitWith {
    ["Rédacteur de fiche déjà ouvert.", "tactical", "info"] call comspec_overwatch_connect_fnc_announce;
    false
};
uiNamespace setVariable ["COMSPEC_IntelNote_Display", displayNull];

if (_kindCode isNotEqualTo "") then {
    uiNamespace setVariable ["COMSPEC_IntelNote_PendingKind", toUpper _kindCode];
};

private _parent = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
if (isNull _parent) then {
    _parent = findDisplay 46;
};

private _ok = false;
private _disp = displayNull;

// Enfant de l’ATAK : le rédacteur recouvre le téléphone sans le fermer.
if (!isNull _parent) then {
    _disp = _parent createDisplay "COMSPEC_IntelNote_Dialog";
    _ok = !isNull _disp;
};

// Repli hors ATAK (menu, mission) : dialog classique.
if (!_ok) then {
    _ok = createDialog "COMSPEC_IntelNote_Dialog";
    _disp = uiNamespace getVariable ["COMSPEC_IntelNote_Display", displayNull];
    if (isNull _disp) then { _disp = findDisplay 9982; };
    _ok = _ok && {!isNull _disp};
};

if (!_ok || {isNull _disp}) exitWith {
    [
        "Impossible d’ouvrir le rédacteur de fiche — refermez les autres interfaces puis réessayez.",
        "tactical",
        "warn"
    ] call comspec_overwatch_connect_fnc_announce;
    false
};

uiNamespace setVariable ["COMSPEC_IntelNote_Display", _disp];
true
