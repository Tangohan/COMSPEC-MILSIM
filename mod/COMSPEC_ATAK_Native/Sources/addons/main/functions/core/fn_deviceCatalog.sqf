/*
    Liste des objets qui comptent comme terminal ATAK, calculée une fois puis à chaque changement de réglage.
    Sources : objets listés par l'admin + objets des mods chargés dont le nom de classe contient un motif
    (android, atak, smartphone par défaut), + tablettes / DAGR si l'admin les accepte.
    Renvoie un tableau de noms de classes en minuscules.
*/
params [["_force", false]];
private _cached = missionNamespace getVariable ["COMSPEC_ATAK_DeviceCatalog", []];
if (!_force && {(count _cached) > 0}) exitWith { _cached };
private _split = {
    params ["_s"];
    if !(_s isEqualType "") exitWith { [] };
    ((_s splitString ",; ") apply { toLower (trim _x) }) select { _x isNotEqualTo "" }
};
private _extra = [missionNamespace getVariable ["comspec_atak_native_device_items", ""]] call _split;
private _patterns = [missionNamespace getVariable ["comspec_atak_native_device_patterns", "android,atak,smartphone"]] call _split;
private _tablets = missionNamespace getVariable ["comspec_atak_native_device_tablets", false];
// Notre téléphone compte toujours ; nos autres objets (kit, câble, antennes…) contiennent « atak » mais n'en sont pas.
private _out = ["comspec_atak_phone", "comspec_atak_phone_s7_od", "comspec_atak_phone_s7_black", "comspec_atak_phone_s23", "comspec_atak_phone_iphone", "comspec_atak_phone_ip17", "comspec_atak_phone_rugged", "comspec_atak_phone_sonim", "comspec_atak_phone_tablet"] + _extra;
{ _out pushBackUnique _x; } forEach ([_patterns] call comspec_atak_native_fnc_deviceScan);
if (_tablets) then {
    { if (isClass (configFile >> "CfgWeapons" >> _x)) then { _out pushBackUnique toLower _x; }; } forEach ["ItemcTab", "ItemMicroDAGR", "ACE_microDAGR"];
};
missionNamespace setVariable ["COMSPEC_ATAK_DeviceCatalog", _out];
// Même liste en table : fn_hasDevice la consulte plusieurs fois par seconde (recherche directe au lieu d'un parcours).
missionNamespace setVariable ["COMSPEC_ATAK_DeviceCatalogSet", createHashMapFromArray (_out apply { [_x, true] })];
["INFO", "DEVICE", format ["Terminaux reconnus : %1", _out]] call comspec_atak_native_fnc_log;
_out
