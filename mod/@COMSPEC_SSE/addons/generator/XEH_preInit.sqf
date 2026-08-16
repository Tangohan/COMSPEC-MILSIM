#include "script_component.hpp"

if (isNil "comspec_sse_models_mission") then {
    comspec_sse_models_mission = createHashMap;
};

[true] call comspec_sse_fnc_registerBuiltinModels;
[] call comspec_sse_fnc_registerDatasets;

if (isServer) then {
    ["comspec_sse_saveModel", {
        params ["_model"];
        if (isNil "comspec_sse_models_mission") then { comspec_sse_models_mission = createHashMap; };
        private _id = _model getOrDefault ["id", ""];
        if (_id != "") then {
            comspec_sse_models_mission set [_id, _model];
            publicVariable "comspec_sse_models_mission";
        };
    }] call CBA_fnc_addEventHandler;
};

["generator preInit — modèles + datasets prêts", "WARN"] call comspec_sse_fnc_log;
