#include "script_component.hpp"

// Même garde que XEH_preInit : sans instrumentation, ni chien de garde ni instantané (RPT de chaque poste).
if !(missionNamespace getVariable ["COMSPEC_DEBUG_FORCE", false]) exitWith {};

if !(["COMSPEC_DEBUG_POSTINIT_DONE", "XEH_postInit debug"] call comspec_debug_fnc_guardOnce) exitWith {};

["comspec_debug_XEH_postInit", []] call comspec_debug_fnc_enter;
["DEBUG", "WATCHDOG", "MARK", "POSTINIT +0ms"] call comspec_debug_fnc_log;

[] call comspec_debug_fnc_watchdog;
[] call comspec_debug_fnc_snapshot;

// Stats ACE retardées (après les autres postInit client)
[{
    [] call comspec_debug_fnc_aceStats;
}, [], 6] call CBA_fnc_waitAndExecute;

["INFO", "Boot", "POSTINIT", "Debug postInit OK"] call comspec_debug_fnc_log;
["comspec_debug_XEH_postInit"] call comspec_debug_fnc_exit;
