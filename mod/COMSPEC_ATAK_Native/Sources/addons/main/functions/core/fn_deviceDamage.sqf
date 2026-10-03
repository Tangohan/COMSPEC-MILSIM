/*
    Dégâts du téléphone natif. Params : [quantité 0-1, raison, durée d'extinction (s, 0 = aucune)]
    Appelé par les événements du joueur (balles au torse ou aux bras, explosions proches, chute, eau).
*/
params [["_amount", 0], ["_why", "Choc"], ["_off", 0]];
if !(missionNamespace getVariable ["comspec_atak_native_damage_sim", true]) exitWith { false };
if !([player] call comspec_atak_native_fnc_hasDevice) exitWith { false };
// Réalisme ATAK d'Overwatch actif : c'est lui qui gère la casse.
if ((missionNamespace getVariable ["comspec_overwatch_atak_realism", 0]) > 0) exitWith { false };
private _n = missionNamespace getVariable ["COMSPEC_ATAK_Device", createHashMap];
private _before = _n getOrDefault ["damage", 0];
if (_before >= 1) exitWith { false };
private _dmg = (_before + _amount) min 1;
_n set ["damage", _dmg];
if (_off > 0 && {_dmg < 1}) then {
    _n set ["offUntil", (time + _off) max (_n getOrDefault ["offUntil", -1])];
    _n set ["offReason", _why];
};
if (_dmg >= 1) then { _n set ["brokenReason", format ["%1 : téléphone détruit", _why]]; };
missionNamespace setVariable ["COMSPEC_ATAK_Device", _n];
private _crack = { switch (true) do { case (_this >= 0.75): { 3 }; case (_this >= 0.45): { 2 }; case (_this >= 0.2): { 1 }; default { 0 }; } };
switch (true) do {
    case (_dmg >= 1): { ["WARNING", format ["%1 : téléphone détruit, il faut le remplacer", _why], 8, 90] call comspec_atak_native_fnc_notify; [] call comspec_atak_native_fnc_close; };
    case ((_dmg call _crack) > (_before call _crack)): { ["WARNING", format ["%1 : écran %2", _why, ["fêlé", "fêlé", "très abîmé", "en morceaux"] select (_dmg call _crack)], 5, 60] call comspec_atak_native_fnc_notify; };
    case (_off > 0): { ["WARNING", format ["%1 : le téléphone s'est éteint", _why], 4, 50] call comspec_atak_native_fnc_notify; };
};
playSound "ClickSoft";
uiNamespace setVariable ["COMSPEC_ATAK_LinkQ", []];
[] call comspec_atak_native_fnc_deviceOverlay;
true
