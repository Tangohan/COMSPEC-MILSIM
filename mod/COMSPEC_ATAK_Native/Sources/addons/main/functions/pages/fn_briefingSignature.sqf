/*
    État courant du briefing, sans téléchargement : [source, index, total, chemin, titres].
    source : "GOOGLE" (deck Google Slides synchronisé par Overwatch), "ATHENA" (diapositives publiées
    sur la plateforme) ou "LOCAL" (DLL native seule). Sert à la page Briefing et à son rafraîchissement.
*/
if ([] call comspec_atak_native_fnc_bridge) exitWith {
    if (missionNamespace getVariable ["COMSPEC_GoogleBriefingActive", false]) exitWith {
        private _total = missionNamespace getVariable ["COMSPEC_GoogleBriefingTotal", 0];
        private _titles = [];
        for "_i" from 1 to _total do { _titles pushBack format ["Diapositive %1", _i]; };
        ["GOOGLE", missionNamespace getVariable ["COMSPEC_GoogleBriefingIndex", 0], _total, missionNamespace getVariable ["COMSPEC_GoogleBriefingPath", ""], _titles]
    };
    private _slides = missionNamespace getVariable ["COMSPEC_BriefingSlides", []];
    private _idx = ((missionNamespace getVariable ["COMSPEC_BriefingSlideIndex", 0]) max 0) min (((count _slides) - 1) max 0);
    private _key = if ((count _slides) > 0) then { str ((_slides select _idx) select 0) } else { "" };
    ["ATHENA", _idx, count _slides, _key, _slides apply { _x select 1 }]
};
private _b = (uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["briefing", createHashMap];
private _total = _b getOrDefault ["total", 0];
private _titles = [];
for "_i" from 1 to _total do { _titles pushBack format ["Diapositive %1", _i]; };
["LOCAL", _b getOrDefault ["index", 0], _total, _b getOrDefault ["path", ""], _titles]
