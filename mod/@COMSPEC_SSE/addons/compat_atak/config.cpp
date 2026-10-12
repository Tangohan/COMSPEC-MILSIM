#include "script_component.hpp"

/*
    Passerelle SSE ↔ ATAK natif : le câble DL-7 se configure dans le SSE.

    Chargée seulement si l'ATAK natif est présent (skipWhenMissingDependencies) :
      - les modules d'extraction de l'ATAK passent dans « COMSPEC SSE · 4. Numérique » ;
      - catégorie Eden « COMSPEC SSE — Extraction DL-7 » sur n'importe quel objet ;
      - le contenu numérique généré par le SSE (fichiers, courriels, messages,
        identifiants, lieux) devient extractible au câble sans rien saisir ;
      - action Zeus (ZEN) « Rendre extractible au DL-7 » sur la sélection.
*/
class CfgPatches {
    class comspec_sse_compat_atak {
        name = "COMSPEC SSE - Compat ATAK (câble DL-7)";
        units[] = {};
        weapons[] = {};
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {
            "comspec_sse_core",
            "comspec_sse_generator",
            "comspec_sse_digital",
            "comspec_sse_zeus",
            "comspec_sse_eden",
            "comspec_atak_native_main"
        };
        skipWhenMissingDependencies = 1;
        author = "COMSPEC";
        VERSION_CONFIG;
    };
};

class CfgFunctions {
    class comspec_sse {
        tag = "comspec_sse";
        class compat_atak {
            file = "z\comspec_sse\addons\compat_atak\functions";
            class dl7FilesFromSse {};
            class dl7Apply {};
            class dl7ApplyEden {};
            class dl7ZeusForm {};
        };
    };
};

class Extended_PostInit_EventHandlers {
    class comspec_sse_compat_atak {
        init = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\compat_atak\XEH_postInit.sqf'";
    };
};

class CfgRemoteExec {
    class Functions {
        class comspec_sse_fnc_dl7Apply { allowedTargets = 2; };
    };
};

// Modules d'extraction de l'ATAK rangés avec le numérique du SSE.
class CfgVehicles {
    class Module_F;
    class COMSPEC_ATAK_Module_DataBase: Module_F {};
    class COMSPEC_ATAK_Module_DataNode: COMSPEC_ATAK_Module_DataBase {
        category = "COMSPEC_SSE_Domex";
        displayName = "DL-7 · Données à extraire au câble";
    };
    class COMSPEC_ATAK_Module_DataFile: COMSPEC_ATAK_Module_DataBase {
        category = "COMSPEC_SSE_Domex";
        displayName = "DL-7 · Ajouter un fichier";
    };
    class COMSPEC_ATAK_Module_DataReset: COMSPEC_ATAK_Module_DataBase {
        category = "COMSPEC_SSE_Domex";
        displayName = "DL-7 · Remettre un support à zéro";
    };
};

#define DL7_COND condition = "objectSimulated"

