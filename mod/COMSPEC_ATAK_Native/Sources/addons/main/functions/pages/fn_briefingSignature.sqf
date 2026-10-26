/*
    État courant du briefing, sans téléchargement : [source, index, total, chemin, titres].
    source : "GOOGLE" (deck Google Slides synchronisé par Overwatch), "ATHENA" (diapositives publiées
    sur la plateforme), "LOCAL" (DLL native seule) ou "FENRIR" (présentation du mod Live Presentation choisie
    dans l'onglet ÉCRANS, fn_fenrir ; chemin = hash de l'image, suivi de « ? » tant qu'elle n'est pas arrivée).
    Sert à la page Briefing et à son rafraîchissement.
*/
private _fid = ["viewed"] call comspec_atak_native_fnc_fenrir;
if (_fid isNotEqualTo "") exitWith {
    private _st = ["state", _fid] call comspec_atak_native_fnc_fenrir;
    private _slides = _st select 2;
    private _idx = ((_st select 3) max 0) min (((count _slides) - 1) max 0);
    private _hash = if ((count _slides) > 0) then { (_slides select _idx) select 0 } else { "" };
    if (_hash isNotEqualTo "" && {(LPRES_cache getOrDefault [_hash, ""]) isEqualTo ""}) then { _hash = _hash + "?"; };
    ["FENRIR", _idx, count _slides, _hash, _slides apply { _x param [1, ""] }]
};
if ([] call comspec_atak_native_fnc_bridge) exitWith {
    // Source « Athena » choisie dans l'engrenage (fn_briefingSettings) : le deck Google partagé est ignoré.
    if ((missionNamespace getVariable ["COMSPEC_GoogleBriefingActive", false]) && {(profileNamespace getVariable ["COMSPEC_ATAK_BriefSource", "AUTO"]) isNotEqualTo "ATHENA"}) exitWith {
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
