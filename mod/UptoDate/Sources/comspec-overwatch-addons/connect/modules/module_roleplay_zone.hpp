/*
    Modules Zeus/Eden pour zones roleplay ATAK.

    IMPORTANT:
    - Ne PAS ouvrir / redéfinir Module_F:Logic (casse ACE Arsenal / ZEN — Updating base class).
    - Ne PAS hériter de AttributesBase si Module_F n’est que forward-déclaré
      (Undefined base class AttributesBase).
    → Attributes et ModuleDescription sont définis à plat (pattern sûr).
    Inclus dans un seul class CfgVehicles (config.cpp) — ne pas rouvrir CfgVehicles ici.
*/

    class COMSPEC_Module_RoleplayZone_Base: Module_F
    {
        author = "COMSPEC";
        scope = 1;
        // Zeus : uniquement via Zeus Enhanced (évite un menu vide si l’icône config échoue).
        scopeCurator = 0;
        category = "COMSPEC_Roleplay";
        functionPriority = 1;
        isGlobal = 1;
        isTriggerActivated = 0;
        isDisposable = 1;
        is3DEN = 1;
        curatorCanAttach = 1;
        canSetArea = 1;
        canSetAreaHeight = 0;
        canSetAreaShape = 0;
        icon = "\A3\ui_f\data\map\markers\military\warning_CA.paa";
        portrait = "\A3\ui_f\data\map\markers\military\warning_CA.paa";

        class AttributeValues
        {
            size3[] = {200, 200, -1};
            isRectangle = 0;
        };

        class Attributes
        {
            class Intensity
            {
                displayName = "$STR_COMSPEC_OW_CFG_67AAB8C24E";
                tooltip = "$STR_COMSPEC_OW_CFG_E55236682D";
                property = "COMSPEC_RoleplayZone_Intensity";
                control = "Edit";
                expression = "_this setVariable ['Intensity',_value,true];";
                defaultValue = "50";
                validate = "number";
                typeName = "NUMBER";
            };
        };

        class Arguments
        {
            class Intensity
            {
                displayName = "$STR_COMSPEC_OW_CFG_67AAB8C24E";
                description = "$STR_COMSPEC_OW_CFG_072BDDCC33";
                typeName = "NUMBER";
                defaultValue = "50";
            };
        };

        class ModuleDescription
        {
            description = "$STR_COMSPEC_OW_CFG_ADCE459A30";
            position = 1;
            direction = 0;
            optional = 1;
            duplicate = 1;
            synced[] = {"AnyPerson", "AnyVehicle"};
        };
    };

    class COMSPEC_Module_NoCoverage: COMSPEC_Module_RoleplayZone_Base
    {
        scope = 2;
        scopeCurator = 0;
        displayName = "$STR_COMSPEC_OW_CFG_8493539798";
        function = "comspec_overwatch_connect_fnc_moduleNoCoverage";
        icon = "\A3\ui_f\data\map\markers\military\objective_CA.paa";
        portrait = "\A3\ui_f\data\map\markers\military\objective_CA.paa";

        class AttributeValues
        {
            size3[] = {200, 200, -1};
            isRectangle = 0;
        };

        class Attributes
        {
            class Intensity
            {
                displayName = "$STR_COMSPEC_OW_CFG_67AAB8C24E";
                tooltip = "$STR_COMSPEC_OW_CFG_E9A09FA5A3";
                property = "COMSPEC_NoCoverage_Intensity";
                control = "Edit";
                expression = "_this setVariable ['Intensity',_value,true];";
                defaultValue = "100";
                validate = "number";
                typeName = "NUMBER";
            };
        };

        class Arguments
        {
            class Intensity
            {
                displayName = "$STR_COMSPEC_OW_CFG_67AAB8C24E";
                description = "$STR_COMSPEC_OW_CFG_A308257713";
                typeName = "NUMBER";
                defaultValue = "100";
            };
        };

        class ModuleDescription
        {
            description = "$STR_COMSPEC_OW_CFG_44F2573C13";
            position = 1;
            direction = 0;
            optional = 1;
            duplicate = 1;
            synced[] = {"AnyPerson", "AnyVehicle"};
        };
    };

    class COMSPEC_Module_Interference: COMSPEC_Module_RoleplayZone_Base
    {
        scope = 2;
        scopeCurator = 0;
        displayName = "$STR_COMSPEC_OW_CFG_EB163E4266";
        function = "comspec_overwatch_connect_fnc_moduleInterference";

        class AttributeValues
        {
            size3[] = {300, 300, -1};
            isRectangle = 0;
        };

        class Attributes
        {
            class Intensity
            {
                displayName = "$STR_COMSPEC_OW_CFG_67AAB8C24E";
                tooltip = "$STR_COMSPEC_OW_CFG_28412253F4";
                property = "COMSPEC_Interference_Intensity";
                control = "Edit";
                expression = "_this setVariable ['Intensity',_value,true];";
                defaultValue = "50";
                validate = "number";
                typeName = "NUMBER";
            };
        };

        class Arguments
        {
            class Intensity
            {
                displayName = "$STR_COMSPEC_OW_CFG_67AAB8C24E";
                description = "$STR_COMSPEC_OW_CFG_16E0AC21A7";
                typeName = "NUMBER";
                defaultValue = "50";
            };
        };

        class ModuleDescription
        {
            description = "$STR_COMSPEC_OW_CFG_F342A5E02A";
            position = 1;
            direction = 0;
            optional = 1;
            duplicate = 1;
            synced[] = {"AnyPerson", "AnyVehicle"};
        };
    };

    class COMSPEC_Module_Degraded: COMSPEC_Module_RoleplayZone_Base
    {
        scope = 2;
        scopeCurator = 0;
        displayName = "$STR_COMSPEC_OW_CFG_F0E580EDB4";
        function = "comspec_overwatch_connect_fnc_moduleDegraded";

        class AttributeValues
        {
            size3[] = {500, 500, -1};
            isRectangle = 0;
        };

        class Attributes
        {
            class Intensity
            {
                displayName = "$STR_COMSPEC_OW_CFG_67AAB8C24E";
                tooltip = "$STR_COMSPEC_OW_CFG_C5157629DF";
                property = "COMSPEC_Degraded_Intensity";
                control = "Edit";
                expression = "_this setVariable ['Intensity',_value,true];";
                defaultValue = "30";
                validate = "number";
                typeName = "NUMBER";
            };
        };

        class Arguments
        {
            class Intensity
            {
                displayName = "$STR_COMSPEC_OW_CFG_67AAB8C24E";
                description = "$STR_COMSPEC_OW_CFG_CBA64DC262";
                typeName = "NUMBER";
                defaultValue = "30";
            };
        };

        class ModuleDescription
        {
            description = "$STR_COMSPEC_OW_CFG_809A929057";
            position = 1;
            direction = 0;
            optional = 1;
            duplicate = 1;
            synced[] = {"AnyPerson", "AnyVehicle"};
        };
    };

    class COMSPEC_Module_Jammer: COMSPEC_Module_RoleplayZone_Base
    {
        scope = 2;
        scopeCurator = 0;
        displayName = "$STR_COMSPEC_OW_CFG_269ED66ECF";
        function = "comspec_overwatch_connect_fnc_moduleJammer";
        icon = "\A3\ui_f\data\map\markers\military\destroy_CA.paa";
        portrait = "\A3\ui_f\data\map\markers\military\destroy_CA.paa";

        class AttributeValues
        {
            size3[] = {400, 400, -1};
            isRectangle = 0;
        };

        class Attributes
        {
            class Intensity
            {
                displayName = "$STR_COMSPEC_OW_CFG_BDD02A65C7";
                tooltip = "$STR_COMSPEC_OW_CFG_D416786C6F";
                property = "COMSPEC_Jammer_Intensity";
                control = "Edit";
                expression = "_this setVariable ['Intensity',_value,true];";
                defaultValue = "80";
                validate = "number";
                typeName = "NUMBER";
            };
        };

        class Arguments
        {
            class Intensity
            {
                displayName = "$STR_COMSPEC_OW_CFG_BDD02A65C7";
                description = "$STR_COMSPEC_OW_CFG_42AAE408F8";
                typeName = "NUMBER";
                defaultValue = "80";
            };
        };

        class ModuleDescription
        {
            description = "$STR_COMSPEC_OW_CFG_A267933A82";
            position = 1;
            direction = 0;
            optional = 1;
            duplicate = 1;
            synced[] = {"AnyPerson", "AnyVehicle"};
        };
    };
