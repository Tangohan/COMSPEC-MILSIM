/*
    Assemble le payload fiche opérateur jeu (HashMap prêt à sérialiser).
    Params: [_unit, _event, _reason, _light]
      _light : true = seulement de quoi calculer l'empreinte (contrôle périodique), sans catalogue ni liste de mods.
    Retour: HashMap
*/
params [
    ["_unit", objNull, [objNull]],
    ["_event", "sync", [""]],
    ["_reason", "profile_sync", [""]],
    ["_light", false, [true]]
];

if (isNull _unit) then { _unit = player; };

private _identity = [_unit] call comspec_overwatch_connect_fnc_collectOperatorIdentity;
private _face = [_unit] call comspec_overwatch_connect_fnc_collectOperatorFace;
private _medical = [_unit] call comspec_overwatch_connect_fnc_collectOperatorMedical;
private _equipment = [_unit] call comspec_overwatch_connect_fnc_collectOperatorLoadout;
private _versions = [] call comspec_overwatch_connect_fnc_collectOperatorVersions;
private _environment = [] call comspec_overwatch_connect_fnc_collectOperatorEnvironment;
private _fp = [_identity, _face, _medical, _equipment, _versions] call comspec_overwatch_connect_fnc_operatorProfileFingerprint;

// Aliases attendus par le registre portail (register/sync), en plus des clés riches.
private _steam = _identity getOrDefault ["steam_uid", ""];
_identity set ["player_uid", _identity getOrDefault ["arma_player_uid", _steam]];
_identity set ["player_name", _identity getOrDefault ["arma_player_name", ""]];
_identity set ["sex", _identity getOrDefault ["sex_detected", ""]];
_identity set ["face_class", _face getOrDefault ["face_class", ""]];
_identity set ["face_texture", _face getOrDefault ["face_texture", ""]];

private _primary = _equipment getOrDefault ["primary", createHashMap];
private _secondary = _equipment getOrDefault ["secondary", createHashMap];
private _handgun = _equipment getOrDefault ["handgun", createHashMap];
if (!(_primary isEqualType createHashMap)) then { _primary = createHashMap; };
if (!(_secondary isEqualType createHashMap)) then { _secondary = createHashMap; };
if (!(_handgun isEqualType createHashMap)) then { _handgun = createHashMap; };
_equipment set ["uniform", _equipment getOrDefault ["uniform_class", ""]];
_equipment set ["vest", _equipment getOrDefault ["vest_class", ""]];
_equipment set ["backpack", _equipment getOrDefault ["backpack_class", ""]];
_equipment set ["headgear", _equipment getOrDefault ["helmet_class", ""]];
_equipment set ["goggles", _equipment getOrDefault ["goggles_class", ""]];
_equipment set ["nvgs", _equipment getOrDefault ["nvgs_class", ""]];
_equipment set ["primary_weapon", _primary getOrDefault ["class", ""]];
_equipment set ["secondary_weapon", _secondary getOrDefault ["class", ""]];
_equipment set ["handgun_weapon", _handgun getOrDefault ["class", ""]];

private _missionFolder = _environment getOrDefault ["mission_name", ""];
private _missionTitle = _environment getOrDefault ["briefing_name", ""];
if (_missionTitle isEqualTo "") then { _missionTitle = _missionFolder; };

private _payload = createHashMap;
_payload set ["event", _event];
_payload set ["reason", _reason];
_payload set ["steam_id", _steam];
_payload set ["steam_uid", _steam];
_payload set ["player_uid", _identity getOrDefault ["player_uid", _steam]];
_payload set ["identity", _identity];
_payload set ["face", _face];
_payload set ["medical", _medical];
_payload set ["equipment", _equipment];
_payload set ["loadout", _equipment getOrDefault ["loadout", []]];
_payload set ["versions", _versions];
_payload set ["environment", _environment];
_payload set ["server_name", _environment getOrDefault ["server_name", ""]];
_payload set ["mission_name", _missionTitle];
_payload set ["mission_id", _environment getOrDefault ["mission_id", _missionFolder]];
_payload set ["world_name", _environment getOrDefault ["world_name", ""]];
_payload set ["fingerprint", _fp];
if (_light) exitWith { _payload };
private _full = (toLower _event) isEqualTo "register";
// Objets portés avec leur mod (top des armes, gilets… et kits par rôle d'Athena) : à l'enregistrement, puis
// seulement quand la liste change. Le site garde le nom et le mod déjà connus de chaque objet (sans catalogue, il
// relève les objets portés depuis l'équipement), inutile de renvoyer ce catalogue à chaque fiche.
private _catalog = [_equipment getOrDefault ["loadout", []]] call comspec_overwatch_connect_fnc_collectItemCatalog;
private _catalogCls = _catalog apply { if (_x isEqualType createHashMap) then { _x getOrDefault ["class", ""] } else { str _x } };
_catalogCls sort true;
private _catalogKey = str _catalogCls;
if (_full || {(missionNamespace getVariable ["COMSPEC_OpCatalogSentKey", ""]) isNotEqualTo _catalogKey}) then {
    _payload set ["item_catalog", _catalog];
    _payload set ["item_catalog_key", _catalogKey];
};
// Mods chargés : à l'enregistrement, puis seulement quand la liste change (elle ne bouge pas en cours de partie).
private _mods = [] call comspec_overwatch_connect_fnc_collectLoadedMods;
private _modsKey = str (_mods apply { _x get "dir" });
if (_full || {(missionNamespace getVariable ["COMSPEC_OpModsSentKey", ""]) isNotEqualTo _modsKey}) then {
    _payload set ["mods", _mods];
    _payload set ["mods_key", _modsKey];
};

_payload
