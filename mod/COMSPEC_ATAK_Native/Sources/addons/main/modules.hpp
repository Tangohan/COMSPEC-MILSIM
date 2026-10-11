// Modules Zeus / Eden de l'extraction de données (inclus dans CfgVehicles de config.cpp).
// Attributs Eden à plat (sans AttributesBase) : ne pas rouvrir Module_F:Logic (casse Eden / Zeus / ACE).
// Zeus n'affiche pas les attributs : chaque module ouvre un formulaire vanilla à la pose (fn_zeusForm).
class Module_F;
class COMSPEC_ATAK_Module_DataBase: Module_F {
    author = "COMSPEC";
    scope = 1;
    scopeCurator = 1;
    category = "COMSPEC_ATAK_Data";
    functionPriority = 1;
    isGlobal = 1;
    isTriggerActivated = 0;
    isDisposable = 1;
    is3DEN = 0;
    curatorCanAttach = 1;
    icon = "\z\comspec_atak_native\addons\main\data\app_datalink.paa";
    portrait = "\z\comspec_atak_native\addons\main\data\app_datalink.paa";
};
class COMSPEC_ATAK_Module_DataNode: COMSPEC_ATAK_Module_DataBase {
    scope = 2;
    scopeCurator = 2;
    displayName = "Données à extraire (câble ATAK)";
    function = "comspec_atak_native_fnc_moduleDataNode";
    class Attributes {
            class Name {
                displayName = "Nom du support";
                tooltip = "Affiché sur l'ATAK et dans les messages Zeus. Vide = nom de l'objet.";
                property = "COMSPEC_ATAK_Module_DataNode_Name";
                control = "Edit";
                expression = "_this setVariable ['Name',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class Support {
                displayName = "Support à créer";
                tooltip = "Si le module n'est synchronisé à aucun objet : objet créé à son emplacement.";
                property = "COMSPEC_ATAK_Module_DataNode_Support";
                control = "Combo";
                expression = "_this setVariable ['Support',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
                class values {
                    class V0 { name = "(objet synchronisé)"; value = ""; default = 1; };
                    class V1 { name = "Ordinateur portable"; value = "portable"; };
                    class V2 { name = "Ordinateur de bureau"; value = "ordinateur"; };
                    class V3 { name = "Serveur / terminal"; value = "serveur"; };
                    class V4 { name = "Téléphone"; value = "telephone"; };
                    class V5 { name = "Tablette"; value = "tablette"; };
                    class V6 { name = "Clé USB"; value = "usb"; };
                    class V7 { name = "Radio numérique"; value = "radio"; };
                };
            };
            class Theme {
                displayName = "Écran de connexion";
                tooltip = "Apparence de l'écran du support dans l'ATAK.";
                property = "COMSPEC_ATAK_Module_DataNode_Theme";
                control = "Combo";
                expression = "_this setVariable ['Theme',_value,true];";
                defaultValue = "'windows'";
                typeName = "STRING";
                class values {
                    class V0 { name = "Windows (bureau bleu)"; value = "windows"; default = 1; };
                    class V1 { name = "Linux (terminal vert)"; value = "linux"; };
                    class V2 { name = "Militaire"; value = "militaire"; };
                    class V3 { name = "Industriel (SCADA)"; value = "scada"; };
                    class V4 { name = "Téléphone verrouillé"; value = "telephone"; };
                    class V5 { name = "Coffre chiffré"; value = "coffre"; };
                };
            };
            class Banner {
                displayName = "Organisation affichée";
                tooltip = "Ex. MINISTÈRE DE LA DÉFENSE, Cartel Rojas… En tête de l'écran de connexion.";
                property = "COMSPEC_ATAK_Module_DataNode_Banner";
                control = "Edit";
                expression = "_this setVariable ['Banner',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class Password {
                displayName = "Mot de passe";
                tooltip = "Vide = accès libre. Jamais diffusé en clair (empreinte seulement).";
                property = "COMSPEC_ATAK_Module_DataNode_Password";
                control = "Edit";
                expression = "_this setVariable ['Password',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class Hint {
                displayName = "Indice";
                tooltip = "Affiché sous le champ du mot de passe (facultatif).";
                property = "COMSPEC_ATAK_Module_DataNode_Hint";
                control = "Edit";
                expression = "_this setVariable ['Hint',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class Attempts {
                displayName = "Essais permis";
                tooltip = "Mauvais mots de passe avant la sanction ci-dessous. 0 = illimité.";
                property = "COMSPEC_ATAK_Module_DataNode_Attempts";
                control = "Edit";
                expression = "_this setVariable ['Attempts',_value,true];";
                defaultValue = "3";
                typeName = "NUMBER";
            };
            class OnFail {
                displayName = "Trop d'essais";
                tooltip = "Sanction quand les essais sont épuisés.";
                property = "COMSPEC_ATAK_Module_DataNode_OnFail";
                control = "Combo";
                expression = "_this setVariable ['OnFail',_value,true];";
                defaultValue = "'lock'";
                typeName = "STRING";
                class values {
                    class V0 { name = "Verrouiller (forçage seul)"; value = "lock"; default = 1; };
                    class V1 { name = "Effacer les données"; value = "wipe"; };
                    class V2 { name = "Déclencher l'alarme"; value = "alarm"; };
                    class V3 { name = "Rien"; value = "none"; };
                };
            };
            class Crack {
                displayName = "Forçage possible";
                tooltip = "Bouton FORCER L'ACCÈS : contourne le mot de passe après la durée ci-dessous, câble branché.";
                property = "COMSPEC_ATAK_Module_DataNode_Crack";
                control = "Checkbox";
                expression = "_this setVariable ['Crack',_value,true];";
                defaultValue = "true";
                typeName = "BOOL";
            };
            class CrackTime {
                displayName = "Durée du forçage (s)";
                tooltip = "Secondes à rester branché pour forcer l'accès.";
                property = "COMSPEC_ATAK_Module_DataNode_CrackTime";
                control = "Edit";
                expression = "_this setVariable ['CrackTime',_value,true];";
                defaultValue = "90";
                typeName = "NUMBER";
            };
            class Duration {
                displayName = "Durée du transfert (s)";
                tooltip = "Secondes pour tout télécharger en USB-C (RJ45 plus rapide, série plus lent ; réglage serveur de vitesse).";
                property = "COMSPEC_ATAK_Module_DataNode_Duration";
                control = "Edit";
                expression = "_this setVariable ['Duration',_value,true];";
                defaultValue = "45";
                typeName = "NUMBER";
            };
            class Port {
                displayName = "Port";
                tooltip = "Prise du support : change la vitesse et l'écran du module DL-7.";
                property = "COMSPEC_ATAK_Module_DataNode_Port";
                control = "Combo";
                expression = "_this setVariable ['Port',_value,true];";
                defaultValue = "'usb'";
                typeName = "STRING";
                class values {
                    class V0 { name = "USB-C"; value = "usb"; default = 1; };
                    class V1 { name = "Ethernet RJ45"; value = "rj45"; };
                    class V2 { name = "Série RS-232 (lent)"; value = "serie"; };
                };
            };
            class Alarm {
                displayName = "Alarme";
                tooltip = "Sirène sur le support, Zeus prévenu et position du joueur révélée aux ennemis à 400 m.";
                property = "COMSPEC_ATAK_Module_DataNode_Alarm";
                control = "Combo";
                expression = "_this setVariable ['Alarm',_value,true];";
                defaultValue = "'none'";
                typeName = "STRING";
                class values {
                    class V0 { name = "Aucune"; value = "none"; default = 1; };
                    class V1 { name = "Au branchement"; value = "connect"; };
                    class V2 { name = "Mauvais mot de passe"; value = "fail"; };
                    class V3 { name = "En fin de téléchargement"; value = "download"; };
                };
            };
            class OneShot {
                displayName = "Effacé après extraction";
                tooltip = "Données effacées après le premier téléchargement complet.";
                property = "COMSPEC_ATAK_Module_DataNode_OneShot";
                control = "Checkbox";
                expression = "_this setVariable ['OneShot',_value,true];";
                defaultValue = "false";
                typeName = "BOOL";
            };
            class Files {
                displayName = "Fichiers";
                tooltip = "Un bloc par fichier, séparés par une ligne ---. Première ligne : nom | type (texte, document, identifiants, coord, image, audio). Lignes suivantes : contenu.";
                property = "COMSPEC_ATAK_Module_DataNode_Files";
                control = "EditMulti5";
                expression = "_this setVariable ['Files',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class Accent {
                displayName = "Couleur de l'écran";
                tooltip = "#rrggbb, vide = couleur du thème.";
                property = "COMSPEC_ATAK_Module_DataNode_Accent";
                control = "Edit";
                expression = "_this setVariable ['Accent',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class Wallpaper {
                displayName = "Image de l'écran";
                tooltip = "Image de la mission (ex. images\logo.paa) en tête de l'écran de connexion.";
                property = "COMSPEC_ATAK_Module_DataNode_Wallpaper";
                control = "Edit";
                expression = "_this setVariable ['Wallpaper',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class Code {
                displayName = "Code serveur en fin";
                tooltip = "Exécuté sur le serveur à la fin d'un téléchargement. params [""_object"", ""_player"", ""_files""]. Aussi : événement CBA comspec_atak_native_dataDownloaded.";
                property = "COMSPEC_ATAK_Module_DataNode_Code";
                control = "EditCodeMulti5";
                expression = "_this setVariable ['Code',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
    };
    class ModuleDescription {
        description = "Fichiers à télécharger au câble DL-7 depuis l'ATAK, sur n'importe quel objet : écran de connexion, mot de passe, forçage, alarme. Synchronisez des objets (Eden) ou lâchez le module sur l'objet (Zeus) ; sans objet, le support choisi est créé.";
    };
};
class COMSPEC_ATAK_Module_DataFile: COMSPEC_ATAK_Module_DataBase {
    scope = 2;
    scopeCurator = 2;
    displayName = "Ajouter un fichier (câble ATAK)";
    function = "comspec_atak_native_fnc_moduleDataFile";
    class Attributes {
            class FileName {
                displayName = "Nom du fichier";
                tooltip = "Ex. ordre_operation.pdf";
                property = "COMSPEC_ATAK_Module_DataFile_FileName";
                control = "Edit";
                expression = "_this setVariable ['FileName',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class FileType {
                displayName = "Type";
                tooltip = "Texte, document, identifiants, coordonnées, image ou audio.";
                property = "COMSPEC_ATAK_Module_DataFile_FileType";
                control = "Combo";
                expression = "_this setVariable ['FileType',_value,true];";
                defaultValue = "'texte'";
                typeName = "STRING";
                class values {
                    class V0 { name = "Texte"; value = "texte"; default = 1; };
                    class V1 { name = "Document"; value = "document"; };
                    class V2 { name = "Identifiants"; value = "identifiants"; };
                    class V3 { name = "Coordonnées"; value = "coord"; };
                    class V4 { name = "Image (chemin dans la mission)"; value = "image"; };
                    class V5 { name = "Audio (CfgSounds ou chemin)"; value = "audio"; };
                };
            };
            class FileContent {
                displayName = "Contenu";
                tooltip = "Texte, carroyage (045123) ou x, y, chemin d'image, classe CfgSounds.";
                property = "COMSPEC_ATAK_Module_DataFile_FileContent";
                control = "EditMulti5";
                expression = "_this setVariable ['FileContent',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
    };
    class ModuleDescription {
        description = "Ajoute un fichier aux données d'un objet (crée des données en accès libre s'il n'en a pas).";
    };
};
class COMSPEC_ATAK_Module_DataReset: COMSPEC_ATAK_Module_DataBase {
    scope = 1;
    scopeCurator = 2;
    displayName = "Remettre / retirer les données (câble ATAK)";
    function = "comspec_atak_native_fnc_moduleDataReset";
};
