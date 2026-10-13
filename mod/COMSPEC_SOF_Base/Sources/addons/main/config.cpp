// COMSPEC Base SOF : mobilier de base forces spéciales posable dans Eden et Zeus (modèles : tools/model_sof_base.py).
// Mod indépendant de l'ATAK natif. Aucune arme ni équipement modélisé ; pancartes et drapeau personnalisables.
#define COLOR_BLUE "#(argb,8,8,3)color(0.06,0.16,0.62,1,co)"
#define COLOR_BLACK "#(argb,8,8,3)color(0.05,0.055,0.055,1,co)"
#define MESH_BLUE "\z\comspec_sof\addons\main\data\sof_mesh_blue_ca.paa"
#define MESH_BLACK "\z\comspec_sof\addons\main\data\sof_mesh_black_ca.paa"

class CfgPatches {
    class comspec_sof_main {
        name="COMSPEC Base SOF"; author="COMSPEC"; requiredVersion=2.14;
        requiredAddons[]={"A3_Structures_F"};
        units[]={
            "COMSPEC_SOF_CagePanel_Blue","COMSPEC_SOF_CagePanel_Black","COMSPEC_SOF_CageDoor_Blue","COMSPEC_SOF_CageDoor_Black",
            "COMSPEC_SOF_Cage4x3_Blue","COMSPEC_SOF_Cage4x3_Black","COMSPEC_SOF_MeshLocker","COMSPEC_SOF_TeamBay",
            "COMSPEC_SOF_TeamBay_x5","COMSPEC_SOF_GunRack","COMSPEC_SOF_Workbench","COMSPEC_SOF_ToolChest","COMSPEC_SOF_Shelf",
            "COMSPEC_SOF_SignLarge","COMSPEC_SOF_SignSmall","COMSPEC_SOF_SignStand","COMSPEC_SOF_WallFlag",
            "COMSPEC_SOF_HQ","COMSPEC_SOF_Annex"
        };
        weapons[]={};
    };
};

class CfgEditorCategories {
    class COMSPEC_SOF_EdCat { displayName="$STR_COMSPEC_SOF_CAT"; };
};
class CfgEditorSubcategories {
    class COMSPEC_SOF_EdSub_Cages { displayName="$STR_COMSPEC_SOF_SUB_CAGES"; };
    class COMSPEC_SOF_EdSub_Storage { displayName="$STR_COMSPEC_SOF_SUB_STORAGE"; };
    class COMSPEC_SOF_EdSub_Workshop { displayName="$STR_COMSPEC_SOF_SUB_WORKSHOP"; };
    class COMSPEC_SOF_EdSub_Signs { displayName="$STR_COMSPEC_SOF_SUB_SIGNS"; };
    class COMSPEC_SOF_EdSub_Buildings { displayName="$STR_COMSPEC_SOF_SUB_BUILDINGS"; };
};

class CfgVehicles {
    class House;
    class House_F: House { class DestructionEffects; };
    class Ruins_F;
    class COMSPEC_SOF_Base: House_F {
        scope=0; scopeCurator=0; author="COMSPEC";
        vehicleClass="Structures"; editorCategory="COMSPEC_SOF_EdCat"; editorSubcategory="COMSPEC_SOF_EdSub_Storage";
        icon="iconObject_1x1"; mapSize=2; cost=1000; armor=300; destrType="DestructNo";
        hiddenSelections[]={}; hiddenSelectionsTextures[]={};
        class AnimationSources {};
        class UserActions {};
    };
    // Porte unique (cages) ou double (casier) : une source « door », ouvrir/fermer à portée de main.
    class COMSPEC_SOF_DoorBase: COMSPEC_SOF_Base {
        class AnimationSources { class door { source="user"; initPhase=0; animPeriod=1.2; }; };
        class UserActions {
            class COMSPEC_SOF_Open {
                displayName="$STR_COMSPEC_SOF_OPEN"; position="door_action"; radius=2.5; onlyForPlayer=0; priority=4;
                condition="(this animationSourcePhase 'door') < 0.5"; statement="this animateSource ['door', 1]";
            };
            class COMSPEC_SOF_Close: COMSPEC_SOF_Open {
                displayName="$STR_COMSPEC_SOF_CLOSE";
                condition="(this animationSourcePhase 'door') >= 0.5"; statement="this animateSource ['door', 0]";
            };
        };
    };

