/*
    Snapshot d’environnement au PostInit.
*/
["INFO", "DEBUG", "SNAPSHOT", "=== COMSPEC DEBUG SNAPSHOT ==="] call comspec_debug_fnc_log;

private _armaVer = productVersion;
["INFO", "DEBUG", "SNAPSHOT", format ["Arma version: %1", _armaVer]] call comspec_debug_fnc_log;

private _mods = [
    ["CBA", "cba_main"],
    ["ACE", "ace_main"],
    ["ACE_Interact", "ace_interact_menu"],
    ["ZEN", "zen_main"],
    ["TFAR", "tfar_core"],
    ["ACRE", "acre_main"],
    ["Mavic", "mavic_main"],
    ["COMSPEC_SSE", "comspec_sse_main"],
    ["COMSPEC_SSE_Core", "comspec_sse_core"],
    ["COMSPEC_SSE_Interaction", "comspec_sse_interaction"],
    ["COMSPEC_SSE_Digital", "comspec_sse_digital"],
    ["COMSPEC_SSE_Biometrics", "comspec_sse_biometrics"],
    ["COMSPEC_Overwatch", "comspec_overwatch_connect"],
    ["COMSPEC_SSE_ACE_OW", "comspec_overwatch_sse_ace"]
];

{
    _x params ["_label", "_patch"];
    private _ok = isClass (configFile >> "CfgPatches" >> _patch);
    ["INFO", "DEBUG", "SNAPSHOT", format ["%1 detected: %2", _label, _ok]] call comspec_debug_fnc_log;
} forEach _mods;

private _sseVer = "unknown";
if (isClass (configFile >> "CfgPatches" >> "comspec_sse_main")) then {
    _sseVer = getText (configFile >> "CfgPatches" >> "comspec_sse_main" >> "versionStr");
    if (_sseVer isEqualTo "") then {
        _sseVer = str (getNumber (configFile >> "CfgPatches" >> "comspec_sse_main" >> "version"));
    };
};
["INFO", "DEBUG", "SNAPSHOT", format ["COMSPEC SSE version: %1", _sseVer]] call comspec_debug_fnc_log;

private _owVer = "unknown";
if (isClass (configFile >> "CfgPatches" >> "comspec_overwatch_connect")) then {
    _owVer = getText (configFile >> "CfgPatches" >> "comspec_overwatch_connect" >> "versionStr");
    if (_owVer isEqualTo "") then {
        _owVer = str (getNumber (configFile >> "CfgPatches" >> "comspec_overwatch_connect" >> "version"));
    };
};
["INFO", "DEBUG", "SNAPSHOT", format ["COMSPEC Overwatch version: %1", _owVer]] call comspec_debug_fnc_log;

["INFO", "DEBUG", "SNAPSHOT", format ["isMultiplayer: %1", isMultiplayer]] call comspec_debug_fnc_log;
["INFO", "DEBUG", "SNAPSHOT", format ["isServer: %1", isServer]] call comspec_debug_fnc_log;
["INFO", "DEBUG", "SNAPSHOT", format ["hasInterface: %1", hasInterface]] call comspec_debug_fnc_log;
["INFO", "DEBUG", "SNAPSHOT", format ["player: %1", if (hasInterface) then { str player } else { "n/a" }]] call comspec_debug_fnc_log;
["INFO", "DEBUG", "SNAPSHOT", format ["worldName: %1", worldName]] call comspec_debug_fnc_log;
["INFO", "DEBUG", "SNAPSHOT", format ["missionName: %1", missionName]] call comspec_debug_fnc_log;
["INFO", "DEBUG", "SNAPSHOT", format ["safeMode: %1", [] call comspec_debug_fnc_isSafeMode]] call comspec_debug_fnc_log;
["INFO", "DEBUG", "SNAPSHOT", format ["callDepth: %1", missionNamespace getVariable ["COMSPEC_DEBUG_CALL_DEPTH", 0]]] call comspec_debug_fnc_log;

["INFO", "DEBUG", "SNAPSHOT", "============================="] call comspec_debug_fnc_log;

true
