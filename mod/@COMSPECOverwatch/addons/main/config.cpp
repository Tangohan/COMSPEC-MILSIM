#include "script_component.hpp"

class CfgPatches {
    class comspec_overwatch_main {
        name = QUOTE(COMPONENT_BEAUTIFIED);
        units[] = {};
        weapons[] = {};
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {"cba_main"};
        author = QUOTE(AUTHOR);
        VERSION_CONFIG;
    };
};
