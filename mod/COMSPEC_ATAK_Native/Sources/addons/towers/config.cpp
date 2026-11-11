// Relais 3D du réseau ATAK natif : pylônes, mâts tactiques, antennes murales, kits déployés (tools/model_relay_towers.py,
// tools/model_relay_kits.py), caméras IP (cfg_cameras.hpp) et infrastructure réseau (cfg_infra.hpp). PBO à part de main.
// Enregistrement comme relais : comspec_atak_native_fnc_relayTower (main). Classes et noms : tools/gen_relay_config.py
// (cfg_relays.hpp, cfg_units.hpp, stringtable.xml de cet addon). Audit : docs/audit/2026-11-relais.md.
#include "cfg_units.hpp"
#define COMSPEC_INFRA_UNITS "COMSPEC_ATAK_Infra_RackPatch","COMSPEC_ATAK_Infra_RackServer","COMSPEC_ATAK_Infra_Wall9U","COMSPEC_ATAK_Infra_Wall12U","COMSPEC_ATAK_Infra_ServerTower","COMSPEC_ATAK_Infra_UPS","COMSPEC_ATAK_Infra_SwitchDesk","COMSPEC_ATAK_Infra_CRAC","COMSPEC_ATAK_Infra_ElecCabinet","COMSPEC_ATAK_Infra_TrayStraight","COMSPEC_ATAK_Infra_TrayCorner","COMSPEC_ATAK_Infra_TrayTee","COMSPEC_ATAK_Infra_TrunkStraight","COMSPEC_ATAK_Infra_TrunkCorner"
#define COMSPEC_CAM_UNITS "COMSPEC_ATAK_Cam_PTZ_Wall","COMSPEC_ATAK_Cam_PTZ_Wall_Dark","COMSPEC_ATAK_Cam_PTZ_Pole","COMSPEC_ATAK_Cam_Bullet_Wall","COMSPEC_ATAK_Cam_Bullet_Wall_Dark","COMSPEC_ATAK_Cam_Bullet_Ceiling","COMSPEC_ATAK_Cam_Bullet_Pole","COMSPEC_ATAK_Cam_Bullet_Corner","COMSPEC_ATAK_Cam_Thermal_Wall","COMSPEC_ATAK_Cam_Thermal_Wall_Dark","COMSPEC_ATAK_Cam_Thermal_Pole","COMSPEC_ATAK_Cam_MiniDome","COMSPEC_ATAK_Cam_MiniDome_Wall","COMSPEC_ATAK_Cam_Fisheye","COMSPEC_ATAK_Cam_Tactical","COMSPEC_ATAK_Cam_Covert"
class CfgPatches {
    class comspec_atak_native_towers {
        name="COMSPEC ATAK Native - Pylônes relais"; author="COMSPEC"; requiredVersion=2.14;
        requiredAddons[]={"comspec_atak_native_main","comspec_atak_native_items","A3_Structures_F","cba_xeh"};
        units[]={COMSPEC_RELAY_UNITS,COMSPEC_CAM_UNITS,COMSPEC_INFRA_UNITS}; weapons[]={};
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
        // Sélections : 0 = camo (toute la structure : galvanisé, rouge-blanc, vert OTAN, sable), 1 = led (voyant d'état des
        // armoires et des radios, posé par fn_relayLed selon l'état réel du relais).
        hiddenSelections[]={"camo","led"};
        hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"};
        // Équipements masquables (model.cfg, animations « hide ») : eq1 = équipement léger, eq2 = complément du complet.
        class AnimationSources {
            class eq1 { source="user"; animPeriod=0.001; initPhase=0; };
            class eq2 { source="user"; animPeriod=0.001; initPhase=0; };
        };
        // Feux d'obstacle rouges (globes émissifs du modèle + lumière du jeu la nuit), points mémoire light_*.
        class MarkerLights {
            class Top {
                name="light_top"; color[]={1,0.08,0.03}; ambient[]={0.1,0.008,0.003};
                brightness=0.01; intensity=80; drawLight=1; drawLightSize=0.35; drawLightCenterSize=0.08;
                activeLight=1; blinking=1; blinkingPattern[]={0.6,1.4}; blinkingPatternGuarantee=0; dayLight=0; useFlare=1;
                class Attenuation { start=0; constant=0; linear=0; quadratic=1; hardLimitStart=40; hardLimitEnd=60; };
            };
        };
        class Attributes {
            class COMSPEC_TowerPaint {
                displayName="$STR_COMSPEC_ATAK_REL_PAINT"; tooltip="$STR_COMSPEC_ATAK_REL_PAINT_TIP";
                property="COMSPEC_TowerPaint"; control="Combo"; typeName="STRING"; defaultValue="''";
                expression="[_this, _value, -1] call comspec_atak_native_fnc_relayLook;";
                class values {
                    class V0 { name="$STR_COMSPEC_ATAK_REL_PAINT_DEF"; value=""; default=1; };
                    class V1 { name="$STR_COMSPEC_ATAK_REL_PAINT_GALVA"; value="galva"; };
                    class V2 { name="$STR_COMSPEC_ATAK_REL_PAINT_RW"; value="rw"; };
                    class V3 { name="$STR_COMSPEC_ATAK_REL_PAINT_OLIVE"; value="olive"; };
                    class V4 { name="$STR_COMSPEC_ATAK_REL_PAINT_SAND"; value="sand"; };
                };
            };
            class COMSPEC_TowerEquip {
                displayName="$STR_COMSPEC_ATAK_REL_EQUIP"; tooltip="$STR_COMSPEC_ATAK_REL_EQUIP_TIP";
                property="COMSPEC_TowerEquip"; control="Combo"; typeName="NUMBER"; defaultValue="0 - 1";
                expression="[_this, '', _value] call comspec_atak_native_fnc_relayLook;";
                class values {
                    class V0 { name="$STR_COMSPEC_ATAK_REL_PAINT_DEF"; value=-1; default=1; };
                    class V1 { name="$STR_COMSPEC_ATAK_REL_EQUIP_FULL"; value=2; };
                    class V2 { name="$STR_COMSPEC_ATAK_REL_EQUIP_LIGHT"; value=1; };
                    class V3 { name="$STR_COMSPEC_ATAK_REL_EQUIP_BARE"; value=0; };
                };
            };
            class COMSPEC_TowerName {
                displayName="$STR_COMSPEC_ATAK_CFG_ACB975B116"; tooltip="$STR_COMSPEC_ATAK_CFG_2669F78090";
                property="COMSPEC_TowerName"; control="Edit"; typeName="STRING"; defaultValue="''";
                expression="_this setVariable ['COMSPEC_TowerName', _value];";
            };
            class COMSPEC_TowerSide {
                displayName="$STR_COMSPEC_ATAK_CFG_7AB7D995F3"; tooltip="$STR_COMSPEC_ATAK_CFG_357E475CDC";
                property="COMSPEC_TowerSide"; control="Combo"; typeName="STRING"; defaultValue="''";
                expression="_this setVariable ['COMSPEC_TowerSide', _value];";
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
                expression="_this setVariable ['COMSPEC_TowerUplink', _value];";
                class values {
                    class V0 { name="$STR_COMSPEC_ATAK_CFG_0167FC858D"; value="root"; default=1; };
                    class V1 { name="$STR_COMSPEC_ATAK_CFG_92D4E717EB"; value="repeater"; };
                };
            };
            // Bande de fréquence (préparation des fréquences par relais : retenue sur le relais, pas encore de brouillage
            // ni de compatibilité entre bandes). 0 = automatique.
            class COMSPEC_TowerFreq {
                displayName="$STR_COMSPEC_ATAK_CFG_FREQ_NAME"; tooltip="$STR_COMSPEC_ATAK_CFG_FREQ_TIP";
                property="COMSPEC_TowerFreq"; control="Combo"; typeName="NUMBER"; defaultValue="0";
                expression="_this setVariable ['COMSPEC_TowerFreq', _value];";
                class values {
                    class V0 { name="$STR_COMSPEC_ATAK_CFG_FREQ_AUTO"; value=0; default=1; };
                    class V1 { name="UHF 400 MHz"; value=400; };
                    class V2 { name="L 1,4 GHz"; value=1400; };
                    class V3 { name="S 2,4 GHz"; value=2400; };
                    class V4 { name="C 4,9 GHz"; value=4900; };
                };
            };
            class COMSPEC_TowerOn {
                displayName="$STR_COMSPEC_ATAK_CFG_A54A861193"; tooltip="$STR_COMSPEC_ATAK_CFG_3C55341417";
                property="COMSPEC_TowerOn"; control="Checkbox"; typeName="BOOL"; defaultValue="true";
                expression="_this setVariable ['COMSPEC_TowerOn', _value];";
            };
            class COMSPEC_TowerIndestructible {
                displayName="$STR_COMSPEC_ATAK_CFG_29CF136691"; tooltip="$STR_COMSPEC_ATAK_CFG_5EE2379472";
                property="COMSPEC_TowerIndestructible"; control="Checkbox"; typeName="BOOL"; defaultValue="false";
                expression="_this setVariable ['COMSPEC_TowerIndestructible', _value];";
            };
        };
    };
    #include "cfg_relays.hpp"
    // Antennes déployées par les kits (fn_antennaKit, classes de main) : modèles du mod à la place des modèles d'Arma.
    // Sans cet addon, main garde les modèles d'Arma : rien ne casse.
    class COMSPEC_ATAK_DeployedAntenna_Base;
    class COMSPEC_ATAK_DeployedAntenna_Sat: COMSPEC_ATAK_DeployedAntenna_Base {
        model="\z\comspec_atak_native\addons\towers\data\relay_kit_sat.p3d";
        hiddenSelections[]={"camo","led"};
        hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_olive_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"};
    };
    class COMSPEC_ATAK_DeployedAntenna_Mini: COMSPEC_ATAK_DeployedAntenna_Base {
        model="\z\comspec_atak_native\addons\towers\data\relay_kit_mini.p3d";
        hiddenSelections[]={"camo","led"};
        hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"};
    };
    class COMSPEC_ATAK_DeployedAntenna_Omni: COMSPEC_ATAK_DeployedAntenna_Base {
        model="\z\comspec_atak_native\addons\towers\data\relay_kit_field.p3d";
        hiddenSelections[]={"camo","led"};
        hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\towers\data\relay_steel_olive_co.paa","#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"};
    };
    // Caméras IP et infrastructure réseau intérieure (modèles dans items.pbo).
    #include "cfg_cameras.hpp"
    #include "cfg_infra.hpp"
};
class CfgEditorSubcategories {
    class COMSPEC_ATAK_EdSubcat_Relays { displayName="$STR_COMSPEC_ATAK_CFG_E4E74278CD"; };
    class COMSPEC_ATAK_EdSubcat_RelayVariants { displayName="$STR_COMSPEC_ATAK_REL_SUB_VARIANTS"; };
    class COMSPEC_ATAK_EdSubcat_RelayAntennas { displayName="$STR_COMSPEC_ATAK_REL_SUB_ANTENNAS"; };
    class COMSPEC_ATAK_EdSubcat_Cameras { displayName="$STR_COMSPEC_ATAK_REL_SUB_CAMERAS"; };
    class COMSPEC_ATAK_EdSubcat_Infra { displayName="$STR_COMSPEC_ATAK_REL_SUB_INFRA"; };
};
class CfgFunctions {
    class comspec_atak_native {
        class towers {
            file="\z\comspec_atak_native\addons\towers\functions";
            class relayLed {}; class relayFx {}; class relayLook {};
        };
        #include "cfg_functions_extra.hpp"
    };
};
class Extended_Init_EventHandlers {
    class COMSPEC_ATAK_RelayTower_Base { class comspec_atak_native_tower { serverInit="[_this select 0] call comspec_atak_native_fnc_relayTower"; }; };
    class COMSPEC_ATAK_Infra_Base { class comspec_atak_native_infra { init="['init', _this select 0] call comspec_atak_native_fnc_infra"; }; };
    class COMSPEC_ATAK_Cam_Base { class comspec_atak_native_cam { serverInit="[_this select 0] call comspec_atak_native_fnc_camProp"; }; };
};
class Extended_Killed_EventHandlers {
    class COMSPEC_ATAK_RelayTower_Base { class comspec_atak_native_tower_fx { killed="[_this select 0] remoteExec ['comspec_atak_native_fnc_relayFx', [0, -2] select isDedicated]"; }; };
};
class Extended_PreInit_EventHandlers {
    class comspec_atak_native_infra { init="['settings'] call comspec_atak_native_fnc_infra"; };
};
class Extended_PostInit_EventHandlers {
    class comspec_atak_native_infra { init="['postInit'] call comspec_atak_native_fnc_infra"; };
    class comspec_atak_native_towers { clientInit="['init'] call comspec_atak_native_fnc_relayLed; [] call comspec_atak_native_fnc_camLed;"; };
};
