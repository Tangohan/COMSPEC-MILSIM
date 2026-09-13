/*
    Assemble le payload fiche opérateur jeu (HashMap prêt à sérialiser).
    Params: [_unit, _event, _reason]
    Retour: HashMap
*/
params [
    ["_unit", objNull, [objNull]],
    ["_event", "sync", [""]],
    ["_reason", "profile_sync", [""]]
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

_payload
