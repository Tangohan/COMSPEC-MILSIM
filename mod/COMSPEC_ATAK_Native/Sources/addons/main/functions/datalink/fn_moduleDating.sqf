/*
    Module Zeus « Tinder : profil HVT / SSE » : posé sur une IA, elle apparaît de temps en temps dans l'app Tinder des
    joueurs à moins de 5 km (fn_datingSpecials), avec son vrai visage en photo. Un match livre des indices de position.
    Formulaire (Zeus) ou attributs du module synchronisé aux IA (Eden) : type (HVT, SSE, retirer de Tinder, automatique),
    prénom ou surnom affiché, bio, chance de match, et pour la chasse aux HVT (fn_hvtIntel) : tuyaux d'informateurs
    oui / non, part de faux tuyaux, faux rendez-vous (-1 = réglage de mission).
    Variables publiques posées sur l'IA : COMSPEC_ATAK_TinderKind, _TinderName, _TinderBio, _TinderChance, _TinderTips,
    _TinderFalse, _TinderLie. Elles s'écrivent aussi dans l'init de l'unité :
      this setVariable ["COMSPEC_ATAK_TinderKind", "HVT", true]; this setVariable ["COMSPEC_ATAK_TinderFalse", 0.5, true];
*/
params [["_logic", objNull, [objNull]], ["_units", [], [[]]], ["_activated", true, [true]]];
private _ctx = [_logic, _units, _activated] call comspec_atak_native_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };
private _targets = (_ctx get "targets") select { _x isKindOf "CAManBase" && {!isPlayer _x} };
private _near = _ctx get "near";
if (_targets isEqualTo [] && {!isNull _near} && {_near isKindOf "CAManBase"} && {!isPlayer _near}) then { _targets pushBack _near; };
// Attributs Eden (lus avant de supprimer la logique).
private _eden = [_logic getVariable ["Kind", "HVT"], _logic getVariable ["Name", ""], _logic getVariable ["Bio", ""], _logic getVariable ["Chance", 50],
    _logic getVariable ["Tips", 1], _logic getVariable ["FalseShare", -1], _logic getVariable ["Lie", -1]];
deleteVehicle _logic;
if (_targets isEqualTo []) exitWith { ["comspec_atak_native_dataNote", ["Posez le module sur une IA (pas un joueur)."]] call CBA_fnc_localEvent; true };
private _apply = {
    params ["_vals", "_targets"];
    _vals params ["_kind", "_name", "_bio", "_chance", ["_tips", 1], ["_false", -1], ["_lie", -1]];
    {
        _x setVariable ["COMSPEC_ATAK_TinderKind", _kind, true];
        _x setVariable ["COMSPEC_ATAK_TinderName", trim _name, true];
        _x setVariable ["COMSPEC_ATAK_TinderBio", trim _bio, true];
        _x setVariable ["COMSPEC_ATAK_TinderChance", (_chance / 100) max 0 min 1, true];
        _x setVariable ["COMSPEC_ATAK_TinderTips", _tips isNotEqualTo 0, true];
        _x setVariable ["COMSPEC_ATAK_TinderFalse", [_false max 0 min 1, -1] select (_false < 0), true];
        _x setVariable ["COMSPEC_ATAK_TinderLie", [_lie max 0 min 1, -1] select (_lie < 0), true];
    } forEach _targets;
    ["comspec_atak_native_dataNote", [format ["Tinder : %1 IA » %2.", count _targets,
        createHashMapFromArray [["HVT", "profil HVT"], ["SSE", "profil SSE"], ["OFF", "retirée de Tinder"], ["", "automatique"]] getOrDefault [_kind, _kind]]]] call CBA_fnc_localEvent;
};
private _u = _targets select 0;
if (_ctx get "zeus") then {
    ["Tinder : profil HVT / SSE", [
        ["COMBO", "Profil", [["HVT (cible de haute valeur)", "HVT"], ["Personne SSE", "SSE"], ["Retirer de Tinder", "OFF"], ["Automatique (selon le SSE)", ""]], "HVT"],
        ["EDIT", "Prénom ou surnom (vide = son nom)", _u getVariable ["COMSPEC_ATAK_TinderName", ""]],
        ["MEMO", "Bio (vide = bio tirée au sort)", _u getVariable ["COMSPEC_ATAK_TinderBio", ""], 3],
        ["SLIDER", "Chance de match (%)", [0, 100, 50]],
        ["COMBO", "Tuyaux d'informateurs sur elle", [["Oui (chasse aux HVT)", 1], ["Non", 0]], [0, 1] select (_u getVariable ["COMSPEC_ATAK_TinderTips", true])],
        ["COMBO", "Part de faux tuyaux", [["Réglage de mission", -1], ["Aucun (0 %)", 0], ["Peu (15 %)", 0.15], ["Moyenne (35 %)", 0.35], ["Beaucoup (60 %)", 0.6]], _u getVariable ["COMSPEC_ATAK_TinderFalse", -1]],
        ["COMBO", "Faux rendez-vous si matché", [["Réglage de mission", -1], ["Jamais", 0], ["Parfois (25 %)", 0.25], ["Souvent (50 %)", 0.5], ["Toujours (piège)", 1]], _u getVariable ["COMSPEC_ATAK_TinderLie", -1]]
    ], _apply, _targets, "La fréquence d'apparition suit le réglage de mission « Tinder : profils HVT et SSE » (CBA). Un HVT est aussi chassé : les informateurs envoient des tuyaux sur lui (réglage « Tinder : tuyaux sur les HVT »)."] call comspec_atak_native_fnc_zeusForm;
} else {
    [_eden, _targets] call _apply;
};
true
