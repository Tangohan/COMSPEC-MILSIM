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
        scopeCurator = 1;
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
                displayName = "Intensité (%)";
                tooltip = "Force de l’effet réseau sur la liaison ATAK (0 à 100).";
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
                displayName = "Intensité (%)";
                description = "Force de l’effet réseau (0-100)";
                typeName = "NUMBER";
                defaultValue = "50";
            };
        };

        class ModuleDescription
        {
            description = "Zone roleplay ATAK";
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
        scopeCurator = 2;
        displayName = "Zone sans couverture ATAK";
        function = "comspec_overwatch_connect_fnc_moduleNoCoverage";
        icon = "\z\comspec_overwatch\addons\connect\img\overlays\comspec_icon_no_coverage_ca.png";
        portrait = "\z\comspec_overwatch\addons\connect\img\overlays\comspec_icon_no_coverage_ca.png";

        class AttributeValues
        {
            size3[] = {200, 200, -1};
            isRectangle = 0;
        };

        class Attributes
        {
            class Intensity
            {
                displayName = "Intensité (%)";
                tooltip = "Coupure totale de liaison (recommandé 100).";
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
                displayName = "Intensité (%)";
                description = "Coupure de liaison (0-100)";
                typeName = "NUMBER";
                defaultValue = "100";
            };
        };

        class ModuleDescription
        {
            description = "Zone où la liaison ATAK est totalement coupée. Posez sur la carte, un objet ou un joueur.";
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
        scopeCurator = 2;
        displayName = "Zone d'interférence ATAK";
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
                displayName = "Intensité (%)";
                tooltip = "Niveau d’interférence radio (pertes de paquets).";
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
                displayName = "Intensité (%)";
                description = "Intensité de l'interférence (0-100)";
                typeName = "NUMBER";
                defaultValue = "50";
            };
        };

        class ModuleDescription
        {
            description = "Zone avec forte interférence radio. Posez sur la carte, un objet ou un joueur.";
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
        scopeCurator = 2;
        displayName = "Zone de couverture dégradée";
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
                displayName = "Intensité (%)";
                tooltip = "Dégradation de la couverture (latence / pertes).";
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
                displayName = "Intensité (%)";
                description = "Intensité de la dégradation (0-100)";
                typeName = "NUMBER";
                defaultValue = "30";
            };
        };

        class ModuleDescription
        {
            description = "Zone de couverture dégradée. Posez sur la carte, un objet ou un joueur.";
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
        scopeCurator = 2;
        displayName = "Brouilleur ATAK actif";
        function = "comspec_overwatch_connect_fnc_moduleJammer";
        icon = "\z\comspec_overwatch\addons\connect\img\overlays\comspec_icon_jammer_ca.png";
        portrait = "\z\comspec_overwatch\addons\connect\img\overlays\comspec_icon_jammer_ca.png";

        class AttributeValues
        {
            size3[] = {400, 400, -1};
            isRectangle = 0;
        };

        class Attributes
        {
            class Intensity
            {
                displayName = "Puissance (%)";
                tooltip = "Puissance du brouilleur (coupures intermittentes).";
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
                displayName = "Puissance (%)";
                description = "Puissance du brouilleur (0-100)";
                typeName = "NUMBER";
                defaultValue = "80";
            };
        };

        class ModuleDescription
        {
            description = "Brouilleur radio actif. Posez sur la carte, un véhicule, un objet ou un joueur.";
            position = 1;
            direction = 0;
            optional = 1;
            duplicate = 1;
            synced[] = {"AnyPerson", "AnyVehicle"};
        };
    };
