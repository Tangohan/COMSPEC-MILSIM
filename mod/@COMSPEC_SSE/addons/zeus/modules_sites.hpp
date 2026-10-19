/*
    Modules « sites et kits » (Zeus et Eden) : sites préfabriqués, bâtiment peuplé, semis de preuves,
    réseau multi-sites, caisse de kit. En Zeus, formulaire vanilla à la pose (comspec_sse_fnc_uiForm) ;
    dans Eden, attributs ci-dessous. Création toujours sur le serveur (comspec_sse_fnc_siteRequest).

    Inclus dans le CfgVehicles de config.cpp — ne pas rouvrir CfgVehicles ici.
*/

    class COMSPEC_SSE_Module_PlaceSite: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_21CF681B20";
        category = "COMSPEC_SSE_Sites";
        function = "comspec_sse_fnc_modulePlaceSite";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\search_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\search_ca.paa";

        class Attributes {
            class SiteType {
                displayName = "$STR_COMSPEC_SSE_CFG_477589B159";
                tooltip = "$STR_COMSPEC_SSE_CFG_96BDE4A5DE";
                property = "COMSPEC_SSE_Module_PlaceSite_SiteType";
                control = "Combo";
                expression = "_this setVariable ['SiteType',_value,true];";
                defaultValue = "'SAFEHOUSE'";
                typeName = "STRING";
                class values {
                    class V0 { name = "$STR_COMSPEC_SSE_CFG_84775AE87C"; value = "SAFEHOUSE"; default = 1; };
                    class V1 { name = "$STR_COMSPEC_SSE_CFG_8960D4CA42"; value = "IED_LAB"; };
                    class V2 { name = "$STR_COMSPEC_SSE_CFG_5703B331E0"; value = "FORGERY"; };
                    class V3 { name = "$STR_COMSPEC_SSE_CFG_0C6C97D939"; value = "WEAPONS_CACHE"; };
                    class V4 { name = "$STR_COMSPEC_SSE_CFG_4007A61ED8"; value = "CELL_HQ"; };
                    class V5 { name = "$STR_COMSPEC_SSE_CFG_E7C960B706"; value = "DEAD_DROP"; };
                    class V6 { name = "$STR_COMSPEC_SSE_CFG_236C3EC690"; value = "HAWALA"; };
                };
            };
            class Occupants {
                displayName = "$STR_COMSPEC_SSE_CFG_DB18959EF2";
                tooltip = "$STR_COMSPEC_SSE_CFG_5D8296B420";
                property = "COMSPEC_SSE_Module_PlaceSite_Occupants";
                control = "Edit";
                expression = "_this setVariable ['Occupants',_value,true];";
                defaultValue = "-1";
                typeName = "NUMBER";
            };
            class Side {
                displayName = "$STR_COMSPEC_SSE_CFG_63DB4D7079";
                tooltip = "$STR_COMSPEC_SSE_CFG_7B21FB2E3F";
                property = "COMSPEC_SSE_Module_PlaceSite_Side";
                control = "Combo";
                expression = "_this setVariable ['Side',_value,true];";
                defaultValue = "'GUER'";
                typeName = "STRING";
                class values {
                    class V0 { name = "$STR_COMSPEC_SSE_CFG_3C7903BF6D"; value = "GUER"; default = 1; };
                    class V1 { name = "$STR_COMSPEC_SSE_CFG_FA79BDC9FF"; value = "EAST"; };
                    class V2 { name = "$STR_COMSPEC_SSE_CFG_7AA3BFB0ED"; value = "CIV"; };
                };
            };
            class Armed {
                displayName = "$STR_COMSPEC_SSE_CFG_9B52E497DD";
                tooltip = "$STR_COMSPEC_SSE_CFG_580E55558E";
                property = "COMSPEC_SSE_Module_PlaceSite_Armed";
                control = "Checkbox";
                expression = "_this setVariable ['Armed',_value,true];";
                defaultValue = "true";
                typeName = "BOOL";
            };
            class Complexity {
                displayName = "$STR_COMSPEC_SSE_CFG_684A67E212";
                tooltip = "$STR_COMSPEC_SSE_CFG_637DD26429";
                property = "COMSPEC_SSE_Module_PlaceSite_Complexity";
                control = "Combo";
                expression = "_this setVariable ['Complexity',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
                class values {
                    class V0 { name = "$STR_COMSPEC_SSE_CFG_4C8BDFF9D2"; value = ""; default = 1; };
                    class V1 { name = "$STR_COMSPEC_SSE_CFG_142CF342A2"; value = "LIGHT"; };
                    class V2 { name = "$STR_COMSPEC_SSE_CFG_2DFA66079D"; value = "STANDARD"; };
                    class V3 { name = "$STR_COMSPEC_SSE_CFG_094D4A1824"; value = "DETAILED"; };
                    class V4 { name = "$STR_COMSPEC_SSE_CFG_78AEB2046E"; value = "HIGH_VALUE"; };
                };
            };
            class Traps {
                displayName = "$STR_COMSPEC_SSE_CFG_8D69643C5D";
                tooltip = "$STR_COMSPEC_SSE_CFG_987B452302";
                property = "COMSPEC_SSE_Module_PlaceSite_Traps";
                control = "Checkbox";
                expression = "_this setVariable ['Traps',_value,true];";
                defaultValue = "false";
                typeName = "BOOL";
            };
            class UseBuilding {
                displayName = "$STR_COMSPEC_SSE_CFG_2FADDEA9A3";
                tooltip = "$STR_COMSPEC_SSE_CFG_7E693DDE9A";
                property = "COMSPEC_SSE_Module_PlaceSite_UseBuilding";
                control = "Checkbox";
                expression = "_this setVariable ['UseBuilding',_value,true];";
                defaultValue = "true";
                typeName = "BOOL";
            };
        };

        class ModuleDescription {
            description = "$STR_COMSPEC_SSE_CFG_C20D94C6EA";
            position = 1;
            direction = 1;
        };
    };

    class COMSPEC_SSE_Module_PopulateBuilding: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_744489D776";
        category = "COMSPEC_SSE_Sites";
        function = "comspec_sse_fnc_modulePopulateBuilding";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\house_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\house_ca.paa";

        class Attributes {
            class SiteType {
                displayName = "$STR_COMSPEC_SSE_CFG_477589B159";
                tooltip = "$STR_COMSPEC_SSE_CFG_3FF39FF030";
                property = "COMSPEC_SSE_Module_PopulateBuilding_SiteType";
                control = "Combo";
                expression = "_this setVariable ['SiteType',_value,true];";
                defaultValue = "'SAFEHOUSE'";
                typeName = "STRING";
                class values {
                    class V0 { name = "$STR_COMSPEC_SSE_CFG_84775AE87C"; value = "SAFEHOUSE"; default = 1; };
                    class V1 { name = "$STR_COMSPEC_SSE_CFG_8960D4CA42"; value = "IED_LAB"; };
                    class V2 { name = "$STR_COMSPEC_SSE_CFG_5703B331E0"; value = "FORGERY"; };
                    class V3 { name = "$STR_COMSPEC_SSE_CFG_0C6C97D939"; value = "WEAPONS_CACHE"; };
                    class V4 { name = "$STR_COMSPEC_SSE_CFG_4007A61ED8"; value = "CELL_HQ"; };
                    class V5 { name = "$STR_COMSPEC_SSE_CFG_236C3EC690"; value = "HAWALA"; };
                };
            };
            class Occupants {
                displayName = "$STR_COMSPEC_SSE_CFG_DB18959EF2";
                tooltip = "$STR_COMSPEC_SSE_CFG_5D8296B420";
                property = "COMSPEC_SSE_Module_PopulateBuilding_Occupants";
                control = "Edit";
                expression = "_this setVariable ['Occupants',_value,true];";
                defaultValue = "-1";
                typeName = "NUMBER";
            };
            class Side {
                displayName = "$STR_COMSPEC_SSE_CFG_63DB4D7079";
                tooltip = "$STR_COMSPEC_SSE_CFG_7B21FB2E3F";
                property = "COMSPEC_SSE_Module_PopulateBuilding_Side";
                control = "Combo";
                expression = "_this setVariable ['Side',_value,true];";
                defaultValue = "'GUER'";
                typeName = "STRING";
                class values {
                    class V0 { name = "$STR_COMSPEC_SSE_CFG_3C7903BF6D"; value = "GUER"; default = 1; };
                    class V1 { name = "$STR_COMSPEC_SSE_CFG_FA79BDC9FF"; value = "EAST"; };
                    class V2 { name = "$STR_COMSPEC_SSE_CFG_7AA3BFB0ED"; value = "CIV"; };
                };
            };
            class Armed {
                displayName = "$STR_COMSPEC_SSE_CFG_9B52E497DD";
                tooltip = "$STR_COMSPEC_SSE_CFG_580E55558E";
                property = "COMSPEC_SSE_Module_PopulateBuilding_Armed";
                control = "Checkbox";
                expression = "_this setVariable ['Armed',_value,true];";
                defaultValue = "true";
                typeName = "BOOL";
            };
            class Complexity {
                displayName = "$STR_COMSPEC_SSE_CFG_684A67E212";
                tooltip = "$STR_COMSPEC_SSE_CFG_637DD26429";
                property = "COMSPEC_SSE_Module_PopulateBuilding_Complexity";
                control = "Combo";
                expression = "_this setVariable ['Complexity',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
                class values {
                    class V0 { name = "$STR_COMSPEC_SSE_CFG_4C8BDFF9D2"; value = ""; default = 1; };
                    class V1 { name = "$STR_COMSPEC_SSE_CFG_142CF342A2"; value = "LIGHT"; };
                    class V2 { name = "$STR_COMSPEC_SSE_CFG_2DFA66079D"; value = "STANDARD"; };
                    class V3 { name = "$STR_COMSPEC_SSE_CFG_094D4A1824"; value = "DETAILED"; };
                    class V4 { name = "$STR_COMSPEC_SSE_CFG_78AEB2046E"; value = "HIGH_VALUE"; };
                };
            };
            class Traps {
                displayName = "$STR_COMSPEC_SSE_CFG_8D69643C5D";
                tooltip = "$STR_COMSPEC_SSE_CFG_F69385CF90";
                property = "COMSPEC_SSE_Module_PopulateBuilding_Traps";
                control = "Checkbox";
                expression = "_this setVariable ['Traps',_value,true];";
                defaultValue = "false";
                typeName = "BOOL";
            };
        };

        class ModuleDescription {
            description = "$STR_COMSPEC_SSE_CFG_770D7BB59C";
            position = 1;
        };
    };

    class COMSPEC_SSE_Module_SeedEvidence: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_3AF582D7C9";
        category = "COMSPEC_SSE_Sites";
        function = "comspec_sse_fnc_moduleSeedEvidence";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\documents_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\documents_ca.paa";

        class Attributes {
            class Radius {
                displayName = "$STR_COMSPEC_SSE_CFG_40BB5C6E81";
                tooltip = "$STR_COMSPEC_SSE_CFG_4CEA110634";
                property = "COMSPEC_SSE_Module_SeedEvidence_Radius";
                control = "Edit";
                expression = "_this setVariable ['Radius',_value,true];";
                defaultValue = "30";
                typeName = "NUMBER";
            };
            class Count {
                displayName = "$STR_COMSPEC_SSE_CFG_857F05E3FA";
                tooltip = "$STR_COMSPEC_SSE_CFG_CFCAF85D8C";
                property = "COMSPEC_SSE_Module_SeedEvidence_Count";
                control = "Edit";
                expression = "_this setVariable ['Count',_value,true];";
                defaultValue = "6";
                typeName = "NUMBER";
            };
            class Profile {
                displayName = "$STR_COMSPEC_SSE_CFG_669FE25A32";
                tooltip = "$STR_COMSPEC_SSE_CFG_1EB4119497";
                property = "COMSPEC_SSE_Module_SeedEvidence_Profile";
                control = "Combo";
                expression = "_this setVariable ['Profile',_value,true];";
                defaultValue = "'INSURGENT'";
                typeName = "STRING";
                class values {
                    class V0 { name = "$STR_COMSPEC_SSE_CFG_DA14187553"; value = "INSURGENT"; default = 1; };
                    class V1 { name = "$STR_COMSPEC_SSE_CFG_496B2073F0"; value = "CIVILIAN"; };
                    class V2 { name = "$STR_COMSPEC_SSE_CFG_1E0730DF18"; value = "MILITARY"; };
                    class V3 { name = "$STR_COMSPEC_SSE_CFG_21C1444A64"; value = "COMMANDER"; };
                    class V4 { name = "$STR_COMSPEC_SSE_CFG_6347EE1AA8"; value = "COURIER"; };
                    class V5 { name = "$STR_COMSPEC_SSE_CFG_8FAF1224A7"; value = "FINANCIER"; };
                    class V6 { name = "$STR_COMSPEC_SSE_CFG_E3CCC90C1A"; value = "TECHNICIAN"; };
                    class V7 { name = "$STR_COMSPEC_SSE_CFG_672B3D9978"; value = "INTELLIGENCE"; };
                    class V8 { name = "$STR_COMSPEC_SSE_CFG_8C266B946B"; value = "LOGISTICS"; };
                    class V9 { name = "$STR_COMSPEC_SSE_CFG_6DBD11FF8A"; value = "RANDOM"; };
                };
            };
            class Complexity {
                displayName = "$STR_COMSPEC_SSE_CFG_684A67E212";
                tooltip = "$STR_COMSPEC_SSE_CFG_1ED5C5DECE";
                property = "COMSPEC_SSE_Module_SeedEvidence_Complexity";
                control = "Combo";
                expression = "_this setVariable ['Complexity',_value,true];";
                defaultValue = "'STANDARD'";
                typeName = "STRING";
                class values {
                    class V0 { name = "$STR_COMSPEC_SSE_CFG_142CF342A2"; value = "LIGHT"; };
                    class V1 { name = "$STR_COMSPEC_SSE_CFG_2DFA66079D"; value = "STANDARD"; default = 1; };
                    class V2 { name = "$STR_COMSPEC_SSE_CFG_094D4A1824"; value = "DETAILED"; };
                    class V3 { name = "$STR_COMSPEC_SSE_CFG_78AEB2046E"; value = "HIGH_VALUE"; };
                };
            };
        };

        class ModuleDescription {
            description = "$STR_COMSPEC_SSE_CFG_93C1E25EC9";
            position = 1;
        };
    };

    class COMSPEC_SSE_Module_SiteNetwork: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_769D705C52";
        category = "COMSPEC_SSE_Sites";
        function = "comspec_sse_fnc_moduleSiteNetwork";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\map_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\map_ca.paa";

        class Attributes {
            class Count {
                displayName = "$STR_COMSPEC_SSE_CFG_DE5B822DEC";
                tooltip = "$STR_COMSPEC_SSE_CFG_46DDBDA375";
                property = "COMSPEC_SSE_Module_SiteNetwork_Count";
                control = "Edit";
                expression = "_this setVariable ['Count',_value,true];";
                defaultValue = "4";
                typeName = "NUMBER";
            };
            class RadiusKm {
                displayName = "$STR_COMSPEC_SSE_CFG_6ED8A6301D";
                tooltip = "$STR_COMSPEC_SSE_CFG_41547EA607";
                property = "COMSPEC_SSE_Module_SiteNetwork_RadiusKm";
                control = "Edit";
                expression = "_this setVariable ['RadiusKm',_value,true];";
                defaultValue = "3";
                typeName = "NUMBER";
            };
            class Occupants {
                displayName = "$STR_COMSPEC_SSE_CFG_4C379DB100";
                tooltip = "$STR_COMSPEC_SSE_CFG_014C543192";
                property = "COMSPEC_SSE_Module_SiteNetwork_Occupants";
                control = "Edit";
                expression = "_this setVariable ['Occupants',_value,true];";
                defaultValue = "-1";
                typeName = "NUMBER";
            };
            class Side {
                displayName = "$STR_COMSPEC_SSE_CFG_63DB4D7079";
                tooltip = "$STR_COMSPEC_SSE_CFG_7B21FB2E3F";
                property = "COMSPEC_SSE_Module_SiteNetwork_Side";
                control = "Combo";
                expression = "_this setVariable ['Side',_value,true];";
                defaultValue = "'GUER'";
                typeName = "STRING";
                class values {
                    class V0 { name = "$STR_COMSPEC_SSE_CFG_3C7903BF6D"; value = "GUER"; default = 1; };
                    class V1 { name = "$STR_COMSPEC_SSE_CFG_FA79BDC9FF"; value = "EAST"; };
                    class V2 { name = "$STR_COMSPEC_SSE_CFG_7AA3BFB0ED"; value = "CIV"; };
                };
            };
            class Armed {
                displayName = "$STR_COMSPEC_SSE_CFG_9B52E497DD";
                tooltip = "$STR_COMSPEC_SSE_CFG_580E55558E";
                property = "COMSPEC_SSE_Module_SiteNetwork_Armed";
                control = "Checkbox";
                expression = "_this setVariable ['Armed',_value,true];";
                defaultValue = "true";
                typeName = "BOOL";
            };
            class Complexity {
                displayName = "$STR_COMSPEC_SSE_CFG_684A67E212";
                tooltip = "$STR_COMSPEC_SSE_CFG_637DD26429";
                property = "COMSPEC_SSE_Module_SiteNetwork_Complexity";
                control = "Combo";
                expression = "_this setVariable ['Complexity',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
                class values {
                    class V0 { name = "$STR_COMSPEC_SSE_CFG_4C8BDFF9D2"; value = ""; default = 1; };
                    class V1 { name = "$STR_COMSPEC_SSE_CFG_142CF342A2"; value = "LIGHT"; };
                    class V2 { name = "$STR_COMSPEC_SSE_CFG_2DFA66079D"; value = "STANDARD"; };
                    class V3 { name = "$STR_COMSPEC_SSE_CFG_094D4A1824"; value = "DETAILED"; };
                    class V4 { name = "$STR_COMSPEC_SSE_CFG_78AEB2046E"; value = "HIGH_VALUE"; };
                };
            };
            class Traps {
                displayName = "$STR_COMSPEC_SSE_CFG_8D69643C5D";
                tooltip = "$STR_COMSPEC_SSE_CFG_E4B1C64B59";
                property = "COMSPEC_SSE_Module_SiteNetwork_Traps";
                control = "Checkbox";
                expression = "_this setVariable ['Traps',_value,true];";
                defaultValue = "false";
                typeName = "BOOL";
            };
        };

        class ModuleDescription {
            description = "$STR_COMSPEC_SSE_CFG_28AE03E00A";
            position = 1;
        };
    };

    class COMSPEC_SSE_Module_PlaceKit: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_46397059AC";
        category = "COMSPEC_SSE_Sites";
        function = "comspec_sse_fnc_modulePlaceKit";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\box_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\box_ca.paa";

        class Attributes {
            class Kit {
                displayName = "$STR_COMSPEC_SSE_CFG_91BF6F557E";
                tooltip = "$STR_COMSPEC_SSE_CFG_E07D1F89CF";
                property = "COMSPEC_SSE_Module_PlaceKit_Kit";
                control = "Combo";
                expression = "_this setVariable ['Kit',_value,true];";
                defaultValue = "'TEAM'";
                typeName = "STRING";
                class values {
                    class V0 { name = "$STR_COMSPEC_SSE_CFG_DBAE9D4694"; value = "TEAM"; default = 1; };
                    class V1 { name = "$STR_COMSPEC_SSE_CFG_39C7BC08FA"; value = "BIO"; };
                    class V2 { name = "$STR_COMSPEC_SSE_CFG_845BE63EE0"; value = "DIGITAL"; };
                    class V3 { name = "$STR_COMSPEC_SSE_CFG_4E5AC91468"; value = "DOMEX"; };
                };
            };
        };

        class ModuleDescription {
            description = "$STR_COMSPEC_SSE_CFG_AD24147564";
            position = 1;
            direction = 1;
        };
    };
