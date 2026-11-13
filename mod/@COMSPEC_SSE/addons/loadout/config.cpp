#include "script_component.hpp"

/*
    COMSPEC SSE · Équipement (configurateur Athena) : kits nommés du site posés en jeu depuis une caisse.
    Chemin : site (Équipement › Configurateur) -> paquet d'identité Athena (settings.loadouts, chargé par
    comspec_sse_fnc_idAthenaPack) -> registre serveur (fn_loadoutRegistry) -> caisse de kits (module Eden / Zeus,
    attributs Eden d'objet) -> action ACE « Prendre mon kit » -> tenue posée chez le joueur (fn_loadoutApply).
    Sens inverse : « Équipement › Copier ma tenue pour Athena » (fn_loadoutExport, JSON) et catalogue des objets
    de la communauté (fn_loadoutCatalog, à lancer en solo ou dans l'éditeur).
    Licence : aucune requête réseau, aucun callExtension ici ; les données du site n'arrivent que par le paquet.
*/
class CfgPatches {
    class comspec_sse_loadout {
        name = "COMSPEC SSE - Loadout";
        units[] = {"COMSPEC_SSE_Module_LoadoutCrate"};
        weapons[] = {};
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {
            "comspec_sse_core",
            "comspec_sse_main",
            "comspec_sse_ui",
            "comspec_sse_zeus",
            "cba_main",
            "cba_xeh",
            "cba_settings",
            "ace_interact_menu",
            "3DEN",
            "A3_Modules_F",
            "comspec_atak_native_main"
        };
        author = "COMSPEC";
        VERSION_CONFIG;
    };
};

class CfgFunctions {
    class comspec_sse {
        tag = "comspec_sse";
        class loadout {
            file = "z\comspec_sse\addons\loadout\functions";
            class loadoutInitSettings {};
            class loadoutParse {};
            class loadoutRegistry {};
            class loadoutApply {};
            class loadoutExport {};
            class loadoutTextDialog {};
            class loadoutTake {};
            class loadoutInitACE {};
            class loadoutModule {};
            class loadoutCatalog {};
        };
    };
};

// Demandes du joueur au serveur et réponses du serveur, par la même fonction : chaque mode vérifie qui l'appelle
// (remoteExecutedOwner : serveur pour les réponses, propriétaire de l'unité pour les demandes).
class CfgRemoteExec {
    class Functions {
        class comspec_sse_fnc_loadoutTake { allowedTargets = 0; jip = 0; };
    };
};

#include "CfgEventHandlers.hpp"

class CfgVehicles {
    class COMSPEC_SSE_Module_Base;
    // Module « Caisse de kits » (Eden et Zeus) : l'objet synchronisé (Eden) ou visé (Zeus) devient une caisse de kits.
    class COMSPEC_SSE_Module_LoadoutCrate: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "Caisse de kits (Athena)";
        category = "COMSPEC_Cat_Sse";
        function = "comspec_sse_fnc_loadoutModule";
        icon = "\z\comspec_sse\addons\zeus\data\mod_evidence.paa";
        portrait = "\z\comspec_sse\addons\zeus\data\mod_evidence.paa";

        class Attributes {
            class KitName {
                displayName = "Nom de la tenue de la caisse";
                tooltip = "Facultatif : nom montré au joueur (ex. « Plongeur »).";
                property = "COMSPEC_SSE_Module_LoadoutCrate_Name";
                control = "Edit";
                expression = "_this setVariable ['KitName',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class KitText {
                displayName = "Tenue de la caisse (JSON collé depuis Athena)";
                tooltip = "Facultatif : tenue copiée depuis Athena (Équipement › Configurateur) ou depuis le jeu (Équipement › Copier ma tenue pour Athena). Vide : la caisse propose les kits Athena de chaque joueur.";
                property = "COMSPEC_SSE_Module_LoadoutCrate_Text";
                control = "EditMulti5";
                expression = "_this setVariable ['KitText',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
        };

        class ModuleDescription {
            description = "Synchronisez le module à un objet (ou posez-le dessus) : l'objet devient une caisse de kits. Les joueurs y prennent leur kit Athena (menu ACE « Prendre mon kit ») ou la tenue collée dans le module.";
            sync[] = {"Anything"};
        };
    };
};

