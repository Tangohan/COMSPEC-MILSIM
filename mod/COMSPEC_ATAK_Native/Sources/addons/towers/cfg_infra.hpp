// Infrastructure réseau intérieure (tools/model_net_infra.py, modèles dans items/data/infra) : à inclure DANS CfgVehicles
// de towers/config.cpp (qui déclare « class Static; »). Logique : functions/infra/fn_infra.sqf.
// Rôle (comspec_atak_infraRole) : "server" (baie serveur, serveur tour), "network" (brassage, coffrets, switch),
// "power" (onduleur, armoire électrique), "cooling" (climatiseur) ; vide = décor (chemins de câbles, goulottes).
// Voyants : sélections led (vert), led2 (ambre, clignote), led3 (bleu / rouge), reposées par fn_infra « tick ».
// side=3 : Static hérite de All (side=4) et ni Eden ni Zeus ne listent ce camp (voir toc_screens3d.hpp).
#define COMSPEC_INFRA_ATTRIBUTES \
    class Attributes { \
        class COMSPEC_InfraName { \
            displayName="$STR_COMSPEC_ATAK_CFG_INFRA_ATTR_NAME"; tooltip="$STR_COMSPEC_ATAK_CFG_INFRA_ATTR_NAME_TIP"; \
            property="COMSPEC_InfraName"; control="Edit"; typeName="STRING"; defaultValue="''"; \
            expression="if (_value isNotEqualTo '') then { _this setVariable ['COMSPEC_InfraName', _value, true]; };"; \
        }; \
        class COMSPEC_InfraOn { \
            displayName="$STR_COMSPEC_ATAK_CFG_INFRA_ATTR_ON"; tooltip="$STR_COMSPEC_ATAK_CFG_INFRA_ATTR_ON_TIP"; \
            property="COMSPEC_InfraOn"; control="Checkbox"; typeName="BOOL"; defaultValue="true"; \
            expression="_this setVariable ['COMSPEC_InfraOn', _value];"; \
        }; \
    }
    class COMSPEC_ATAK_Infra_Base: Static {
        scope=0; scopeCurator=0; author="COMSPEC";
        side=3; faction="Default";
        icon="iconObject_1x1"; mapSize=1;
        vehicleClass="Items"; editorCategory="COMSPEC_ATAK_EdCat"; editorSubcategory="COMSPEC_ATAK_EdSubcat_Infra";
        destrType="DestructDefault"; armor=60;
        comspec_atak_infraRole="";
        hiddenSelections[]={"led","led2","led3"};
        hiddenSelectionsTextures[]={"#(argb,8,8,3)color(0.1,1,0.25,1,co)","#(argb,8,8,3)color(1,0.55,0.05,1,co)","#(argb,8,8,3)color(0.15,0.45,1,1,co)"};
    };
    class COMSPEC_ATAK_Infra_RackPatch: COMSPEC_ATAK_Infra_Base {
        scope=2; scopeCurator=2;
        displayName="$STR_COMSPEC_ATAK_CFG_INFRA_RACK_PATCH";
        model="\z\comspec_atak_native\addons\items\data\infra\infra_rack_patch.p3d";
        editorPreview="\z\comspec_atak_native\addons\items\data\infra\infra_rack_patch_preview.jpg";
        comspec_atak_infraRole="network"; armor=120; mapSize=1;
        COMSPEC_INFRA_ATTRIBUTES;
        class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_INFRA_ACE"; selection=""; distance=3; condition="true"; position="[0,-0.55,1.2]"; }; };
    };
    class COMSPEC_ATAK_Infra_RackServer: COMSPEC_ATAK_Infra_Base {
        scope=2; scopeCurator=2;
        displayName="$STR_COMSPEC_ATAK_CFG_INFRA_RACK_SERVER";
        model="\z\comspec_atak_native\addons\items\data\infra\infra_rack_server.p3d";
        editorPreview="\z\comspec_atak_native\addons\items\data\infra\infra_rack_server_preview.jpg";
        comspec_atak_infraRole="server"; armor=120; mapSize=1;
        COMSPEC_INFRA_ATTRIBUTES;
        class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_INFRA_ACE"; selection=""; distance=3; condition="true"; position="[0,-0.55,1.2]"; }; };
    };
    class COMSPEC_ATAK_Infra_Wall9U: COMSPEC_ATAK_Infra_Base {
        scope=2; scopeCurator=2;
        displayName="$STR_COMSPEC_ATAK_CFG_INFRA_WALL_9U";
        model="\z\comspec_atak_native\addons\items\data\infra\infra_wall_9u.p3d";
        editorPreview="\z\comspec_atak_native\addons\items\data\infra\infra_wall_9u_preview.jpg";
        comspec_atak_infraRole="network"; armor=40; mapSize=0.6;
        COMSPEC_INFRA_ATTRIBUTES;
        class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_INFRA_ACE"; selection=""; distance=3; condition="true"; position="[0,0.5,0]"; }; };
    };
    class COMSPEC_ATAK_Infra_Wall12U: COMSPEC_ATAK_Infra_Base {
        scope=2; scopeCurator=2;
        displayName="$STR_COMSPEC_ATAK_CFG_INFRA_WALL_12U";
        model="\z\comspec_atak_native\addons\items\data\infra\infra_wall_12u.p3d";
        editorPreview="\z\comspec_atak_native\addons\items\data\infra\infra_wall_12u_preview.jpg";
        comspec_atak_infraRole="network"; armor=40; mapSize=0.6;
        COMSPEC_INFRA_ATTRIBUTES;
        class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_INFRA_ACE"; selection=""; distance=3; condition="true"; position="[0,0.5,0]"; }; };
    };
    class COMSPEC_ATAK_Infra_ServerTower: COMSPEC_ATAK_Infra_Base {
        scope=2; scopeCurator=2;
        displayName="$STR_COMSPEC_ATAK_CFG_INFRA_SERVER_TOWER";
        model="\z\comspec_atak_native\addons\items\data\infra\infra_server_tower.p3d";
        editorPreview="\z\comspec_atak_native\addons\items\data\infra\infra_server_tower_preview.jpg";
        comspec_atak_infraRole="server"; armor=20; mapSize=0.4;
        COMSPEC_INFRA_ATTRIBUTES;
        class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_INFRA_ACE"; selection=""; distance=3; condition="true"; position="[0,-0.3,0.3]"; }; };
    };
    class COMSPEC_ATAK_Infra_UPS: COMSPEC_ATAK_Infra_Base {
        scope=2; scopeCurator=2;
        displayName="$STR_COMSPEC_ATAK_CFG_INFRA_UPS";
        model="\z\comspec_atak_native\addons\items\data\infra\infra_ups.p3d";
        editorPreview="\z\comspec_atak_native\addons\items\data\infra\infra_ups_preview.jpg";
        comspec_atak_infraRole="power"; armor=20; mapSize=0.4;
        COMSPEC_INFRA_ATTRIBUTES;
        class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_INFRA_ACE"; selection=""; distance=3; condition="true"; position="[0,-0.25,0.2]"; }; };
    };
    class COMSPEC_ATAK_Infra_SwitchDesk: COMSPEC_ATAK_Infra_Base {
        scope=2; scopeCurator=2;
        displayName="$STR_COMSPEC_ATAK_CFG_INFRA_SWITCH_DESK";
        model="\z\comspec_atak_native\addons\items\data\infra\infra_switch_desk.p3d";
        editorPreview="\z\comspec_atak_native\addons\items\data\infra\infra_switch_desk_preview.jpg";
        comspec_atak_infraRole="network"; armor=6; mapSize=0.3;
        COMSPEC_INFRA_ATTRIBUTES;
        class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_INFRA_ACE"; selection=""; distance=3; condition="true"; position="[0,-0.15,0.05]"; }; };
    };
    class COMSPEC_ATAK_Infra_CRAC: COMSPEC_ATAK_Infra_Base {
        scope=2; scopeCurator=2;
        displayName="$STR_COMSPEC_ATAK_CFG_INFRA_CRAC";
        model="\z\comspec_atak_native\addons\items\data\infra\infra_crac.p3d";
        editorPreview="\z\comspec_atak_native\addons\items\data\infra\infra_crac_preview.jpg";
        comspec_atak_infraRole="cooling"; armor=150; mapSize=1;
        COMSPEC_INFRA_ATTRIBUTES;
        class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_INFRA_ACE"; selection=""; distance=3; condition="true"; position="[0,-0.45,1.2]"; }; };
    };
    class COMSPEC_ATAK_Infra_ElecCabinet: COMSPEC_ATAK_Infra_Base {
        scope=2; scopeCurator=2;
        displayName="$STR_COMSPEC_ATAK_CFG_INFRA_ELEC";
        model="\z\comspec_atak_native\addons\items\data\infra\infra_elec_cabinet.p3d";
        editorPreview="\z\comspec_atak_native\addons\items\data\infra\infra_elec_cabinet_preview.jpg";
        comspec_atak_infraRole="power"; armor=100; mapSize=0.8;
        COMSPEC_INFRA_ATTRIBUTES;
        class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_INFRA_ACE"; selection=""; distance=3; condition="true"; position="[0,-0.2,1.2]"; }; };
    };
    class COMSPEC_ATAK_Infra_TrayStraight: COMSPEC_ATAK_Infra_Base {
        scope=2; scopeCurator=2;
        displayName="$STR_COMSPEC_ATAK_CFG_INFRA_TRAY_STRAIGHT";
        model="\z\comspec_atak_native\addons\items\data\infra\infra_tray_straight.p3d";
        editorPreview="\z\comspec_atak_native\addons\items\data\infra\infra_tray_straight_preview.jpg";
        comspec_atak_infraRole=""; armor=30; mapSize=2; hiddenSelections[]={}; hiddenSelectionsTextures[]={};
    };
    class COMSPEC_ATAK_Infra_TrayCorner: COMSPEC_ATAK_Infra_Base {
        scope=2; scopeCurator=2;
        displayName="$STR_COMSPEC_ATAK_CFG_INFRA_TRAY_CORNER";
        model="\z\comspec_atak_native\addons\items\data\infra\infra_tray_corner.p3d";
        editorPreview="\z\comspec_atak_native\addons\items\data\infra\infra_tray_corner_preview.jpg";
        comspec_atak_infraRole=""; armor=30; mapSize=1; hiddenSelections[]={}; hiddenSelectionsTextures[]={};
    };
    class COMSPEC_ATAK_Infra_TrayTee: COMSPEC_ATAK_Infra_Base {
        scope=2; scopeCurator=2;
        displayName="$STR_COMSPEC_ATAK_CFG_INFRA_TRAY_TEE";
        model="\z\comspec_atak_native\addons\items\data\infra\infra_tray_tee.p3d";
        editorPreview="\z\comspec_atak_native\addons\items\data\infra\infra_tray_tee_preview.jpg";
        comspec_atak_infraRole=""; armor=30; mapSize=1; hiddenSelections[]={}; hiddenSelectionsTextures[]={};
    };
    class COMSPEC_ATAK_Infra_TrunkStraight: COMSPEC_ATAK_Infra_Base {
        scope=2; scopeCurator=2;
        displayName="$STR_COMSPEC_ATAK_CFG_INFRA_TRUNK_STRAIGHT";
        model="\z\comspec_atak_native\addons\items\data\infra\infra_trunk_straight.p3d";
        editorPreview="\z\comspec_atak_native\addons\items\data\infra\infra_trunk_straight_preview.jpg";
        comspec_atak_infraRole=""; armor=30; mapSize=2; hiddenSelections[]={}; hiddenSelectionsTextures[]={};
    };
    class COMSPEC_ATAK_Infra_TrunkCorner: COMSPEC_ATAK_Infra_Base {
        scope=2; scopeCurator=2;
        displayName="$STR_COMSPEC_ATAK_CFG_INFRA_TRUNK_CORNER";
        model="\z\comspec_atak_native\addons\items\data\infra\infra_trunk_corner.p3d";
        editorPreview="\z\comspec_atak_native\addons\items\data\infra\infra_trunk_corner_preview.jpg";
        comspec_atak_infraRole=""; armor=30; mapSize=1; hiddenSelections[]={}; hiddenSelectionsTextures[]={};
    };
