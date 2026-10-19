/*
    Module Eden : relevé des routes et des villes seulement (fn_sampleGeoNetwork), sans bâtiments ni relief.
    Zeus : module « Relever routes et villes » par Zeus Enhanced / ACE Zeus (fn_registerZenTheaterSurvey).
    Inclus dans le seul class CfgVehicles (config.cpp).
*/

    class COMSPEC_Module_GeoNetwork: Module_F
    {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 0;
        category = "COMSPEC_Outils";
        displayName = "$STR_COMSPEC_OW_CFG_DEEF9340A8";
        function = "comspec_overwatch_connect_fnc_moduleGeoNetwork";
        functionPriority = 1;
        isGlobal = 1;
        isTriggerActivated = 0;
        isDisposable = 1;
        is3DEN = 0;
        curatorCanAttach = 0;
        canSetArea = 0;
        canSetAreaHeight = 0;
        canSetAreaShape = 0;
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\move_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\move_ca.paa";

        class Attributes
        {
            class RunAtStart
            {
                displayName = "$STR_COMSPEC_OW_CFG_0A8F3CF162";
                tooltip = "$STR_COMSPEC_OW_CFG_CF11B05A6E";
                property = "COMSPEC_GeoNetwork_RunAtStart";
                control = "Combo";
                expression = "_this setVariable ['RunAtStart',_value,true];";
                defaultValue = "'start'";
                typeName = "STRING";

                class Values
                {
                    class Start { name = "$STR_COMSPEC_OW_CFG_DEDDC0711F"; value = "start"; default = 1; };
                    class Manual { name = "$STR_COMSPEC_OW_CFG_3B201F65E4"; value = "manual"; };
                };
            };
            class Resend
            {
                displayName = "$STR_COMSPEC_OW_CFG_1E73219ED6";
                tooltip = "$STR_COMSPEC_OW_CFG_BE3E044D54";
                property = "COMSPEC_GeoNetwork_Resend";
                control = "Checkbox";
                expression = "_this setVariable ['Resend',_value,true];";
                defaultValue = "false";
                typeName = "BOOL";
            };
        };

        class ModuleDescription
        {
            description = "$STR_COMSPEC_OW_CFG_70DEE56AF4";
            position = 0;
            direction = 0;
            optional = 1;
            duplicate = 0;
        };
    };
