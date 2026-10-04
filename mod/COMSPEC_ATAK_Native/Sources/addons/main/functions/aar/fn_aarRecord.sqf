/*
    Enregistrement local pour le rejeu de mission (app AAR), appelé toutes les 10 s par XEH_postInitClient.
    Le téléphone garde ce qu'il a vu : les positions de son camp (comme le suivi des forces amies)
    et sa propre trace, depuis le début de la mission. Réglage serveur comspec_atak_native_aar (vrai par défaut).
    État : missionNamespace COMSPEC_ATAK_Aar = [[temps mission, heure "HH:MM", [[x, y, cap, nom, groupe, moi, véhicule]...]]...]
           (720 images au plus, soit 2 h ; au-delà les plus anciennes partent).
*/
if (!hasInterface || {isNull player}) exitWith {};
if !(missionNamespace getVariable ["comspec_atak_native_aar", true]) exitWith {};
private _side = side group player;
private _units = (allUnits select { alive _x && {(side group _x) isEqualTo _side} }) select [0, 120];
if !(player in _units) then { _units pushBack player; };
private _frame = _units apply {
    private _p = getPosASL _x;
    [round (_p select 0), round (_p select 1), round getDir (vehicle _x), name _x, [_x] call comspec_atak_native_fnc_unitGroup, _x isEqualTo player, (vehicle _x) isNotEqualTo _x]
};
private _log = missionNamespace getVariable ["COMSPEC_ATAK_Aar", []];
_log pushBack [time, [dayTime, "HH:MM"] call BIS_fnc_timeToString, _frame];
while {(count _log) > 720} do { _log deleteAt 0; };
missionNamespace setVariable ["COMSPEC_ATAK_Aar", _log];
