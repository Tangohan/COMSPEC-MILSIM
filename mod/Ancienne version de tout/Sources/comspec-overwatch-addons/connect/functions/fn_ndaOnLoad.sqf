/*
    Remplit l’écran NDA selon la langue (fr / en) et ajuste la hauteur du texte.
*/
params [["_display", displayNull]];

if (isNull _display) then {
    _display = uiNamespace getVariable ["COMSPEC_NDA_Display", displayNull];
};
if (isNull _display) exitWith {};

private _lang = uiNamespace getVariable ["COMSPEC_NDA_Lang", ""];
if (!(_lang isEqualType "") || {_lang isEqualTo ""}) then {
    private _gameLang = toLower (language);
    _lang = if (_gameLang isEqualTo "french") then { "fr" } else { "en" };
    uiNamespace setVariable ["COMSPEC_NDA_Lang", _lang];
};

[_lang] call comspec_overwatch_connect_fnc_ndaSetLanguage;
