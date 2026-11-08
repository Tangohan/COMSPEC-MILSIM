/*
    Module Eden « ATAK · Maillage » (lot 12) : réglages du maillage pour la mission. Lu une fois par le serveur au démarrage.
    Attributs, textes séparés par des points-virgules (une entrée fausse est ignorée et notée dans le RPT) :
      Keys   : "K-ALPHA:WEST;K-BRAVO:WEST"          clés de groupe (nom:camp), avec le réglage mesh_keys
      Groups : "1:CMD:K-ALPHA;2:ALPHA:"             groupes (numéro:nom:clé, clé vide : en clair)
      Freq   : "43.5:2;44:3"                        table fréquence vers groupe (décision 7), sinon groupe déduit
      Backup : numéro du canal de secours, 0 : réglage du serveur
      Profile : -1 réglage du serveur, 3 Milsim, 2 Standard, 1 Arcade
*/
params [["_logic", objNull, [objNull]], ["_units", [], [[]]], ["_activated", true, [true]]];
if (!isServer || {!_activated} || {isNull _logic} || {is3DEN}) exitWith { true };
private _entries = {
    params ["_text"];
    if !(_text isEqualType "") exitWith { [] };
    (_text splitString ";") apply { _x splitString ":" }
};
private _warn = { params ["_what", "_row"]; diag_log format ["[COMSPEC ATAK NATIVE][WARN][MESH] Module : %1 ignoré : %2", _what, _row]; };
{
    _x params [["_name", ""], ["_side", ""]];
    if (_name isEqualTo "" || {!(_side in ["WEST", "EAST", "GUER", "CIV"])}) then { ["clé", _x] call _warn; continue };
    ["define", [_name, _side, false]] call comspec_atak_native_fnc_meshKeys;
} forEach ([_logic getVariable ["Keys", ""]] call _entries);
private _groups = [];
{
    private _n = parseNumber (_x param [0, "0"]);
    if (_n < 1 || {_n > 16}) then { ["groupe", _x] call _warn; continue };
    _groups pushBack [_n, _x param [1, ""], _x param [2, ""]];
} forEach ([_logic getVariable ["Groups", ""]] call _entries);
if (_groups isNotEqualTo []) then { missionNamespace setVariable ["COMSPEC_ATAK_MeshGroupTable", _groups, true]; };
private _freq = [];
{
    private _f = parseNumber (_x param [0, "0"]);
    private _n = parseNumber (_x param [1, "0"]);
    if (_f <= 0 || {_n < 1} || {_n > 16}) then { ["fréquence", _x] call _warn; continue };
    _freq pushBack [_f, _n];
} forEach ([_logic getVariable ["Freq", ""]] call _entries);
if (_freq isNotEqualTo []) then { missionNamespace setVariable ["COMSPEC_ATAK_MeshFreqTable", _freq, true]; };
private _bk = _logic getVariable ["Backup", 0];
if (_bk isEqualType 0 && {_bk >= 1} && {_bk <= 16}) then { missionNamespace setVariable ["comspec_atak_native_mesh_backup_channel", _bk, true]; };
private _pr = _logic getVariable ["Profile", -1];
if (_pr in [1, 2, 3]) then {
    missionNamespace setVariable ["comspec_atak_native_realism_profile", _pr, true];
    ["refresh"] remoteExecCall ["comspec_atak_native_fnc_netCond", 0];
};
["log", ["mission", format ["Module : %1 groupe(s), %2 fréquence(s)", count _groups, count _freq]]] call comspec_atak_native_fnc_meshGraph;
true
