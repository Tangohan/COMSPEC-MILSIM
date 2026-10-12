/*
    Module Zeus « Tinder : profil HVT / SSE » : posé sur une IA, elle apparaît de temps en temps dans l'app Tinder des
    joueurs à moins de 5 km (fn_datingSpecials), avec son vrai visage en photo. Un match livre des indices de position.
    Formulaire : type (HVT, SSE, retirer de Tinder, automatique), prénom ou surnom affiché, bio, chance de match.
    Variables publiques posées sur l'IA : COMSPEC_ATAK_TinderKind, _TinderName, _TinderBio, _TinderChance.
    En Eden, la même chose s'écrit dans l'init de l'unité : this setVariable ["COMSPEC_ATAK_TinderKind", "HVT", true];
*/
params [["_logic", objNull, [objNull]], ["_units", [], [[]]], ["_activated", true, [true]]];
private _ctx = [_logic, _units, _activated] call comspec_atak_native_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };
private _targets = (_ctx get "targets") select { _x isKindOf "CAManBase" && {!isPlayer _x} };
private _near = _ctx get "near";
if (_targets isEqualTo [] && {!isNull _near} && {_near isKindOf "CAManBase"} && {!isPlayer _near}) then { _targets pushBack _near; };
deleteVehicle _logic;
if (_targets isEqualTo []) exitWith { ["comspec_atak_native_dataNote", ["Posez le module sur une IA (pas un joueur)."]] call CBA_fnc_localEvent; true };
private _apply = {
    params ["_vals", "_targets"];
    _vals params ["_kind", "_name", "_bio", "_chance"];
    {
        _x setVariable ["COMSPEC_ATAK_TinderKind", _kind, true];
        _x setVariable ["COMSPEC_ATAK_TinderName", trim _name, true];
        _x setVariable ["COMSPEC_ATAK_TinderBio", trim _bio, true];
        _x setVariable ["COMSPEC_ATAK_TinderChance", (_chance / 100) max 0 min 1, true];
    } forEach _targets;
    ["comspec_atak_native_dataNote", [format ["Tinder : %1 IA → %2.", count _targets,
        createHashMapFromArray [["HVT", "profil HVT"], ["SSE", "profil SSE"], ["OFF", "retirée de Tinder"], ["", "automatique"]] getOrDefault [_kind, _kind]]]] call CBA_fnc_localEvent;
};
private _u = _targets select 0;
if (_ctx get "zeus") then {
    ["Tinder : profil HVT / SSE", [
        ["COMBO", "Profil", [["HVT (cible de haute valeur)", "HVT"], ["Personne SSE", "SSE"], ["Retirer de Tinder", "OFF"], ["Automatique (selon le SSE)", ""]], "HVT"],
        ["EDIT", "Prénom ou surnom (vide = son nom)", _u getVariable ["COMSPEC_ATAK_TinderName", ""]],
        ["MEMO", "Bio (vide = bio tirée au sort)", _u getVariable ["COMSPEC_ATAK_TinderBio", ""], 3],
        ["SLIDER", "Chance de match (%)", [0, 100, 50]]
    ], _apply, _targets, "La fréquence d'apparition suit le réglage de mission « Tinder : profils HVT et SSE » (CBA)."] call comspec_atak_native_fnc_zeusForm;
} else {
    [["HVT", "", "", 50], _targets] call _apply;
};
true
