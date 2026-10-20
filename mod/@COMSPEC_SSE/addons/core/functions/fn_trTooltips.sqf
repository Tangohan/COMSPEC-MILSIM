/*
    Bulles d'aide d'un écran SSE dans la langue du téléphone, au chargement (onLoad, après fn_trDisplay qui ne
    traduit que les textes affichés). Chaque contrôle est retrouvé par son nom de classe dans la config du dialogue.
    [_display, _dialogClass] call comspec_sse_fnc_trTooltips
*/
params [["_display", displayNull, [displayNull]], ["_class", "", [""]]];
disableSerialization;
if (isNull _display || {_class isEqualTo ""} || {isNil "comspec_atak_native_fnc_tr"} || {(missionNamespace getVariable ["COMSPEC_ATAK_I18nN", 0]) isEqualTo 0}) exitWith {};
private _cfg = configFile >> _class;
{
    private _ctrl = _x;
    private _name = ctrlClassName _ctrl;
    {
        private _tip = getText (_cfg >> _x >> _name >> "tooltip");
        if (_tip isNotEqualTo "") exitWith { _ctrl ctrlSetTooltip (_tip call comspec_atak_native_fnc_tr); }; // i18n:brut
    } forEach ["controls", "controlsBackground"];
} forEach (allControls _display);
