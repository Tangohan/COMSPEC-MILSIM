/*
    Modules Eden repris du mod Overwatch (mêmes classes : les missions déjà faites
    continuent de les charger). Tout le SSE passe désormais par ce pack.

      · Dossier SSE actif      → masqué, doublon de « Référence de dossier SSE »
      · Identité d'un sujet    → ce que la requête rendra pour les personnes synchronisées
      · Doter en terminal SEEK → SEEK d'Overwatch s'il est chargé, sinon SEEK II du pack
      · Présentation des documents

    Inclus dans le CfgVehicles de config.cpp — ne pas rouvrir CfgVehicles ici.
*/

    class COMSPEC_Module_SSE_Base: Module_F
    {
        author = "COMSPEC";
        scope = 1;
        scopeCurator = 0;
        category = "COMSPEC_SSE";
        functionPriority = 1;
        isGlobal = 1;
        isTriggerActivated = 0;
        isDisposable = 1;
        is3DEN = 1;
        curatorCanAttach = 1;
        canSetArea = 0;
        canSetAreaHeight = 0;
        canSetAreaShape = 0;
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\intel_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\intel_ca.paa";
    };

    class COMSPEC_Module_SSE_Case: COMSPEC_Module_SSE_Base
    {
        scope = 1;
        scopeCurator = 0;
        category = "COMSPEC_SSE_Mission";
        displayName = "$STR_COMSPEC_SSE_CFG_08EFE8648A";
        function = "comspec_sse_fnc_moduleCaseReference";

        class Attributes
        {
            class Reference
            {
                displayName = "$STR_COMSPEC_SSE_CFG_B37A519E31";
                tooltip = "$STR_COMSPEC_SSE_CFG_579AC7FBD5";
                property = "COMSPEC_SSE_Case_Reference";
                control = "Edit";
                expression = "_this setVariable ['Reference',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
        };

        class Arguments
        {
            class Reference
            {
                displayName = "$STR_COMSPEC_SSE_CFG_B37A519E31";
                description = "$STR_COMSPEC_SSE_CFG_5CAC4AD624";
                typeName = "STRING";
                defaultValue = "";
            };
        };

        class ModuleDescription
        {
            description = "$STR_COMSPEC_SSE_CFG_62652263FE";
            position = 0;
            direction = 0;
            optional = 1;
            duplicate = 1;
        };
    };

    class COMSPEC_Module_SSE_Profile: COMSPEC_Module_SSE_Base
    {
        scope = 2;
        scopeCurator = 0;
        category = "COMSPEC_SSE_People";
        displayName = "$STR_COMSPEC_SSE_CFG_2D8C8F1C55";
        function = "comspec_sse_fnc_moduleSubjectProfile";

        class Attributes
        {
            class Preset
            {
                displayName = "$STR_COMSPEC_SSE_CFG_144C58520E";
                tooltip = "$STR_COMSPEC_SSE_CFG_E22995322F";
                property = "COMSPEC_SSE_Profile_Preset";
                control = "Combo";
                expression = "[_this, 'Preset', _value] call comspec_sse_fnc_edenWriteField;";
                defaultValue = "'auto'";
                typeName = "STRING";

                class values
                {
                    class Auto        { name = "$STR_COMSPEC_SSE_CFG_A3D3991651";        value = "auto"; default = 1; };
                    class Inconnu     { name = "$STR_COMSPEC_SSE_CFG_D73F31553D";                      value = "inconnu"; };
                    class Signale     { name = "$STR_COMSPEC_SSE_CFG_E4E69F4E5C";     value = "signale"; };
                    class Recherche   { name = "$STR_COMSPEC_SSE_CFG_43C9CA349C";   value = "recherche"; };
                };
            };

            class LastName
            {
                displayName = "$STR_COMSPEC_SSE_CFG_7FF20370C8";
                tooltip = "$STR_COMSPEC_SSE_CFG_BE68E61364";
                property = "COMSPEC_SSE_Profile_LastName";
                control = "Edit";
                expression = "[_this, 'LastName', _value] call comspec_sse_fnc_edenWriteField;";
                defaultValue = "''";
                typeName = "STRING";
            };

            class FirstName
            {
                displayName = "$STR_COMSPEC_SSE_CFG_E4387ADE0F";
                tooltip = "$STR_COMSPEC_SSE_CFG_FD57B2EF48";
                property = "COMSPEC_SSE_Profile_FirstName";
                control = "Edit";
                expression = "[_this, 'FirstName', _value] call comspec_sse_fnc_edenWriteField;";
                defaultValue = "''";
                typeName = "STRING";
            };

            class Alias
            {
                displayName = "$STR_COMSPEC_SSE_CFG_A48482A2F9";
                tooltip = "$STR_COMSPEC_SSE_CFG_1FC4EAC8DB";
                property = "COMSPEC_SSE_Profile_Alias";
                control = "Edit";
                expression = "[_this, 'Alias', _value] call comspec_sse_fnc_edenWriteField;";
                defaultValue = "''";
                typeName = "STRING";
            };

            class Nationality
            {
                displayName = "$STR_COMSPEC_SSE_CFG_E3E921B379";
                tooltip = "$STR_COMSPEC_SSE_CFG_5CE9A82831";
                property = "COMSPEC_SSE_Profile_Nationality";
                control = "Edit";
                expression = "[_this, 'Nationality', _value] call comspec_sse_fnc_edenWriteField;";
                defaultValue = "''";
                typeName = "STRING";
            };

            class Language
            {
                displayName = "$STR_COMSPEC_SSE_CFG_8CED3222ED";
                tooltip = "$STR_COMSPEC_SSE_CFG_81740FA951";
                property = "COMSPEC_SSE_Profile_Language";
                control = "Edit";
                expression = "[_this, 'Language', _value] call comspec_sse_fnc_edenWriteField;";
                defaultValue = "''";
                typeName = "STRING";
            };

            class RecordRef
            {
                displayName = "$STR_COMSPEC_SSE_CFG_437A61441B";
                tooltip = "$STR_COMSPEC_SSE_CFG_7FAF9B4872";
                property = "COMSPEC_SSE_Profile_RecordRef";
                control = "Edit";
                expression = "[_this, 'RecordRef', _value] call comspec_sse_fnc_edenWriteField;";
                defaultValue = "''";
                typeName = "STRING";
            };

            class Seed
            {
                displayName = "$STR_COMSPEC_SSE_CFG_15D618B854";
                tooltip = "$STR_COMSPEC_SSE_CFG_C881C96E3C";
                property = "COMSPEC_SSE_Profile_Seed";
                control = "Edit";
                expression = "[_this, 'Seed', _value] call comspec_sse_fnc_edenWriteField;";
                defaultValue = "0";
                validate = "number";
                typeName = "NUMBER";
            };
        };

        class Arguments
        {
            class Preset
            {
                displayName = "$STR_COMSPEC_SSE_CFG_144C58520E";
                description = "$STR_COMSPEC_SSE_CFG_4463EFBA0C";
                typeName = "STRING";
                defaultValue = "auto";
            };
            class Alias
            {
                displayName = "$STR_COMSPEC_SSE_CFG_A48482A2F9";
                description = "$STR_COMSPEC_SSE_CFG_E488F0F130";
                typeName = "STRING";
                defaultValue = "";
            };
        };

        class ModuleDescription
        {
            description = "$STR_COMSPEC_SSE_CFG_17890A8F23";
            position = 0;
            direction = 0;
            optional = 1;
            duplicate = 1;
            synced[] = {"AnyPerson"};
        };
    };

    class COMSPEC_Module_SSE_Equip: COMSPEC_Module_SSE_Base
    {
        scope = 2;
        scopeCurator = 0;
        category = "COMSPEC_SSE_Mission";
        displayName = "$STR_COMSPEC_SSE_CFG_74968C997F";
        function = "comspec_sse_fnc_moduleEquipSeek";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\documents_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\documents_ca.paa";

        class Attributes
        {
            class Radius
            {
                displayName = "$STR_COMSPEC_SSE_CFG_DEE8290EC3";
                tooltip = "$STR_COMSPEC_SSE_CFG_93B9028CAE";
                property = "COMSPEC_SSE_Equip_Radius";
                control = "Edit";
                expression = "_this setVariable ['Radius',_value,true];";
                defaultValue = "25";
                validate = "number";
                typeName = "NUMBER";
            };
        };

        class Arguments
        {
            class Radius
            {
                displayName = "$STR_COMSPEC_SSE_CFG_DEE8290EC3";
                description = "$STR_COMSPEC_SSE_CFG_F5745B35D6";
                typeName = "NUMBER";
                defaultValue = 25;
            };
        };

        class ModuleDescription
        {
            description = "$STR_COMSPEC_SSE_CFG_E4737DAB40";
            position = 0;
            direction = 0;
            optional = 1;
            duplicate = 1;
            synced[] = {"AnyPerson"};
        };
    };

    class COMSPEC_Module_SSE_DocChrome: COMSPEC_Module_SSE_Base
    {
        scope = 2;
        scopeCurator = 0;
        category = "COMSPEC_SSE_Mission";
        displayName = "$STR_COMSPEC_SSE_CFG_C3162D0B05";
        function = "comspec_sse_fnc_moduleDocChrome";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\documents_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\documents_ca.paa";

        class Attributes
        {
            class Prefab
            {
                displayName = "$STR_COMSPEC_SSE_CFG_A351AF061A";
                tooltip = "$STR_COMSPEC_SSE_CFG_1941DB564B";
                property = "COMSPEC_SSE_DocChrome_Prefab";
                control = "Combo";
                expression = "_this setVariable ['Prefab',_value,true];";
                defaultValue = "'standard_restreint'";
                typeName = "STRING";
                class values
                {
                    class Standard { name = "$STR_COMSPEC_SSE_CFG_010090159B"; value = "standard_restreint"; default = 1; };
                    class Terrain { name = "$STR_COMSPEC_SSE_CFG_E7B1CF1A3D"; value = "terrain_tache"; };
                    class Brouillon { name = "$STR_COMSPEC_SSE_CFG_8285B56EE8"; value = "brouillon_froisse"; };
                    class Archives { name = "$STR_COMSPEC_SSE_CFG_EBF0353DB8"; value = "bureau_jauni"; };
                    class Formel { name = "$STR_COMSPEC_SSE_CFG_EF61D20F94"; value = "formel_propre"; };
                };
            };
            class PaperStyle
            {
                displayName = "$STR_COMSPEC_SSE_CFG_38EF9DD8EF";
                tooltip = "$STR_COMSPEC_SSE_CFG_D5AA87A3D9";
                property = "COMSPEC_SSE_DocChrome_PaperStyle";
                control = "Combo";
                expression = "_this setVariable ['PaperStyle',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
                class values
                {
                    class Keep { name = "$STR_COMSPEC_SSE_CFG_55A168F759"; value = ""; default = 1; };
                    class Clean { name = "$STR_COMSPEC_SSE_CFG_4462C38458"; value = "clean"; };
                    class Stained { name = "$STR_COMSPEC_SSE_CFG_4B1188BA94"; value = "stained"; };
                    class Crumpled { name = "$STR_COMSPEC_SSE_CFG_6F8F4AE415"; value = "crumpled"; };
                    class Aged { name = "$STR_COMSPEC_SSE_CFG_1D6C2F3BA3"; value = "aged"; };
                };
            };
            class Banner
            {
                displayName = "$STR_COMSPEC_SSE_CFG_D64D8E38B3";
                tooltip = "$STR_COMSPEC_SSE_CFG_B3D29703D1";
                property = "COMSPEC_SSE_DocChrome_Banner";
                control = "Edit";
                expression = "_this setVariable ['Banner',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class TitlePerson
            {
                displayName = "$STR_COMSPEC_SSE_CFG_50EB4CF8B0";
                tooltip = "$STR_COMSPEC_SSE_CFG_04599608E2";
                property = "COMSPEC_SSE_DocChrome_TitlePerson";
                control = "Edit";
                expression = "_this setVariable ['TitlePerson',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class TitleDocs
            {
                displayName = "$STR_COMSPEC_SSE_CFG_865E727F4F";
                tooltip = "$STR_COMSPEC_SSE_CFG_510609F529";
                property = "COMSPEC_SSE_DocChrome_TitleDocs";
                control = "Edit";
                expression = "_this setVariable ['TitleDocs',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class SubtitleDossier
            {
                displayName = "$STR_COMSPEC_SSE_CFG_6ABCDD2502";
                tooltip = "$STR_COMSPEC_SSE_CFG_4ECFD04A93";
                property = "COMSPEC_SSE_DocChrome_SubtitleDossier";
                control = "Edit";
                expression = "_this setVariable ['SubtitleDossier',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class Footer
            {
                displayName = "$STR_COMSPEC_SSE_CFG_09EAEE23DA";
                tooltip = "$STR_COMSPEC_SSE_CFG_54676EF14E";
                property = "COMSPEC_SSE_DocChrome_Footer";
                control = "Edit";
                expression = "_this setVariable ['Footer',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class BtnConsult
            {
                displayName = "$STR_COMSPEC_SSE_CFG_3484725616";
                tooltip = "$STR_COMSPEC_SSE_CFG_45581DDE0C";
                property = "COMSPEC_SSE_DocChrome_BtnConsult";
                control = "Edit";
                expression = "_this setVariable ['BtnConsult',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class BtnTransmit
            {
                displayName = "$STR_COMSPEC_SSE_CFG_DEABE47183";
                tooltip = "$STR_COMSPEC_SSE_CFG_05E0AFA220";
                property = "COMSPEC_SSE_DocChrome_BtnTransmit";
                control = "Edit";
                expression = "_this setVariable ['BtnTransmit',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class BtnClose
            {
                displayName = "$STR_COMSPEC_SSE_CFG_767FD5BDDA";
                tooltip = "$STR_COMSPEC_SSE_CFG_6D51BE0AA5";
                property = "COMSPEC_SSE_DocChrome_BtnClose";
                control = "Edit";
                expression = "_this setVariable ['BtnClose',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
        };

        class Arguments
        {
            class Prefab
            {
                displayName = "$STR_COMSPEC_SSE_CFG_A351AF061A";
                description = "$STR_COMSPEC_SSE_CFG_43E48B8E9C";
                typeName = "STRING";
                class values
                {
                    class Standard { name = "$STR_COMSPEC_SSE_CFG_010090159B"; value = "standard_restreint"; default = 1; };
                    class Terrain { name = "$STR_COMSPEC_SSE_CFG_E7B1CF1A3D"; value = "terrain_tache"; };
                    class Brouillon { name = "$STR_COMSPEC_SSE_CFG_8285B56EE8"; value = "brouillon_froisse"; };
                    class Archives { name = "$STR_COMSPEC_SSE_CFG_EBF0353DB8"; value = "bureau_jauni"; };
                    class Formel { name = "$STR_COMSPEC_SSE_CFG_EF61D20F94"; value = "formel_propre"; };
                };
            };
            class Footer
            {
                displayName = "$STR_COMSPEC_SSE_CFG_09EAEE23DA";
                description = "$STR_COMSPEC_SSE_CFG_1A05DB15DF";
                typeName = "STRING";
                defaultValue = "";
            };
        };

        class ModuleDescription
        {
            description = "$STR_COMSPEC_SSE_CFG_7C7ECA35F0";
            position = 0;
            direction = 0;
            optional = 1;
            duplicate = 0;
        };
    };
