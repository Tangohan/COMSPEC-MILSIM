#include "script_component.hpp"

/*
    Tutoriels du SSE :
      - manuel de terrain (Field Manual, catégorie COMSPEC SSE), lisible dans Eden, en jeu et au Zeus ;
      - menu « COMSPEC SSE » dans la barre de menus d'Eden, avec le tableau de bord SSE de la mission ouverte ;
      - Zeus : manuel et exercice complet posé en un clic ;
      - ACE : « Guide SSE (manuel) ».
*/
class CfgPatches {
    class comspec_sse_guide {
        name = "COMSPEC SSE - Tutoriels";
        units[] = {};
        weapons[] = {};
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {"comspec_sse_main", "comspec_sse_analysis", "comspec_sse_campaign", "comspec_sse_zeus", "3DEN", "cba_xeh", "comspec_atak_native_main"};
        author = "COMSPEC";
        VERSION_CONFIG;
    };
};

class CfgFunctions {
    class comspec_sse {
        tag = "comspec_sse";
        class guide {
            file = "z\comspec_sse\addons\guide\functions";
            class guideExercise {};
            class openGuide {};
            class openDashboard {};
            class dashCollect {};
            class dashSite {};
            class dashRender {};
            class athenaEden {};
        };
    };
};

class Extended_PostInit_EventHandlers {
    class comspec_sse_guide {
        init = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\guide\XEH_postInit.sqf'";
    };
};

#define HINT_IMG "\a3\ui_f\data\igui\cfg\simpleTasks\types\intel_ca.paa"

