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
        tooltip = "$STR_COMSPEC_SSE_CFG_25ECDF92AF";
        author = "COMSPEC";
    };
};

class CfgFactionClasses {
    class NO_CATEGORY;
    // Une famille de catégories, numérotées dans l'ordre d'une préparation de mission.
    class COMSPEC_SSE: NO_CATEGORY {
        displayName = "$STR_COMSPEC_SSE_CFG_378C13828D";
        priority = 2;
        side = 7;
    };
    class COMSPEC_SSE_People: NO_CATEGORY {
        displayName = "$STR_COMSPEC_SSE_CFG_206D673475";
        priority = 2;
        side = 7;
    };
    class COMSPEC_SSE_Mission: NO_CATEGORY {
        displayName = "$STR_COMSPEC_SSE_CFG_5E9801F32C";
        priority = 2;
        side = 7;
    };
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
