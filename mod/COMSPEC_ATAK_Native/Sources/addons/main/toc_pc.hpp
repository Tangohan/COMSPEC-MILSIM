// Postes PC fixes de salle d'opérations COMSPEC (tools/model_toc_pc.py, data\toc\) : unité centrale, moniteur(s), clavier
// et souris en un seul objet. Origine sur le plateau de la table (à poser à la hauteur du plateau : 0,8 m sur le bureau
// du jeu), milieu du poste en largeur, l'opérateur du côté de la direction de l'objet.
// Postes TOC (comspec_tocPost=1, fn_tocPost) : même action « PC du poste TOC » que le PC portable de TOC, enregistrables
// dans une salle (fn_tocHall). Leurs moniteurs sont de vrais écrans TOC (comspec_screen=1) : désignés d'eux-mêmes à la
// pose (fn_tocPc « initServer »), le moniteur principal en PIP du poste (comspec_tocPostView), un contenu par moniteur
// (comspec_screenPanels, index de gauche à droite vu par l'opérateur).
class COMSPEC_ATAK_TocPC_1: COMSPEC_ATAK_Screen_Base3D {
    scope=2; scopeCurator=2;
    displayName="$STR_COMSPEC_ATAK_CFG_TOCPC_1";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_pc_1.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_pc_1_preview.jpg";
    editorSubcategory="COMSPEC_ATAK_EdSubcat_TocPc";
    mapSize=1.2; armor=200;
    comspec_tocPost=1;
    comspec_tocPostView="TOC";
    class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_TOCPC_ACE"; selection=""; distance=3; condition="true"; position="[0, 0.1, 0.25]"; }; };
    class Attributes {
        class COMSPEC_ATAK_TocSide {
            displayName="$STR_COMSPEC_ATAK_CFG_TOCSIDE";
            tooltip="$STR_COMSPEC_ATAK_CFG_TOCSIDE_TIP";
            property="COMSPEC_ATAK_TocSide"; control="Edit"; typeName="STRING"; defaultValue="''";
            expression="if (_value isNotEqualTo '') then { _this setVariable ['COMSPEC_ATAK_TocSide', toUpper _value, true]; };";
        };
    };
};
// Deux 27" : PIP du poste à gauche, carte et flux à droite.
class COMSPEC_ATAK_TocPC_2: COMSPEC_ATAK_TocPC_1 {
    displayName="$STR_COMSPEC_ATAK_CFG_TOCPC_2";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_pc_2.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_pc_2_preview.jpg";
    mapSize=1.6;
    comspec_screenPanels=2;
    comspec_screenPanelViews[]={"TOC","SPLIT"};
    hiddenSelections[]={"panel1","panel2"};
    hiddenSelectionsTextures[]={COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF};
    hiddenSelectionsMaterials[]={COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT};
};
// Trois 27" en arc : flux à gauche, PIP du poste au centre, carte et flux à droite.
class COMSPEC_ATAK_TocPC_3: COMSPEC_ATAK_TocPC_1 {
    displayName="$STR_COMSPEC_ATAK_CFG_TOCPC_3";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_pc_3.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_pc_3_preview.jpg";
    mapSize=2;
    comspec_tocPostView="FEED";
    comspec_screenPanels=3;
    comspec_screenPanelViews[]={"FEED","TOC","SPLIT"};
    hiddenSelections[]={"panel1","panel2","panel3"};
    hiddenSelectionsTextures[]={COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF,COMSPEC_TOC3D_OFF};
    hiddenSelectionsMaterials[]={COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT,COMSPEC_TOC3D_MAT};
};
// 49" incurvé 32:9 : dalle continue en deux zones 16:9 (PIP du poste à gauche, carte et flux à droite).
class COMSPEC_ATAK_TocPC_Curved: COMSPEC_ATAK_TocPC_2 {
    displayName="$STR_COMSPEC_ATAK_CFG_TOCPC_C";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_pc_curved.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_pc_curved_preview.jpg";
};
