/*
    Affichage de la fenêtre de relevé : mémorise le display et rafraîchit les libellés.
*/
disableSerialization;
private _disp = displayNull;
if (_this isEqualType []) then {
    _disp = _this param [0, displayNull];
} else {
    if (_this isEqualType displayNull) then { _disp = _this; };
};
if (!(_disp isEqualType displayNull) || {isNull _disp}) exitWith {};

uiNamespace setVariable ["COMSPEC_TheaterSurvey_Display", _disp];
[] call comspec_overwatch_connect_fnc_theaterSurveyRefresh;

if !(isNil "COMSPEC_TheaterSurveyPFH") exitWith {};
COMSPEC_TheaterSurveyPFH = [{
    if (isNull (uiNamespace getVariable ["COMSPEC_TheaterSurvey_Display", displayNull])) exitWith {
        [_this select 1] call CBA_fnc_removePerFrameHandler;
        COMSPEC_TheaterSurveyPFH = nil;
    };
    [] call comspec_overwatch_connect_fnc_theaterSurveyRefresh;
}, 0.4, []] call CBA_fnc_addPerFrameHandler;
