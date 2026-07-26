/*
    Ouvre le tableau de briefing (dialog 9970).
    Charge les diapositives Athena si besoin. Si un deck Google est actif, affiche celui-ci.
    Sur ATAK : createDisplay sous le téléphone quand possible.
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

private _ensureDialog = {
    if (!isNull (findDisplay 9970)) exitWith { true };
    private _parent = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
    private _ok = false;
    if (!isNull _parent) then {
        private _disp = _parent createDisplay "COMSPEC_Briefing_Dialog";
        _ok = !isNull _disp;
    };
    if (!_ok) then {
        _ok = createDialog "COMSPEC_Briefing_Dialog";
    };
    _ok
};

if (missionNamespace getVariable ["COMSPEC_GoogleBriefingActive", false]) then {
    if !(call _ensureDialog) exitWith {
        ["Impossible d’ouvrir le briefing.", "system", "warn"] call comspec_overwatch_connect_fnc_announce;
    };
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
    if (count _slides == 0) then {
        _slides = [] call comspec_overwatch_connect_fnc_getBriefingSlides;
    };
    if (count _slides == 0) exitWith {
        // Repli : brief Google communauté si publié
        private _url = missionNamespace getVariable ["COMSPEC_CommunityGoogleSlidesUrl", ""];
        if (_url isEqualTo "") then {
            [] call comspec_overwatch_connect_fnc_getBriefingSlides;
            _url = missionNamespace getVariable ["COMSPEC_CommunityGoogleSlidesUrl", ""];
        };
        if (_url isNotEqualTo "") exitWith {
            [_url, 0, true] call comspec_overwatch_connect_fnc_loadGoogleBriefing;
        };
        ["COMSPEC_Warning", ["Aucune diapositive de briefing disponible."]] call comspec_overwatch_connect_fnc_showNotification;
    };
    if !(call _ensureDialog) exitWith {
        ["Impossible d’ouvrir le briefing.", "system", "warn"] call comspec_overwatch_connect_fnc_announce;
    };
    [0] call comspec_overwatch_connect_fnc_briefingBoardShow;
};
