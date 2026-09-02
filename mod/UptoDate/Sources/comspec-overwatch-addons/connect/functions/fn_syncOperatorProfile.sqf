/* Significant Arma observation snapshot. Never sent by the position loop. */
params [["_reason", "SYNC", [""]]];
if (!hasInterface || {isNull player}) exitWith { "" };
private _uid = getPlayerUID player;
if (_uid isEqualTo "") exitWith { "" };
private _faceClass = face player;
private _faceTexture = getText (configFile >> "CfgFaces" >> "Man_A3" >> _faceClass >> "texture");
private _identity = createHashMapFromArray [
    ["player_uid", _uid], ["player_name", name player],
    ["display_name", profileName], ["callsign", [] call comspec_overwatch_connect_fnc_getCallsign],
    ["face_class", _faceClass], ["face_texture", _faceTexture], ["sex", ""],
    ["role", [player] call comspec_overwatch_connect_fnc_getUnitRole], ["group_name", groupId group player],
    ["faction", faction player], ["side", str side player], ["rank", rank player]
];
// Sex and blood type stay empty unless a mission/mod explicitly exposes them: no inference.
_identity set ["sex", player getVariable ["COMSPEC_Sex", ""]];
private _medical = createHashMapFromArray [["blood_type", player getVariable ["COMSPEC_BloodType", ""]]];
private _equipment = createHashMapFromArray [
    ["uniform", uniform player], ["vest", vest player], ["backpack", backpack player],
    ["headgear", headgear player], ["goggles", goggles player], ["nvgs", hmd player],
    ["primary_weapon", primaryWeapon player], ["secondary_weapon", secondaryWeapon player], ["handgun", handgunWeapon player],
    ["magazines", magazines player], ["items", items player], ["assigned_items", assignedItems player]
];
private _versions = createHashMapFromArray [
    ["overwatch", missionNamespace getVariable ["COMSPEC_ModVersion", ""]],
    ["atak", missionNamespace getVariable ["COMSPEC_AtakVersion", ""]],
    ["dll", missionNamespace getVariable ["COMSPEC_DllVersion", ""]],
    ["ace", if (isClass (configFile >> "CfgPatches" >> "ace_main")) then {getText (configFile >> "CfgPatches" >> "ace_main" >> "versionStr")} else {""}],
    ["cba", if (isClass (configFile >> "CfgPatches" >> "cba_main")) then {getText (configFile >> "CfgPatches" >> "cba_main" >> "version")} else {""}],
    ["arma", productVersion joinString "."]
];
private _payload = createHashMapFromArray [
    ["steam_id", _uid], ["reason", _reason], ["identity", _identity], ["medical", _medical],
    ["equipment", _equipment], ["loadout", getUnitLoadout player], ["versions", _versions],
    ["server_name", serverName], ["mission_name", getMissionConfigValue ["onLoadName", missionName]],
    ["mission_id", missionName], ["world_name", worldName]
];
private _json = [_payload] call comspec_overwatch_connect_fnc_hashMapToJson;
"COMSPECExtension" callExtension ["OperatorProfile", [_reason, _json, _uid]]
