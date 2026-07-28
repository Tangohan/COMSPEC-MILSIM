/*

    Affiche la diapositive à l'index donné dans l’UI Briefing ATAK (prioritaire)

    ou le dialog 9970 (repli).



    Params: [_index]

*/

params [["_index", 0, [0]]];



// Deck Google actif : ne pas écraser avec les images Athena

if (missionNamespace getVariable ["COMSPEC_GoogleBriefingActive", false]) exitWith {

    private _path = missionNamespace getVariable ["COMSPEC_GoogleBriefingPath", ""];

    private _gIndex = missionNamespace getVariable ["COMSPEC_GoogleBriefingIndex", 0];

    private _total = missionNamespace getVariable ["COMSPEC_GoogleBriefingTotal", 1];

    if (_path isNotEqualTo "") then {

        [

            _path,

            format ["Google Slides — diapositive %1", _gIndex + 1],

            _gIndex,

            _total

        ] call comspec_overwatch_connect_fnc_applyGoogleBriefingSlide;

    };

};



private _slides = missionNamespace getVariable ["COMSPEC_BriefingSlides", []];

if (count _slides == 0) exitWith {};

_index = (_index max 0) min (count _slides - 1);

missionNamespace setVariable ["COMSPEC_BriefingSlideIndex", _index];



private _atakOpen = !isNull (uiNamespace getVariable ["COMSPEC_ATAK_Briefing_group", controlNull]);

private _dialogOpen = !isNull (findDisplay 9970);

if (!_atakOpen && {!_dialogOpen}) exitWith {};



private _slide = _slides select _index;

private _title = _slide select 1;



// Téléchargement bloquant (cache extension)

private _path = [_slide] call comspec_overwatch_connect_fnc_downloadBriefingSlide;



if (_path != "") then {

    [_path, _title, _index, count _slides] call comspec_overwatch_connect_fnc_applyGoogleBriefingSlide;

} else {

    ["COMSPEC_Warning", ["Impossible de charger cette diapositive (réseau ou cache indisponible)."]] call comspec_overwatch_connect_fnc_showNotification;

};

