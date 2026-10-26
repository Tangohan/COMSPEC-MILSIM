/*
    Affiche le guide d’expérience communauté (journal + notification une fois par session).
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
if (missionNamespace getVariable ["COMSPEC_ExperienceGuideShown", false]) exitWith {};
if (isNull player) exitWith {};

private _map = missionNamespace getVariable ["COMSPEC_TenantExperience", createHashMap];
if (!(_map isEqualType createHashMap)) exitWith {};

private _guide = _map getOrDefault ["guide", ""];
if (_guide isEqualTo "") exitWith {};

missionNamespace setVariable ["COMSPEC_ExperienceGuideShown", true, false];

if !(player diarySubjectExists "COMSPEC_Athena") then {
    player createDiarySubject ["COMSPEC_Athena", "COMSPEC Athena"];
};

// Journal = texte structuré : échapper & < > (texte du portail, sinon <execute expression=...> interprété)
// et rendre les sauts de ligne (LF de fn_pollExperience) en <br/>.
private _guideLines = [_guide, toString [10]] call comspec_overwatch_connect_fnc_splitKeepEmpty;
private _guideHtml = (_guideLines apply {
    private _out = [];
    {
        switch (_x) do {
            case 38: { _out append (toArray "&amp;") };
            case 60: { _out append (toArray "&lt;") };
            case 62: { _out append (toArray "&gt;") };
            case 13: {};
            default { _out pushBack _x };
        };
    } forEach toArray _x;
    toString _out
}) joinString "<br/>";

player createDiaryRecord [
    "COMSPEC_Athena",
    ["Guide configuration", _guideHtml]
];

// Réalisme : journal seulement, pas de bandeau / chat.
if ((_map getOrDefault ["realism", "0"]) isEqualTo "1") exitWith {};

private _headline = "Guide Overwatch — journal de mission (COMSPEC Athena).";
if ((_map getOrDefault ["troll", "0"]) isEqualTo "1") then {
    _headline = "Mode troll actif — journal COMSPEC Athena.";
};

[_headline, "system", "info"] call comspec_overwatch_connect_fnc_announce;
