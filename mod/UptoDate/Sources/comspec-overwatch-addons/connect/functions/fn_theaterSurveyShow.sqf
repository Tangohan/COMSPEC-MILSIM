/*
    Ouvre la fenêtre de relevé du théâtre. [_autostart] lance le parcours si Athena est liée.
*/
params [["_autostart", false, [true]]];
if (!hasInterface) exitWith {};

disableSerialization;

if (!isNull (uiNamespace getVariable ["COMSPEC_TheaterSurvey_Display", displayNull])) then {
    [] call comspec_overwatch_connect_fnc_theaterSurveyRefresh;
} else {
    if (missionNamespace getVariable ["COMSPEC_TheaterSurveyOpening", false]) exitWith {};
    missionNamespace setVariable ["COMSPEC_TheaterSurveyOpening", true, false];

    private _parent = displayNull;
    if (is3DEN) then { _parent = findDisplay 313; };
    // Ne pas parenter au Zeus (312) : l’enfant vole le curseur et ne le rend souvent pas à la fermeture.
    if (isNull _parent) then { _parent = findDisplay 46; };
    if (isNull _parent) then { _parent = findDisplay 0; };

    private _ok = false;
    private _disp = displayNull;
    if (!isNull _parent) then {
        _disp = _parent createDisplay "COMSPEC_TheaterSurvey_Dialog";
        _ok = !isNull _disp;
    };
    if (!_ok || {isNull _disp}) then {
        _ok = createDialog "COMSPEC_TheaterSurvey_Dialog";
        _disp = uiNamespace getVariable ["COMSPEC_TheaterSurvey_Display", displayNull];
        if (isNull _disp) then { _disp = findDisplay 9994; };
    };

    missionNamespace setVariable ["COMSPEC_TheaterSurveyOpening", false, false];

    if (!_ok || {isNull _disp}) exitWith {
        ["Impossible d’ouvrir la fenêtre de relevé. Réessayez depuis Zeus ou l’éditeur.", "system", "warn"] call comspec_overwatch_connect_fnc_announce;
    };

    uiNamespace setVariable ["COMSPEC_TheaterSurvey_Display", _disp];
};

if (_autostart) then {
    [] call comspec_overwatch_connect_fnc_sampleTheater;
};
