// Configuration du module Zeus pour scanner les relais
// À ajouter dans config.cpp du mod

class CfgFactionClasses {
    class COMSPEC_ATAK {
        displayName = "$STR_COMSPEC_OW_CFG_AE461ABCB5";
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
        displayName = "$STR_COMSPEC_OW_CFG_D04248E677";
        icon = "\a3\ui_f\data\IGUI\Cfg\simpleTasks\types\signal_ca.paa";
        category = "COMSPEC_ATAK";
        function = "ATHENA_fnc_zeusModuleScanRelays";
        functionPriority = 1;
        isGlobal = 0;
        isTriggerActivated = 1;
        isDisposable = 0;
        
        class Arguments {
            class ScanRadius {
                displayName = "$STR_COMSPEC_OW_CFG_64CDA735DA";
                description = "$STR_COMSPEC_OW_CFG_F5B75017DE";
                typeName = "NUMBER";
                defaultValue = 5000;
            };
            class AutoUpload {
                displayName = "$STR_COMSPEC_OW_CFG_EA0A853A02";
                description = "$STR_COMSPEC_OW_CFG_22E71CD3F3";
                typeName = "BOOL";
                defaultValue = 1;
            };
        };
        
        class ModuleDescription {
            description = "$STR_COMSPEC_OW_CFG_3EA68EFD33";
            sync[] = {};
        };
    };
    
    // Module Zeus : Tableau de bord des relais
    class COMSPEC_ModuleRelayDashboard: Module_F {
        scope = 2;
        displayName = "$STR_COMSPEC_OW_CFG_5A2FEC5375";
        icon = "\a3\ui_f\data\IGUI\Cfg\simpleTasks\types\signal_ca.paa";
        category = "COMSPEC_ATAK";
        function = "ATHENA_fnc_openRelayDashboard";
        functionPriority = 1;
        isGlobal = 0;
        isTriggerActivated = 1;
        isDisposable = 0;
        
        class ModuleDescription {
            description = "$STR_COMSPEC_OW_CFG_1B834F3DE2";
            sync[] = {};
        };
    };
    
    // Module Zeus : Resync d'un relais spécifique
    class COMSPEC_ModuleResyncRelay: Module_F {
        scope = 2;
        displayName = "$STR_COMSPEC_OW_CFG_9BE0C2885D";
        icon = "\a3\ui_f\data\IGUI\Cfg\simpleTasks\types\upload_ca.paa";
        category = "COMSPEC_ATAK";
        function = "ATHENA_fnc_zeusResyncRelay";
        functionPriority = 1;
        isGlobal = 0;
        isTriggerActivated = 1;
        isDisposable = 0;
        
        class ModuleDescription {
            description = "$STR_COMSPEC_OW_CFG_3D21C9B243";
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
