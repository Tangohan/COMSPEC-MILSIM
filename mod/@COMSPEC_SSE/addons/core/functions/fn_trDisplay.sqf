/*
    Textes fixes d'un écran SSE (dialogs/*.hpp) dans la langue du téléphone, au chargement (onLoad, posé par
    tools/i18n.py --sse wrap). Sans effet sans dictionnaire chargé (fn_langApply) ou sans l'ATAK natif.
*/
params ["_display"];
disableSerialization;
if (isNull _display || {isNil "comspec_atak_native_fnc_tr"} || {(missionNamespace getVariable ["COMSPEC_ATAK_I18nN", 0]) isEqualTo 0}) exitWith {};
{
    if (ctrlType _x in [0, 1, 16]) then {
        private _t = ctrlText _x;
        if (_t isNotEqualTo "") then { _x ctrlSetText (_t call comspec_atak_native_fnc_tr); }; // i18n:brut
    };
} forEach (allControls _display);
