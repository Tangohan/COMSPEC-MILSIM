    class COMSPEC_Module_AtakRelay: Module_F
    {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 0;
        displayName = "Relais ATAK (mât)";
        category = "COMSPEC_Roleplay";
        function = "comspec_overwatch_connect_fnc_moduleAtakRelay";
        functionPriority = 1;
        isGlobal = 1;
        isTriggerActivated = 0;
        isDisposable = 0;
        is3DEN = 1;
        curatorCanAttach = 0;
        icon = "\A3\ui_f\data\map\markers\military\flag_CA.paa";
        portrait = "\A3\ui_f\data\map\markers\military\flag_CA.paa";

        class Attributes
        {
            class RelayName
            {
                displayName = "Nom du relais";
                tooltip = "Libellé visible au poste (Relais Nord, Relais LZ…).";
                property = "COMSPEC_AtakRelay_Name";
                control = "Edit";
                expression = "_this setVariable ['RelayName',_value,true];";
                defaultValue = """Relais ATAK""";
                typeName = "STRING";
            };
            class RangeM
            {
                displayName = "Portée (m)";
                tooltip = "Rayon dans lequel un téléphone peut s’appuyer sur ce relais.";
                property = "COMSPEC_AtakRelay_Range";
                control = "Edit";
                expression = "_this setVariable ['RangeM',_value,true];";
                defaultValue = "2000";
                validate = "number";
                typeName = "NUMBER";
            };
        };

        class Arguments
        {
            class RelayName
            {
                displayName = "Nom du relais";
                description = "Libellé visible au poste";
                typeName = "STRING";
                defaultValue = "Relais ATAK";
            };
            class RangeM
            {
                displayName = "Portée (m)";
                description = "Rayon de liaison du relais";
                typeName = "NUMBER";
                defaultValue = "2000";
            };
        };

        class ModuleDescription
        {
            description = "Pose un mât relais détruisible. En mode « via relais », hors de portée d’un mât intact, le téléphone ne transmet plus vers le poste.";
            position = 1;
            direction = 0;
            optional = 1;
            duplicate = 1;
            synced[] = {};
        };
    };
