// Configuration du module Zeus pour scanner les relais
// À ajouter dans config.cpp du mod

class CfgFactionClasses {
    class COMSPEC_ATAK {
        displayName = "COMSPEC ATAK";
        priority = 2;
        side = 7; // Logic
        icon = "\a3\ui_f\data\IGUI\Cfg\simpleTasks\types\signal_ca.paa";
    };
};

class CfgVehicles {
    // Module Zeus : Scanner et remonter les relais
    class Module_F;
    class COMSPEC_ModuleScanRelays: Module_F {
        scope = 2;
        displayName = "Scanner réseau relais";
        icon = "\a3\ui_f\data\IGUI\Cfg\simpleTasks\types\signal_ca.paa";
        category = "COMSPEC_ATAK";
        function = "ATHENA_fnc_zeusModuleScanRelays";
        functionPriority = 1;
        isGlobal = 0;
        isTriggerActivated = 1;
        isDisposable = 0;
        
        class Arguments {
            class ScanRadius {
                displayName = "Rayon de scan (m)";
                description = "Distance maximum pour détecter les relais";
                typeName = "NUMBER";
                defaultValue = 5000;
            };
            class AutoUpload {
                displayName = "Upload automatique";
                description = "Remonter automatiquement les relais détectés";
                typeName = "BOOL";
                defaultValue = 1;
            };
        };
        
        class ModuleDescription {
            description = "Scanne tous les relais radio dans un rayon et les remonte vers le serveur ATHENA. Affiche un rapport style relevé terrain avec feedback visuel.";
            sync[] = {};
        };
    };
    
    // Module Zeus : Tableau de bord des relais
    class COMSPEC_ModuleRelayDashboard: Module_F {
        scope = 2;
        displayName = "Tableau de bord relais";
        icon = "\a3\ui_f\data\IGUI\Cfg\simpleTasks\types\signal_ca.paa";
        category = "COMSPEC_ATAK";
        function = "ATHENA_fnc_openRelayDashboard";
        functionPriority = 1;
        isGlobal = 0;
        isTriggerActivated = 1;
        isDisposable = 0;
        
        class ModuleDescription {
            description = "Ouvre le tableau de bord des relais radio avec statut en temps réel, détection d'erreurs, et actions de gestion.";
            sync[] = {};
        };
    };
    
    // Module Zeus : Resync d'un relais spécifique
    class COMSPEC_ModuleResyncRelay: Module_F {
        scope = 2;
        displayName = "Resync relais";
        icon = "\a3\ui_f\data\IGUI\Cfg\simpleTasks\types\upload_ca.paa";
        category = "COMSPEC_ATAK";
        function = "ATHENA_fnc_zeusResyncRelay";
        functionPriority = 1;
        isGlobal = 0;
        isTriggerActivated = 1;
        isDisposable = 0;
        
        class ModuleDescription {
            description = "Resynchronise immédiatement un relais spécifique vers le serveur ATHENA. Placez le module sur le relais.";
            sync[] = {};
        };
    };
};

// Export des fonctions
class CfgFunctions {
    class ATHENA {
        class RelayManagement {
            file = "comspec-overwatch-addons\connect\functions";
            class zeusModuleScanRelays {};
            class openRelayDashboard {};
            class addRelayDashboardActions {};
            class zeusResyncRelay {
                // Fonction inline pour resync rapide
                postInit = 0;
                preInit = 0;
            };
        };
    };
};
