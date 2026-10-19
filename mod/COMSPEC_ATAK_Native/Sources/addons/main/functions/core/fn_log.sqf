/* Journal natif : RPT Arma + tampon mémoire (300 lignes) lu par l'app Debug.
   Caméras, drone, performances et diapositives (modules CAMERA, PHOTO, DRONE, PERF, BRIEFING) : recopiés aussi dans le journal de session
   COMSPEC (fichier .log de COMSPEC Overwatch, celui que les joueurs envoient), sinon absents des retours. */
params [["_level","INFO"], ["_module","CORE"], ["_message",""]];
diag_log format ["[COMSPEC ATAK NATIVE][%1][%2] %3", toUpper _level, toUpper _module, _message];
private _buf = missionNamespace getVariable ["COMSPEC_ATAK_Log", []];
_buf pushBack [diag_tickTime, toUpper _level, toUpper _module, _message];
if ((count _buf) > 300) then { _buf deleteAt 0; };
missionNamespace setVariable ["COMSPEC_ATAK_Log", _buf];
if ((toUpper _module) in ["CAMERA", "PHOTO", "DRONE", "PERF", "BRIEFING"] && {!isNil "comspec_overwatch_connect_fnc_log"}) then {
    [toUpper _level, "ATAK " + (toUpper _module), _message] call comspec_overwatch_connect_fnc_log;
};