    // ---- Cages grillagées
    class COMSPEC_SOF_CagePanel_Blue: COMSPEC_SOF_Base {
        scope=2; scopeCurator=2; editorSubcategory="COMSPEC_SOF_EdSub_Cages";
        displayName="$STR_COMSPEC_SOF_CAGE_PANEL_BLUE";
        model="\z\comspec_sof\addons\main\data\sof_cage_panel.p3d";
        editorPreview="\z\comspec_sof\addons\main\data\sof_cage_panel_preview.jpg";
        hiddenSelections[]={"frame","mesh"}; hiddenSelectionsTextures[]={COLOR_BLUE, MESH_BLUE};
    };
    class COMSPEC_SOF_CagePanel_Black: COMSPEC_SOF_CagePanel_Blue {
        displayName="$STR_COMSPEC_SOF_CAGE_PANEL_BLACK"; hiddenSelectionsTextures[]={COLOR_BLACK, MESH_BLACK};
    };
    class COMSPEC_SOF_CageDoor_Blue: COMSPEC_SOF_DoorBase {
        scope=2; scopeCurator=2; editorSubcategory="COMSPEC_SOF_EdSub_Cages";
        displayName="$STR_COMSPEC_SOF_CAGE_DOOR_BLUE";
        model="\z\comspec_sof\addons\main\data\sof_cage_door.p3d";
        editorPreview="\z\comspec_sof\addons\main\data\sof_cage_door_preview.jpg";
        hiddenSelections[]={"frame","mesh"}; hiddenSelectionsTextures[]={COLOR_BLUE, MESH_BLUE};
    };
    class COMSPEC_SOF_CageDoor_Black: COMSPEC_SOF_CageDoor_Blue {
        displayName="$STR_COMSPEC_SOF_CAGE_DOOR_BLACK"; hiddenSelectionsTextures[]={COLOR_BLACK, MESH_BLACK};
    };
    class COMSPEC_SOF_Cage4x3_Blue: COMSPEC_SOF_DoorBase {
        scope=2; scopeCurator=2; editorSubcategory="COMSPEC_SOF_EdSub_Cages"; mapSize=5;
        displayName="$STR_COMSPEC_SOF_CAGE_4X3_BLUE";
        model="\z\comspec_sof\addons\main\data\sof_cage_4x3.p3d";
        editorPreview="\z\comspec_sof\addons\main\data\sof_cage_4x3_preview.jpg";
        hiddenSelections[]={"frame","mesh"}; hiddenSelectionsTextures[]={COLOR_BLUE, MESH_BLUE};
    };
    class COMSPEC_SOF_Cage4x3_Black: COMSPEC_SOF_Cage4x3_Blue {
        displayName="$STR_COMSPEC_SOF_CAGE_4X3_BLACK"; hiddenSelectionsTextures[]={COLOR_BLACK, MESH_BLACK};
    };

    // ---- Rangement (dépôt d'équipement à venir via ACE)
    class COMSPEC_SOF_MeshLocker: COMSPEC_SOF_DoorBase {
        scope=2; scopeCurator=2;
        displayName="$STR_COMSPEC_SOF_LOCKER";
        model="\z\comspec_sof\addons\main\data\sof_mesh_locker.p3d";
        editorPreview="\z\comspec_sof\addons\main\data\sof_mesh_locker_preview.jpg";
        hiddenSelections[]={"frame","mesh"}; hiddenSelectionsTextures[]={COLOR_BLACK, MESH_BLACK};
    };
    class COMSPEC_SOF_TeamBay: COMSPEC_SOF_Base {
        scope=2; scopeCurator=2;
        displayName="$STR_COMSPEC_SOF_TEAMBAY";
        model="\z\comspec_sof\addons\main\data\sof_team_bay.p3d";
        editorPreview="\z\comspec_sof\addons\main\data\sof_team_bay_preview.jpg";
    };
    class COMSPEC_SOF_TeamBay_x5: COMSPEC_SOF_TeamBay {
        displayName="$STR_COMSPEC_SOF_TEAMBAY_X5"; mapSize=5;
        model="\z\comspec_sof\addons\main\data\sof_team_bay_x5.p3d";
        editorPreview="\z\comspec_sof\addons\main\data\sof_team_bay_x5_preview.jpg";
    };
    class COMSPEC_SOF_GunRack: COMSPEC_SOF_Base {
        scope=2; scopeCurator=2;
        displayName="$STR_COMSPEC_SOF_GUNRACK";
        model="\z\comspec_sof\addons\main\data\sof_gun_rack.p3d";
        editorPreview="\z\comspec_sof\addons\main\data\sof_gun_rack_preview.jpg";
    };
    class COMSPEC_SOF_Shelf: COMSPEC_SOF_Base {
        scope=2; scopeCurator=2;
        displayName="$STR_COMSPEC_SOF_SHELF";
        model="\z\comspec_sof\addons\main\data\sof_shelf.p3d";
        editorPreview="\z\comspec_sof\addons\main\data\sof_shelf_preview.jpg";
    };

