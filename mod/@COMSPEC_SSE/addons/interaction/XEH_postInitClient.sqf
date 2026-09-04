if (!hasInterface) exitWith {};

["DEBUG", "POSTINIT", "BEGIN", "interaction"] call comspec_debug_fnc_log;

if !(["interaction"] call comspec_debug_fnc_isModuleEnabled) exitWith {
    ["WARN", "Boot", "ISOLATION", "SSE ACE / Interaction disabled by debug isolation"] call comspec_debug_fnc_log;
    ["DEBUG", "POSTINIT", "END", "interaction (disabled)"] call comspec_debug_fnc_log;
};

try {
    [] call comspec_sse_fnc_initACE;
} catch {
    ["comspec_sse_fnc_initACE", _exception, "XEH_postInitClient interaction"] call comspec_debug_fnc_exception;
};

if (!isNil "CBA_fnc_waitAndExecute") then {
    [{
        if (isNull player) exitWith {};
        if (!isNil "COMSPEC_SSE_HatchetGetInEH") exitWith {};
        COMSPEC_SSE_HatchetGetInEH = player addEventHandler ["GetInMan", {
            if (!isNil "ace_interact_menu_fnc_hideMenu") then {
                [] call ace_interact_menu_fnc_hideMenu;
            };
        }];
    }, [], 1] call CBA_fnc_waitAndExecute;
};

["DEBUG", "POSTINIT", "END", "interaction"] call comspec_debug_fnc_log;