class Cfg3DEN {
    class Object {
        class AttributeCategories {
            class COMSPEC_SSE_Dl7 {
                displayName = "COMSPEC SSE — Extraction DL-7";
                collapsed = 1;
                class Attributes {
                    class COMSPEC_SSE_Dl7_Enabled {
                        displayName = "Extractible au câble DL-7";
                        tooltip = "Un opérateur équipé de l'ATAK et du câble DL-7 peut se brancher sur cet objet et en télécharger les données.";
                        property = "COMSPEC_SSE_Dl7_Enabled";
                        control = "Checkbox";
                        expression = "_this setVariable ['COMSPEC_SSE_Dl7_Enabled', _value];";
                        defaultValue = "false";
                        typeName = "BOOL";
                        DL7_COND;
                    };
                    class COMSPEC_SSE_Dl7_Content {
                        displayName = "Contenu";
                        tooltip = "Données SSE : ce que le générateur SSE a mis dans l'appareil (fichiers, courriels, messages, identifiants, lieux). Fichiers saisis : seulement le champ ci-dessous. Les deux : l'ensemble.";
                        property = "COMSPEC_SSE_Dl7_Content";
                        control = "Combo";
                        expression = "_this setVariable ['COMSPEC_SSE_Dl7_Content', _value];";
                        defaultValue = "'sse'";
                        typeName = "STRING";
                        DL7_COND;
                        class values {
                            class Sse { name = "Données SSE générées"; value = "sse"; default = 1; };
                            class Manual { name = "Fichiers saisis ci-dessous"; value = "manual"; };
                            class Both { name = "Les deux"; value = "both"; };
                        };
                    };
                    class COMSPEC_SSE_Dl7_Files {
                        displayName = "Fichiers saisis";
                        tooltip = "Blocs séparés par une ligne « --- ». Première ligne : « nom | type | taille Ko » (texte, document, identifiants, coord, image, audio), puis le contenu.";
                        property = "COMSPEC_SSE_Dl7_Files";
                        control = "EditMulti5";
                        expression = "_this setVariable ['COMSPEC_SSE_Dl7_Files', _value];";
                        defaultValue = "''";
                        typeName = "STRING";
                        DL7_COND;
                    };
                    class COMSPEC_SSE_Dl7_Name {
                        displayName = "Nom du support";
                        tooltip = "Affiché dans l'app Extraction. Vide = nom de l'objet ou de l'appareil SSE.";
                        property = "COMSPEC_SSE_Dl7_Name";
                        control = "Edit";
                        expression = "_this setVariable ['COMSPEC_SSE_Dl7_Name', _value];";
                        defaultValue = "''";
                        typeName = "STRING";
                        DL7_COND;
                    };
                    class COMSPEC_SSE_Dl7_Theme {
                        displayName = "Écran de connexion";
                        property = "COMSPEC_SSE_Dl7_Theme";
                        control = "Combo";
                        expression = "_this setVariable ['COMSPEC_SSE_Dl7_Theme', _value];";
                        defaultValue = "'auto'";
                        typeName = "STRING";
                        DL7_COND;
                        class values {
                            class Auto { name = "Selon l'appareil"; value = "auto"; default = 1; };
                            class Windows { name = "Windows (bureau bleu)"; value = "windows"; };
                            class Linux { name = "Linux (terminal vert)"; value = "linux"; };
                            class Militaire { name = "Militaire"; value = "militaire"; };
                            class Scada { name = "Industriel (SCADA)"; value = "scada"; };
                            class Telephone { name = "Téléphone verrouillé"; value = "telephone"; };
                            class Coffre { name = "Coffre chiffré"; value = "coffre"; };
                        };
                    };
                    class COMSPEC_SSE_Dl7_Banner {
                        displayName = "Organisation affichée";
                        property = "COMSPEC_SSE_Dl7_Banner";
                        control = "Edit";
                        expression = "_this setVariable ['COMSPEC_SSE_Dl7_Banner', _value];";
                        defaultValue = "''";
                        typeName = "STRING";
                        DL7_COND;
                    };
                    class COMSPEC_SSE_Dl7_Password {
                        displayName = "Mot de passe";
                        tooltip = "Vide = accès libre. Pensez à le cacher ailleurs : document, autre appareil, interrogatoire.";
                        property = "COMSPEC_SSE_Dl7_Password";
                        control = "Edit";
                        expression = "_this setVariable ['COMSPEC_SSE_Dl7_Password', _value];";
                        defaultValue = "''";
                        typeName = "STRING";
                        DL7_COND;
                    };
                    class COMSPEC_SSE_Dl7_Hint {
                        displayName = "Indice";
                        property = "COMSPEC_SSE_Dl7_Hint";
                        control = "Edit";
                        expression = "_this setVariable ['COMSPEC_SSE_Dl7_Hint', _value];";
                        defaultValue = "''";
                        typeName = "STRING";
                        DL7_COND;
                    };
                    class COMSPEC_SSE_Dl7_Attempts {
                        displayName = "Essais permis (0 = illimité)";
                        property = "COMSPEC_SSE_Dl7_Attempts";
                        control = "Edit";
                        expression = "_this setVariable ['COMSPEC_SSE_Dl7_Attempts', _value];";
                        defaultValue = "3";
                        validate = "number";
                        typeName = "NUMBER";
                        DL7_COND;
                    };
                    class COMSPEC_SSE_Dl7_OnFail {
                        displayName = "Trop d'essais";
                        property = "COMSPEC_SSE_Dl7_OnFail";
                        control = "Combo";
                        expression = "_this setVariable ['COMSPEC_SSE_Dl7_OnFail', _value];";
                        defaultValue = "'lock'";
                        typeName = "STRING";
                        DL7_COND;
                        class values {
                            class Lock { name = "Verrouiller (forçage seul)"; value = "lock"; default = 1; };
                            class Wipe { name = "Effacer les données"; value = "wipe"; };
                            class Alarm { name = "Déclencher l'alarme"; value = "alarm"; };
                            class None { name = "Rien"; value = "none"; };
                        };
                    };
                    class COMSPEC_SSE_Dl7_Crack {
                        displayName = "Forçage possible";
                        property = "COMSPEC_SSE_Dl7_Crack";
                        control = "Checkbox";
                        expression = "_this setVariable ['COMSPEC_SSE_Dl7_Crack', _value];";
                        defaultValue = "true";
                        typeName = "BOOL";
                        DL7_COND;
                    };
                    class COMSPEC_SSE_Dl7_CrackTime {
                        displayName = "Durée du forçage (s)";
                        property = "COMSPEC_SSE_Dl7_CrackTime";
                        control = "Edit";
                        expression = "_this setVariable ['COMSPEC_SSE_Dl7_CrackTime', _value];";
                        defaultValue = "90";
                        validate = "number";
                        typeName = "NUMBER";
                        DL7_COND;
                    };
                    class COMSPEC_SSE_Dl7_Duration {
                        displayName = "Durée du transfert (s)";
                        property = "COMSPEC_SSE_Dl7_Duration";
                        control = "Edit";
                        expression = "_this setVariable ['COMSPEC_SSE_Dl7_Duration', _value];";
                        defaultValue = "45";
                        validate = "number";
                        typeName = "NUMBER";
                        DL7_COND;
                    };
                    class COMSPEC_SSE_Dl7_Port {
                        displayName = "Port";
                        property = "COMSPEC_SSE_Dl7_Port";
                        control = "Combo";
                        expression = "_this setVariable ['COMSPEC_SSE_Dl7_Port', _value];";
                        defaultValue = "'usb'";
                        typeName = "STRING";
                        DL7_COND;
                        class values {
                            class Usb { name = "USB-C"; value = "usb"; default = 1; };
                            class Rj45 { name = "Ethernet RJ45"; value = "rj45"; };
                            class Serie { name = "Série RS-232 (lent)"; value = "serie"; };
                        };
                    };
                    class COMSPEC_SSE_Dl7_Alarm {
                        displayName = "Alarme";
                        tooltip = "Sirène, Zeus prévenu, position du pirate révélée aux ennemis proches.";
                        property = "COMSPEC_SSE_Dl7_Alarm";
                        control = "Combo";
                        expression = "_this setVariable ['COMSPEC_SSE_Dl7_Alarm', _value];";
                        defaultValue = "'none'";
                        typeName = "STRING";
                        DL7_COND;
                        class values {
                            class None { name = "Aucune"; value = "none"; default = 1; };
                            class Connect { name = "Au branchement"; value = "connect"; };
                            class Fail { name = "Mauvais mot de passe"; value = "fail"; };
                            class Download { name = "En fin de téléchargement"; value = "download"; };
                        };
                    };
                    class COMSPEC_SSE_Dl7_OneShot {
                        displayName = "Effacé après le premier téléchargement";
                        property = "COMSPEC_SSE_Dl7_OneShot";
                        control = "Checkbox";
                        expression = "_this setVariable ['COMSPEC_SSE_Dl7_OneShot', _value];";
                        defaultValue = "false";
                        typeName = "BOOL";
                        DL7_COND;
                    };
                };
            };
        };
    };
};
