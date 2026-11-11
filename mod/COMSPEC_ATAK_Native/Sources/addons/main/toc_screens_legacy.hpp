// Premiers écrans TOC, toiles et vidéoprojecteur COMSPEC, refaits avec les modèles du mod (tools/model_toc_legacy.py,
// data\toc\) : ils étaient des objets du jeu de base réhabillés (grand écran sur trépied, double écran, moniteur,
// téléviseur, tableaux blancs, vidéoprojecteur). Les classes gardent leur nom : les missions et les sauvegardes de
// salle TOC (fn_tocPersist) les retrouvent, seul le modèle change. Dalle « screen » à l'index 0 (comme l'index gardé
// dans les sauvegardes) ; double écran : deuxième dalle recopiée (comspec_screenMirror, comme avant).
// Modèles centrés par le moteur comme les objets du jeu qu'ils remplacent (origine au milieu de la boîte englobante) :
// positions ACE et de portage données autour du centre. Seul le vidéoprojecteur de plafond a son origine sur le dessus
// de sa platine. Toiles (comspec_projBoard=1) : l'image va sur la surface posée devant la toile par fn_projector, qui
// mesure la face par des rayons sur la géométrie (toile plane), seulement quand un projecteur allumé l'éclaire.
#define COMSPEC_TOCOLD_CANVAS "#(argb,8,8,3)color(0.84,0.84,0.82,1,co)"
class COMSPEC_ATAK_Screen_Tripod: COMSPEC_ATAK_Screen_Base3D {
    scope=2; scopeCurator=2;
    COMSPEC_SCREEN_ATTRIBUTES;
    displayName="$STR_COMSPEC_ATAK_CFG_8C3886D686";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_tripod_65.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_tripod_65_preview.jpg";
    ace_dragging_canDrag=1; ace_dragging_dragPosition[]={0,1.4,0}; ace_dragging_dragDirection=0;
    ace_cargo_canLoad=1; ace_cargo_size=3;
    class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_41D5DDB858"; selection=""; distance=4; condition="true"; position="[0, 0.1, 0.4]"; }; };
};
class COMSPEC_ATAK_Screen_Dual: COMSPEC_ATAK_Screen_Tripod {
    displayName="$STR_COMSPEC_ATAK_CFG_477D05DF6A";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_tripod_dual_43.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_tripod_dual_43_preview.jpg";
    comspec_screenMirror[]={1};
    hiddenSelections[]={"screen","screen2"};
    hiddenSelectionsTextures[]={COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF};
    hiddenSelectionsMaterials[]={COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT};
};
class COMSPEC_ATAK_Screen_Desk: COMSPEC_ATAK_Screen_Base3D {
    scope=2; scopeCurator=2;
    COMSPEC_SCREEN_ATTRIBUTES;
    displayName="$STR_COMSPEC_ATAK_CFG_D875FDECAE";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_desk_27.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_desk_27_preview.jpg";
    mapSize=0.7;
    ace_dragging_canCarry=1; ace_dragging_carryPosition[]={0,1,1}; ace_dragging_carryDirection=0;
    ace_cargo_canLoad=1; ace_cargo_size=1;
    class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_41D5DDB858"; selection=""; distance=3; condition="true"; position="[0, 0, 0]"; }; };
};
class COMSPEC_ATAK_Screen_Desk2: COMSPEC_ATAK_Screen_Desk {
    displayName="$STR_COMSPEC_ATAK_CFG_TOCOLD_DESK2";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_desk_27x2.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_desk_27x2_preview.jpg";
    comspec_screenPanels=2;
    hiddenSelections[]={"panel1","panel2"};
    hiddenSelectionsTextures[]={COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF};
    hiddenSelectionsMaterials[]={COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT};
};
class COMSPEC_ATAK_Screen_Desk3: COMSPEC_ATAK_Screen_Desk {
    displayName="$STR_COMSPEC_ATAK_CFG_TOCOLD_DESK3";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_desk_27x3.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_desk_27x3_preview.jpg";
    comspec_screenPanels=3;
    hiddenSelections[]={"panel1","panel2","panel3"};
    hiddenSelectionsTextures[]={COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF};
    hiddenSelectionsMaterials[]={COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT};
};
class COMSPEC_ATAK_Screen_Wall: COMSPEC_ATAK_Screen_Desk {
    displayName="$STR_COMSPEC_ATAK_CFG_BFF6157B7C";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_tv_55.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_tv_55_preview.jpg";
    mapSize=1.3;
};
class COMSPEC_ATAK_Screen_Wall75: COMSPEC_ATAK_Screen_Wall {
    displayName="$STR_COMSPEC_ATAK_CFG_TOCOLD_TV75";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_tv_75.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_tv_75_preview.jpg";
    mapSize=1.7;
    ace_dragging_canCarry=0; ace_dragging_canDrag=1; ace_dragging_dragPosition[]={0,1.2,0}; ace_dragging_dragDirection=0;
    ace_cargo_size=2;
};
// Toiles de projection : blanches tant que rien n'est projeté.
class COMSPEC_ATAK_ProjScreen: COMSPEC_ATAK_Screen_Base3D {
    scope=2; scopeCurator=2;
    COMSPEC_SCREEN_ATTRIBUTES;
    displayName="$STR_COMSPEC_ATAK_CFG_43A7D9BDFC";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_proj_screen_tripod.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_proj_screen_tripod_preview.jpg";
    comspec_screen=0; comspec_projBoard=1;
    comspec_screenMat[]={};
    hiddenSelections[]={"canvas"};
    hiddenSelectionsTextures[]={COMSPEC_TOCOLD_CANVAS};
    hiddenSelectionsMaterials[]={"\z\comspec_atak_native\addons\main\data\toc\toc_canvas.rvmat"};
    ace_dragging_canDrag=1; ace_dragging_dragPosition[]={0,1.4,0}; ace_dragging_dragDirection=0;
    ace_cargo_canLoad=1; ace_cargo_size=2;
    class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_41D5DDB858"; selection=""; distance=4; condition="true"; position="[0, 0.1, 0.3]"; }; };
};
class COMSPEC_ATAK_ProjScreen_Wall: COMSPEC_ATAK_ProjScreen {
    displayName="$STR_COMSPEC_ATAK_CFG_C3303CA58C";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_proj_screen_wall.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_proj_screen_wall_preview.jpg";
    ace_dragging_canDrag=0; ace_dragging_canCarry=1; ace_dragging_carryPosition[]={0,1,1}; ace_dragging_carryDirection=0;
    ace_cargo_size=1;
    class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_41D5DDB858"; selection=""; distance=4; condition="true"; position="[0, 0.1, 0]"; }; };
};
// Vidéoprojecteur (fn_projector) : COMSPEC_ATAK_Proj [allumé, tableau] public (joueurs qui rejoignent compris). Objectif
// centré sur la face avant : fn_projector prend le milieu de la face de la boîte englobante tournée vers la toile.
class COMSPEC_ATAK_Projector: Static {
    scope=2; scopeCurator=2; author="COMSPEC";
    side=3; faction="Default";
    icon="iconObject_1x1"; mapSize=0.4;
    vehicleClass="Items"; editorCategory="COMSPEC_ATAK_EdCat"; editorSubcategory="COMSPEC_ATAK_EdSubcat_Screens";
    destrType="DestructNo"; armor=200;
    displayName="$STR_COMSPEC_ATAK_CFG_81785FF8C2";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_projector.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_projector_preview.jpg";
    comspec_projector=1;
    ace_dragging_canCarry=1; ace_dragging_carryPosition[]={0,0.8,1}; ace_dragging_carryDirection=0;
    ace_cargo_canLoad=1; ace_cargo_size=1;
    class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_06F212475E"; selection=""; distance=3; condition="true"; position="[0, 0, 0]"; }; };
    class Attributes {
        class COMSPEC_ATAK_ProjOn {
            displayName="$STR_COMSPEC_ATAK_CFG_8F3EFAEC0E";
            tooltip="$STR_COMSPEC_ATAK_CFG_0B8E6B4E77";
            property="COMSPEC_ATAK_ProjOn"; control="Checkbox"; typeName="BOOL"; defaultValue="false";
            expression="if (_value && {isServer}) then { [{ [""power"", [_this, true]] call comspec_atak_native_fnc_projector; }, _this, 3] call comspec_atak_native_fnc_waitAndExec; };";
        };
    };
};
// Suspendu à une potence de plafond : origine sur le dessus de la platine (à monter jusqu'au plafond).
class COMSPEC_ATAK_Projector_Ceiling: COMSPEC_ATAK_Projector {
    displayName="$STR_COMSPEC_ATAK_CFG_TOCOLD_PROJC";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_projector_ceiling.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_projector_ceiling_preview.jpg";
    ace_dragging_canCarry=0; ace_cargo_canLoad=0;
    class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_06F212475E"; selection=""; distance=4; condition="true"; position="[0, 0, -0.2]"; }; };
};
