/*
    Ouverture de l’app Briefing ATAK (pattern ATAK_APPs Opened).
*/
params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];

if (isNull _group) exitWith {};

uiNamespace setVariable ["COMSPEC_ATAK_Briefing_group", _group];
["briefing"] call comspec_overwatch_atak_athena_fnc_athena_hideForeignPages;

// Fermer l’ancien dialog plein écran s’il est encore ouvert
private _legacy = findDisplay 9970;
if (!isNull _legacy) then { _legacy closeDisplay 1; };

[] spawn {
    uiSleep 0.05;
    if (isNull (uiNamespace getVariable ["COMSPEC_ATAK_Briefing_group", controlNull])) exitWith {};

    if (missionNamespace getVariable ["COMSPEC_GoogleBriefingActive", false]) then {
        private _path = missionNamespace getVariable ["COMSPEC_GoogleBriefingPath", ""];
        private _index = missionNamespace getVariable ["COMSPEC_GoogleBriefingIndex", 0];
        private _total = missionNamespace getVariable ["COMSPEC_GoogleBriefingTotal", 1];
        if (_path isNotEqualTo "") then {
            [
                _path,
                format ["Briefing — diapositive %1", _index + 1],
                _index,
                _total
            ] call comspec_overwatch_connect_fnc_applyGoogleBriefingSlide;
        };
    } else {
        private _slides = missionNamespace getVariable ["COMSPEC_BriefingSlides", []];
        if ((count _slides) < 1) then {
            _slides = [] call comspec_overwatch_connect_fnc_getBriefingSlides;
        };
        if ((count _slides) < 1) then {
            private _url = missionNamespace getVariable ["COMSPEC_CommunityGoogleSlidesUrl", ""];
            if (_url isEqualTo "") then {
                [] call comspec_overwatch_connect_fnc_getBriefingSlides;
                _url = missionNamespace getVariable ["COMSPEC_CommunityGoogleSlidesUrl", ""];
            };
            if (_url isNotEqualTo "") then {
                [_url, 0, true] call comspec_overwatch_connect_fnc_loadGoogleBriefing;
            } else {
                private _group = uiNamespace getVariable ["COMSPEC_ATAK_Briefing_group", controlNull];
                if (!isNull _group) then {
                    private _cap = _group controlsGroupCtrl 9853;
                    if (!isNull _cap) then {
                        _cap ctrlSetStructuredText parseText "<t align='center'>Aucune diapositive disponible.</t>";
                    };
                    private _idx = _group controlsGroupCtrl 9851;
                    if (!isNull _idx) then {
                        _idx ctrlSetStructuredText parseText "<t align='center'>— / —</t>";
                    };
                };
            };
        } else {
            private _index = missionNamespace getVariable ["COMSPEC_BriefingSlideIndex", 0];
            [_index] call comspec_overwatch_connect_fnc_briefingBoardShow;
        };
    };
};
