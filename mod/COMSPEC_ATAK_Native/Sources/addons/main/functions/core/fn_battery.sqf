/*
    Batterie simulée du téléphone (réglage COMSPEC_ATAK_BatterySim, actif par défaut) :
    -1 % toutes les 3 minutes téléphone allumé, recharge à bord d'un véhicule (+1 % / 10 s).
    Retourne le niveau 0–100.
*/
private _level = missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100];
if !(profileNamespace getVariable ["COMSPEC_ATAK_BatterySim", true]) exitWith { 100 };
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _now = diag_tickTime;
private _dt = _now - (_s getOrDefault ["batteryTick", _now]);
_s set ["batteryTick", _now];
if (_dt > 5) then { _dt = 1; };
_level = if ((vehicle player) isNotEqualTo player) then { _level + _dt / 10 } else { _level - _dt / 180 };
_level = 0 max (_level min 100);
missionNamespace setVariable ["COMSPEC_ATAK_Battery", _level];
_level
