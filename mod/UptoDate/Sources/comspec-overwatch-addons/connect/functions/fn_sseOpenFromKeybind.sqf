/*
    Raccourci Overwatch — fiche de renseignement (RENS).

    Si le mod COMSPEC SSE est chargé, son raccourci Ctrl+Shift+S reste prioritaire
    (on n'enregistre pas de second défaut). Ici : rédacteur plein cadre, puis
    terminal SEEK en repli.
*/
if (!hasInterface) exitWith { false };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };

if (!isNil "comspec_sse_fnc_openSeekKeybind") exitWith {
    [] call comspec_sse_fnc_openSeekKeybind
};

if (!isNil "comspec_overwatch_atak_athena_fnc_athena_openNote") exitWith {
    [""] call comspec_overwatch_atak_athena_fnc_athena_openNote
};

if (!isNil "comspec_overwatch_connect_fnc_intelNoteShow") exitWith {
    if (isNull (uiNamespace getVariable ["cTab_Android_dlg", displayNull])
        && {!isNil "comspec_overwatch_connect_fnc_openAtakEnhanced"}) then {
        [] call comspec_overwatch_connect_fnc_openAtakEnhanced;
    };
    [""] call comspec_overwatch_connect_fnc_intelNoteShow
};

private _target = cursorObject;
if (isNull _target) then { _target = cursorTarget; };
if (isNull _target || {!(_target isKindOf "CAManBase")}) then {
    _target = objNull;
};

if (!isNil "comspec_overwatch_connect_fnc_ssePersonDialogShow") exitWith {
    [_target] call comspec_overwatch_connect_fnc_ssePersonDialogShow
};

[
    "Impossible d'ouvrir le rédacteur de fiche. Emportez un téléphone ATAK.",
    "tactical",
    "warn"
] call comspec_overwatch_connect_fnc_announce;
false
