/*
    Module Eden : afficher ou masquer les IA ennemies sur le poste ATAK.

    Zeus passe par Zeus Enhanced (scopeCurator = 0).
    Inclus dans un seul class CfgVehicles (config.cpp).
*/

    class COMSPEC_Module_AtakShowEnemyAi: Module_F
    {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 0;
        category = "COMSPEC_Outils";
        displayName = "$STR_COMSPEC_OW_CFG_C2A0D7DA8A";
        function = "comspec_overwatch_connect_fnc_moduleAtakShowEnemyAi";
        functionPriority = 1;
        isGlobal = 1;
        isTriggerActivated = 0;
        isDisposable = 1;
        is3DEN = 1;
        curatorCanAttach = 0;
        canSetArea = 0;
        canSetAreaHeight = 0;
        canSetAreaShape = 0;
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\attack_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\attack_ca.paa";

        class Attributes
        {
            class ShowAtStart
            {
                displayName = "$STR_COMSPEC_OW_CFG_6FA6AB6924";
                tooltip = "$STR_COMSPEC_OW_CFG_7857339678";
                property = "COMSPEC_AtakShowEnemyAi_ShowAtStart";
                control = "Combo";
                expression = "_this setVariable ['ShowAtStart',_value,true];";
                defaultValue = "'hidden'";
                typeName = "STRING";

                class Values
                {
                    class Hidden { name = "$STR_COMSPEC_OW_CFG_C9D13447C8"; value = "hidden"; default = 1; };
                    class Show { name = "$STR_COMSPEC_OW_CFG_8179936E65"; value = "show"; };
                };
            };
        };

        class Arguments
        {
            class ShowAtStart
            {
                displayName = "$STR_COMSPEC_OW_CFG_6FA6AB6924";
                description = "$STR_COMSPEC_OW_CFG_80F9B4B19B";
                typeName = "STRING";
                defaultValue = "hidden";
                class values
                {
                    class Hidden { name = "$STR_COMSPEC_OW_CFG_C9D13447C8"; value = "hidden"; default = 1; };
                    class Show { name = "$STR_COMSPEC_OW_CFG_8179936E65"; value = "show"; };
                };
            };
        };

        class ModuleDescription
        {
            description = "$STR_COMSPEC_OW_CFG_B1E2532872";
            position = 0;
            direction = 0;
            optional = 1;
            duplicate = 0;
        };
    };
