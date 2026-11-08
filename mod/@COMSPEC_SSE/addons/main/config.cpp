#include "script_component.hpp"

class CfgPatches {
    class comspec_sse_main {
        name = "COMSPEC SSE - Main";
        units[] = {};
        weapons[] = {};
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {
            "cba_main",
            "cba_common",
            "cba_xeh",
            "cba_settings",
            "ace_common",
            "ace_interact_menu",
            "comspec_atak_native_main"
        };
        author = "COMSPEC";
        authors[] = {"COMSPEC"};
        url = "https://athena-comspec.eu";
        VERSION_CONFIG;
    };
};

class CfgMods {
    class COMSPEC_SSE {
        dir = "@COMSPEC_SSE";
        name = "COMSPEC SSE";
        picture = "";
        logo = "";
        logoOver = "";
        logoSmall = "";
        actionName = "Website";
        action = "https://athena-comspec.eu";
        overview = "$STR_COMSPEC_SSE_CFG_5EF301D74E";
        tooltip = "$STR_COMSPEC_SSE_CFG_25ECDF92AF";
        author = "COMSPEC";
    };
};

class CfgFactionClasses {
    class NO_CATEGORY;
    // Rangement par usage, le même dans Zeus et Eden.
    class COMSPEC_Cat_Sse: NO_CATEGORY { displayName = "$STR_COMSPEC_SSE_CFG_D87E995DD7"; priority = 2; side = 7; };
    class COMSPEC_Cat_Identite: NO_CATEGORY { displayName = "$STR_COMSPEC_SSE_CFG_69CF391658"; priority = 2; side = 7; };
    class COMSPEC_Cat_Mission: NO_CATEGORY { displayName = "$STR_COMSPEC_SSE_CFG_BF752EC697"; priority = 2; side = 7; };
};

class CfgEditorCategories {
    class COMSPEC_SSE {
        displayName = "$STR_COMSPEC_SSE_CFG_25ECDF92AF";
    };
};

class CfgEditorSubcategories {
    class COMSPEC_SSE_Items {
        displayName = "$STR_COMSPEC_SSE_CFG_69DCC1CC45";
    };
    class COMSPEC_SSE_Modules {
        displayName = "$STR_COMSPEC_SSE_CFG_85359FF17D";
    };
};

class CfgVehicleClasses {
    class COMSPEC_SSE_Items {
        displayName = "$STR_COMSPEC_SSE_CFG_25ECDF92AF";
    };
};
