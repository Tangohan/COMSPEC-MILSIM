// Écrans de salle d'opérations COMSPEC (TOC / JOC) : modèles du mod (tools/model_toc_screens.py, data\toc\), tailles
// réelles. Origine au sol, dos contre le mur (Y = 0), milieu en largeur : posé contre un mur, l'écran est à la bonne
// hauteur. Deux murs côte à côte se touchent sans jour si leurs origines sont espacées de leur largeur hors tout
// (2x2 : 2,4572 m ; 3x2 : 3,6738 m ; 4x3 : 4,8904 m ; 5x3 : 6,1070 m ; 6x4 : 7,3236 m ; 98" : 2,1935 m ; 136" : 3,0270 m ;
// 220" : 4,8784 m ; bandeaux LED texte : 2,44 m et 4,84 m), même direction. Écrans suspendus : origine sur le dessus des
// platines de plafond (à monter jusqu'au plafond) ; double 65" : 2,926 m, triple : 4,389 m, 98" : 2,1935 m.
// Grand écran incurvé : 7,01 m hors tout en largeur, 0,92 m de flèche (pièce unique, ne s'accole pas).
// Dalles : comspec_screen=1, matières jour / nuit comspec_screenMat sous une image, verre noir brillant éteint.
// Murs à plusieurs dalles (comspec_screenPanels) : une sélection par dalle, index = ordre de lecture vu de face
// (panel1 en haut à gauche) ; chaque index reçoit sa propre image (fn_tocScreen « paint » / « surface » par dalle).
// Bandeau horloge et plaque de nom : face 4:1 (comspec_screenRatio), sélection « screen » (une carte 16:9 y serait
// écrasée) : affichages bandeau, plaque ou texte. Bandeaux LED texte : faces 8:1 et 16:1.
// Point du menu ACE : centre de la boîte du modèle (fn_tocConfig « acePos »), jamais une position écrite à la main
// (pas de point mémoire dans ces modèles ; origine au sol ou au plafond). Toute classe héritée de la base l'a donc,
// bandeau et plaque compris, avec les attributs Eden (COMSPEC_SCREEN_ATTRIBUTES).
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
    class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_41D5DDB858"; selection=""; distance=6; condition="true"; position="['acePos', _target] call comspec_atak_native_fnc_tocConfig"; }; };
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
};
class COMSPEC_ATAK_Screen_65_Ceiling: COMSPEC_ATAK_Screen_Base3D {
    scope=2; scopeCurator=2;
    COMSPEC_SCREEN_ATTRIBUTES;
    displayName="$STR_COMSPEC_ATAK_CFG_TOC3D_65C";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_65_ceiling.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_65_ceiling_preview.jpg";
};
class COMSPEC_ATAK_Screen_ClockStrip: COMSPEC_ATAK_Screen_Base3D {
    scope=2; scopeCurator=2;
    displayName="$STR_COMSPEC_ATAK_CFG_TOC3D_CLK";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_clock_strip.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_clock_strip_preview.jpg";
    comspec_screenRatio=4;
    COMSPEC_SCREEN_ATTRIBUTES;
};
class COMSPEC_ATAK_Screen_NamePlate: COMSPEC_ATAK_Screen_ClockStrip {
    displayName="$STR_COMSPEC_ATAK_CFG_TOC3D_PLT";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_name_plate.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_name_plate_preview.jpg";
    hiddenSelectionsTextures[]={"#(argb,8,8,3)color(0.06,0.065,0.07,1,co)"};
};
// Bandeaux LED texte (affichage TEXTE, défilant) : faces 8:1 et 16:1 (comspec_screenRatio), muraux (milieu à 2,55 m) ou
// suspendus sous deux tiges (origine sur le dessus des platines de plafond).
class COMSPEC_ATAK_Screen_LedText8: COMSPEC_ATAK_Screen_Base3D {
    scope=2; scopeCurator=2;
    COMSPEC_SCREEN_ATTRIBUTES;
    displayName="$STR_COMSPEC_ATAK_CFG_TOC3D_LT8";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_led_text_8.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_led_text_8_preview.jpg";
    comspec_screenRatio=8;
    class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_41D5DDB858"; selection=""; distance=6; condition="true"; position="['acePos', _target] call comspec_atak_native_fnc_tocConfig"; }; };
};
class COMSPEC_ATAK_Screen_LedText16: COMSPEC_ATAK_Screen_Base3D {
    scope=2; scopeCurator=2;
    COMSPEC_SCREEN_ATTRIBUTES;
    displayName="$STR_COMSPEC_ATAK_CFG_TOC3D_LT16";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_led_text_16.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_led_text_16_preview.jpg";
    comspec_screenRatio=16;
    class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_41D5DDB858"; selection=""; distance=6; condition="true"; position="['acePos', _target] call comspec_atak_native_fnc_tocConfig"; }; };
};
class COMSPEC_ATAK_Screen_LedText8_Ceiling: COMSPEC_ATAK_Screen_Base3D {
    scope=2; scopeCurator=2;
    COMSPEC_SCREEN_ATTRIBUTES;
    displayName="$STR_COMSPEC_ATAK_CFG_TOC3D_LT8C";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_led_text_8_ceiling.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_led_text_8_ceiling_preview.jpg";
    comspec_screenRatio=8;
    class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_41D5DDB858"; selection=""; distance=6; condition="true"; position="['acePos', _target] call comspec_atak_native_fnc_tocConfig"; }; };
};
class COMSPEC_ATAK_Screen_LedText16_Ceiling: COMSPEC_ATAK_Screen_Base3D {
    scope=2; scopeCurator=2;
    COMSPEC_SCREEN_ATTRIBUTES;
    displayName="$STR_COMSPEC_ATAK_CFG_TOC3D_LT16C";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_led_text_16_ceiling.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_led_text_16_ceiling_preview.jpg";
    comspec_screenRatio=16;
    class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_41D5DDB858"; selection=""; distance=6; condition="true"; position="['acePos', _target] call comspec_atak_native_fnc_tocConfig"; }; };
};
// Écran géant 136 pouces LED tout-en-un (3,011 x 1,694 m) : mural ou sur pied de sol lourd (semelles lestées, sans roulettes).
class COMSPEC_ATAK_Screen_136: COMSPEC_ATAK_Screen_Base3D {
    scope=2; scopeCurator=2;
    COMSPEC_SCREEN_ATTRIBUTES;
    displayName="$STR_COMSPEC_ATAK_CFG_TOC3D_136";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_136_wall.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_136_wall_preview.jpg";
    class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_41D5DDB858"; selection=""; distance=6; condition="true"; position="['acePos', _target] call comspec_atak_native_fnc_tocConfig"; }; };
};
class COMSPEC_ATAK_Screen_136_Stand: COMSPEC_ATAK_Screen_Base3D {
    scope=2; scopeCurator=2;
    COMSPEC_SCREEN_ATTRIBUTES;
    displayName="$STR_COMSPEC_ATAK_CFG_TOC3D_136S";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_136_stand.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_136_stand_preview.jpg";
    class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_41D5DDB858"; selection=""; distance=6; condition="true"; position="['acePos', _target] call comspec_atak_native_fnc_tocConfig"; }; };
};
// Mur LED 220 pouces à vue directe (4,870 x 2,740 m), une seule image : mural ou autoportant sur trois échelles en treillis.
class COMSPEC_ATAK_Screen_Led220: COMSPEC_ATAK_Screen_Base3D {
    scope=2; scopeCurator=2;
    COMSPEC_SCREEN_ATTRIBUTES;
    displayName="$STR_COMSPEC_ATAK_CFG_TOC3D_L220";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_led_220_wall.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_led_220_wall_preview.jpg";
    class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_41D5DDB858"; selection=""; distance=6; condition="true"; position="['acePos', _target] call comspec_atak_native_fnc_tocConfig"; }; };
};
class COMSPEC_ATAK_Screen_Led220_Truss: COMSPEC_ATAK_Screen_Base3D {
    scope=2; scopeCurator=2;
    COMSPEC_SCREEN_ATTRIBUTES;
    displayName="$STR_COMSPEC_ATAK_CFG_TOC3D_L220T";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_led_220_truss.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_led_220_truss_preview.jpg";
    class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_41D5DDB858"; selection=""; distance=6; condition="true"; position="['acePos', _target] call comspec_atak_native_fnc_tocConfig"; }; };
};
// Très grands murs de dalles 55 pouces. Une image 16:9 ne couvre pas un mur 4x3 (64:27), 5x3 (80:27) ni 6x4 (8:3) sans
// déformation : l'image principale prend le plus grand bloc 16:9 (index 0), les autres dalles gardent chacune leur image.
//   4x3 : index 0 = bloc 3x3 de gauche, 1 à 3 = colonne de droite (haut -> bas) ; _Panels : 12 dalles, ordre de lecture.
//   5x3 : index 0 = bloc 3x3 central, 1 à 3 = colonne gauche, 4 à 6 = colonne droite ; _Panels : 15 dalles.
//   6x4 : index 0 = bloc 4x4 central (220 pouces), 1 à 4 = colonne gauche, 5 à 8 = colonne droite ;
//         _Zones : six blocs 2x2 (110 pouces chacun), ordre de lecture (0 à 2 en haut, 3 à 5 en bas).
class COMSPEC_ATAK_Screen_Wall4x3: COMSPEC_ATAK_Screen_Base3D {
    scope=2; scopeCurator=2;
    COMSPEC_SCREEN_ATTRIBUTES;
    displayName="$STR_COMSPEC_ATAK_CFG_TOC3D_W43";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_wall_4x3.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_wall_4x3_preview.jpg";
    comspec_screenPanels=4;
    hiddenSelections[]={"panel1","panel2","panel3","panel4"};
    hiddenSelectionsTextures[]={COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF};
    hiddenSelectionsMaterials[]={COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT};
    class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_41D5DDB858"; selection=""; distance=6; condition="true"; position="['acePos', _target] call comspec_atak_native_fnc_tocConfig"; }; };
};
class COMSPEC_ATAK_Screen_Wall4x3_Panels: COMSPEC_ATAK_Screen_Base3D {
    scope=2; scopeCurator=2;
    COMSPEC_SCREEN_ATTRIBUTES;
    displayName="$STR_COMSPEC_ATAK_CFG_TOC3D_W43P";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_wall_4x3_panels.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_wall_4x3_panels_preview.jpg";
    comspec_screenPanels=12;
    hiddenSelections[]={"panel1","panel2","panel3","panel4","panel5","panel6","panel7","panel8","panel9","panel10","panel11","panel12"};
    hiddenSelectionsTextures[]={COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF};
    hiddenSelectionsMaterials[]={COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT};
    class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_41D5DDB858"; selection=""; distance=6; condition="true"; position="['acePos', _target] call comspec_atak_native_fnc_tocConfig"; }; };
};
class COMSPEC_ATAK_Screen_Wall5x3: COMSPEC_ATAK_Screen_Base3D {
    scope=2; scopeCurator=2;
    COMSPEC_SCREEN_ATTRIBUTES;
    displayName="$STR_COMSPEC_ATAK_CFG_TOC3D_W53";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_wall_5x3.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_wall_5x3_preview.jpg";
    comspec_screenPanels=7;
    hiddenSelections[]={"panel1","panel2","panel3","panel4","panel5","panel6","panel7"};
    hiddenSelectionsTextures[]={COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF};
    hiddenSelectionsMaterials[]={COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT};
    class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_41D5DDB858"; selection=""; distance=6; condition="true"; position="['acePos', _target] call comspec_atak_native_fnc_tocConfig"; }; };
};
class COMSPEC_ATAK_Screen_Wall5x3_Panels: COMSPEC_ATAK_Screen_Base3D {
    scope=2; scopeCurator=2;
    COMSPEC_SCREEN_ATTRIBUTES;
    displayName="$STR_COMSPEC_ATAK_CFG_TOC3D_W53P";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_wall_5x3_panels.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_wall_5x3_panels_preview.jpg";
    comspec_screenPanels=15;
    hiddenSelections[]={"panel1","panel2","panel3","panel4","panel5","panel6","panel7","panel8","panel9","panel10","panel11","panel12","panel13","panel14","panel15"};
    hiddenSelectionsTextures[]={COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF};
    hiddenSelectionsMaterials[]={COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT};
    class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_41D5DDB858"; selection=""; distance=6; condition="true"; position="['acePos', _target] call comspec_atak_native_fnc_tocConfig"; }; };
};
class COMSPEC_ATAK_Screen_Wall6x4: COMSPEC_ATAK_Screen_Base3D {
    scope=2; scopeCurator=2;
    COMSPEC_SCREEN_ATTRIBUTES;
    displayName="$STR_COMSPEC_ATAK_CFG_TOC3D_W64";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_wall_6x4.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_wall_6x4_preview.jpg";
    comspec_screenPanels=9;
    hiddenSelections[]={"panel1","panel2","panel3","panel4","panel5","panel6","panel7","panel8","panel9"};
    hiddenSelectionsTextures[]={COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF};
    hiddenSelectionsMaterials[]={COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT};
    class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_41D5DDB858"; selection=""; distance=6; condition="true"; position="['acePos', _target] call comspec_atak_native_fnc_tocConfig"; }; };
};
class COMSPEC_ATAK_Screen_Wall6x4_Zones: COMSPEC_ATAK_Screen_Base3D {
    scope=2; scopeCurator=2;
    COMSPEC_SCREEN_ATTRIBUTES;
    displayName="$STR_COMSPEC_ATAK_CFG_TOC3D_W64Z";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_wall_6x4_zones.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_wall_6x4_zones_preview.jpg";
    comspec_screenPanels=6;
    hiddenSelections[]={"panel1","panel2","panel3","panel4","panel5","panel6"};
    hiddenSelectionsTextures[]={COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF};
    hiddenSelectionsMaterials[]={COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT};
    class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_41D5DDB858"; selection=""; distance=6; condition="true"; position="['acePos', _target] call comspec_atak_native_fnc_tocConfig"; }; };
};
// Écrans suspendus multiples : une barre sous deux tubes de plafond, dalles inclinées de 10° vers le bas, une image par
// dalle (index de gauche à droite vu de face). _Arc : dalles latérales tournées de 15° vers le centre.
class COMSPEC_ATAK_Screen_65_Ceiling2: COMSPEC_ATAK_Screen_Base3D {
    scope=2; scopeCurator=2;
    COMSPEC_SCREEN_ATTRIBUTES;
    displayName="$STR_COMSPEC_ATAK_CFG_TOC3D_65C2";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_65_ceiling_2.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_65_ceiling_2_preview.jpg";
    comspec_screenPanels=2;
    hiddenSelections[]={"panel1","panel2"};
    hiddenSelectionsTextures[]={COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF};
    hiddenSelectionsMaterials[]={COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT};
    class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_41D5DDB858"; selection=""; distance=6; condition="true"; position="['acePos', _target] call comspec_atak_native_fnc_tocConfig"; }; };
};
class COMSPEC_ATAK_Screen_65_Ceiling3: COMSPEC_ATAK_Screen_Base3D {
    scope=2; scopeCurator=2;
    COMSPEC_SCREEN_ATTRIBUTES;
    displayName="$STR_COMSPEC_ATAK_CFG_TOC3D_65C3";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_65_ceiling_3.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_65_ceiling_3_preview.jpg";
    comspec_screenPanels=3;
    hiddenSelections[]={"panel1","panel2","panel3"};
    hiddenSelectionsTextures[]={COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF};
    hiddenSelectionsMaterials[]={COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT};
    class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_41D5DDB858"; selection=""; distance=6; condition="true"; position="['acePos', _target] call comspec_atak_native_fnc_tocConfig"; }; };
};
class COMSPEC_ATAK_Screen_65_Ceiling3_Arc: COMSPEC_ATAK_Screen_Base3D {
    scope=2; scopeCurator=2;
    COMSPEC_SCREEN_ATTRIBUTES;
    displayName="$STR_COMSPEC_ATAK_CFG_TOC3D_65C3A";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_65_ceiling_3_arc.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_65_ceiling_3_arc_preview.jpg";
    comspec_screenPanels=3;
    hiddenSelections[]={"panel1","panel2","panel3"};
    hiddenSelectionsTextures[]={COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF};
    hiddenSelectionsMaterials[]={COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT};
    class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_41D5DDB858"; selection=""; distance=6; condition="true"; position="['acePos', _target] call comspec_atak_native_fnc_tocConfig"; }; };
};
class COMSPEC_ATAK_Screen_98_Ceiling: COMSPEC_ATAK_Screen_Base3D {
    scope=2; scopeCurator=2;
    COMSPEC_SCREEN_ATTRIBUTES;
    displayName="$STR_COMSPEC_ATAK_CFG_TOC3D_98C";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_98_ceiling.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_98_ceiling_preview.jpg";
    class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_41D5DDB858"; selection=""; distance=6; condition="true"; position="['acePos', _target] call comspec_atak_native_fnc_tocConfig"; }; };
};
// Grand écran LED incurvé 32:9 : arc de 7,20 m x 2,025 m de haut, rayon 7 m, surface d'image continue (aucun joint).
// Trois zones : index 0 = zone centrale 16:9, 1 = colonne gauche 8:9, 2 = colonne droite 8:9 (comspec_screenPanelRatio,
// affichages proposés par comspec_screenPanelViews) ; _Duo : deux moitiés 16:9 (index 0 à gauche, 1 à droite).
// Sur pieds (dos de l'ouvrage contre le mur : la face est à 0,90 m du mur au milieu de l'arc) ou mural (0,27 m).
class COMSPEC_ATAK_Screen_Curved: COMSPEC_ATAK_Screen_Base3D {
    scope=2; scopeCurator=2;
    COMSPEC_SCREEN_ATTRIBUTES;
    displayName="$STR_COMSPEC_ATAK_CFG_TOC3D_CRV";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_curved_floor.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_curved_floor_preview.jpg";
    comspec_screenPanels=3; comspec_screenPanelRatio[]={1.778,0.889,0.889}; comspec_screenPanelViews[]={"","COLA","COLB"};
    hiddenSelections[]={"panel1","panel2","panel3"};
    hiddenSelectionsTextures[]={COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF};
    hiddenSelectionsMaterials[]={COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT};
    class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_41D5DDB858"; selection=""; distance=6; condition="true"; position="['acePos', _target] call comspec_atak_native_fnc_tocConfig"; }; };
};
class COMSPEC_ATAK_Screen_Curved_Duo: COMSPEC_ATAK_Screen_Base3D {
    scope=2; scopeCurator=2;
    COMSPEC_SCREEN_ATTRIBUTES;
    displayName="$STR_COMSPEC_ATAK_CFG_TOC3D_CRVD";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_curved_floor_duo.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_curved_floor_duo_preview.jpg";
    comspec_screenPanels=2;
    hiddenSelections[]={"panel1","panel2"};
    hiddenSelectionsTextures[]={COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF};
    hiddenSelectionsMaterials[]={COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT};
    class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_41D5DDB858"; selection=""; distance=6; condition="true"; position="['acePos', _target] call comspec_atak_native_fnc_tocConfig"; }; };
};
class COMSPEC_ATAK_Screen_Curved_Wall: COMSPEC_ATAK_Screen_Base3D {
    scope=2; scopeCurator=2;
    COMSPEC_SCREEN_ATTRIBUTES;
    displayName="$STR_COMSPEC_ATAK_CFG_TOC3D_CRVW";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_curved_wall.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_curved_wall_preview.jpg";
    comspec_screenPanels=3; comspec_screenPanelRatio[]={1.778,0.889,0.889}; comspec_screenPanelViews[]={"","COLA","COLB"};
    hiddenSelections[]={"panel1","panel2","panel3"};
    hiddenSelectionsTextures[]={COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF};
    hiddenSelectionsMaterials[]={COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT};
    class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_41D5DDB858"; selection=""; distance=6; condition="true"; position="['acePos', _target] call comspec_atak_native_fnc_tocConfig"; }; };
};
class COMSPEC_ATAK_Screen_Curved_Wall_Duo: COMSPEC_ATAK_Screen_Base3D {
    scope=2; scopeCurator=2;
    COMSPEC_SCREEN_ATTRIBUTES;
    displayName="$STR_COMSPEC_ATAK_CFG_TOC3D_CRVWD";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_curved_wall_duo.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_curved_wall_duo_preview.jpg";
    comspec_screenPanels=2;
    hiddenSelections[]={"panel1","panel2"};
    hiddenSelectionsTextures[]={COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF};
    hiddenSelectionsMaterials[]={COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT};
    class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_41D5DDB858"; selection=""; distance=6; condition="true"; position="['acePos', _target] call comspec_atak_native_fnc_tocConfig"; }; };
};
