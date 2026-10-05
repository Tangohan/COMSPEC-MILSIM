/*
    État matériel du téléphone. Renvoie un HashMap :
      state  : "OK" | "CRACKED" (écran fêlé, utilisable) | "OFF" (éteint, redémarre seul) | "BROKEN" (à remplacer)
      damage : 0-1, crack : 0-3 (fêlures affichées), offLeft : secondes avant redémarrage, reason : texte,
      offUntil / offFrom : heures (time) de fin et de début de l'extinction (-1 si inconnues).
    Avec le réalisme ATAK d'Overwatch actif, son état (COMSPEC_AtakState) prime ; sinon modèle natif
    (COMSPEC_ATAK_Device, alimenté par fn_deviceDamage), réglage serveur comspec_atak_native_damage_sim.
*/
private _out = createHashMapFromArray [["state", "OK"], ["damage", 0], ["crack", 0], ["offLeft", 0], ["reason", ""], ["offUntil", -1], ["offFrom", -1]];
// Équipage d'aéronef : l'ATAK tourne sur la tablette de bord, l'état du téléphone porté ne compte pas.
if ([player] call comspec_atak_native_fnc_aircrewTerminal) exitWith { _out };
private _ow = missionNamespace getVariable ["COMSPEC_AtakState", createHashMap];
if ((missionNamespace getVariable ["comspec_overwatch_atak_realism", 0]) > 0 && {_ow isEqualType createHashMap} && {(count _ow) > 0}) then {
    if (_ow getOrDefault ["screen_destroyed", false]) then { _out set ["state", "CRACKED"]; _out set ["crack", 3]; _out set ["damage", 0.85]; _out set ["reason", "Écran endommagé"]; };
    if (!(_ow getOrDefault ["powered_on", true]) || {_ow getOrDefault ["device_crashed", false]}) then {
        _out set ["state", "OFF"]; _out set ["reason", "Éteint par un choc"];
        private _until = _ow getOrDefault ["crash_until", -1];
        _out set ["offLeft", [0, round (_until - time)] select (_until > time)];
        _out set ["offUntil", _until];
    };
    if (_ow getOrDefault ["device_destroyed", false]) then { _out set ["state", "BROKEN"]; _out set ["damage", 1]; _out set ["crack", 3]; _out set ["reason", "Appareil détruit"]; };
};
private _batteryEmpty = {
    if ((_out get "state") isNotEqualTo "BROKEN" && {profileNamespace getVariable ["COMSPEC_ATAK_BatterySim", true]} && {missionNamespace getVariable ["comspec_atak_native_battery_sim", true]} && {(missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100]) <= 0}) then {
        _out set ["state", "OFF"]; _out set ["offLeft", 0]; _out set ["reason", "Batterie vide"];
    };
    _out
};
if ((_out get "state") isNotEqualTo "OK" || {!(missionNamespace getVariable ["comspec_atak_native_damage_sim", true])}) exitWith { call _batteryEmpty };
private _n = missionNamespace getVariable ["COMSPEC_ATAK_Device", createHashMap];
private _dmg = _n getOrDefault ["damage", 0];
_out set ["damage", _dmg];
_out set ["crack", switch (true) do { case (_dmg >= 0.75): { 3 }; case (_dmg >= 0.45): { 2 }; case (_dmg >= 0.2): { 1 }; default { 0 }; }];
if (_dmg >= 0.2) then { _out set ["state", "CRACKED"]; _out set ["reason", ["Écran fêlé", "Écran très abîmé"] select (_dmg >= 0.6)]; };
private _offUntil = _n getOrDefault ["offUntil", -1];
if (_offUntil > time) then {
    _out set ["state", "OFF"]; _out set ["offLeft", round (_offUntil - time)]; _out set ["reason", _n getOrDefault ["offReason", "Redémarrage"]];
    _out set ["offUntil", _offUntil]; _out set ["offFrom", _n getOrDefault ["offFrom", -1]];
};
if (_dmg >= 1) then { _out set ["state", "BROKEN"]; _out set ["reason", _n getOrDefault ["brokenReason", "Téléphone détruit"]]; };
// Batterie vide : éteint jusqu'à recharge ou changement de batterie.
call _batteryEmpty
