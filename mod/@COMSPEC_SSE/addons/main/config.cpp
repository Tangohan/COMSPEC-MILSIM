#include "script_component.hpp"

class CfgPatches {
    class comspec_sse_main {
        name = "COMSPEC SSE - Main";
        units[] = {};
        weapons[] = {};
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {
            "cba_main",
            "cba_xeh",
            "cba_settings",
            "ace_common",
            "ace_interact_menu"
        };
        author = "COMSPEC";
        authors[] = {"COMSPEC"};
        url = "https://athena.ttrd.fr";
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
        action = "https://athena.ttrd.fr";
        overview = "Sensitive Site Exploitation — Zeus, Eden, ACE, génération déterministe et liaison Athena.";
        tooltip = "COMSPEC SSE";
        author = "COMSPEC";
    };
};

class CfgFactionClasses {
    class NO_CATEGORY;
    class COMSPEC_SSE: NO_CATEGORY {
        displayName = "COMSPEC — SSE";
        priority = 2;
        side = 7;
    };
};

class CfgEditorCategories {
    class COMSPEC_SSE {
        displayName = "COMSPEC SSE";
    };
};

class CfgEditorSubcategories {
    class COMSPEC_SSE_Items {
        displayName = "Matériel SSE";
    };
    class COMSPEC_SSE_Modules {
        displayName = "Modules SSE";
    };
};

class CfgVehicleClasses {
    class COMSPEC_SSE_Items {
        displayName = "COMSPEC SSE";
    };
};
