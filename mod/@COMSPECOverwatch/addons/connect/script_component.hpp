#define COMPONENT connect
#include "\z\comspec_overwatch\addons\main\script_mod.hpp"

#define COMPONENT_BEAUTIFIED Connect

#ifdef DEBUG_ENABLED_CONNECT
    #define DEBUG_MODE_FULL
#endif
#ifdef DEBUG_SETTINGS_CONNECT
    #define DEBUG_SETTINGS DEBUG_SETTINGS_CONNECT
#endif

#include "\z\comspec_overwatch\addons\main\script_macros.hpp"

#define GVAR(var) TRIPLES(comspec_overwatch,connect,var)
#define FUNC(var) TRIPLES(comspec_overwatch,fnc,var)
#define QGVAR(var) QUOTE(GVAR(var))
#define QFUNC(var) QUOTE(FUNC(var))
