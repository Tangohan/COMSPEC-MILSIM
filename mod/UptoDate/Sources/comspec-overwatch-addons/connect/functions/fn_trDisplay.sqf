/*
    Textes fixes d'un écran (display*.hpp) dans la langue du téléphone, au chargement (onLoad, posé par
    tools/i18n.py --connect wrap). Sans effet sans dictionnaire chargé (fn_langApply) ou sans l'ATAK natif.
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
// Textes enrichis (RscStructuredText, type 13 : « ŒIL DROIT (OD) », « NOM ») et infobulles : aucune commande ne les relit
// sur le contrôle ; ils sont repris dans la config de l'écran (retrouvée par son idd, une fois par session).
private _idd = ctrlIDD _display;
private _cfgCache = uiNamespace getVariable ["COMSPEC_OW_TrCfg", createHashMap];
private _byClass = _cfgCache getOrDefault [_idd, nil];
if (isNil "_byClass") then {
    _byClass = createHashMap;
    private _root = (format ["getNumber (_x >> 'idd') isEqualTo %1", _idd]) configClasses configFile;
    if (_root isNotEqualTo []) then {
        private _walk = {
            params ["_group"];
            {
                private _tx = [getText (_x >> "text"), ""] select !(isText (_x >> "text"));
                private _tt = [getText (_x >> "tooltip"), ""] select !(isText (_x >> "tooltip"));
                if (_tx isNotEqualTo "" || {_tt isNotEqualTo ""}) then { _byClass set [configName _x, [getNumber (_x >> "type"), _tx, _tt]]; };
                if (isClass (_x >> "controls")) then { [_x >> "controls"] call _walk; };
            } forEach ("true" configClasses _group);
        };
        { if (isClass ((_root select 0) >> _x)) then { [(_root select 0) >> _x] call _walk; }; } forEach ["controlsBackground", "controls", "objects"];
    };
    _cfgCache set [_idd, _byClass];
    uiNamespace setVariable ["COMSPEC_OW_TrCfg", _cfgCache];
};
{
    private _e = _byClass getOrDefault [ctrlClassName _x, []];
    if (_e isNotEqualTo []) then {
        _e params ["_ty", "_tx", "_tt"];
        if (_ty isEqualTo 13 && {_tx isNotEqualTo ""}) then { _x ctrlSetStructuredText parseText (_tx call comspec_atak_native_fnc_tr); }; // i18n:brut
        if (_tt isNotEqualTo "") then { _x ctrlSetTooltip (_tt call comspec_atak_native_fnc_tr); }; // i18n:brut
    };
} forEach (allControls _display);
