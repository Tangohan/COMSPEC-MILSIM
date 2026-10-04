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
private _skip = ["hcam", "helmetcam", "helmet_cam", "_base", "battery", "batterie"]; // batterie ATAK : pas un téléphone
private _out = +_extra;
{
    private _cls = toLower (configName _x);
    if ((_patterns findIf { (_cls find _x) >= 0 }) >= 0 && {(_skip findIf { (_cls find _x) >= 0 }) < 0} && {(getNumber (_x >> "scope")) >= 2}) then { _out pushBackUnique _cls; };
} forEach ("isClass _x" configClasses (configFile >> "CfgWeapons"));
if (_tablets) then {
    { if (isClass (configFile >> "CfgWeapons" >> _x)) then { _out pushBackUnique toLower _x; }; } forEach ["ItemcTab", "ItemMicroDAGR", "ACE_microDAGR"];
};
missionNamespace setVariable ["COMSPEC_ATAK_DeviceCatalog", _out];
["INFO", "DEVICE", format ["Terminaux reconnus : %1", _out]] call comspec_atak_native_fnc_log;
_out
