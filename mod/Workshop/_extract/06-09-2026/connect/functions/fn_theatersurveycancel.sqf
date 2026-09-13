/*
    Interrompt le relevé du théâtre. Le drapeau occupé est relâché par le spawn en cours.
*/
if (!(missionNamespace getVariable ["COMSPEC_TheaterSampling", false])) exitWith {};
missionNamespace setVariable ["COMSPEC_TheaterAbort", true, false];
missionNamespace setVariable ["COMSPEC_TerrainAbort", true, false];
missionNamespace setVariable ["COMSPEC_TheaterPhase", "abort", false];
missionNamespace setVariable ["COMSPEC_TheaterCurrent", "Interruption demandée…", false];
["Relevé de la carte interrompu.", "system", "info"] call comspec_overwatch_connect_fnc_announce;
[] call comspec_overwatch_connect_fnc_theaterSurveyRefresh;
