// Caméras IP 3D (tools/model_ip_cameras.py, modèles dans items\data\cam\) : à inclure DANS CfgVehicles de towers/config.cpp.
// Toutes héritent de COMSPEC_ATAK_CameraObj (main) : fn_camAction, fn_camView, fn_camShare, fn_feedLink les reconnaissent
// par isKindOf. Posées dans Eden / Zeus, elles s'enregistrent comme caméras du camp choisi (fn_camProp, serveur).
// comspec_camEye[] : oeil du flux en coordonnées du modèle ; comspec_camTilt : plongée de la visée (degrés) ;
// comspec_camPtz=1 : la tête suit le panoramique du flux (animateSource "pan", model.cfg).
// Sélections : camo (0), led (1 : voyant d'état), ir (2 : LED infrarouges), dome (3 : verre), posées par fn_camLed.
#define COMSPEC_CAM_TEX "\z\comspec_atak_native\addons\items\data\cam\cam_co.paa"
#define COMSPEC_CAM_DARK "\z\comspec_atak_native\addons\items\data\cam\cam_dark_co.paa"
#define COMSPEC_CAM_OFF "#(argb,8,8,3)color(0.02,0.02,0.02,1,co)"
#define COMSPEC_CAM_GLASS "#(argb,8,8,3)color(0.012,0.014,0.018,1,co)"
class Static;
// Caméra posée par le joueur (classe de main, modèle camera_ip.p3d refait) : mêmes sélections retexturables.
class COMSPEC_ATAK_CameraObj: Static {
    hiddenSelections[]={"camo","led","ir","dome"};
    hiddenSelectionsTextures[]={COMSPEC_CAM_TEX,COMSPEC_CAM_OFF,COMSPEC_CAM_OFF,COMSPEC_CAM_GLASS};
    comspec_camEye[]={0,0.12,0.03}; comspec_camTilt=0; comspec_camPtz=0;
};
class COMSPEC_ATAK_Cam_Base: COMSPEC_ATAK_CameraObj {
    scope=0; scopeCurator=0; author="COMSPEC";
    side=3; faction="Default";
    icon="iconObject_circle"; mapSize=0.4;
    vehicleClass="Items"; editorCategory="COMSPEC_ATAK_EdCat"; editorSubcategory="COMSPEC_ATAK_EdSubcat_Cameras";
    destrType="DestructNo"; armor=5;
    class Attributes {
        class COMSPEC_CamName {
            displayName="$STR_COMSPEC_ATAK_CFG_CAM_ATTR_NAME"; tooltip="$STR_COMSPEC_ATAK_CFG_CAM_ATTR_NAME_TIP";
            property="COMSPEC_CamName"; control="Edit"; typeName="STRING"; defaultValue="''";
            expression="_this setVariable ['COMSPEC_CamName', _value];";
        };
        class COMSPEC_CamSide {
            displayName="$STR_COMSPEC_ATAK_CFG_CAM_ATTR_SIDE"; tooltip="$STR_COMSPEC_ATAK_CFG_CAM_ATTR_SIDE_TIP";
            property="COMSPEC_CamSide"; control="Combo"; typeName="STRING"; defaultValue="'WEST'";
            expression="_this setVariable ['COMSPEC_CamSide', _value];";
            class values {
                class V0 { name="BLUFOR"; value="WEST"; default=1; };
                class V1 { name="OPFOR"; value="EAST"; };
                class V2 { name="$STR_COMSPEC_ATAK_CFG_621714C49D"; value="GUER"; };
                class V3 { name="$STR_COMSPEC_ATAK_CFG_7AA3BFB0ED"; value="CIV"; };
            };
        };
    };
};
class COMSPEC_ATAK_Cam_PtzBase: COMSPEC_ATAK_Cam_Base {
    comspec_camPtz=1;
    class AnimationSources {
        class pan { source="user"; animPeriod=1.5; initPhase=0; };
        class tilt { source="user"; animPeriod=1.5; initPhase=0; };
    };
};
class COMSPEC_ATAK_Cam_PTZ_Wall: COMSPEC_ATAK_Cam_PtzBase {
    scope=2; scopeCurator=2; armor=8;
    displayName="$STR_COMSPEC_ATAK_CFG_CAM_PTZ_WALL";
    model="\z\comspec_atak_native\addons\items\data\cam\cam_ptz_wall.p3d";
    editorPreview="\z\comspec_atak_native\addons\items\data\cam\cam_ptz_wall_preview.jpg";
    comspec_camEye[]={0,0.366,-0.3}; comspec_camTilt=15;
};
class COMSPEC_ATAK_Cam_PTZ_Wall_Dark: COMSPEC_ATAK_Cam_PTZ_Wall {
    displayName="$STR_COMSPEC_ATAK_CFG_CAM_PTZ_WALL_DARK";
    hiddenSelectionsTextures[]={COMSPEC_CAM_DARK,COMSPEC_CAM_OFF,COMSPEC_CAM_OFF,COMSPEC_CAM_GLASS};
};
class COMSPEC_ATAK_Cam_PTZ_Pole: COMSPEC_ATAK_Cam_PtzBase {
    scope=2; scopeCurator=2; armor=8;
    displayName="$STR_COMSPEC_ATAK_CFG_CAM_PTZ_POLE";
    model="\z\comspec_atak_native\addons\items\data\cam\cam_ptz_pole.p3d";
    editorPreview="\z\comspec_atak_native\addons\items\data\cam\cam_ptz_pole_preview.jpg";
    comspec_camEye[]={0,0.368,-0.3}; comspec_camTilt=15;
};
class COMSPEC_ATAK_Cam_Bullet_Wall: COMSPEC_ATAK_Cam_Base {
    scope=2; scopeCurator=2; armor=5;
    displayName="$STR_COMSPEC_ATAK_CFG_CAM_BULLET_WALL";
    model="\z\comspec_atak_native\addons\items\data\cam\cam_bullet_wall.p3d";
    editorPreview="\z\comspec_atak_native\addons\items\data\cam\cam_bullet_wall_preview.jpg";
    comspec_camEye[]={0,0.287,0.009}; comspec_camTilt=12;
};
class COMSPEC_ATAK_Cam_Bullet_Wall_Dark: COMSPEC_ATAK_Cam_Bullet_Wall {
    displayName="$STR_COMSPEC_ATAK_CFG_CAM_BULLET_WALL_DARK";
    hiddenSelectionsTextures[]={COMSPEC_CAM_DARK,COMSPEC_CAM_OFF,COMSPEC_CAM_OFF,COMSPEC_CAM_GLASS};
};
class COMSPEC_ATAK_Cam_Bullet_Ceiling: COMSPEC_ATAK_Cam_Base {
    scope=2; scopeCurator=2; armor=5;
    displayName="$STR_COMSPEC_ATAK_CFG_CAM_BULLET_CEIL";
    model="\z\comspec_atak_native\addons\items\data\cam\cam_bullet_ceil.p3d";
    editorPreview="\z\comspec_atak_native\addons\items\data\cam\cam_bullet_ceil_preview.jpg";
    comspec_camEye[]={0,0.177,-0.141}; comspec_camTilt=12;
};
class COMSPEC_ATAK_Cam_Bullet_Pole: COMSPEC_ATAK_Cam_Base {
    scope=2; scopeCurator=2; armor=5;
    displayName="$STR_COMSPEC_ATAK_CFG_CAM_BULLET_POLE";
    model="\z\comspec_atak_native\addons\items\data\cam\cam_bullet_pole.p3d";
    editorPreview="\z\comspec_atak_native\addons\items\data\cam\cam_bullet_pole_preview.jpg";
    comspec_camEye[]={0,0.295,0.009}; comspec_camTilt=12;
};
class COMSPEC_ATAK_Cam_Bullet_Corner: COMSPEC_ATAK_Cam_Base {
    scope=2; scopeCurator=2; armor=5;
    displayName="$STR_COMSPEC_ATAK_CFG_CAM_BULLET_CORNER";
    model="\z\comspec_atak_native\addons\items\data\cam\cam_bullet_corner.p3d";
    editorPreview="\z\comspec_atak_native\addons\items\data\cam\cam_bullet_corner_preview.jpg";
    comspec_camEye[]={0,0.322,0.009}; comspec_camTilt=12;
};
class COMSPEC_ATAK_Cam_Thermal_Wall: COMSPEC_ATAK_Cam_Base {
    scope=2; scopeCurator=2; armor=8;
    displayName="$STR_COMSPEC_ATAK_CFG_CAM_THERMAL_WALL";
    model="\z\comspec_atak_native\addons\items\data\cam\cam_thermal_wall.p3d";
    editorPreview="\z\comspec_atak_native\addons\items\data\cam\cam_thermal_wall_preview.jpg";
    comspec_camEye[]={0,0.386,0.055}; comspec_camTilt=10;
};
class COMSPEC_ATAK_Cam_Thermal_Wall_Dark: COMSPEC_ATAK_Cam_Thermal_Wall {
    displayName="$STR_COMSPEC_ATAK_CFG_CAM_THERMAL_WALL_DARK";
    hiddenSelectionsTextures[]={COMSPEC_CAM_DARK,COMSPEC_CAM_OFF,COMSPEC_CAM_OFF,COMSPEC_CAM_GLASS};
};
class COMSPEC_ATAK_Cam_Thermal_Pole: COMSPEC_ATAK_Cam_Base {
    scope=2; scopeCurator=2; armor=8;
    displayName="$STR_COMSPEC_ATAK_CFG_CAM_THERMAL_POLE";
    model="\z\comspec_atak_native\addons\items\data\cam\cam_thermal_pole.p3d";
    editorPreview="\z\comspec_atak_native\addons\items\data\cam\cam_thermal_pole_preview.jpg";
    comspec_camEye[]={0,0.388,0.055}; comspec_camTilt=10;
};
class COMSPEC_ATAK_Cam_MiniDome: COMSPEC_ATAK_Cam_Base {
    scope=2; scopeCurator=2; armor=3;
    displayName="$STR_COMSPEC_ATAK_CFG_CAM_MINIDOME";
    model="\z\comspec_atak_native\addons\items\data\cam\cam_minidome.p3d";
    editorPreview="\z\comspec_atak_native\addons\items\data\cam\cam_minidome_preview.jpg";
    comspec_camEye[]={0,0.03,-0.1}; comspec_camTilt=30;
};
class COMSPEC_ATAK_Cam_MiniDome_Wall: COMSPEC_ATAK_Cam_Base {
    scope=2; scopeCurator=2; armor=3;
    displayName="$STR_COMSPEC_ATAK_CFG_CAM_MINIDOME_WALL";
    model="\z\comspec_atak_native\addons\items\data\cam\cam_minidome_wall.p3d";
    editorPreview="\z\comspec_atak_native\addons\items\data\cam\cam_minidome_wall_preview.jpg";
    comspec_camEye[]={0,0.1,0}; comspec_camTilt=10;
};
class COMSPEC_ATAK_Cam_Fisheye: COMSPEC_ATAK_Cam_Base {
    scope=2; scopeCurator=2; armor=3;
    displayName="$STR_COMSPEC_ATAK_CFG_CAM_FISHEYE";
    model="\z\comspec_atak_native\addons\items\data\cam\cam_fisheye.p3d";
    editorPreview="\z\comspec_atak_native\addons\items\data\cam\cam_fisheye_preview.jpg";
    comspec_camEye[]={0,0,-0.09}; comspec_camTilt=60;
};
class COMSPEC_ATAK_Cam_Tactical: COMSPEC_ATAK_Cam_PtzBase {
    scope=2; scopeCurator=2; armor=10;
    displayName="$STR_COMSPEC_ATAK_CFG_CAM_TACTICAL";
    model="\z\comspec_atak_native\addons\items\data\cam\cam_tactical.p3d";
    editorPreview="\z\comspec_atak_native\addons\items\data\cam\cam_tactical_preview.jpg";
    comspec_camEye[]={0,0.17,1.225}; comspec_camTilt=0;
};
class COMSPEC_ATAK_Cam_Covert: COMSPEC_ATAK_Cam_Base {
    scope=2; scopeCurator=2; armor=2;
    displayName="$STR_COMSPEC_ATAK_CFG_CAM_COVERT";
    model="\z\comspec_atak_native\addons\items\data\cam\cam_covert.p3d";
    editorPreview="\z\comspec_atak_native\addons\items\data\cam\cam_covert_preview.jpg";
    comspec_camEye[]={0,0.06,0.008}; comspec_camTilt=0;
};
