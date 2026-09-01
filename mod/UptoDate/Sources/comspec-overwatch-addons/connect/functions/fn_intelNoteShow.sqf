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

[_disp] spawn {
    params ["_disp"];
    uiSleep 0.08;
    if (!isNull _disp) then {
        [] call comspec_overwatch_connect_fnc_intelNoteApplyGeometry;
        [] call comspec_overwatch_connect_fnc_intelNoteRefresh;
    };
};

// Le téléphone peut refermer un display enfant pendant le calage du menu.
[_disp, _parent] spawn {
    params ["_disp", "_parent"];
    uiSleep 0.35;
    if (!isNull _disp && {!isNull (findDisplay 9982)}) exitWith {
        [] call comspec_overwatch_connect_fnc_intelNoteApplyGeometry;
    };
    uiNamespace setVariable ["COMSPEC_IntelNote_Display", displayNull];
    private _againParent = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
    if (isNull _againParent) then { _againParent = _parent; };
    private _retryOk = false;
    if (!isNull _againParent) then {
        private _retryDisp = _againParent createDisplay "COMSPEC_IntelNote_Dialog";
        if (!isNull _retryDisp) then {
            uiNamespace setVariable ["COMSPEC_IntelNote_Display", _retryDisp];
            [] call comspec_overwatch_connect_fnc_intelNoteApplyGeometry;
            _retryOk = true;
        };
    };
    if (!_retryOk) then {
        [
            "Le rédacteur de fiche s’est refermé. Utilisez la tuile FRS/FRM ou Rédiger une fiche.",
            "tactical",
            "warn"
        ] call comspec_overwatch_connect_fnc_announce;
    };
};

true
