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
([_lang, _mode] call comspec_overwatch_connect_fnc_athenaChoiceTexts) params ["_title", "_subtitle", "_question", "_yesTitle", "_yes", "_yesBtn", "_noTitle", "_no", "_noBtn", "_footer", "_current", "_closeTxt"];

{
    _x params ["_idc", "_txt"];
    (_display displayCtrl _idc) ctrlSetStructuredText parseText ((_txt) call comspec_overwatch_connect_fnc_tr);
} forEach [[9462, _title], [9475, _subtitle], [9465, _question], [9473, _yesTitle], [9466, _yes], [9474, _noTitle], [9468, _no], [9470, _footer], [9471, _current]];
(_display displayCtrl 9467) ctrlSetText ((_yesBtn) call comspec_overwatch_connect_fnc_tr);
(_display displayCtrl 9469) ctrlSetText ((_noBtn) call comspec_overwatch_connect_fnc_tr);

private _close = _display displayCtrl 9472;
_close ctrlSetText ((_closeTxt) call comspec_overwatch_connect_fnc_tr);
_close ctrlShow (_mode isNotEqualTo "");

private _btnFr = _display displayCtrl 9463;
private _btnEn = _display displayCtrl 9464;
private _on = [0.08, 0.32, 0.28, 0.95];
private _off = [0.05, 0.12, 0.16, 0.95];
_btnFr ctrlSetBackgroundColor ([_off, _on] select (_lang isEqualTo "fr"));
_btnEn ctrlSetBackgroundColor ([_off, _on] select (_lang isEqualTo "en"));
