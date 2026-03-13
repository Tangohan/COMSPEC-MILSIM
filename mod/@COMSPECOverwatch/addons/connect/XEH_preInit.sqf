#include "script_component.hpp"

if (!hasInterface) exitWith {};

GVAR(enabled) = true;
GVAR(uri) = "";
GVAR(key) = "";

[QGVAR(enabled), "CHECKBOX", ["Activer COMSPEC Overwatch", "Connexion au nœud ATAK / Tacmap"], ["COMSPEC Overwatch", "Connexion"], true, 0, {}, true] call CBA_fnc_addSetting;
[QGVAR(uri), "EDITBOX", ["URL du nœud ATAK", "Ex: https://votre-domaine.com:3001 (sans slash final)"], ["COMSPEC Overwatch", "Connexion"], "http://localhost:3001", 0, {}, true] call CBA_fnc_addSetting;
[QGVAR(key), "EDITBOX", ["Clé d'accès (optionnel)", "Clé fournie par l'admin si requise"], ["COMSPEC Overwatch", "Connexion"], "", 0, {}, true] call CBA_fnc_addSetting;

["CBA_settingsInitialized", {
    if (!GVAR(enabled)) exitWith {};
    if (GVAR(uri) == "") exitWith {};
    call compile preprocessFileLineNumbers "\z\comspec_overwatch\addons\connect\functions\fnc_connect.sqf";
    GVAR(nextUpdate) = diag_tickTime + 0.5;
    GVAR(updatePFH) = [{ call compile preprocessFileLineNumbers "\z\comspec_overwatch\addons\connect\functions\fnc_updatePosition.sqf"; }, 0.5] call CBA_fnc_addPerFrameHandler;
}] call CBA_fnc_addEventHandler;
