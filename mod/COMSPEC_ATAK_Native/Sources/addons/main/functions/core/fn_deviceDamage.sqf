/*
    Applique des dégâts au téléphone natif (état par appareil : missionNamespace COMSPEC_ATAK_Device, remis à neuf
    par la réparation, le changement d'appareil ou la réapparition).
    Params : [écran / boîtier 0-1 ajouté, raison, durée d'extinction (s, 0 = aucune), composants HashMap, lieu (texte)]
      composants : "scratch" (rayures), "pixels" (pixels morts puis lignes), "battery" (cellule abîmée : décharge plus
      rapide), "audio" (haut-parleur et micro), "gps" (position imprécise puis perdue), "antenna" (signal plus faible),
      "destroy" (true : appareil détruit). Valeurs 0-1 ajoutées à l'état existant.
    Appelé par fn_deviceImpact (balles, éclats, souffle, chute, accident, eau, selon l'endroit où le téléphone est porté)
    et par d'autres scripts avec les seuls trois premiers paramètres (comportement d'origine).
    Réalisme ATAK d'Overwatch (comspec_overwatch_atak_realism) : niveau 1 = le téléphone peut seulement s'éteindre,
    niveau 2 = écran et composants s'abîment mais l'appareil n'est jamais détruit, niveau 3 (ou 0) = tout.
    Overwatch ne casse plus le téléphone de son côté quand ce mod est chargé (son contrôle « blessure au torse »
    lui laisse la main) ; un appareil détruit ici est recopié dans son état (COMSPEC_AtakState) pour rester cohérent.
*/
params [["_amount", 0], ["_why", "Choc"], ["_off", 0], ["_parts", createHashMap], ["_where", ""]];
if !(missionNamespace getVariable ["comspec_atak_native_damage_sim", true]) exitWith { false };
if !([player] call comspec_atak_native_fnc_hasDevice) exitWith { false };
if !(_parts isEqualType createHashMap) then { _parts = createHashMap; };
private _n = missionNamespace getVariable ["COMSPEC_ATAK_Device", createHashMap];
private _before = _n getOrDefault ["damage", 0];
if (_before >= 1) exitWith { false };
private _lvl = if ([] call comspec_atak_native_fnc_bridge) then { missionNamespace getVariable ["comspec_overwatch_atak_realism", 0] } else { 0 };
if !(_lvl isEqualType 0) then { _lvl = 0; };
if (_lvl isEqualTo 1) then {
    if (_amount > 0 || {(count _parts) > 0}) then { _off = _off max 15; };
    _amount = 0;
    _parts = createHashMap;
};
private _destroy = _parts getOrDefault ["destroy", false];
if (_destroy) then { _amount = 1; };
private _dmg = (_before + _amount) min 1;
if (_lvl isEqualTo 2) then { _dmg = _dmg min 0.95; };
_n set ["damage", _dmg];
// Composants : textes de ce qui vient de lâcher.
private _what = [];
private _comp = {
    params ["_k", "_lbl"];
    private _add = _parts getOrDefault [_k, 0];
    if (_add <= 0) exitWith {};
    private _old = _n getOrDefault [_k, 0];
    private _new = (_old + _add) min 1;
    _n set [_k, _new];
    if (_new > _old) then { _what pushBack ([_new, _old] call _lbl); };
};
["scratch", { "rayures sur l'écran" }] call _comp;
["pixels", { params ["_v"]; ["pixels morts", "lignes sur l'écran"] select (_v >= 0.5) }] call _comp;
["battery", { "batterie endommagée (se vide plus vite)" }] call _comp;
["audio", { params ["_v"]; ["haut-parleur qui grésille", "haut-parleur et micro hors service"] select (_v >= 0.5) }] call _comp;
["gps", { params ["_v"]; ["GPS imprécis", "GPS hors service"] select (_v >= 1) }] call _comp;
["antenna", { "antenne endommagée (signal plus faible)" }] call _comp;
if (_off > 0 && {_dmg < 1}) then {
    if ((_n getOrDefault ["offUntil", -1]) <= time) then { _n set ["offFrom", time]; };
    _n set ["offUntil", (time + _off) max (_n getOrDefault ["offUntil", -1])];
    _n set ["offReason", _why];
};
if (_dmg >= 1) then { _n set ["brokenReason", format ["%1 : téléphone détruit", _why]]; };
private _crack = { switch (true) do { case (_this >= 0.75): { 3 }; case (_this >= 0.45): { 2 }; case (_this >= 0.2): { 1 }; default { 0 }; } };
private _c0 = _before call _crack;
private _c1 = _dmg call _crack;
if (_c1 > _c0 && {_dmg < 1}) then { _what = [["écran fêlé", "écran fêlé", "écran très abîmé", "écran en morceaux"] select _c1] + _what; };
if (_off > 0 && {_dmg < 1}) then { _what pushBack "il s'éteint"; };
// Journal de l'appareil (diagnostic de l'app Profil, lecture NFC par un équipier).
private _log = _n getOrDefault ["log", []];
_log pushBack [[dayTime, "HH:MM"] call BIS_fnc_timeToString, _why, [(_what joinString ", "), "détruit"] select (_dmg >= 1)];
while { (count _log) > 6 } do { _log deleteAt 0; };
_n set ["log", _log];
missionNamespace setVariable ["COMSPEC_ATAK_Device", _n];
private _at = ["", format [" (%1)", _where]] select (_where isNotEqualTo "");
switch (true) do {
    // Détruit : l'écran se brouille et clignote (fn_powerFx), puis reste éclaté et inutilisable (fn_deviceOverlay).
    case (_dmg >= 1): {
        ["WARNING", format ["%1%2 : téléphone détruit (kit de réparation ATAK ou nouvel appareil)", _why, _at], 8, 90] call comspec_atak_native_fnc_notify;
        ["broken"] call comspec_atak_native_fnc_powerFx;
        // Réalisme Overwatch : même état des deux côtés (sa propre logique ne casse plus l'appareil quand ce mod est chargé).
        private _ow = missionNamespace getVariable ["COMSPEC_AtakState", createHashMap];
        if (_lvl > 0 && {_ow isEqualType createHashMap} && {(count _ow) > 0}) then {
            _ow set ["device_destroyed", true]; _ow set ["screen_destroyed", true];
            missionNamespace setVariable ["COMSPEC_AtakState", _ow, false];
        };
    };
    case (_what isNotEqualTo []): { ["WARNING", format ["%1%2 : %3", _why, _at, _what joinString ", "], 5, 60] call comspec_atak_native_fnc_notify; };
};
playSound "ClickSoft";
uiNamespace setVariable ["COMSPEC_ATAK_LinkQ", []];
uiNamespace setVariable ["COMSPEC_ATAK_EwFx", []];
[] call comspec_atak_native_fnc_deviceOverlay;
true
