/* Journal natif : RPT Arma + tampon mémoire (300 lignes) lu par l'app Debug. */
params [["_level","INFO"], ["_module","CORE"], ["_message",""]];
diag_log format ["[COMSPEC ATAK NATIVE][%1][%2] %3", toUpper _level, toUpper _module, _message];
private _buf = missionNamespace getVariable ["COMSPEC_ATAK_Log", []];
_buf pushBack [diag_tickTime, toUpper _level, toUpper _module, _message];
if ((count _buf) > 300) then { _buf deleteAt 0; };
missionNamespace setVariable ["COMSPEC_ATAK_Log", _buf];
