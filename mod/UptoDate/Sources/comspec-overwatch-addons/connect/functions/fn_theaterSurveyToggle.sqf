/*
    Bouton unique : lancer si inactif, interrompre si un relevé tourne.
*/
if (
    (missionNamespace getVariable ["COMSPEC_TheaterSampling", false])
    || {missionNamespace getVariable ["COMSPEC_GeoSampling", false]}
) then {
    [] call comspec_overwatch_connect_fnc_theaterSurveyCancel;
} else {
    [] call comspec_overwatch_connect_fnc_sampleTheater;
};
