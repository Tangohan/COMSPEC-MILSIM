/*
    Ouvre le tableau de briefing plein écran (dialog 9970).
    Charge les diapositives Athena si besoin. Si un deck Google est actif, affiche celui-ci.
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

if (missionNamespace getVariable ["COMSPEC_GoogleBriefingActive", false]) then {
    if (isNull (findDisplay 9970)) then {
        createDialog "COMSPEC_Briefing_Dialog";
    };
    private _path = missionNamespace getVariable ["COMSPEC_GoogleBriefingPath", ""];
    private _index = missionNamespace getVariable ["COMSPEC_GoogleBriefingIndex", 0];
    private _total = missionNamespace getVariable ["COMSPEC_GoogleBriefingTotal", 1];
    if (_path isNotEqualTo "") then {
        [
            _path,
            format ["Google Slides — diapositive %1", _index + 1],
            _index,
            _total
        ] call comspec_overwatch_connect_fnc_applyGoogleBriefingSlide;
    };
} else {
    private _slides = missionNamespace getVariable ["COMSPEC_BriefingSlides", []];
    if (count _slides == 0) then {
        _slides = [] call comspec_overwatch_connect_fnc_getBriefingSlides;
    };
    if (count _slides == 0) exitWith {
        ["COMSPEC_Warning", ["Aucune diapositive de briefing disponible."]] call comspec_overwatch_connect_fnc_showNotification;
    };
    if (isNull (findDisplay 9970)) then {
        createDialog "COMSPEC_Briefing_Dialog";
    } else {
        [0] call comspec_overwatch_connect_fnc_briefingBoardShow;
    };
};
