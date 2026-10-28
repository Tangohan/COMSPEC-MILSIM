/*
    Module Eden : relevé complet du théâtre (bâtiments, forêts, relief).

    Zeus passe par Zeus Enhanced (scopeCurator = 0), comme les modules roleplay / SSE.
    Inclus dans un seul class CfgVehicles (config.cpp).
*/

    class COMSPEC_Module_TheaterSurvey: Module_F
    {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 0;
        category = "COMSPEC_Outils";
        displayName = "$STR_COMSPEC_OW_CFG_23009D76F3";
        function = "comspec_overwatch_connect_fnc_moduleTheaterSurvey";
        functionPriority = 1;
        isGlobal = 1;
        isTriggerActivated = 0;
        isDisposable = 1;
        is3DEN = 1;
        curatorCanAttach = 0;
        canSetArea = 0;
        canSetAreaHeight = 0;
        canSetAreaShape = 0;
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\map_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\map_ca.paa";

        class Attributes
        {
            class RunAtStart
            {
                displayName = "$STR_COMSPEC_OW_CFG_0A8F3CF162";
                tooltip = "$STR_COMSPEC_OW_CFG_7CE39D8F2A";
                property = "COMSPEC_TheaterSurvey_RunAtStart";
                control = "Combo";
                expression = "_this setVariable ['RunAtStart',_value,true];";
                defaultValue = "'manual'";
                typeName = "STRING";

                class Values
                {
                    class Manual { name = "$STR_COMSPEC_OW_CFG_F3B177121B"; value = "manual"; default = 1; };
                    class Start { name = "$STR_COMSPEC_OW_CFG_104A74EE1A"; value = "start"; };
                };
            };
        };

        class Arguments
        {
            class RunAtStart
            {
                displayName = "$STR_COMSPEC_OW_CFG_0A8F3CF162";
                description = "$STR_COMSPEC_OW_CFG_1B837A0BAC";
                typeName = "STRING";
                defaultValue = "manual";
                class values
                {
                    class Manual { name = "$STR_COMSPEC_OW_CFG_F3B177121B"; value = "manual"; default = 1; };
                    class Start { name = "$STR_COMSPEC_OW_CFG_104A74EE1A"; value = "start"; };
                };
            };
        };

        class ModuleDescription
        {
            description = "$STR_COMSPEC_OW_CFG_EA54D6AD87";
            position = 0;
            direction = 0;
            optional = 1;
            duplicate = 0;
        };
    };