    // ---- Atelier
    class COMSPEC_SOF_Workbench: COMSPEC_SOF_Base {
        scope=2; scopeCurator=2; editorSubcategory="COMSPEC_SOF_EdSub_Workshop";
        displayName="$STR_COMSPEC_SOF_WORKBENCH";
        model="\z\comspec_sof\addons\main\data\sof_workbench.p3d";
        editorPreview="\z\comspec_sof\addons\main\data\sof_workbench_preview.jpg";
    };
    class COMSPEC_SOF_ToolChest: COMSPEC_SOF_Base {
        scope=2; scopeCurator=2; editorSubcategory="COMSPEC_SOF_EdSub_Workshop";
        displayName="$STR_COMSPEC_SOF_TOOLCHEST";
        model="\z\comspec_sof\addons\main\data\sof_tool_chest.p3d";
        editorPreview="\z\comspec_sof\addons\main\data\sof_tool_chest_preview.jpg";
    };

    // ---- Pancartes et drapeau personnalisables (attribut Eden : chemin d'une image .paa / .jpg)
    class COMSPEC_SOF_SignBase: COMSPEC_SOF_Base {
        editorSubcategory="COMSPEC_SOF_EdSub_Signs";
        hiddenSelections[]={"sign"}; hiddenSelectionsTextures[]={"\z\comspec_sof\addons\main\data\sof_sign_co.paa"};
        class Attributes {
            class COMSPEC_SOF_Texture {
                displayName="$STR_COMSPEC_SOF_ATTR_TEX"; tooltip="$STR_COMSPEC_SOF_ATTR_TEX_TIP";
                property="COMSPEC_SOF_Texture"; control="Edit"; typeName="STRING"; defaultValue="''";
                expression="if (_value != '') then { _this setObjectTexture [0, _value]; };";
            };
        };
    };
    class COMSPEC_SOF_SignLarge: COMSPEC_SOF_SignBase {
        scope=2; scopeCurator=2;
        displayName="$STR_COMSPEC_SOF_SIGN_LARGE";
        model="\z\comspec_sof\addons\main\data\sof_sign_large.p3d";
        editorPreview="\z\comspec_sof\addons\main\data\sof_sign_large_preview.jpg";
    };
    class COMSPEC_SOF_SignSmall: COMSPEC_SOF_SignBase {
        scope=2; scopeCurator=2;
        displayName="$STR_COMSPEC_SOF_SIGN_SMALL";
        model="\z\comspec_sof\addons\main\data\sof_sign_small.p3d";
        editorPreview="\z\comspec_sof\addons\main\data\sof_sign_small_preview.jpg";
    };
    class COMSPEC_SOF_SignStand: COMSPEC_SOF_SignBase {
        scope=2; scopeCurator=2;
        displayName="$STR_COMSPEC_SOF_SIGN_STAND";
        model="\z\comspec_sof\addons\main\data\sof_sign_stand.p3d";
        editorPreview="\z\comspec_sof\addons\main\data\sof_sign_stand_preview.jpg";
    };
    class COMSPEC_SOF_WallFlag: COMSPEC_SOF_SignBase {
        scope=2; scopeCurator=2;
        displayName="$STR_COMSPEC_SOF_FLAG";
        model="\z\comspec_sof\addons\main\data\sof_wall_flag.p3d";
        editorPreview="\z\comspec_sof\addons\main\data\sof_wall_flag_preview.jpg";
        hiddenSelections[]={"flag"}; hiddenSelectionsTextures[]={"\z\comspec_sof\addons\main\data\sof_flag_co.paa"};
    };

    // ---- Bâtiments explorables (tools/model_sof_buildings.py) : étages, portes animées, vitres cassables, ruine.
    class COMSPEC_SOF_BuildingBase: House_F {
        scope=0; scopeCurator=0; author="COMSPEC";
        vehicleClass="Structures"; editorCategory="COMSPEC_SOF_EdCat"; editorSubcategory="COMSPEC_SOF_EdSub_Buildings";
        icon="iconObject_4x2"; cost=500000; armor=3000; destrType="DestructBuilding";
        class DestructionEffects: DestructionEffects {};
    };
    #include "buildings.hpp"
};
