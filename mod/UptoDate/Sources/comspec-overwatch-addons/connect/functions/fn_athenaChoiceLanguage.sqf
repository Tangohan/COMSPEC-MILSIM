/*
    Applique la langue (fr / en) sur l'écran du choix Athena.
*/
params [["_lang", "fr"]];
if (!(_lang isEqualType "")) then { _lang = "fr"; };
_lang = toLower _lang;
if (!(_lang in ["fr", "en"])) then { _lang = "fr"; };

private _display = uiNamespace getVariable ["COMSPEC_AthenaChoice_Display", displayNull];
if (isNull _display) exitWith {};
uiNamespace setVariable ["COMSPEC_AthenaChoice_Lang", _lang];

private _mode = [] call comspec_overwatch_connect_fnc_athenaMode;
([_lang, _mode] call comspec_overwatch_connect_fnc_athenaChoiceTexts) params ["_title", "_question", "_yes", "_yesBtn", "_no", "_noBtn", "_footer", "_current", "_closeTxt"];

(_display displayCtrl 9462) ctrlSetStructuredText parseText _title;
(_display displayCtrl 9465) ctrlSetStructuredText parseText _question;
(_display displayCtrl 9466) ctrlSetStructuredText parseText _yes;
(_display displayCtrl 9467) ctrlSetText _yesBtn;
(_display displayCtrl 9468) ctrlSetStructuredText parseText _no;
(_display displayCtrl 9469) ctrlSetText _noBtn;
(_display displayCtrl 9470) ctrlSetStructuredText parseText _footer;
(_display displayCtrl 9471) ctrlSetStructuredText parseText _current;

private _close = _display displayCtrl 9472;
_close ctrlSetText _closeTxt;
_close ctrlShow (_mode isNotEqualTo "");

private _btnFr = _display displayCtrl 9463;
private _btnEn = _display displayCtrl 9464;
private _on = [0.08, 0.32, 0.28, 0.95];
private _off = [0.05, 0.12, 0.16, 0.95];
_btnFr ctrlSetBackgroundColor ([_off, _on] select (_lang isEqualTo "fr"));
_btnEn ctrlSetBackgroundColor ([_off, _on] select (_lang isEqualTo "en"));
