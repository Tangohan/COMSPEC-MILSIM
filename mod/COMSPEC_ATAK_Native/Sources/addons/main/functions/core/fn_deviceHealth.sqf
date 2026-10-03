/*
    État matériel du téléphone. Renvoie un HashMap :
      state  : "OK" | "CRACKED" (écran fêlé, utilisable) | "OFF" (éteint, redémarre seul) | "BROKEN" (à remplacer)
      damage : 0-1, crack : 0-3 (fêlures affichées), offLeft : secondes avant redémarrage, reason : texte.
    Avec le réalisme ATAK d'Overwatch actif, son état (COMSPEC_AtakState) prime ; sinon modèle natif
    (COMSPEC_ATAK_Device, alimenté par fn_deviceDamage), réglage serveur comspec_atak_native_damage_sim.
*/
private _out = createHashMapFromArray [["state", "OK"], ["damage", 0], ["crack", 0], ["offLeft", 0], ["reason", ""]];
private _ow = missionNamespace getVariable ["COMSPEC_AtakState", createHashMap];
if ((missionNamespace getVariable ["comspec_overwatch_atak_realism", 0]) > 0 && {_ow isEqualType createHashMap} && {(count _ow) > 0}) then {
    if (_ow getOrDefault ["screen_destroyed", false]) then { _out set ["state", "CRACKED"]; _out set ["crack", 3]; _out set ["damage", 0.85]; _out set ["reason", "Écran endommagé"]; };
    if (!(_ow getOrDefault ["powered_on", true]) || {_ow getOrDefault ["device_crashed", false]}) then {
        _out set ["state", "OFF"]; _out set ["reason", "Éteint par un choc"];
        private _until = _ow getOrDefault ["crash_until", -1];
        _out set ["offLeft", [0, round (_until - time)] select (_until > time)];
    };
    if (_ow getOrDefault ["device_destroyed", false]) then { _out set ["state", "BROKEN"]; _out set ["damage", 1]; _out set ["crack", 3]; _out set ["reason", "Appareil détruit"]; };
};
if ((_out get "state") isNotEqualTo "OK" || {!(missionNamespace getVariable ["comspec_atak_native_damage_sim", true])}) exitWith { _out };
private _n = missionNamespace getVariable ["COMSPEC_ATAK_Device", createHashMap];
private _dmg = _n getOrDefault ["damage", 0];
_out set ["damage", _dmg];
_out set ["crack", switch (true) do { case (_dmg >= 0.75): { 3 }; case (_dmg >= 0.45): { 2 }; case (_dmg >= 0.2): { 1 }; default { 0 }; }];
if (_dmg >= 0.2) then { _out set ["state", "CRACKED"]; _out set ["reason", ["Écran fêlé", "Écran très abîmé"] select (_dmg >= 0.6)]; };
private _offUntil = _n getOrDefault ["offUntil", -1];
if (_offUntil > time) then { _out set ["state", "OFF"]; _out set ["offLeft", round (_offUntil - time)]; _out set ["reason", _n getOrDefault ["offReason", "Redémarrage"]]; };
if (_dmg >= 1) then { _out set ["state", "BROKEN"]; _out set ["reason", _n getOrDefault ["brokenReason", "Téléphone détruit"]]; };
_out
