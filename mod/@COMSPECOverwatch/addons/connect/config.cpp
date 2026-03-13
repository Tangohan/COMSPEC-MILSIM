#include "script_component.hpp"

class CfgPatches {
    class comspec_overwatch_connect {
        name = QUOTE(COMPONENT_BEAUTIFIED);
        units[] = {};
        weapons[] = {};
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {"comspec_overwatch_main","cba_main"};
        author = QUOTE(AUTHOR);
        VERSION_CONFIG;
    };
};

#include "CfgEventHandlers.hpp"
