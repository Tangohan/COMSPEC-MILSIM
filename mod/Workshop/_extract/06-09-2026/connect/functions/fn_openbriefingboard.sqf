/*

    Ouvre le briefing dans l’ATAK (app native). Repli dialog uniquement sans téléphone ATAK.

*/

if (!hasInterface) exitWith {};

if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};



// Prefer UI ATAK Enhanced

if (!isNil "comspec_overwatch_atak_athena_fnc_athena_openBriefing"

    && { isClass (configFile >> "CfgPatches" >> "comspec_overwatch_atak_athena") }

) exitWith {

    // Si le téléphone est déjà là ou qu’un terminal est porté → app ATAK

    private _hasAtakUi = !isNull (uiNamespace getVariable ["cTab_Android_dlg", displayNull]);

    private _hasTerm = [player] call comspec_overwatch_connect_fnc_hasTerminal;

    if (_hasAtakUi || {_hasTerm}) exitWith {

        [] call comspec_overwatch_atak_athena_fnc_athena_openBriefing;

    };

    // Sans terminal : message clair plutôt que l’overlay moche

    ["COMSPEC_Warning", ["Ouvrez votre téléphone ATAK pour consulter le briefing."]] call comspec_overwatch_connect_fnc_showNotification;

};



// Repli ultime (pack sans athena) — dialog legacy

private _ensureDialog = {

    if (!isNull (findDisplay 9970)) exitWith { true };

    createDialog "COMSPEC_Briefing_Dialog"

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

