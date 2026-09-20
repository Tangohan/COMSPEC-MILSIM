/*
    Tuile P2P : ne pas initialiser un second écran Messagerie.
    IceMan n’alimente que la page native « message ».
*/
params ["_group"];

if (!isNull _group) then {
    _group ctrlShow false;
    _group ctrlEnable false;
};

if (missionNamespace getVariable ["COMSPEC_ATAK_P2P_opening", false]) exitWith {};
[] call comspec_overwatch_atak_athena_fnc_athena_messageHubOpenP2P;
