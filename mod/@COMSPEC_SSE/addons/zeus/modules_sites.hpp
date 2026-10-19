/*
    Modules « sites et kits » (Zeus et Eden) : sites préfabriqués, bâtiment peuplé, semis de preuves,
    réseau multi-sites, caisse de kit. En Zeus, formulaire vanilla à la pose (comspec_sse_fnc_uiForm) ;
    dans Eden, attributs ci-dessous. Création toujours sur le serveur (comspec_sse_fnc_siteRequest).

    Inclus dans le CfgVehicles de config.cpp — ne pas rouvrir CfgVehicles ici.
*/

    class COMSPEC_SSE_Module_PlaceSite: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "Poser un site SSE";
        category = "COMSPEC_SSE_Sites";
        function = "comspec_sse_fnc_modulePlaceSite";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\search_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\search_ca.paa";

        class Attributes {
            class SiteType {
                displayName = "Type de site";
                tooltip = "Mobilier, pièces de preuve et thème narratif propres à chaque site.";
                property = "COMSPEC_SSE_Module_PlaceSite_SiteType";
                control = "Combo";
                expression = "_this setVariable ['SiteType',_value,true];";
                defaultValue = "'SAFEHOUSE'";
                typeName = "STRING";
                class values {
                    class V0 { name = "Planque"; value = "SAFEHOUSE"; default = 1; };
                    class V1 { name = "Labo d'IED"; value = "IED_LAB"; };
                    class V2 { name = "Atelier de faux papiers"; value = "FORGERY"; };
                    class V3 { name = "Cache d'armes"; value = "WEAPONS_CACHE"; };
                    class V4 { name = "PC de cellule"; value = "CELL_HQ"; };
                    class V5 { name = "Boîte aux lettres morte"; value = "DEAD_DROP"; };
                    class V6 { name = "Nœud hawala / changeur"; value = "HAWALA"; };
                };
            };
            class Occupants {
                displayName = "Occupants";
                tooltip = "Nombre de personnes présentes (-1 : selon le site). La première devient le chef de la cellule.";
                property = "COMSPEC_SSE_Module_PlaceSite_Occupants";
                control = "Edit";
                expression = "_this setVariable ['Occupants',_value,true];";
                defaultValue = "-1";
                typeName = "NUMBER";
            };
            class Side {
                displayName = "Camp des occupants";
                tooltip = "Camp des occupants créés.";
                property = "COMSPEC_SSE_Module_PlaceSite_Side";
                control = "Combo";
                expression = "_this setVariable ['Side',_value,true];";
                defaultValue = "'GUER'";
                typeName = "STRING";
                class values {
                    class V0 { name = "Insurgés (indépendants)"; value = "GUER"; default = 1; };
                    class V1 { name = "Insurgés (OPFOR)"; value = "EAST"; };
                    class V2 { name = "Civils"; value = "CIV"; };
                };
            };
            class Armed {
                displayName = "Occupants armés";
                tooltip = "Décoché : occupants sans arme (jamais armés pour des civils).";
                property = "COMSPEC_SSE_Module_PlaceSite_Armed";
                control = "Checkbox";
                expression = "_this setVariable ['Armed',_value,true];";
                defaultValue = "true";
                typeName = "BOOL";
            };
            class Complexity {
                displayName = "Richesse";
                tooltip = "Quantité d'indices des fiches.";
                property = "COMSPEC_SSE_Module_PlaceSite_Complexity";
                control = "Combo";
                expression = "_this setVariable ['Complexity',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
                class values {
                    class V0 { name = "Selon le site"; value = ""; default = 1; };
                    class V1 { name = "$STR_COMSPEC_SSE_CFG_142CF342A2"; value = "LIGHT"; };
                    class V2 { name = "$STR_COMSPEC_SSE_CFG_2DFA66079D"; value = "STANDARD"; };
                    class V3 { name = "$STR_COMSPEC_SSE_CFG_094D4A1824"; value = "DETAILED"; };
                    class V4 { name = "$STR_COMSPEC_SSE_CFG_78AEB2046E"; value = "HIGH_VALUE"; };
                };
            };
            class Traps {
                displayName = "Pièges";
                tooltip = "Mine à fil à l'entrée, engin piégé dans un labo d'IED.";
                property = "COMSPEC_SSE_Module_PlaceSite_Traps";
                control = "Checkbox";
                expression = "_this setVariable ['Traps',_value,true];";
                defaultValue = "false";
                typeName = "BOOL";
            };
            class UseBuilding {
                displayName = "Dans un bâtiment proche";
                tooltip = "Cherche un bâtiment habitable à moins de 40 m ; sinon le site est posé au sol.";
                property = "COMSPEC_SSE_Module_PlaceSite_UseBuilding";
                control = "Checkbox";
                expression = "_this setVariable ['UseBuilding',_value,true];";
                defaultValue = "true";
                typeName = "BOOL";
            };
        };

        class ModuleDescription {
            description = "Pose un site préfabriqué exploitable : mobilier, pièces de preuve, occupants et pièges optionnels, dossier qui cite d'autres lieux réels de la carte.";
            position = 1;
            direction = 1;
        };
    };

    class COMSPEC_SSE_Module_PopulateBuilding: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "Peupler un bâtiment";
        category = "COMSPEC_SSE_Sites";
        function = "comspec_sse_fnc_modulePopulateBuilding";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\house_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\house_ca.paa";

        class Attributes {
            class SiteType {
                displayName = "Type de site";
                tooltip = "Ce que le bâtiment abrite.";
                property = "COMSPEC_SSE_Module_PopulateBuilding_SiteType";
                control = "Combo";
                expression = "_this setVariable ['SiteType',_value,true];";
                defaultValue = "'SAFEHOUSE'";
                typeName = "STRING";
                class values {
                    class V0 { name = "Planque"; value = "SAFEHOUSE"; default = 1; };
                    class V1 { name = "Labo d'IED"; value = "IED_LAB"; };
                    class V2 { name = "Atelier de faux papiers"; value = "FORGERY"; };
                    class V3 { name = "Cache d'armes"; value = "WEAPONS_CACHE"; };
                    class V4 { name = "PC de cellule"; value = "CELL_HQ"; };
                    class V5 { name = "Nœud hawala / changeur"; value = "HAWALA"; };
                };
            };
            class Occupants {
                displayName = "Occupants";
                tooltip = "Nombre de personnes présentes (-1 : selon le site). La première devient le chef de la cellule.";
                property = "COMSPEC_SSE_Module_PopulateBuilding_Occupants";
                control = "Edit";
                expression = "_this setVariable ['Occupants',_value,true];";
                defaultValue = "-1";
                typeName = "NUMBER";
            };
            class Side {
                displayName = "Camp des occupants";
                tooltip = "Camp des occupants créés.";
                property = "COMSPEC_SSE_Module_PopulateBuilding_Side";
                control = "Combo";
                expression = "_this setVariable ['Side',_value,true];";
                defaultValue = "'GUER'";
                typeName = "STRING";
                class values {
                    class V0 { name = "Insurgés (indépendants)"; value = "GUER"; default = 1; };
                    class V1 { name = "Insurgés (OPFOR)"; value = "EAST"; };
                    class V2 { name = "Civils"; value = "CIV"; };
                };
            };
            class Armed {
                displayName = "Occupants armés";
                tooltip = "Décoché : occupants sans arme (jamais armés pour des civils).";
                property = "COMSPEC_SSE_Module_PopulateBuilding_Armed";
                control = "Checkbox";
                expression = "_this setVariable ['Armed',_value,true];";
                defaultValue = "true";
                typeName = "BOOL";
            };
            class Complexity {
                displayName = "Richesse";
                tooltip = "Quantité d'indices des fiches.";
                property = "COMSPEC_SSE_Module_PopulateBuilding_Complexity";
                control = "Combo";
                expression = "_this setVariable ['Complexity',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
                class values {
                    class V0 { name = "Selon le site"; value = ""; default = 1; };
                    class V1 { name = "$STR_COMSPEC_SSE_CFG_142CF342A2"; value = "LIGHT"; };
                    class V2 { name = "$STR_COMSPEC_SSE_CFG_2DFA66079D"; value = "STANDARD"; };
                    class V3 { name = "$STR_COMSPEC_SSE_CFG_094D4A1824"; value = "DETAILED"; };
                    class V4 { name = "$STR_COMSPEC_SSE_CFG_78AEB2046E"; value = "HIGH_VALUE"; };
                };
            };
            class Traps {
                displayName = "Pièges";
                tooltip = "Mine à fil sur la sortie du bâtiment, engin piégé dans un labo d'IED.";
                property = "COMSPEC_SSE_Module_PopulateBuilding_Traps";
                control = "Checkbox";
                expression = "_this setVariable ['Traps',_value,true];";
                defaultValue = "false";
                typeName = "BOOL";
            };
        };

        class ModuleDescription {
            description = "Posé sur un bâtiment habitable (15 m au plus), le transforme en site SSE : mobilier réparti dans les pièces, preuves, occupants, pièges.";
            position = 1;
        };
    };

    class COMSPEC_SSE_Module_SeedEvidence: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "Semer des preuves";
        category = "COMSPEC_SSE_Sites";
        function = "comspec_sse_fnc_moduleSeedEvidence";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\documents_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\documents_ca.paa";

        class Attributes {
            class Radius {
                displayName = "Rayon (m)";
                tooltip = "Les pièces vont d'abord dans les bâtiments du rayon.";
                property = "COMSPEC_SSE_Module_SeedEvidence_Radius";
                control = "Edit";
                expression = "_this setVariable ['Radius',_value,true];";
                defaultValue = "30";
                typeName = "NUMBER";
            };
            class Count {
                displayName = "Nombre de pièces";
                tooltip = "De 1 à 20.";
                property = "COMSPEC_SSE_Module_SeedEvidence_Count";
                control = "Edit";
                expression = "_this setVariable ['Count',_value,true];";
                defaultValue = "6";
                typeName = "NUMBER";
            };
            class Profile {
                displayName = "$STR_COMSPEC_SSE_CFG_669FE25A32";
                tooltip = "Profil narratif du contenu.";
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
                tooltip = "Quantité d'indices.";
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
            description = "Pose des pièces de preuve (téléphones, clés USB, documents, carnets…) dans les bâtiments du rayon, reliées aux personnes présentes.";
            position = 1;
        };
    };

    class COMSPEC_SSE_Module_SiteNetwork: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "Réseau multi-sites";
        category = "COMSPEC_SSE_Sites";
        function = "comspec_sse_fnc_moduleSiteNetwork";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\map_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\map_ca.paa";

        class Attributes {
            class Count {
                displayName = "Nombre de sites";
                tooltip = "De 3 à 6 ; le dernier est toujours le PC de la cellule.";
                property = "COMSPEC_SSE_Module_SiteNetwork_Count";
                control = "Edit";
                expression = "_this setVariable ['Count',_value,true];";
                defaultValue = "4";
                typeName = "NUMBER";
            };
            class RadiusKm {
                displayName = "Rayon (km)";
                tooltip = "Les sites sont posés près de villes et villages réels dans ce rayon.";
                property = "COMSPEC_SSE_Module_SiteNetwork_RadiusKm";
                control = "Edit";
                expression = "_this setVariable ['RadiusKm',_value,true];";
                defaultValue = "3";
                typeName = "NUMBER";
            };
            class Occupants {
                displayName = "Occupants par site";
                tooltip = "-1 : selon le site (planque et PC occupés, cache et boîte aux lettres vides).";
                property = "COMSPEC_SSE_Module_SiteNetwork_Occupants";
                control = "Edit";
                expression = "_this setVariable ['Occupants',_value,true];";
                defaultValue = "-1";
                typeName = "NUMBER";
            };
            class Side {
                displayName = "Camp des occupants";
                tooltip = "Camp des occupants créés.";
                property = "COMSPEC_SSE_Module_SiteNetwork_Side";
                control = "Combo";
                expression = "_this setVariable ['Side',_value,true];";
                defaultValue = "'GUER'";
                typeName = "STRING";
                class values {
                    class V0 { name = "Insurgés (indépendants)"; value = "GUER"; default = 1; };
                    class V1 { name = "Insurgés (OPFOR)"; value = "EAST"; };
                    class V2 { name = "Civils"; value = "CIV"; };
                };
            };
            class Armed {
                displayName = "Occupants armés";
                tooltip = "Décoché : occupants sans arme (jamais armés pour des civils).";
                property = "COMSPEC_SSE_Module_SiteNetwork_Armed";
                control = "Checkbox";
                expression = "_this setVariable ['Armed',_value,true];";
                defaultValue = "true";
                typeName = "BOOL";
            };
            class Complexity {
                displayName = "Richesse";
                tooltip = "Quantité d'indices des fiches.";
                property = "COMSPEC_SSE_Module_SiteNetwork_Complexity";
                control = "Combo";
                expression = "_this setVariable ['Complexity',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
                class values {
                    class V0 { name = "Selon le site"; value = ""; default = 1; };
                    class V1 { name = "$STR_COMSPEC_SSE_CFG_142CF342A2"; value = "LIGHT"; };
                    class V2 { name = "$STR_COMSPEC_SSE_CFG_2DFA66079D"; value = "STANDARD"; };
                    class V3 { name = "$STR_COMSPEC_SSE_CFG_094D4A1824"; value = "DETAILED"; };
                    class V4 { name = "$STR_COMSPEC_SSE_CFG_78AEB2046E"; value = "HIGH_VALUE"; };
                };
            };
            class Traps {
                displayName = "Pièges";
                tooltip = "Pièges de chaque site.";
                property = "COMSPEC_SSE_Module_SiteNetwork_Traps";
                control = "Checkbox";
                expression = "_this setVariable ['Traps',_value,true];";
                defaultValue = "false";
                typeName = "BOOL";
            };
        };

        class ModuleDescription {
            description = "Pose 3 à 6 sites reliés près de lieux réels : chaque site cite le suivant, le PC de la cellule cite tous les autres, les chefs sont reliés.";
            position = 1;
        };
    };

    class COMSPEC_SSE_Module_PlaceKit: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "Poser une caisse de kit";
        category = "COMSPEC_SSE_Sites";
        function = "comspec_sse_fnc_modulePlaceKit";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\box_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\box_ca.paa";

        class Attributes {
            class Kit {
                displayName = "Kit";
                tooltip = "Contenu de la caisse.";
                property = "COMSPEC_SSE_Module_PlaceKit_Kit";
                control = "Combo";
                expression = "_this setVariable ['Kit',_value,true];";
                defaultValue = "'TEAM'";
                typeName = "STRING";
                class values {
                    class V0 { name = "Kit SSE d'équipe"; value = "TEAM"; default = 1; };
                    class V1 { name = "Kit biométrie"; value = "BIO"; };
                    class V2 { name = "Kit exploitation numérique"; value = "DIGITAL"; };
                    class V3 { name = "Valise DOMEX de terrain"; value = "DOMEX"; };
                };
            };
        };

        class ModuleDescription {
            description = "Pose une caisse de kit SSE à ouvrir (équipe, biométrie, exploitation numérique, valise DOMEX). Les caisses existent aussi comme objets dans Eden et Zeus.";
            position = 1;
            direction = 1;
        };
    };
