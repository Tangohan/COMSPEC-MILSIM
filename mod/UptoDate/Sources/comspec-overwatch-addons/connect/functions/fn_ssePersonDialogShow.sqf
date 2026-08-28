/*
    Ouvre le terminal SSE — enregistrement d’une personne.
    Args optionnels: [targetUnit]
*/
params [["_target", objNull, [objNull]]];

if (!hasInterface) exitWith { false };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };

// Référence stale (fermeture brutale / createDisplay avorté) : ne pas bloquer l’ouverture.
private _existing = uiNamespace getVariable ["COMSPEC_SsePerson_Display", displayNull];
private _live = findDisplay 9991;
if (!isNull _existing && {!isNull _live}) exitWith {
    [
        "Terminal SEEK déjà ouvert.",
        "tactical",
        "info"
    ] call comspec_overwatch_connect_fnc_announce;
    false
};
uiNamespace setVariable ["COMSPEC_SsePerson_Display", displayNull];

private _resume = uiNamespace getVariable ["COMSPEC_SsePerson_ResumeCollect", false];
if (!_resume) then {
    if (isNull _target) then {
        // L’exploitation d’un corps est un cas SSE courant : pas de filtre « alive » ici.
        private _cursor = cursorObject;
        if (!isNull _cursor && { _cursor isKindOf "CAManBase" } && { _cursor != player }) then {
            _target = _cursor;
        };
    };
    uiNamespace setVariable ["COMSPEC_SsePerson_Target", _target];
    uiNamespace setVariable ["COMSPEC_SsePerson_BioPending", false];
    uiNamespace setVariable ["COMSPEC_SsePerson_PhotoPending", false];
    uiNamespace setVariable ["COMSPEC_SsePerson_PhotoStem", ""];
    if (!isNull _target) then {
        private _priorStem = _target getVariable ["comspec_sse_facePhotoStem", ""];
        if (_priorStem isEqualType "" && {_priorStem isNotEqualTo ""}) then {
            uiNamespace setVariable ["COMSPEC_SsePerson_PhotoPending", true];
            uiNamespace setVariable ["COMSPEC_SsePerson_PhotoStem", _priorStem];
        };
    };
} else {
    if (isNull _target) then {
        _target = uiNamespace getVariable ["COMSPEC_SsePerson_Target", objNull];
    };
};

private _parent = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
if (isNull _parent) then {
    _parent = findDisplay 46;
};

private _ok = false;
private _disp = displayNull;

// 1) Enfant du jeu / cTab — préféré (ne ferme pas le téléphone).
if (!isNull _parent) then {
    _disp = _parent createDisplay "COMSPEC_SsePerson_Dialog";
    _ok = !isNull _disp;
};

// 2) createDialog de secours (mission / menu quand createDisplay échoue).
if (!_ok) then {
    _ok = createDialog "COMSPEC_SsePerson_Dialog";
    _disp = uiNamespace getVariable ["COMSPEC_SsePerson_Display", displayNull];
    if (isNull _disp) then { _disp = findDisplay 9991; };
    _ok = _ok && {!isNull _disp};
};

if (!_ok || {isNull _disp}) exitWith {
    [
        "Impossible d’ouvrir le terminal SEEK — refermez les autres interfaces puis réessayez.",
        "tactical",
        "warn"
    ] call comspec_overwatch_connect_fnc_announce;
    false
};

uiNamespace setVariable ["COMSPEC_SsePerson_Display", _disp];
true