class CfgHints {
    class COMSPEC_SSE {
        displayName = "$STR_COMSPEC_SSE_CFG_25ECDF92AF";
        logicalOrder = 90;
        class Start {
            displayName = "$STR_COMSPEC_SSE_CFG_7549FAA67C";
            displayNameShort = "$STR_COMSPEC_SSE_CFG_7549FAA67C";
            description = "$STR_COMSPEC_SSE_CFG_9A715CB0F1";
            tip = "$STR_COMSPEC_SSE_CFG_3646C2D826";
            arguments[] = {};
            image = HINT_IMG;
            noImage = 0;
        };
        class Bio {
            displayName = "$STR_COMSPEC_SSE_CFG_B2EF3D8634";
            displayNameShort = "$STR_COMSPEC_SSE_CFG_5427FD158C";
            description = "$STR_COMSPEC_SSE_CFG_ACDEF74322";
            tip = "$STR_COMSPEC_SSE_CFG_61CCF4C126";
            arguments[] = {};
            image = HINT_IMG;
            noImage = 0;
        };
        class Capture {
            displayName = "$STR_COMSPEC_SSE_CFG_F503CC4364";
            displayNameShort = "$STR_COMSPEC_SSE_CFG_3D7AF5EC00";
            description = "$STR_COMSPEC_SSE_CFG_25C0AE9942";
            tip = "$STR_COMSPEC_SSE_CFG_ABAA25A627";
            arguments[] = {};
            image = HINT_IMG;
            noImage = 0;
        };
        class Evidence {
            displayName = "$STR_COMSPEC_SSE_CFG_B80CA83E9F";
            displayNameShort = "$STR_COMSPEC_SSE_CFG_F4F05DEBDE";
            description = "$STR_COMSPEC_SSE_CFG_B59B00E2C9";
            tip = "$STR_COMSPEC_SSE_CFG_8D04FF3E4A";
            arguments[] = {};
            image = HINT_IMG;
            noImage = 0;
        };
        class Links {
            displayName = "$STR_COMSPEC_SSE_CFG_DB6388D385";
            displayNameShort = "$STR_COMSPEC_SSE_CFG_3343043971";
            description = "$STR_COMSPEC_SSE_CFG_54F61CC779";
            tip = "$STR_COMSPEC_SSE_CFG_9F02B44AA2";
            arguments[] = {};
            image = HINT_IMG;
            noImage = 0;
        };
        class Digital {
            displayName = "$STR_COMSPEC_SSE_CFG_9FA7DC4B0C";
            displayNameShort = "$STR_COMSPEC_SSE_CFG_BFFAB189C7";
            description = "$STR_COMSPEC_SSE_CFG_A873796D42";
            tip = "$STR_COMSPEC_SSE_CFG_A46EA4BB22";
            arguments[] = {};
            image = HINT_IMG;
            noImage = 0;
        };
        class Campaigns {
            displayName = "$STR_COMSPEC_SSE_CFG_EF527E85F0";
            displayNameShort = "$STR_COMSPEC_SSE_CFG_EF527E85F0";
            description = "$STR_COMSPEC_SSE_CFG_0DA4135C9F";
            tip = "$STR_COMSPEC_SSE_CFG_37316FE63F";
            arguments[] = {};
            image = HINT_IMG;
            noImage = 0;
        };
        class Eden {
            displayName = "$STR_COMSPEC_SSE_CFG_D34119DF2D";
            displayNameShort = "$STR_COMSPEC_SSE_CFG_770CB7BEB2";
            description = "$STR_COMSPEC_SSE_CFG_F42128454E";
            tip = "$STR_COMSPEC_SSE_CFG_E01A8ED6C5";
            arguments[] = {};
            image = HINT_IMG;
            noImage = 0;
        };
        class Zeus {
            displayName = "$STR_COMSPEC_SSE_CFG_0F926D953A";
            displayNameShort = "$STR_COMSPEC_SSE_CFG_0093EDC3AB";
            description = "$STR_COMSPEC_SSE_CFG_6E79CF4CEF";
            tip = "$STR_COMSPEC_SSE_CFG_1F35AA56A1";
            arguments[] = {};
            image = HINT_IMG;
            noImage = 0;
        };
        class Custom {
            displayName = "$STR_COMSPEC_SSE_CFG_385FD78DF8";
            displayNameShort = "$STR_COMSPEC_SSE_CFG_385FD78DF8";
            description = "$STR_COMSPEC_SSE_CFG_763969454F";
            tip = "$STR_COMSPEC_SSE_CFG_32875EE6F1";
            arguments[] = {};
            image = HINT_IMG;
            noImage = 0;
        };
        class Datasets {
            displayName = "$STR_COMSPEC_SSE_CFG_A917899E61";
            displayNameShort = "$STR_COMSPEC_SSE_CFG_93A7F22476";
            description = "$STR_COMSPEC_SSE_CFG_099DA36626";
            tip = "$STR_COMSPEC_SSE_CFG_56C051C840";
            arguments[] = {};
            image = HINT_IMG;
            noImage = 0;
        };
        class CaseRef {
            displayName = "$STR_COMSPEC_SSE_CFG_EA0F6EC944";
            displayNameShort = "$STR_COMSPEC_SSE_CFG_834EA30E42";
            description = "$STR_COMSPEC_SSE_CFG_85EE05467D";
            tip = "$STR_COMSPEC_SSE_CFG_8F9638520A";
            arguments[] = {};
            image = HINT_IMG;
            noImage = 0;
        };
        class ModMission {
            displayName = "$STR_COMSPEC_SSE_CFG_D5FAA07334";
            displayNameShort = "$STR_COMSPEC_SSE_CFG_C9084946F5";
            description = "$STR_COMSPEC_SSE_CFG_8767304505";
            tip = "$STR_COMSPEC_SSE_CFG_086D7D3EAA";
            arguments[] = {};
            image = HINT_IMG;
            noImage = 0;
        };
        class ModPeople {
            displayName = "$STR_COMSPEC_SSE_CFG_F6800D38C9";
            displayNameShort = "$STR_COMSPEC_SSE_CFG_EB3DCC5483";
            description = "$STR_COMSPEC_SSE_CFG_BB03B66639";
            tip = "$STR_COMSPEC_SSE_CFG_437FA69F3E";
            arguments[] = {};
            image = HINT_IMG;
            noImage = 0;
        };
        class ModDigital {
            displayName = "$STR_COMSPEC_SSE_CFG_C3B4425DDB";
            displayNameShort = "$STR_COMSPEC_SSE_CFG_0A7C3A5AB0";
            description = "$STR_COMSPEC_SSE_CFG_E272B72F39";
            tip = "$STR_COMSPEC_SSE_CFG_439C4B448B";
            arguments[] = {};
            image = HINT_IMG;
            noImage = 0;
        };
        class ModTools {
            displayName = "$STR_COMSPEC_SSE_CFG_F7CD61CB3B";
            displayNameShort = "$STR_COMSPEC_SSE_CFG_8DF66CF5F5";
            description = "$STR_COMSPEC_SSE_CFG_A5427D36D6";
            tip = "$STR_COMSPEC_SSE_CFG_6B1C0F1BB5";
            arguments[] = {};
            image = HINT_IMG;
            noImage = 0;
        };
        class Documents {
            displayName = "$STR_COMSPEC_SSE_CFG_DF90578A65";
            displayNameShort = "$STR_COMSPEC_SSE_CFG_C513967CEA";
            description = "$STR_COMSPEC_SSE_CFG_EAFF9C49E6";
            tip = "$STR_COMSPEC_SSE_CFG_1C1FD222F7";
            arguments[] = {};
            image = HINT_IMG;
            noImage = 0;
        };
        class Items {
            displayName = "$STR_COMSPEC_SSE_CFG_0266B57201";
            displayNameShort = "$STR_COMSPEC_SSE_CFG_C39E887B1D";
            description = "$STR_COMSPEC_SSE_CFG_DE3BF4BD58";
            tip = "$STR_COMSPEC_SSE_CFG_FFF57E16D1";
            arguments[] = {};
            image = HINT_IMG;
            noImage = 0;
        };
        class Models {
            displayName = "$STR_COMSPEC_SSE_CFG_144B2D2353";
            displayNameShort = "$STR_COMSPEC_SSE_CFG_0F7183BE84";
            description = "$STR_COMSPEC_SSE_CFG_C527E20D98";
            tip = "$STR_COMSPEC_SSE_CFG_EFFB33E883";
            arguments[] = {};
            image = HINT_IMG;
            noImage = 0;
        };
    };
};

