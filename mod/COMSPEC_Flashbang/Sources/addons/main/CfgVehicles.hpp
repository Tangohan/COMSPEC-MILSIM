class CfgFactionClasses {
    class COMSPEC_FB {
        displayName = "COMSPEC Flashbang";
        priority = 2;
        side = 7;
    };
};
class CfgEditorSubcategories {
    class COMSPEC_FB_Sub {
        displayName = "COMSPEC Flashbang";
    };
};

class CfgVehicles {
    class ThingX;
    class COMSPEC_FB_Device_Base: ThingX {
        author = "COMSPEC";
        scope = 1;
        scopeCurator = 0;
        displayName = "$STR_COMSPEC_FB_Device";
        model = QPATHTOF(data\fb_1_spent.p3d);
        icon = "iconObject_circle";
        mapSize = 0.12;
        destrType = "DestructNo";
        armor = 1000;
        editorSubcategory = "COMSPEC_FB_Sub";
    };
#include "CfgVehicles_devices.hpp"

    // Caisse de grenades : tous les modèles, 10 de chaque (Eden et Zeus).
    class Box_NATO_Grenades_F;
    class COMSPEC_FB_Box: Box_NATO_Grenades_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_FB_Box";
        editorSubcategory = "COMSPEC_FB_Sub";
        maximumLoad = 2000;
        class TransportMagazines {
#include "CfgVehicles_box.hpp"
        };
        class TransportItems {};
        class TransportWeapons {};
        class TransportBackpacks {};
    };

    // Module Eden (et Zeus vanilla, valeurs par défaut) : grenade à N flashs, effet de zone ou grenade d'entraînement.
    class Logic;
    class Module_F: Logic {
        class AttributesBase {
            class Edit;
            class Combo;
            class Checkbox;
            class ModuleDescription;
        };
        class ModuleDescription;
    };
    class COMSPEC_FB_Module: Module_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_FB_Module";
        icon = QPATHTOF(ui\fb_module_ca.paa);
        portrait = QPATHTOF(ui\fb_module_ca.paa);
        category = "COMSPEC_FB";
        function = "comspec_fb_fnc_moduleEden";
        functionPriority = 1;
        isGlobal = 0;
        isTriggerActivated = 1;
        isDisposable = 1;
        is3DEN = 0;
        curatorCanAttach = 0;
        class Attributes: AttributesBase {
            class Mode: Combo {
                property = "COMSPEC_FB_Module_Mode";
                displayName = "$STR_COMSPEC_FB_Module_Mode";
                tooltip = "$STR_COMSPEC_FB_Module_Mode_Tip";
                typeName = "STRING";
                defaultValue = """grenade""";
                class Values {
                    class Grenade { name = "$STR_COMSPEC_FB_Module_Mode_Grenade"; value = "grenade"; };
                    class Zone { name = "$STR_COMSPEC_FB_Module_Mode_Zone"; value = "zone"; };
                    class Drill { name = "$STR_COMSPEC_FB_Module_Mode_Drill"; value = "drill"; };
                };
            };
            class Bursts: Edit {
                property = "COMSPEC_FB_Module_Bursts";
                displayName = "$STR_COMSPEC_FB_Module_Bursts";
                tooltip = "$STR_COMSPEC_FB_Module_Bursts_Tip";
                typeName = "NUMBER";
                defaultValue = "3";
            };
            class Delay: Edit {
                property = "COMSPEC_FB_Module_Delay";
                displayName = "$STR_COMSPEC_FB_Module_Delay";
                tooltip = "$STR_COMSPEC_FB_Module_Delay_Tip";
                typeName = "NUMBER";
                defaultValue = "0";
            };
            class Radius: Edit {
                property = "COMSPEC_FB_Module_Radius";
                displayName = "$STR_COMSPEC_FB_Module_Radius";
                tooltip = "$STR_COMSPEC_FB_Module_Radius_Tip";
                typeName = "NUMBER";
                defaultValue = "10";
            };
            class Strength: Edit {
                property = "COMSPEC_FB_Module_Strength";
                displayName = "$STR_COMSPEC_FB_Module_Strength";
                tooltip = "$STR_COMSPEC_FB_Module_Strength_Tip";
                typeName = "NUMBER";
                defaultValue = "0.8";
            };
            class ShowFx: Checkbox {
                property = "COMSPEC_FB_Module_ShowFx";
                displayName = "$STR_COMSPEC_FB_Module_ShowFx";
                tooltip = "$STR_COMSPEC_FB_Module_ShowFx_Tip";
                typeName = "BOOL";
                defaultValue = "true";
            };
            class ModuleDescription: ModuleDescription {};
        };
        class ModuleDescription: ModuleDescription {
            description = "$STR_COMSPEC_FB_Module_Desc";
        };
    };
};
