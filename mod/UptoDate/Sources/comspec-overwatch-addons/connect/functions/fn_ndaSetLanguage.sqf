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
_pack params ["_title", "_subtitle", "_body", "_accept", "_decline", "_footer", "_legal", ["_close", "Fermer"], "", "", "", "", ["_summary", ""], ["_acceptLocked", ""]];
uiNamespace setVariable ["COMSPEC_NDA_Pack", _pack];
// Le texte change de hauteur : fn_ndaWatch recalibre le défilement. Ce qui a déjà été lu reste lu.
uiNamespace setVariable ["COMSPEC_NDA_Recal", true];
// Licence déjà acceptée (relecture) ou en mission : le second bouton ferme simplement la note.
if ((([] call comspec_overwatch_connect_fnc_licenceStatus) select 1) || {!isNull findDisplay 46}) then { _decline = _close; };

(_display displayCtrl 9510) ctrlSetStructuredText parseText ((_title) call comspec_overwatch_connect_fnc_tr);
(_display displayCtrl 9511) ctrlSetStructuredText parseText ((_subtitle) call comspec_overwatch_connect_fnc_tr);
(_display displayCtrl 9512) ctrlSetStructuredText parseText ((_footer) call comspec_overwatch_connect_fnc_tr);
(_display displayCtrl 9513) ctrlSetStructuredText parseText ((_legal) call comspec_overwatch_connect_fnc_tr);
(_display displayCtrl 9518) ctrlSetStructuredText parseText ((_summary) call comspec_overwatch_connect_fnc_tr);
private _read = uiNamespace getVariable ["COMSPEC_NDA_ReadDone", false];
(_display displayCtrl 9501) ctrlSetText (([_acceptLocked, _accept] select _read) call comspec_overwatch_connect_fnc_tr);
(_display displayCtrl 9501) ctrlEnable _read;
(_display displayCtrl 9502) ctrlSetText ((_decline) call comspec_overwatch_connect_fnc_tr);

private _bodyCtrl = _display displayCtrl 9500;
_bodyCtrl ctrlSetStructuredText parseText ((_body) call comspec_overwatch_connect_fnc_tr);

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
    _btnFr ctrlSetBackgroundColor [0.18, 0.46, 0.24, 1];
    _btnEn ctrlSetBackgroundColor [0.08, 0.11, 0.095, 1];
} else {
    _btnEn ctrlSetBackgroundColor [0.18, 0.46, 0.24, 1];
    _btnFr ctrlSetBackgroundColor [0.08, 0.11, 0.095, 1];
};
