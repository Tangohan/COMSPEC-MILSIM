/*
    Navigue vers la diapositive précédente/suivante dans le tableau de briefing ouvert.
    Params: [_delta] (ex. 1 = suivante, -1 = précédente)
*/
params [["_delta", 1, [0]]];

if (missionNamespace getVariable ["COMSPEC_GoogleBriefingActive", false]) exitWith {
    [_delta] call comspec_overwatch_connect_fnc_googleBriefingStep;
};

private _slides = missionNamespace getVariable ["COMSPEC_BriefingSlides", []];
private _count = count _slides;
if (_count == 0) exitWith {};

private _index = missionNamespace getVariable ["COMSPEC_BriefingSlideIndex", 0];
_index = (_index + _delta) mod _count;
if (_index < 0) then { _index = _index + _count; };

[_index] call comspec_overwatch_connect_fnc_briefingBoardShow;
