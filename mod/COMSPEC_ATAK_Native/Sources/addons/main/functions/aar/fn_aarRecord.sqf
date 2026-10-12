/*
    Enregistrement local pour le rejeu de mission (app AAR), appelé toutes les 10 s par XEH_postInitClient.
    Le téléphone garde ce qu'il a vu : les positions de son camp (comme le suivi des forces amies)
    et sa propre trace, depuis le début de la mission. Réglage serveur comspec_atak_native_aar (vrai par défaut).
    État : missionNamespace COMSPEC_ATAK_Aar = [[temps mission, heure "HH:MM", [[x, y, cap, nom, groupe, moi, véhicule]...], n°, date réelle "JJ/MM/AAAA HH:MM"]...]
           (30 dernières minutes à 10 s, plus ancien à 30 s, 400 images au plus ; au-delà les plus anciennes partent).
*/
if (!hasInterface || {isNull player}) exitWith {};
if !(missionNamespace getVariable ["comspec_atak_native_aar", true]) exitWith {};
private _side = side group player;
private _units = (allUnits select { alive _x && {(side group _x) isEqualTo _side} }) select [0, 120];
if !(player in _units) then { _units pushBack player; };
// Noms et groupes partagés d'une image à l'autre (une seule copie de chaque texte au lieu d'une par unité et par image).
private _intern = missionNamespace getVariable ["COMSPEC_ATAK_AarStr", createHashMap];
if ((count _intern) > 3000) then { _intern = createHashMap; };
missionNamespace setVariable ["COMSPEC_ATAK_AarStr", _intern];
private _str = { params ["_t"]; private _k = _intern getOrDefault [_t, ""]; if (_k isEqualTo "") then { _intern set [_t, _t]; _k = _t; }; _k };
private _frame = _units apply {
    private _p = getPosASL _x;
    [round (_p select 0), round (_p select 1), round getDir (vehicle _x), [name _x] call _str, [[_x] call comspec_atak_native_fnc_unitGroup] call _str, _x isEqualTo player, (vehicle _x) isNotEqualTo _x]
};
private _log = missionNamespace getVariable ["COMSPEC_ATAK_Aar", []];
private _seq = (missionNamespace getVariable ["COMSPEC_ATAK_AarSeq", 0]) + 1;
missionNamespace setVariable ["COMSPEC_ATAK_AarSeq", _seq];
// Date réelle (horloge du PC) : le rejeu dit quel jour et à quelle heure la partie a eu lieu, pas seulement l'heure du jeu.
private _now = systemTime;
private _pad2 = { params ["_n"]; [str _n, "0" + str _n] select (_n < 10) };
private _real = format ["%1/%2/%3 %4:%5", [_now select 2] call _pad2, [_now select 1] call _pad2, _now select 0, [_now select 3] call _pad2, [_now select 4] call _pad2];
_log pushBack [time, [dayTime, "HH:MM"] call BIS_fnc_timeToString, _frame, _seq, _real];
// Mémoire bornée : les 30 dernières minutes gardent une image toutes les 10 s, au-delà une sur trois (30 s).
// Une mission de 2 h tient ainsi en environ 360 images au lieu de 720.
private _old = (count _log) - 181;
if (_old >= 0 && {((_log select _old) param [3, 0]) mod 3 != 0}) then { _log deleteAt _old; };
while {(count _log) > 400} do { _log deleteAt 0; };
missionNamespace setVariable ["COMSPEC_ATAK_Aar", _log];
