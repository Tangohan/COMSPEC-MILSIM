// Pylônes relais 3D du réseau ATAK natif (tools/model_relay_towers.py), PBO à part de main (taille des modèles MLOD).
// Fonction d'enregistrement comme relais : comspec_atak_native_fnc_relayTower (main) ; textes : stringtable de main.
class CfgPatches {
    class comspec_atak_native_towers {
        name="COMSPEC ATAK Native - Pylônes relais"; author="COMSPEC"; requiredVersion=2.14;
        requiredAddons[]={"comspec_atak_native_main","A3_Structures_F","cba_xeh"};
        units[]={"COMSPEC_ATAK_RelayTower_30","COMSPEC_ATAK_RelayTower_40","COMSPEC_ATAK_RelayTower_50"}; weapons[]={};
    };
};
class CfgVehicles {
    class House;
    class House_F: House { class DestructionEffects; };
    class Ruins_F;
    // Pylônes relais 3D (tools/model_relay_towers.py) : relais du réseau ATAK dès leur pose (fn_relayTower), destructibles
    // (ruine : pylône plié et couché, équipements arrachés). Valeurs du relais : comspec_atak_relay* ; attributs Eden ci-dessous.
    class COMSPEC_ATAK_RelayTower_Base: House_F {
        scope=0; scopeCurator=0; author="COMSPEC";
        vehicleClass="Structures"; editorCategory="EdCat_Structures"; editorSubcategory="COMSPEC_ATAK_EdSubcat_Relays";
        icon="iconObject_1x1"; mapSize=4; cost=50000;
        destrType="DestructBuilding"; armor=700;
        comspec_atak_relayRange=6000; comspec_atak_relayHeight=28; comspec_atak_relayThroughput=20; comspec_atak_relaySlots=64;
        class DestructionEffects: DestructionEffects {};
        class Attributes {
            class COMSPEC_TowerName {
                displayName="$STR_COMSPEC_ATAK_CFG_ACB975B116"; tooltip="$STR_COMSPEC_ATAK_CFG_2669F78090";
                property="COMSPEC_TowerName"; control="Edit"; typeName="STRING"; defaultValue="''";
                expression="_this setVariable ['COMSPEC_TowerName', _value, true];";
            };
            class COMSPEC_TowerSide {
                displayName="$STR_COMSPEC_ATAK_CFG_7AB7D995F3"; tooltip="$STR_COMSPEC_ATAK_CFG_357E475CDC";
                property="COMSPEC_TowerSide"; control="Combo"; typeName="STRING"; defaultValue="''";
                expression="_this setVariable ['COMSPEC_TowerSide', _value, true];";
                class values {
                    class V0 { name="$STR_COMSPEC_ATAK_CFG_A79E887C37"; value=""; default=1; };
                    class V1 { name="BLUFOR"; value="WEST"; };
                    class V2 { name="OPFOR"; value="EAST"; };
                    class V3 { name="$STR_COMSPEC_ATAK_CFG_621714C49D"; value="GUER"; };
                    class V4 { name="$STR_COMSPEC_ATAK_CFG_7AA3BFB0ED"; value="CIV"; };
                };
            };
            class COMSPEC_TowerUplink {
                displayName="$STR_COMSPEC_ATAK_CFG_30DE5E847C"; tooltip="$STR_COMSPEC_ATAK_CFG_DF1600163F";
                property="COMSPEC_TowerUplink"; control="Combo"; typeName="STRING"; defaultValue="'root'";
                expression="_this setVariable ['COMSPEC_TowerUplink', _value, true];";
                class values {
                    class V0 { name="$STR_COMSPEC_ATAK_CFG_0167FC858D"; value="root"; default=1; };
                    class V1 { name="$STR_COMSPEC_ATAK_CFG_92D4E717EB"; value="repeater"; };
                };
            };
            class COMSPEC_TowerOn {
                displayName="$STR_COMSPEC_ATAK_CFG_A54A861193"; tooltip="$STR_COMSPEC_ATAK_CFG_3C55341417";
                property="COMSPEC_TowerOn"; control="Checkbox"; typeName="BOOL"; defaultValue="true";
                expression="_this setVariable ['COMSPEC_TowerOn', _value, true];";
            };
            class COMSPEC_TowerIndestructible {
                displayName="$STR_COMSPEC_ATAK_CFG_29CF136691"; tooltip="$STR_COMSPEC_ATAK_CFG_5EE2379472";
                property="COMSPEC_TowerIndestructible"; control="Checkbox"; typeName="BOOL"; defaultValue="false";
                expression="_this setVariable ['COMSPEC_TowerIndestructible', _value, true];";
            };
        };
    };
    class COMSPEC_ATAK_RelayTower_30: COMSPEC_ATAK_RelayTower_Base {
        scope=2; scopeCurator=2;
        displayName="$STR_COMSPEC_ATAK_CFG_A820F0E110";
        model="\z\comspec_atak_native\addons\towers\data\relay_tower_30.p3d";
        editorPreview="\z\comspec_atak_native\addons\towers\data\relay_tower_30_preview.jpg";
        comspec_atak_relayRange=6000; comspec_atak_relayHeight=28; comspec_atak_relayThroughput=20; comspec_atak_relaySlots=64;
        armor=500;
        class DestructionEffects: DestructionEffects {
            class Ruin1 { simulation="ruin"; type="\z\comspec_atak_native\addons\towers\data\relay_tower_30_ruin.p3d"; position=""; intensity=1; interval=1; lifeTime=1; };
        };
    };
    class COMSPEC_ATAK_RelayTower_40: COMSPEC_ATAK_RelayTower_Base {
        scope=2; scopeCurator=2;
        displayName="$STR_COMSPEC_ATAK_CFG_7FACF53561";
        model="\z\comspec_atak_native\addons\towers\data\relay_tower_40.p3d";
        editorPreview="\z\comspec_atak_native\addons\towers\data\relay_tower_40_preview.jpg";
        comspec_atak_relayRange=9000; comspec_atak_relayHeight=38; comspec_atak_relayThroughput=30; comspec_atak_relaySlots=96;
        armor=700;
        class DestructionEffects: DestructionEffects {
            class Ruin1 { simulation="ruin"; type="\z\comspec_atak_native\addons\towers\data\relay_tower_40_ruin.p3d"; position=""; intensity=1; interval=1; lifeTime=1; };
        };
    };
    class COMSPEC_ATAK_RelayTower_50: COMSPEC_ATAK_RelayTower_Base {
        scope=2; scopeCurator=2;
        displayName="$STR_COMSPEC_ATAK_CFG_83983FCE7E";
        model="\z\comspec_atak_native\addons\towers\data\relay_tower_50.p3d";
        editorPreview="\z\comspec_atak_native\addons\towers\data\relay_tower_50_preview.jpg";
        comspec_atak_relayRange=12000; comspec_atak_relayHeight=48; comspec_atak_relayThroughput=40; comspec_atak_relaySlots=128;
        armor=900;
        class DestructionEffects: DestructionEffects {
            class Ruin1 { simulation="ruin"; type="\z\comspec_atak_native\addons\towers\data\relay_tower_50_ruin.p3d"; position=""; intensity=1; interval=1; lifeTime=1; };
        };
    };
    class COMSPEC_ATAK_RelayTower_30_ruins: Ruins_F { scope=1; scopeCurator=0; author="COMSPEC"; displayName="$STR_COMSPEC_ATAK_CFG_DB4BE04D53"; model="\z\comspec_atak_native\addons\towers\data\relay_tower_30_ruin.p3d"; };
    class COMSPEC_ATAK_RelayTower_40_ruins: Ruins_F { scope=1; scopeCurator=0; author="COMSPEC"; displayName="$STR_COMSPEC_ATAK_CFG_69F7129E3B"; model="\z\comspec_atak_native\addons\towers\data\relay_tower_40_ruin.p3d"; };
    class COMSPEC_ATAK_RelayTower_50_ruins: Ruins_F { scope=1; scopeCurator=0; author="COMSPEC"; displayName="$STR_COMSPEC_ATAK_CFG_BFA2360137"; model="\z\comspec_atak_native\addons\towers\data\relay_tower_50_ruin.p3d"; };
};
class CfgEditorSubcategories {
    class COMSPEC_ATAK_EdSubcat_Relays { displayName="$STR_COMSPEC_ATAK_CFG_E4E74278CD"; };
};
class Extended_Init_EventHandlers {
    class COMSPEC_ATAK_RelayTower_Base { class comspec_atak_native_tower { serverInit="[_this select 0] call comspec_atak_native_fnc_relayTower"; }; };
};
