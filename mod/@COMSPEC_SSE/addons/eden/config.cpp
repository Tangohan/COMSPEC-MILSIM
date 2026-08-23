#include "script_component.hpp"

class CfgPatches {
    class comspec_sse_eden {
        name = "COMSPEC SSE - Eden";
        units[] = {};
        weapons[] = {};
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {"comspec_sse_core", "comspec_sse_generator", "3DEN"};
        author = "COMSPEC";
        VERSION_CONFIG;
    };
};

class CfgFunctions {
    class comspec_sse {
        tag = "comspec_sse";
        class eden {
            file = "z\comspec_sse\addons\eden\functions";
            class edenApplyAttributes {};
            class edenInitEntity {};
            class domexGetNode {};
            class domexApplyObject {};
        };
    };
};

class Extended_InitPost_EventHandlers {
    class CAManBase {
        class comspec_sse_eden {
            // CBA passe déjà [_unit] dans _this — pas de [_this] (sinon [[_unit]]).
            init = "_this call comspec_sse_fnc_edenInitEntity";
        };
    };
};

class Cfg3DEN {
    class Object {
        class AttributeCategories {
            class COMSPEC_SSE {
                displayName = "COMSPEC SSE";
                collapsed = 1;

                class Attributes {
                    class comspec_sse_enabled {
                        displayName = "SSE activé";
                        tooltip = "Active le renseignement SSE sur cette entité.";
                        property = "comspec_sse_enabled";
                        control = "Checkbox";
                        expression = "_this setVariable ['comspec_sse_enabled', _value, true];";
                        defaultValue = "false";
                        typeName = "BOOL";
                        condition = "objectBrain + objectVehicle + objectControllable";
                    };
                    class comspec_sse_profile {
                        displayName = "Profil";
                        tooltip = "Profil narratif utilisé pour la génération.";
                        property = "comspec_sse_profile";
                        control = "Combo";
                        expression = "_this setVariable ['comspec_sse_profile', _value, true];";
                        defaultValue = "'RANDOM'";
                        typeName = "STRING";
                        class values {
                            class RANDOM { name = "RANDOM"; value = "RANDOM"; default = 1; };
                            class CIVILIAN { name = "CIVILIAN"; value = "CIVILIAN"; };
                            class INSURGENT { name = "INSURGENT"; value = "INSURGENT"; };
                            class MILITARY { name = "MILITARY"; value = "MILITARY"; };
                            class HVT { name = "HVT"; value = "HVT"; };
                            class CUSTOM { name = "CUSTOM"; value = "CUSTOM"; };
                        };
                    };
                    class comspec_sse_generation {
                        displayName = "Génération";
                        property = "comspec_sse_generation";
                        control = "Combo";
                        expression = "_this setVariable ['comspec_sse_generation', _value, true];";
                        defaultValue = "'AUTO'";
                        typeName = "STRING";
                        class values {
                            class AUTO { name = "AUTO"; value = "AUTO"; default = 1; };
                            class MANUAL { name = "MANUAL"; value = "MANUAL"; };
                        };
                    };
                    class comspec_sse_complexity {
                        displayName = "Richesse";
                        property = "comspec_sse_complexity";
                        control = "Combo";
                        expression = "_this setVariable ['comspec_sse_complexity', _value, true];";
                        defaultValue = "'STANDARD'";
                        typeName = "STRING";
                        class values {
                            class LIGHT { name = "LIGHT"; value = "LIGHT"; };
                            class STANDARD { name = "STANDARD"; value = "STANDARD"; default = 1; };
                            class DETAILED { name = "DETAILED"; value = "DETAILED"; };
                            class HIGH_VALUE { name = "HIGH_VALUE"; value = "HIGH_VALUE"; };
                        };
                    };
                    class comspec_sse_identityMode {
                        displayName = "Identité";
                        tooltip = "Automatique : SEEK reprend le nom du panneau Identité Eden (ou Nom/Prénom COMSPEC s’ils sont remplis). Inventer : ignore l’identité Eden. Forcer : même effet que automatique, nom Eden imposé.";
                        property = "comspec_sse_identityMode";
                        control = "Combo";
                        expression = "_this setVariable ['comspec_sse_identityMode', _value, true];";
                        defaultValue = "'AUTO'";
                        typeName = "STRING";
                        class values {
                            class AUTO { name = "Automatique (identité Eden)"; value = "AUTO"; default = 1; };
                            class NONE { name = "Inventer un nom SSE"; value = "NONE"; };
                            class CUSTOM { name = "Forcer le nom Eden"; value = "CUSTOM"; };
                        };
                    };
                    class comspec_sse_personRole {
                        displayName = "Rôle déclaré";
                        tooltip = "Fonction ou rôle connu (courrier, financier, habitant…). Vide = génération automatique.";
                        property = "comspec_sse_personRole";
                        control = "Edit";
                        expression = "_this setVariable ['comspec_sse_personRole', _value, true];";
                        defaultValue = "''";
                        typeName = "STRING";
                    };
                    class comspec_sse_personNationality {
                        displayName = "Nationalité déclarée";
                        property = "comspec_sse_personNationality";
                        control = "Edit";
                        expression = "_this setVariable ['comspec_sse_personNationality', _value, true];";
                        defaultValue = "''";
                        typeName = "STRING";
                    };
                    class comspec_sse_personLanguage {
                        displayName = "Langue parlée";
                        property = "comspec_sse_personLanguage";
                        control = "Edit";
                        expression = "_this setVariable ['comspec_sse_personLanguage', _value, true];";
                        defaultValue = "''";
                        typeName = "STRING";
                    };
                    class comspec_sse_personPhone {
                        displayName = "Numéro connu";
                        tooltip = "Numéro associé à la personne (indépendant du téléphone saisi). Vide = génération automatique.";
                        property = "comspec_sse_personPhone";
                        control = "Edit";
                        expression = "_this setVariable ['comspec_sse_personPhone', _value, true];";
                        defaultValue = "''";
                        typeName = "STRING";
                    };
                    class comspec_sse_bioMode {
                        displayName = "Biométrie";
                        property = "comspec_sse_bioMode";
                        control = "Combo";
                        expression = "_this setVariable ['comspec_sse_bioMode', _value, true];";
                        defaultValue = "'AUTO'";
                        typeName = "STRING";
                        class values {
                            class AUTO { name = "Générer automatiquement"; value = "AUTO"; default = 1; };
                            class NONE { name = "Ne pas inclure de biométrie"; value = "NONE"; };
                        };
                    };
                    class comspec_sse_zeusNotes {
                        displayName = "Notes Zeus";
                        tooltip = "Notes libres visibles uniquement côté préparation / Zeus.";
                        property = "comspec_sse_zeusNotes";
                        control = "Edit";
                        expression = "_this setVariable ['comspec_sse_zeusNotes', _value, true];";
                        defaultValue = "''";
                        typeName = "STRING";
                    };
                    class comspec_sse_networkId {
                        displayName = "ID réseau";
                        tooltip = "Identifiant de cluster / réseau relationnel partagé.";
                        property = "comspec_sse_networkId";
                        control = "Edit";
                        expression = "_this setVariable ['comspec_sse_networkId', _value, true];";
                        defaultValue = "''";
                        typeName = "STRING";
                    };
                    class comspec_sse_modelId {
                        displayName = "Modèle SSE";
                        tooltip = "ID d'un modèle (ex. builtin_chef_hvt). Prioritaire sur le profil si renseigné.";
                        property = "comspec_sse_modelId";
                        control = "Edit";
                        expression = "_this setVariable ['comspec_sse_modelId', _value, true];";
                        defaultValue = "''";
                        typeName = "STRING";
                    };
                    class comspec_sse_datasetId {
                        displayName = "Dataset mission";
                        tooltip = "Pack narratif (ex. falcon). Prioritaire sur le modèle seul si un rôle est aussi renseigné.";
                        property = "comspec_sse_datasetId";
                        control = "Edit";
                        expression = "_this setVariable ['comspec_sse_datasetId', _value, true];";
                        defaultValue = "''";
                        typeName = "STRING";
                    };
                    class comspec_sse_datasetRole {
                        displayName = "Rôle dans le dataset";
                        tooltip = "Ex. falcon_hvt, falcon_courier, falcon_ied…";
                        property = "comspec_sse_datasetRole";
                        control = "Edit";
                        expression = "_this setVariable ['comspec_sse_datasetRole', _value, true];";
                        defaultValue = "''";
                        typeName = "STRING";
                    };
                    class comspec_sse_missionSeed {
                        displayName = "Graine mission (affichage)";
                        tooltip = "Référence stable (ex. FALCON-IQ-2012-A). Renseignée auto par le dataset si vide.";
                        property = "comspec_sse_missionSeed";
                        control = "Edit";
                        expression = "_this setVariable ['comspec_sse_missionSeed', _value, true];";
                        defaultValue = "''";
                        typeName = "STRING";
                    };
                    class comspec_sse_region {
                        displayName = "Région narrative";
                        property = "comspec_sse_region";
                        control = "Combo";
                        expression = "_this setVariable ['comspec_sse_region', _value, true];";
                        defaultValue = "'IRAQ'";
                        typeName = "STRING";
                        class values {
                            class IRAQ { name = "Irak"; value = "IRAQ"; default = 1; };
                            class SYRIA { name = "Syrie"; value = "SYRIA"; };
                            class LEVANT { name = "Levant"; value = "LEVANT"; };
                            class AFRICA_SAHEL { name = "Sahel"; value = "AFRICA_SAHEL"; };
                            class RUSSIA { name = "Russie / théâtre Est"; value = "RUSSIA"; };
                            class GENERIC { name = "Générique"; value = "GENERIC"; };
                            class RANDOM { name = "Aléatoire"; value = "RANDOM"; };
                        };
                    };
                    class comspec_sse_advancedData {
                        displayName = "Complément d’identité (usage avancé)";
                        tooltip = "Réservé à un besoin ponctuel. Préférez les catégories Documents, Téléphone et Ordinateur pour le contenu saisi.";
                        property = "comspec_sse_advancedData";
                        control = "Edit";
                        expression = "_this setVariable ['comspec_sse_advancedData', _value, true];";
                        defaultValue = "''";
                        typeName = "STRING";
                    };
                };
            };
            #include "attributes_authored.hpp"
            #include "attributes_domex.hpp"
        };
    };
};
