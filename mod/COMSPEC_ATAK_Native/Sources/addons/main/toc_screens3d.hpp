// Écrans de salle d'opérations COMSPEC (TOC / JOC) : modèles du mod (tools/model_toc_screens.py, data\toc\), tailles
// réelles. Origine au sol, dos contre le mur (Y = 0), milieu en largeur : posé contre un mur, l'écran est à la bonne
// hauteur. Deux murs côte à côte se touchent sans jour si leurs origines sont espacées de leur largeur hors tout
// (2x2 : 2,4572 m ; 3x2 : 3,6738 m ; 98" : 2,1935 m), même direction. Écran suspendu : origine sur le dessus de la
// platine de plafond (à monter jusqu'au plafond).
// Dalles : comspec_screen=1, matières jour / nuit comspec_screenMat sous une image, verre noir brillant éteint.
// Murs à plusieurs dalles (comspec_screenPanels) : une sélection par dalle, index = ordre de lecture vu de face
// (panel1 en haut à gauche) ; chaque index reçoit sa propre image (fn_tocScreen « paint » / « surface » par dalle).
// Bandeau horloge et plaque de nom : face 4:1 (comspec_screenRatio), sélection « screen », hors liste des écrans TOC
// (une carte 16:9 y serait écrasée), contenu posé par d'autres affichages.
#define COMSPEC_TOC3D_MAT "\z\comspec_atak_native\addons\main\data\toc\toc_glass_off.rvmat"
#define COMSPEC_TOC3D_OFF "#(argb,8,8,3)color(0,0,0,1,co)"
// side=3 obligatoire : Static hérite de All (side=4, camp « inconnu ») et Eden comme Zeus ne listent que les objets
// des camps 0 à 3 (Thing, NonStrategic : 3). Sans lui, ces écrans existaient sans apparaître nulle part (TTRD, 11/11).
class COMSPEC_ATAK_Screen_Base3D: Static {
    scope=0; scopeCurator=0; author="COMSPEC";
    side=3; faction="Default";
    icon="iconObject_1x1"; mapSize=2;
    vehicleClass="Items"; editorCategory="COMSPEC_ATAK_EdCat"; editorSubcategory="COMSPEC_ATAK_EdSubcat_Screens";
    destrType="DestructNo"; armor=500;
    comspec_screen=1;
    comspec_screenMat[]={"\z\comspec_atak_native\addons\items\data\comspec_screen_on.rvmat","\z\comspec_atak_native\addons\items\data\comspec_screen_night.rvmat"};
    hiddenSelections[]={"screen"};
    hiddenSelectionsTextures[]={COMSPEC_TOC3D_OFF};
    hiddenSelectionsMaterials[]={COMSPEC_TOC3D_MAT};
    class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_41D5DDB858"; selection=""; distance=4; condition="true"; position="[0, 0.2, 1.6]"; }; };
};
class COMSPEC_ATAK_Screen_Wall2x2: COMSPEC_ATAK_Screen_Base3D {
    scope=2; scopeCurator=2;
    COMSPEC_SCREEN_ATTRIBUTES;
    displayName="$STR_COMSPEC_ATAK_CFG_TOC3D_W22";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_wall_2x2.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_wall_2x2_preview.jpg";
};
class COMSPEC_ATAK_Screen_Wall2x2_Quad: COMSPEC_ATAK_Screen_Wall2x2 {
    displayName="$STR_COMSPEC_ATAK_CFG_TOC3D_W22Q";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_wall_2x2_quad.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_wall_2x2_quad_preview.jpg";
    comspec_screenPanels=4;
    hiddenSelections[]={"panel1","panel2","panel3","panel4"};
    hiddenSelectionsTextures[]={COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF};
    hiddenSelectionsMaterials[]={COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT};
};
class COMSPEC_ATAK_Screen_Wall3x2: COMSPEC_ATAK_Screen_Wall2x2 {
    displayName="$STR_COMSPEC_ATAK_CFG_TOC3D_W32";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_wall_3x2.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_wall_3x2_preview.jpg";
    comspec_screenPanels=6;
    hiddenSelections[]={"panel1","panel2","panel3","panel4","panel5","panel6"};
    hiddenSelectionsTextures[]={COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF};
    hiddenSelectionsMaterials[]={COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT};
};
// Image 16:9 sur le bloc 2x2 de gauche (panel1), deux dalles à droite (panel2 en haut, panel3 en bas).
class COMSPEC_ATAK_Screen_Wall3x2_Mixed: COMSPEC_ATAK_Screen_Wall2x2 {
    displayName="$STR_COMSPEC_ATAK_CFG_TOC3D_W32M";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_wall_3x2_mixed.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_wall_3x2_mixed_preview.jpg";
    comspec_screenPanels=3;
    hiddenSelections[]={"panel1","panel2","panel3"};
    hiddenSelectionsTextures[]={COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF};
    hiddenSelectionsMaterials[]={COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT};
};
class COMSPEC_ATAK_Screen_98: COMSPEC_ATAK_Screen_Base3D {
    scope=2; scopeCurator=2;
    COMSPEC_SCREEN_ATTRIBUTES;
    displayName="$STR_COMSPEC_ATAK_CFG_TOC3D_98";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_98_wall.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_98_wall_preview.jpg";
};
class COMSPEC_ATAK_Screen_98_Stand: COMSPEC_ATAK_Screen_98 {
    displayName="$STR_COMSPEC_ATAK_CFG_TOC3D_98S";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_98_stand.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_98_stand_preview.jpg";
    ace_dragging_canDrag=1; ace_dragging_dragPosition[]={0,1.6,0}; ace_dragging_dragDirection=0;
    class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_41D5DDB858"; selection=""; distance=4; condition="true"; position="[0, 0.2, 1.4]"; }; };
};
class COMSPEC_ATAK_Screen_65_Ceiling: COMSPEC_ATAK_Screen_Base3D {
    scope=2; scopeCurator=2;
    COMSPEC_SCREEN_ATTRIBUTES;
    displayName="$STR_COMSPEC_ATAK_CFG_TOC3D_65C";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_65_ceiling.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_65_ceiling_preview.jpg";
    class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_41D5DDB858"; selection=""; distance=4; condition="true"; position="[0, 0.1, -1.1]"; }; };
};
class COMSPEC_ATAK_Screen_ClockStrip: COMSPEC_ATAK_Screen_Base3D {
    scope=2; scopeCurator=2;
    displayName="$STR_COMSPEC_ATAK_CFG_TOC3D_CLK";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_clock_strip.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_clock_strip_preview.jpg";
    comspec_screenRatio=4;
    class ACE_Actions {};
};
class COMSPEC_ATAK_Screen_NamePlate: COMSPEC_ATAK_Screen_ClockStrip {
    displayName="$STR_COMSPEC_ATAK_CFG_TOC3D_PLT";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_name_plate.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_name_plate_preview.jpg";
    hiddenSelectionsTextures[]={"#(argb,8,8,3)color(0.06,0.065,0.07,1,co)"};
};
