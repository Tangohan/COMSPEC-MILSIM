// Mobilier et périphériques de salle d'opérations COMSPEC (tools/model_toc_furniture.py, data\toc\) : consoles
// d'opérateur, table de briefing, fauteuil, claviers, souris, unités centrales, accessoires. Modèles du mod, sans
// simulation (Static), posables dans Eden et Zeus (COMSPEC › Mobilier de TOC, Accessoires de TOC).
// ORIENTATION : l'opérateur est du côté de la direction d'une console, d'un poste PC, d'un clavier (côté genoux, façade
// de la baie PC, face des moniteurs) ; le fauteuil regarde dans sa direction. Un poste PC se pose sur une console à la
// même position, à la même direction, à la hauteur comspec_tocDeskTop. Console à plusieurs postes : un poste tous les
// 1,6 m (points mémoire pc_1.. de la gauche de l'opérateur vers sa droite, chair_N, mon_N, bay_N, name_N).
// Fauteuil : s'asseoir avec ACE (ace_sitting), l'opérateur regarde dans la direction du fauteuil.
class COMSPEC_ATAK_TocFurn_Base: Static {
    scope=0; scopeCurator=0; author="COMSPEC";
    side=3; faction="Default";
    icon="iconObject_1x1"; mapSize=1.6;
    vehicleClass="Items"; editorCategory="COMSPEC_ATAK_EdCat"; editorSubcategory="COMSPEC_ATAK_EdSubcat_TocFurn";
    destrType="DestructNo"; armor=500;
    comspec_tocFurn=1;
};
class COMSPEC_ATAK_TocProp_Base: COMSPEC_ATAK_TocFurn_Base {
    mapSize=0.3; armor=50;
    editorSubcategory="COMSPEC_ATAK_EdSubcat_TocProps";
};
class COMSPEC_ATAK_TocProp_CableTray: COMSPEC_ATAK_TocProp_Base {
    scope=2; scopeCurator=2;
    displayName="$STR_COMSPEC_ATAK_CFG_TOCFURN_PROP_CABLETRAY";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_furn_cabletray.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_furn_cabletray_preview.jpg";
    mapSize=0.8;
};
class COMSPEC_ATAK_TocChair: COMSPEC_ATAK_TocFurn_Base {
    scope=2; scopeCurator=2;
    displayName="$STR_COMSPEC_ATAK_CFG_TOCFURN_CHAIR";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_furn_chair.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_furn_chair_preview.jpg";
    mapSize=0.7; armor=100;
    ace_sitting_canSit=1; ace_sitting_sitDirection=0; ace_sitting_sitPosition[]={0,-0.05,0}; ace_sitting_interactPosition[]={0,0,0.5};
};
class COMSPEC_ATAK_TocDesk_Corner: COMSPEC_ATAK_TocFurn_Base {
    scope=2; scopeCurator=2;
    displayName="$STR_COMSPEC_ATAK_CFG_TOCFURN_DESK_CORNER";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_furn_desk_corner.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_furn_desk_corner_preview.jpg";
    mapSize=1.2; comspec_tocDeskTop=0.74; comspec_tocDeskSeats=0; comspec_tocDeskWidth=1.15;
};
class COMSPEC_ATAK_TocDesk_Curved: COMSPEC_ATAK_TocFurn_Base {
    scope=2; scopeCurator=2;
    displayName="$STR_COMSPEC_ATAK_CFG_TOCFURN_DESK_CURVED";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_furn_desk_curved.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_furn_desk_curved_preview.jpg";
    mapSize=2.2; comspec_tocDeskTop=0.74; comspec_tocDeskSeats=1; comspec_tocDeskWidth=2.19;
};
class COMSPEC_ATAK_TocDesk_Double: COMSPEC_ATAK_TocFurn_Base {
    scope=2; scopeCurator=2;
    displayName="$STR_COMSPEC_ATAK_CFG_TOCFURN_DESK_DOUBLE";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_furn_desk_double.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_furn_desk_double_preview.jpg";
    mapSize=3.2; comspec_tocDeskTop=0.74; comspec_tocDeskSeats=2; comspec_tocDeskWidth=3.2;
};
class COMSPEC_ATAK_TocDesk_Single: COMSPEC_ATAK_TocFurn_Base {
    scope=2; scopeCurator=2;
    displayName="$STR_COMSPEC_ATAK_CFG_TOCFURN_DESK_SINGLE";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_furn_desk_single.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_furn_desk_single_preview.jpg";
    mapSize=1.6; comspec_tocDeskTop=0.74; comspec_tocDeskSeats=1; comspec_tocDeskWidth=1.6;
};
class COMSPEC_ATAK_TocDesk_Triple: COMSPEC_ATAK_TocFurn_Base {
    scope=2; scopeCurator=2;
    displayName="$STR_COMSPEC_ATAK_CFG_TOCFURN_DESK_TRIPLE";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_furn_desk_triple.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_furn_desk_triple_preview.jpg";
    mapSize=4.8; comspec_tocDeskTop=0.74; comspec_tocDeskSeats=3; comspec_tocDeskWidth=4.8;
};
class COMSPEC_ATAK_TocProp_Handset: COMSPEC_ATAK_TocProp_Base {
    scope=2; scopeCurator=2;
    displayName="$STR_COMSPEC_ATAK_CFG_TOCFURN_PROP_HANDSET";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_furn_handset.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_furn_handset_preview.jpg";
};
class COMSPEC_ATAK_TocProp_Headset: COMSPEC_ATAK_TocProp_Base {
    scope=2; scopeCurator=2;
    displayName="$STR_COMSPEC_ATAK_CFG_TOCFURN_PROP_HEADSET";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_furn_headset.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_furn_headset_preview.jpg";
};
class COMSPEC_ATAK_TocKeyboard: COMSPEC_ATAK_TocProp_Base {
    scope=2; scopeCurator=2;
    displayName="$STR_COMSPEC_ATAK_CFG_TOCFURN_KEYBOARD";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_furn_kbd.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_furn_kbd_preview.jpg";
    mapSize=0.45;
};
class COMSPEC_ATAK_TocKeyboard_Backlit: COMSPEC_ATAK_TocProp_Base {
    scope=2; scopeCurator=2;
    displayName="$STR_COMSPEC_ATAK_CFG_TOCFURN_KEYBOARD_BACKLIT";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_furn_kbd_backlit.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_furn_kbd_backlit_preview.jpg";
    mapSize=0.45;
};
class COMSPEC_ATAK_TocKeyboard_Qwerty: COMSPEC_ATAK_TocProp_Base {
    scope=2; scopeCurator=2;
    displayName="$STR_COMSPEC_ATAK_CFG_TOCFURN_KEYBOARD_QWERTY";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_furn_kbd_qwerty.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_furn_kbd_qwerty_preview.jpg";
    mapSize=0.45;
};
class COMSPEC_ATAK_TocKeyboard_TKL: COMSPEC_ATAK_TocProp_Base {
    scope=2; scopeCurator=2;
    displayName="$STR_COMSPEC_ATAK_CFG_TOCFURN_KEYBOARD_TKL";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_furn_kbd_tkl.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_furn_kbd_tkl_preview.jpg";
    mapSize=0.4;
};
class COMSPEC_ATAK_TocMiniPC: COMSPEC_ATAK_TocProp_Base {
    scope=2; scopeCurator=2;
    displayName="$STR_COMSPEC_ATAK_CFG_TOCFURN_MINIPC";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_furn_minipc.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_furn_minipc_preview.jpg";
};
class COMSPEC_ATAK_TocMouse: COMSPEC_ATAK_TocProp_Base {
    scope=2; scopeCurator=2;
    displayName="$STR_COMSPEC_ATAK_CFG_TOCFURN_MOUSE";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_furn_mouse.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_furn_mouse_preview.jpg";
};
class COMSPEC_ATAK_TocMouse_Pad: COMSPEC_ATAK_TocProp_Base {
    scope=2; scopeCurator=2;
    displayName="$STR_COMSPEC_ATAK_CFG_TOCFURN_MOUSE_PAD";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_furn_mouse_pad.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_furn_mouse_pad_preview.jpg";
    mapSize=0.8;
};
class COMSPEC_ATAK_TocProp_Mug: COMSPEC_ATAK_TocProp_Base {
    scope=2; scopeCurator=2;
    displayName="$STR_COMSPEC_ATAK_CFG_TOCFURN_PROP_MUG";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_furn_mug.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_furn_mug_preview.jpg";
};
class COMSPEC_ATAK_TocProp_Notepad: COMSPEC_ATAK_TocProp_Base {
    scope=2; scopeCurator=2;
    displayName="$STR_COMSPEC_ATAK_CFG_TOCFURN_PROP_NOTEPAD";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_furn_notepad.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_furn_notepad_preview.jpg";
};
class COMSPEC_ATAK_TocTower_SFF: COMSPEC_ATAK_TocProp_Base {
    scope=2; scopeCurator=2;
    displayName="$STR_COMSPEC_ATAK_CFG_TOCFURN_TOWER_SFF";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_furn_sff.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_furn_sff_preview.jpg";
};
class COMSPEC_ATAK_TocTower_Under: COMSPEC_ATAK_TocProp_Base {
    scope=2; scopeCurator=2;
    displayName="$STR_COMSPEC_ATAK_CFG_TOCFURN_TOWER_UNDER";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_furn_sff_under.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_furn_sff_under_preview.jpg";
};
class COMSPEC_ATAK_TocDesk_Briefing: COMSPEC_ATAK_TocFurn_Base {
    scope=2; scopeCurator=2;
    displayName="$STR_COMSPEC_ATAK_CFG_TOCFURN_DESK_BRIEFING";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_furn_table_brief.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_furn_table_brief_preview.jpg";
    mapSize=2.4; comspec_tocDeskTop=0.74; comspec_tocDeskSeats=0; comspec_tocDeskWidth=2.4;
};
class COMSPEC_ATAK_TocTower: COMSPEC_ATAK_TocProp_Base {
    scope=2; scopeCurator=2;
    displayName="$STR_COMSPEC_ATAK_CFG_TOCFURN_TOWER";
    model="\z\comspec_atak_native\addons\main\data\toc\toc_furn_tower.p3d";
    editorPreview="\z\comspec_atak_native\addons\main\data\toc\toc_furn_tower_preview.jpg";
    mapSize=0.5;
};
