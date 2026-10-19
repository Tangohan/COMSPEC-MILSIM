/*
    Symbole OTAN (MIL-STD-2525 / APP-6, textures d'Arma \A3\ui_f\data\map\markers\nato\) d'une unité de la carte.
    Params : [entité (HashMap de la carte : id, object, affiliation, type, callsign)]
    Renvoie [texture du cadre + modificateur, texture d'échelon ("" sans échelon)].
    Cadre selon l'affiliation (b_ ami, o_ hostile, n_ neutre / inconnu), modificateur selon la situation :
    véhicule (drone, avion, hélico, navire, char, VBTT, artillerie, voiture), sinon rôle (PC, sanitaire, reco,
    mortier, télépilote, sapeur) puis infanterie. Échelon (points d'équipe / groupe) pour les chefs.
    Résultat gardé 4 s par unité : aucune lecture de config à chaque image.
*/
params [["_e", createHashMap]];
private _cache = uiNamespace getVariable "COMSPEC_ATAK_NatoCache";
if (isNil "_cache") then { _cache = createHashMap; uiNamespace setVariable ["COMSPEC_ATAK_NatoCache", _cache]; };
private _o = _e getOrDefault ["object", objNull];
private _id = _e getOrDefault ["id", ""];
private _key = format ["%1|%2", _id, _e getOrDefault ["affiliation", ""]];
private _hit = _cache getOrDefault [_key, []];
if ((count _hit) > 0 && {diag_tickTime < (_hit select 0)}) exitWith { [_hit select 1, _hit select 2] };
// Plus de 400 unités : cache vidé d'un coup (avant, chaque absence copiait les 400 clés à chaque image).
if ((count _cache) > 400) then { _cache = createHashMap; uiNamespace setVariable ["COMSPEC_ATAK_NatoCache", _cache]; };

private _pre = switch (_e getOrDefault ["affiliation", ""]) do { case "friend": { "b_" }; case "hostile": { "o_" }; default { "n_" }; };
private _mod = "inf";
private _ech = "";
private _has = {
    params ["_txt", "_words"];
    private _t = toLower _txt;
    (_words findIf { (_t find _x) >= 0 }) >= 0
};
if (isNull _o) then {
    // Unité Athena sans objet en jeu : seul son type est connu.
    _mod = switch (_e getOrDefault ["type", "infantry"]) do {
        case "air": { "air" }; case "armor": { "armor" }; case "vehicle": { "motor_inf" }; case "medical": { "med" };
        default { "inf" };
    };
} else {
    private _v = vehicle _o;
    private _role = _o getVariable ["COMSPEC_FTRole", ""];
    private _desc = format ["%1 %2", roleDescription _o, _e getOrDefault ["callsign", ""]];
    if (_v isNotEqualTo _o || {unitIsUAV _o}) then {
        private _cfg = configOf _v;
        _mod = switch (true) do {
            case (unitIsUAV _v): { "uav" };
            case (_v isKindOf "Plane"): { "plane" };
            case (_v isKindOf "Air"): { "air" };
            case (_v isKindOf "Ship"): { "naval" };
            case (_v isKindOf "StaticMortar"): { "mortar" };
            case (_v isKindOf "StaticWeapon"): { "support" };
            case ((getNumber (_cfg >> "artilleryScanner")) > 0): { "art" };
            case ((getNumber (_cfg >> "attendant")) > 0): { "med" };
            case (_v isKindOf "Wheeled_APC_F"): { "mech_inf" };
            case (_v isKindOf "Tank"): { ["armor", "mech_inf"] select ((getNumber (_cfg >> "transportSoldier")) >= 4) };
            case (_v isKindOf "LandVehicle"): { "motor_inf" };
            default { "unknown" };
        };
    } else {
        private _medic = (_o getUnitTrait "medic") || {(_o getVariable ["ace_medical_medicClass", 0]) > 0} || {_role isEqualTo "MED"};
        _mod = switch (true) do {
            case _medic: { "med" };
            case ([_desc, ["hq", "poste de commandement", "commandant", "company commander", "platoon commander", "officer", "officier", "platoon lead", "chef de section", "actual"]] call _has): { "hq" };
            case (_role isEqualTo "TE" || {[_desc, ["sniper", "marksman", "recon", "spotter", "éclaireur", "eclaireur", "tireur d'élite"]] call _has}): { "recon" };
            case ([_desc, ["mortar", "mortier"]] call _has): { "mortar" };
            case (_role isEqualTo "DRN" || {_o getUnitTrait "UAVHacker"}): { "uav" };
            case (_role in ["ENG", "EOD"] || {_o getUnitTrait "engineer"} || {_o getUnitTrait "explosiveSpecialist"}): { "maint" };
            default { "inf" };
        };
    };
    // Échelon au-dessus du cadre : chef de groupe (taille du groupe) ou chef d'équipe de feu.
    private _g = group _o;
    if (leader _g isEqualTo _o && {(count units _g) > 1}) then {
        private _n = count units _g;
        _ech = switch (true) do { case (_n <= 4): { "group_0" }; case (_n <= 13): { "group_2" }; default { "group_4" }; };
    } else {
        if (_role isEqualTo "CDE") then { _ech = "group_0"; };
    };
};
private _n = "\A3\ui_f\data\map\markers\nato\";
private _tex = _n + _pre + _mod + ".paa";
if (_ech isNotEqualTo "") then { _ech = _n + _ech + ".paa"; };
_cache set [_key, [diag_tickTime + 4, _tex, _ech]];
[_tex, _ech]
