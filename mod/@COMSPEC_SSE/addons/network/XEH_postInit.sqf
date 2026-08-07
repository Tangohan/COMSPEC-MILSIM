if (isNil "comspec_sse_txQueue") then {
    comspec_sse_txQueue = [];
};

[{
    if ([] call comspec_sse_fnc_isOnline) then {
        private _n = [] call comspec_sse_fnc_flushQueue;
        if (_n > 0) then {
            [format ["flushQueue auto: %1 envoyés", _n]] call comspec_sse_fnc_log;
        };
    };
}, 45] call CBA_fnc_addPerFrameHandler;

["network postInit OK"] call comspec_sse_fnc_log;
