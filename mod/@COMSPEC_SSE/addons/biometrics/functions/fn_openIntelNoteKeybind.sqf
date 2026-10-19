/*
    Raccourci Ctrl+Shift+R (modifiable dans CBA) — rédiger une fiche de renseignement (RENS).

    Priorité : rédacteur de fiche d'Athena (Overwatch), puis fiche RENS d'Overwatch connect (ouvre la tablette si
    besoin), puis app FRS / FRM du téléphone ATAK natif. Séparé de Ctrl+Shift+S, qui ouvre l'app SSE / le SEEK.
*/
if (!hasInterface) exitWith { false };

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

if (!isNil "comspec_atak_native_fnc_hasDevice" && {[player] call comspec_atak_native_fnc_hasDevice}) exitWith {
    if (isNull ([] call comspec_atak_native_fnc_display) || {!((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["interactive", false])}) then {
        [true] call comspec_atak_native_fnc_open;
    };
    [{ ["FRS"] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame;
    true
};

["Aucun rédacteur de fiche RENS : emportez un téléphone ATAK.", "WARNING"] call comspec_sse_fnc_notify;
false
