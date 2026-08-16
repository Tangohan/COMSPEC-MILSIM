/*
    Watchdog PostInit — timestamps pour localiser la fenêtre de crash.
*/
if (missionNamespace getVariable ["COMSPEC_DEBUG_WATCHDOG_STARTED", false]) exitWith { false };
missionNamespace setVariable ["COMSPEC_DEBUG_WATCHDOG_STARTED", true];

["DEBUG", "WATCHDOG", "START", "POSTINIT +0ms"] call comspec_debug_fnc_log;

// Watchdog grossier (secondes)
[] spawn {
    private _marks = [0.1, 0.25, 0.5, 1, 2, 5];
    {
        private _sec = _x;
        uiSleep _sec;
        ["DEBUG", "WATCHDOG", "ALIVE", format [
            "Alive after %1 sec | fps=%2 | diag_frameNo=%3 | depth=%4",
            _sec,
            diag_fps,
            diag_frameNo,
            missionNamespace getVariable ["COMSPEC_DEBUG_CALL_DEPTH", 0]
        ]] call comspec_debug_fnc_log;
    } forEach _marks;
};

// Watchdog fin 100 ms × 30 (= 3 s) pour caler le crash vs compat async
[] spawn {
    for "_i" from 1 to 30 do {
        uiSleep 0.1;
        diag_log format [
            "[COMSPEC SSE][WATCHDOG] +%1ms frame=%2 fps=%3 depth=%4 lastBreadcrumb=%5",
            _i * 100,
            diag_frameNo,
            diag_fps,
            missionNamespace getVariable ["COMSPEC_DEBUG_CALL_DEPTH", 0],
            missionNamespace getVariable ["COMSPEC_DEBUG_LAST_BREADCRUMB", []]
        ];
    };
};

true
