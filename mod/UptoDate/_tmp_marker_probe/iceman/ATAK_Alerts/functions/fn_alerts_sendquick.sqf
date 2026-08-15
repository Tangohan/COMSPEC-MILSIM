#include "..\script_component.hpp"

params [["_kind", "TIC"]];

if !(_kind isEqualType "") then {
    _kind = "TIC";
};

switch (toUpper _kind) do {
    case "PANIC": {
        ["EAGLE_DOWN", format ["Eagle Down report from %1.", name player], getPosASL player] call Iceman_fnc_alerts_send;
    };
    case "EAGLE_DOWN": {
        ["EAGLE_DOWN", format ["Eagle Down report from %1.", name player], getPosASL player] call Iceman_fnc_alerts_send;
    };
    case "TIC_CLEAR": {
        ["TIC_CLEAR", format ["TIC cleared by %1.", name player], getPosASL player] call Iceman_fnc_alerts_send;
    };
    case "CLEAR": {
        ["TIC_CLEAR", format ["TIC cleared by %1.", name player], getPosASL player] call Iceman_fnc_alerts_send;
    };
    default {
        ["TIC", format ["Troops in contact reported by %1.", name player], getPosASL player] call Iceman_fnc_alerts_send;
    };
}
