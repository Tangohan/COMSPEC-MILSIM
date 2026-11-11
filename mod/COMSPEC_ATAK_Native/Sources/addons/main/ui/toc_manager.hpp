// Fenêtre « Gérer les écrans » du TOC (fn_tocManagerUi, conception docs/audit/2026-11-gestionnaire-ecrans-ui.md).
// Contrôles créés par le code (ctrlCreate) dans les classes ci-dessous ; identifiants 8700 à 8799 (table _ids de
// fn_tocManagerUiData, vérifiée par tests/Unit/AtakTocManagerUiAssetTest.php). Tailles en « pixels de conception »
// sur 1080 de haut : n * safezoneH / 1080, donc les mêmes à toutes les tailles d'interface (Petite à Très grande).
// Couleurs : jetons du thème TOC (ui/toc_theme.hpp, mêmes valeurs que fn_tocTheme).
#define COMSPEC_ATAK_IDD_TOCMANAGER 88570
class RscTree;
class RscToolbox;
class RscControlsGroupNoHScrollbars;
class RscControlsGroupNoScrollbars;

class COMSPEC_RscTocMgrText: COMSPEC_RscText { font="RobotoCondensed"; colorText[]=TOC_TEXT; sizeEx="18 * safezoneH / 1080"; };
class COMSPEC_RscTocMgrTextRight: COMSPEC_RscTocMgrText { style=1; };
class COMSPEC_RscTocMgrTextCenter: COMSPEC_RscTocMgrText { style=2; };
class COMSPEC_RscTocMgrPanel: COMSPEC_RscTocMgrText { colorBackground[]=TOC_BG1; };
class COMSPEC_RscTocMgrStx: COMSPEC_RscStructuredText {
    size="18 * safezoneH / 1080"; colorText[]=TOC_TEXT;
    class Attributes: Attributes { font="RobotoCondensed"; };
};
class COMSPEC_RscTocMgrButton: COMSPEC_RscTocButton { font="RobotoCondensedBold"; sizeEx="16 * safezoneH / 1080"; };
class COMSPEC_RscTocMgrButtonPrimary: COMSPEC_RscTocButtonPrimary { font="RobotoCondensedBold"; sizeEx="16 * safezoneH / 1080"; };
class COMSPEC_RscTocMgrTab: COMSPEC_RscTocTab { font="RobotoCondensedBold"; sizeEx="16 * safezoneH / 1080"; };
// Zone de clic posée sur une dalle du schéma : sans fond, le survol seul l'éclaire un peu.
class COMSPEC_RscTocMgrHit: COMSPEC_RscButtonOverlay { colorBackgroundActive[]={1,1,1,0.06}; };
class COMSPEC_RscTocMgrEdit: COMSPEC_RscTocEdit { font="RobotoCondensed"; sizeEx="18 * safezoneH / 1080"; };
class COMSPEC_RscTocMgrEditMulti: COMSPEC_RscTocMgrEdit { style=16; lineSpacing=1; };
// Liste déroulante bornée à 8 lignes : jamais par-dessus la barre d'actions.
class COMSPEC_RscTocMgrCombo: COMSPEC_RscTocCombo { font="RobotoCondensed"; sizeEx="18 * safezoneH / 1080"; wholeHeight="8 * 28 * safezoneH / 1080"; };
class COMSPEC_RscTocMgrList: COMSPEC_RscTocList { font="RobotoCondensedBold"; sizeEx="17 * safezoneH / 1080"; rowHeight="34 * safezoneH / 1080"; };
class COMSPEC_RscTocMgrTable: COMSPEC_RscListNBox {
    font="RobotoCondensed"; sizeEx="18 * safezoneH / 1080"; rowHeight="34 * safezoneH / 1080";
    colorText[]=TOC_TEXT; colorSelect[]=TOC_TEXT; colorSelect2[]=TOC_TEXT; colorBackground[]=TOC_BG0;
    colorSelectBackground[]=TOC_ACCENTDIM; colorSelectBackground2[]=TOC_ACCENTDIM;
    columns[]={0,0.5}; drawSideArrows=0; idcLeft=-1; idcRight=-1;
};
class COMSPEC_RscTocMgrTree: RscTree {
    font="RobotoCondensed"; sizeEx="18 * safezoneH / 1080"; shadow=0;
    colorText[]=TOC_TEXT; colorSelectText[]=TOC_TEXT; colorSelect[]=TOC_ACCENTDIM; colorBackground[]=TOC_BG1;
    colorBorder[]={0,0,0,0}; colorLines[]=TOC_LINE; colorArrow[]=TOC_TEXT2; colorPicture[]={1,1,1,1}; colorPictureSelected[]={1,1,1,1};
    colorPictureRight[]={1,1,1,1}; colorPictureRightSelected[]={1,1,1,1};
    multiselectEnabled=0; expandOnDoubleClick=0; disableKeyboardSearch=1;
};
class COMSPEC_RscTocMgrSlider: RscXSliderH { color[]={0.204,0.827,0.6,0.85}; colorActive[]={0.43,0.9,0.72,1}; colorDisabled[]={0.518,0.58,0.627,0.4}; };
class COMSPEC_RscTocMgrCheck: RscCheckBox { color[]=TOC_TEXT2; colorFocused[]=TOC_ACCENT; colorHover[]=TOC_TEXT; colorPressed[]=TOC_ACCENT; colorDisabled[]={0.518,0.58,0.627,0.4}; };
class COMSPEC_RscTocMgrGroup: RscControlsGroupNoHScrollbars {};
class COMSPEC_RscTocMgrGroupFixed: RscControlsGroupNoScrollbars {};
// Vignette d'une dalle : la texture déjà rendue de l'écran (aucun rendu de plus), étirée dans sa case.
class COMSPEC_RscTocMgrPic: RscPicture { style=48; colorText[]={1,1,1,1}; colorBackground[]={0,0,0,0}; };
class COMSPEC_RscTocMgrPower: RscActivePicture {
    style=2096; color[]=TOC_TEXT2; colorActive[]=TOC_ACCENT; colorDisabled[]={0.518,0.58,0.627,0.4};
    text="\a3\ui_f\data\igui\cfg\actions\engine_on_ca.paa";
};
// Choix exclusifs (2 à 5 choix) : libellés dans la stringtable (langue du jeu), flèches du clavier.
class COMSPEC_RscTocMgrTbx: RscToolbox {
    font="RobotoCondensedBold"; sizeEx="16 * safezoneH / 1080"; shadow=0;
    colorText[]=TOC_TEXT2; color[]=TOC_TEXT2; colorTextSelect[]=TOC_ACCENT; colorSelect[]=TOC_ACCENT;
    colorTextDisable[]={0.518,0.58,0.627,0.5}; colorDisable[]={0.518,0.58,0.627,0.5};
    colorBackground[]=TOC_BG0; colorSelectedBg[]=TOC_ACCENTDIM;
    rows=1; columns=2; strings[]={"",""};
};
class COMSPEC_RscTocMgrTbxViews: COMSPEC_RscTocMgrTbx { columns=4; strings[]={"$STR_COMSPEC_ATAK_CFG_TOCMGR_V_SCREEN","$STR_COMSPEC_ATAK_CFG_TOCMGR_V_ROOMS","$STR_COMSPEC_ATAK_CFG_TOCMGR_V_LOG","$STR_COMSPEC_ATAK_CFG_TOCMGR_V_ROUTE"}; };
class COMSPEC_RscTocMgrTbxScope: COMSPEC_RscTocMgrTbx { columns=4; strings[]={"$STR_COMSPEC_ATAK_CFG_TOCMGR_S_ONE","$STR_COMSPEC_ATAK_CFG_TOCMGR_S_SEL","$STR_COMSPEC_ATAK_CFG_TOCMGR_S_SCREEN","$STR_COMSPEC_ATAK_CFG_TOCMGR_S_ROOM"}; };
class COMSPEC_RscTocMgrTbxCams: COMSPEC_RscTocMgrTbx { columns=5; strings[]={"$STR_COMSPEC_ATAK_CFG_TOCMGR_C_ALL","$STR_COMSPEC_ATAK_CFG_TOCMGR_C_LIVE","$STR_COMSPEC_ATAK_CFG_TOCMGR_C_UAV","$STR_COMSPEC_ATAK_CFG_TOCMGR_C_FIXED","$STR_COMSPEC_ATAK_CFG_TOCMGR_C_WORN"}; };
class COMSPEC_RscTocMgrTbxPower: COMSPEC_RscTocMgrTbx { columns=3; strings[]={"$STR_COMSPEC_ATAK_CFG_TOCMGR_P_ON","$STR_COMSPEC_ATAK_CFG_TOCMGR_P_STANDBY","$STR_COMSPEC_ATAK_CFG_TOCMGR_P_OFF"}; };
class COMSPEC_RscTocMgrTbxCenter: COMSPEC_RscTocMgrTbx { columns=3; strings[]={"$STR_COMSPEC_ATAK_CFG_TOCMGR_X_KEEP","$STR_COMSPEC_ATAK_CFG_TOCMGR_X_LOOK","$STR_COMSPEC_ATAK_CFG_TOCMGR_X_ME"}; };
class COMSPEC_RscTocMgrTbxGrid: COMSPEC_RscTocMgrTbx { columns=2; strings[]={"2 × 2","3 × 3"}; };
class COMSPEC_RscTocMgrTbxColor: COMSPEC_RscTocMgrTbx { columns=4; strings[]={"$STR_COMSPEC_ATAK_CFG_TOCMGR_K_GREEN","$STR_COMSPEC_ATAK_CFG_TOCMGR_K_AMBER","$STR_COMSPEC_ATAK_CFG_TOCMGR_K_RED","$STR_COMSPEC_ATAK_CFG_TOCMGR_K_WHITE"}; };
class COMSPEC_RscTocMgrTbxSpeed: COMSPEC_RscTocMgrTbx { columns=3; strings[]={"$STR_COMSPEC_ATAK_CFG_TOCMGR_D_SLOW","$STR_COMSPEC_ATAK_CFG_TOCMGR_D_NORMAL","$STR_COMSPEC_ATAK_CFG_TOCMGR_D_FAST"}; };
class COMSPEC_RscTocMgrTbxScroll: COMSPEC_RscTocMgrTbx { columns=2; strings[]={"$STR_COMSPEC_ATAK_CFG_TOCMGR_R_FIT","$STR_COMSPEC_ATAK_CFG_TOCMGR_R_ALWAYS"}; };
class COMSPEC_RscTocMgrTbxAlign: COMSPEC_RscTocMgrTbx { columns=3; strings[]={"$STR_COMSPEC_ATAK_CFG_TOCMGR_A_CENTER","$STR_COMSPEC_ATAK_CFG_TOCMGR_A_LEFT","$STR_COMSPEC_ATAK_CFG_TOCMGR_A_RIGHT"}; };

// La fenêtre elle-même : vide, tout est créé par fn_tocManagerUi « open ». enableSimulation=1 : le jeu continue derrière.
class COMSPEC_RscTocManager {
    idd=COMSPEC_ATAK_IDD_TOCMANAGER; movingEnable=0; enableSimulation=1;
    onUnload="['unload', _this select 0] call comspec_atak_native_fnc_tocManagerUi";
    class controlsBackground {};
    class controls {};
};
