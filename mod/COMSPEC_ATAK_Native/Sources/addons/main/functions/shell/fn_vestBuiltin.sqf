/*
    Le gilet porté a-t-il déjà un ATAK modélisé (certains packs, ex. gilets AVS de TF13 / GUS) ?
    Sources : réglage serveur comspec_atak_native_vest_atak_items (classes exactes), motifs du réglage
    comspec_atak_native_vest_atak_patterns cherchés dans la classe, le nom et le modèle du gilet, et les gilets
    que le joueur a lui-même marqués (profil COMSPEC_ATAK_VestBuiltinMine / COMSPEC_ATAK_VestBuiltinNot, menu ACE « Gilet »).
    Sur ces gilets, notre téléphone n'est pas accroché sauf si le joueur le demande (profil COMSPEC_ATAK_VestBuiltinUse = "ours").
    Params : [unité, (optionnel) classe du gilet]. Renvoie un booléen.
*/
params [["_unit", player], ["_vest", ""]];
if (_vest isEqualTo "") then { _vest = vest _unit; };
if (_vest isEqualTo "") exitWith { false };
private _cls = toLower _vest;
private _cache = missionNamespace getVariable ["COMSPEC_ATAK_VestBuiltinCache", createHashMap];
missionNamespace setVariable ["COMSPEC_ATAK_VestBuiltinCache", _cache];
if ((profileNamespace getVariable ["COMSPEC_ATAK_VestBuiltinMine", []]) findIf { (toLower _x) isEqualTo _cls } >= 0) exitWith { true };
if ((profileNamespace getVariable ["COMSPEC_ATAK_VestBuiltinNot", []]) findIf { (toLower _x) isEqualTo _cls } >= 0) exitWith { false };
if (_cls in _cache) exitWith { _cache get _cls };
private _split = {
    params ["_s"];
    if !(_s isEqualType "") exitWith { [] };
    ((_s splitString ",; ") apply { toLower (trim _x) }) select { _x isNotEqualTo "" }
};
private _list = [missionNamespace getVariable ["comspec_atak_native_vest_atak_items", ""]] call _split;
private _pats = [missionNamespace getVariable ["comspec_atak_native_vest_atak_patterns", "atak,eud"]] call _split;
private _cfg = configFile >> "CfgWeapons" >> _vest;
private _hay = toLower format ["%1 %2 %3", _cls, getText (_cfg >> "displayName"), getText (_cfg >> "model")];
private _res = (_cls in _list) || {(_pats findIf { (_hay find _x) >= 0 }) >= 0};
_cache set [_cls, _res];
_res