// Barre de menus d'Eden : COMSPEC SSE.
class ctrlMenuStrip;
class Display3DEN {
    class Controls {
        class MenuStrip: ctrlMenuStrip {
            class Items {
                items[] += {"COMSPEC_SSE_Menu"};
                class COMSPEC_SSE_Menu {
                    text = "COMSPEC SSE";
                    items[] = {"COMSPEC_SSE_MenuAthena", "COMSPEC_SSE_MenuDashboard", "COMSPEC_SSE_MenuEden", "COMSPEC_SSE_MenuStart", "COMSPEC_SSE_MenuLinks", "COMSPEC_SSE_MenuCampaigns", "COMSPEC_SSE_MenuZeus", "COMSPEC_SSE_MenuDatasets", "COMSPEC_SSE_MenuCaseRef", "COMSPEC_SSE_MenuModMission", "COMSPEC_SSE_MenuModPeople", "COMSPEC_SSE_MenuModDigital", "COMSPEC_SSE_MenuModTools", "COMSPEC_SSE_MenuDocuments", "COMSPEC_SSE_MenuItems", "COMSPEC_SSE_MenuModels", "COMSPEC_SSE_MenuCustom"};
                };
                class COMSPEC_SSE_MenuAthena { text = "$STR_COMSPEC_SSE_CFG_79E31EE88D"; action = "['open'] call comspec_sse_fnc_athenaEden"; };
                class COMSPEC_SSE_MenuDashboard { text = "$STR_COMSPEC_SSE_CFG_519542D0B0"; action = "[] call comspec_sse_fnc_openDashboard"; };
                class COMSPEC_SSE_MenuEden { text = "$STR_COMSPEC_SSE_CFG_D2BC59B49C"; action = "['Eden'] call comspec_sse_fnc_openGuide"; };
                class COMSPEC_SSE_MenuStart { text = "$STR_COMSPEC_SSE_CFG_20FDBBF21A"; action = "['Start'] call comspec_sse_fnc_openGuide"; };
                class COMSPEC_SSE_MenuLinks { text = "$STR_COMSPEC_SSE_CFG_DB6388D385"; action = "['Links'] call comspec_sse_fnc_openGuide"; };
                class COMSPEC_SSE_MenuCampaigns { text = "$STR_COMSPEC_SSE_CFG_EF527E85F0"; action = "['Campaigns'] call comspec_sse_fnc_openGuide"; };
                class COMSPEC_SSE_MenuZeus { text = "$STR_COMSPEC_SSE_CFG_0F926D953A"; action = "['Zeus'] call comspec_sse_fnc_openGuide"; };
                class COMSPEC_SSE_MenuDatasets { text = "$STR_COMSPEC_SSE_CFG_A917899E61"; action = "['Datasets'] call comspec_sse_fnc_openGuide"; };
                class COMSPEC_SSE_MenuCaseRef { text = "$STR_COMSPEC_SSE_CFG_EA0F6EC944"; action = "['CaseRef'] call comspec_sse_fnc_openGuide"; };
                class COMSPEC_SSE_MenuModMission { text = "$STR_COMSPEC_SSE_CFG_D5FAA07334"; action = "['ModMission'] call comspec_sse_fnc_openGuide"; };
                class COMSPEC_SSE_MenuModPeople { text = "$STR_COMSPEC_SSE_CFG_F6800D38C9"; action = "['ModPeople'] call comspec_sse_fnc_openGuide"; };
                class COMSPEC_SSE_MenuModDigital { text = "$STR_COMSPEC_SSE_CFG_C3B4425DDB"; action = "['ModDigital'] call comspec_sse_fnc_openGuide"; };
                class COMSPEC_SSE_MenuModTools { text = "$STR_COMSPEC_SSE_CFG_F7CD61CB3B"; action = "['ModTools'] call comspec_sse_fnc_openGuide"; };
                class COMSPEC_SSE_MenuDocuments { text = "$STR_COMSPEC_SSE_CFG_DF90578A65"; action = "['Documents'] call comspec_sse_fnc_openGuide"; };
                class COMSPEC_SSE_MenuItems { text = "$STR_COMSPEC_SSE_CFG_0266B57201"; action = "['Items'] call comspec_sse_fnc_openGuide"; };
                class COMSPEC_SSE_MenuModels { text = "$STR_COMSPEC_SSE_CFG_144B2D2353"; action = "['Models'] call comspec_sse_fnc_openGuide"; };
                class COMSPEC_SSE_MenuCustom { text = "$STR_COMSPEC_SSE_CFG_385FD78DF8"; action = "['Custom'] call comspec_sse_fnc_openGuide"; };
            };
        };
    };
};

// Temps passé dans Eden (comspec_sse_fnc_athenaEden) : le compteur démarre avec l'éditeur et reprend après un aperçu ;
// le temps gardé part sur Athena au lancement d'un aperçu.
class Cfg3DEN {
    class EventHandlers {
        class comspec_sse_guide {
            onTerrainNew = "['track'] call comspec_sse_fnc_athenaEden";
            onMissionNew = "['track'] call comspec_sse_fnc_athenaEden";
            onMissionLoad = "['track'] call comspec_sse_fnc_athenaEden";
            onMissionPreviewEnd = "['track'] call comspec_sse_fnc_athenaEden";
            onMissionPreview = "['flush'] call comspec_sse_fnc_athenaEden";
        };
    };
};