class Cfg3DEN {
    class Object {
        class AttributeCategories {
            class COMSPEC_SSE_Loadout {
                displayName = "COMSPEC SSE · Caisse de kits";
                collapsed = 1;
                class Attributes {
                    class comspec_sse_loadoutCrate {
                        displayName = "Caisse de kits";
                        tooltip = "Les joueurs y prennent leur kit Athena (menu ACE « Prendre mon kit »).";
                        property = "comspec_sse_loadoutCrate";
                        control = "Checkbox";
                        expression = "if (isServer && {_value}) then { private _l = missionNamespace getVariable ['comspec_sse_loPending', []]; _l pushBackUnique _this; missionNamespace setVariable ['comspec_sse_loPending', _l]; };";
                        defaultValue = "false";
                        typeName = "BOOL";
                        condition = "objectSimulated - objectBrain";
                    };
                    class comspec_sse_loadoutCrateName {
                        displayName = "Nom de la tenue de la caisse";
                        tooltip = "Facultatif : nom montré au joueur.";
                        property = "comspec_sse_loadoutCrateName";
                        control = "Edit";
                        expression = "if (isServer && {_value isNotEqualTo ''}) then { _this setVariable ['comspec_sse_loCrateName', _value]; };";
                        defaultValue = "''";
                        typeName = "STRING";
                        condition = "objectSimulated - objectBrain";
                    };
                    class comspec_sse_loadoutCrateText {
                        displayName = "Tenue de la caisse (JSON collé depuis Athena)";
                        tooltip = "Facultatif : tenue copiée depuis Athena ou depuis le jeu. Rend aussi l'objet caisse de kits.";
                        property = "comspec_sse_loadoutCrateText";
                        control = "EditMulti5";
                        expression = "if (isServer && {_value isNotEqualTo ''}) then { _this setVariable ['comspec_sse_loCrateText', _value]; private _l = missionNamespace getVariable ['comspec_sse_loPending', []]; _l pushBackUnique _this; missionNamespace setVariable ['comspec_sse_loPending', _l]; };";
                        defaultValue = "''";
                        typeName = "STRING";
                        condition = "objectSimulated - objectBrain";
                    };
                };
            };
        };
    };
};

// Texte de la tenue à copier quand le presse-papiers est inopérant (multijoueur) : champ à sélectionner (Ctrl+A, Ctrl+C).
class RscText;
class RscEdit;
class RscButton;
class COMSPEC_SSE_LoadoutTextDialog {
    idd = 93850;
    movingEnable = 0;
    enableSimulation = 1;
    class controlsBackground {
        class Back: RscText {
            idc = -1;
            colorBackground[] = {0.05, 0.06, 0.07, 0.94};
            x = "safezoneX + safezoneW * 0.2";
            y = "safezoneY + safezoneH * 0.2";
            w = "safezoneW * 0.6";
            h = "safezoneH * 0.6";
        };
    };
    class controls {
        class Title: RscText {
            idc = 93851;
            text = "";
            colorBackground[] = {0.22, 0.43, 0.27, 1};
            x = "safezoneX + safezoneW * 0.2";
            y = "safezoneY + safezoneH * 0.2";
            w = "safezoneW * 0.6";
            h = "safezoneH * 0.04";
        };
        class Help: RscText {
            idc = 93852;
            text = "";
            x = "safezoneX + safezoneW * 0.21";
            y = "safezoneY + safezoneH * 0.245";
            w = "safezoneW * 0.58";
            h = "safezoneH * 0.035";
        };
        class Body: RscEdit {
            idc = 93853;
            style = 16;
            maxChars = 200000;
            autocomplete = "";
            colorBackground[] = {0.02, 0.03, 0.035, 1};
            x = "safezoneX + safezoneW * 0.21";
            y = "safezoneY + safezoneH * 0.285";
            w = "safezoneW * 0.58";
            h = "safezoneH * 0.44";
        };
        class Close: RscButton {
            idc = 93854;
            text = "FERMER";
            action = "closeDialog 0";
            x = "safezoneX + safezoneW * 0.69";
            y = "safezoneY + safezoneH * 0.74";
            w = "safezoneW * 0.1";
            h = "safezoneH * 0.045";
        };
    };
};
