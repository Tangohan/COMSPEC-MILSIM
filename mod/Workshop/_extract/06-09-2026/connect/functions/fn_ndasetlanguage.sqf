/*
    Applique la langue FR/EN sur l’écran NDA (libellés + corps défilant).
*/
params [["_lang", "fr"]];

if (!(_lang isEqualType "")) then { _lang = "fr"; };
_lang = toLower _lang;
if (!(_lang in ["fr", "en"])) then { _lang = "fr"; };

private _display = uiNamespace getVariable ["COMSPEC_NDA_Display", displayNull];
if (isNull _display) exitWith {};

uiNamespace setVariable ["COMSPEC_NDA_Lang", _lang];

private _pack = [_lang] call comspec_overwatch_connect_fnc_ndaTexts;
_pack params ["_title", "_subtitle", "_body", "_accept", "_decline", "_footer", "_legal"];

(_display displayCtrl 9510) ctrlSetStructuredText parseText _title;
(_display displayCtrl 9511) ctrlSetStructuredText parseText _subtitle;
(_display displayCtrl 9512) ctrlSetStructuredText parseText _footer;
(_display displayCtrl 9513) ctrlSetStructuredText parseText _legal;
(_display displayCtrl 9501) ctrlSetText _accept;
(_display displayCtrl 9502) ctrlSetText _decline;

private _bodyCtrl = _display displayCtrl 9500;
_bodyCtrl ctrlSetStructuredText parseText _body;

private _h = ctrlTextHeight _bodyCtrl;
if (_h < 0.5) then { _h = 2.5 * safezoneH; };
_h = _h + 0.02;
private _pos = ctrlPosition _bodyCtrl;
_bodyCtrl ctrlSetPosition [_pos select 0, _pos select 1, _pos select 2, _h];
_bodyCtrl ctrlCommit 0;

// Mise en avant du bouton de langue actif
private _btnFr = _display displayCtrl 9505;
private _btnEn = _display displayCtrl 9506;
if (_lang isEqualTo "fr") then {
    _btnFr ctrlSetBackgroundColor [0.08, 0.32, 0.28, 0.95];
    _btnEn ctrlSetBackgroundColor [0.05, 0.12, 0.16, 0.95];
} else {
    _btnEn ctrlSetBackgroundColor [0.08, 0.32, 0.28, 0.95];
    _btnFr ctrlSetBackgroundColor [0.05, 0.12, 0.16, 0.95];
};
