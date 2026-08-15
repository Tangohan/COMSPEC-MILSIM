if (isNil "acre_sys_data_fnc_registerRadioPreset") exitWith {
    diag_log "[Iceman MPU5] Native preset registration skipped; ACRE data API unavailable.";
};

private _presetData = [32] call Iceman_fnc_mpu5_buildPreset;
["ACRE_MPU5", "default", _presetData] call acre_sys_data_fnc_registerRadioPreset;

if (!(isNil "acre_api_fnc_setPreset")) then {
    ["ACRE_MPU5", "default"] call acre_api_fnc_setPreset;
};

diag_log "[Iceman MPU5] Registered native ACRE_MPU5 preset.";
