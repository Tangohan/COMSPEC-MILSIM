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

player createDiaryRecord [
    "COMSPEC_Athena",
    ["Guide configuration", _guide]
];

// Réalisme : journal seulement, pas de bandeau / chat.
if ((_map getOrDefault ["realism", "0"]) isEqualTo "1") exitWith {};

private _headline = "Guide Overwatch — journal de mission (COMSPEC Athena).";
if ((_map getOrDefault ["troll", "0"]) isEqualTo "1") then {
    _headline = "Mode troll actif — journal COMSPEC Athena.";
};

[_headline, "system", "info"] call comspec_overwatch_connect_fnc_announce;
