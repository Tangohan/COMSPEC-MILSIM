diag_log "[SSE][POSTINIT][network] BEGIN";

[] call comspec_sse_fnc_restoreQueue;
if (isNil "comspec_sse_txQueue") then {
    comspec_sse_txQueue = [];
};

// Flush périodique si liaison disponible (45 s)
[{
    if ([] call comspec_sse_fnc_isOnline) then {
        private _n = [] call comspec_sse_fnc_flushQueue;
        if (_n > 0) then {
            [format ["flushQueue auto: %1 envoyés", _n], "WARN"] call comspec_sse_fnc_log;
        };
    };
}, 45] call CBA_fnc_addPerFrameHandler;

// Snapshot profil toutes les 3 min même hors flush
[{
    if (!isNil "comspec_sse_txQueue" && {count comspec_sse_txQueue > 0}) then {
        [] call comspec_sse_fnc_persistQueue;
    };
}, 180] call CBA_fnc_addPerFrameHandler;

["network postInit OK — offline/sync LOT 7", "WARN"] call comspec_sse_fnc_log;
diag_log "[SSE][POSTINIT][network] END";
