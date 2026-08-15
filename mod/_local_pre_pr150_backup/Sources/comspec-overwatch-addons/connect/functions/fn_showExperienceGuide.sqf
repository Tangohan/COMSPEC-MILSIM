/*
    Affiche le guide d’expérience communauté (journal + notification une fois par session).
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
if (missionNamespace getVariable ["COMSPEC_ExperienceGuideShown", false]) exitWith {};

private _map = missionNamespace getVariable ["COMSPEC_TenantExperience", createHashMap];
private _guide = _map getOrDefault ["guide", ""];
if (_guide isEqualTo "") exitWith {};

missionNamespace setVariable ["COMSPEC_ExperienceGuideShown", true, false];

player createDiaryRecord [
    "COMSPEC_Athena",
    "Guide configuration",
    _guide
];

private _headline = "Guide Overwatch — voir journal (COMSPEC Athena)";
if ((_map getOrDefault ["realism", "0"]) isEqualTo "1") then {
    _headline = "Mode réalisme actif pour votre communauté — consultez le journal COMSPEC Athena.";
};
if ((_map getOrDefault ["troll", "0"]) isEqualTo "1") then {
    _headline = "Mode troll actif — alertes de suivi renforcées. Journal COMSPEC Athena.";
};

[_headline, "system", "info"] call comspec_overwatch_connect_fnc_announce;
