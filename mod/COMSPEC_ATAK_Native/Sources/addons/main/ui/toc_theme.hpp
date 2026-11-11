// Couleurs du thème TOC pour les classes de config du PC du poste (fn_tocLaptop) : mêmes valeurs que la table _tokens de
// functions/toc/fn_tocTheme.sqf (seule source ; tests/Unit/AtakTocThemeTest.php vérifie l'égalité). Voir docs/TOC-THEME.md.
#define TOC_BG0 {0.027,0.039,0.051,1}
#define TOC_BG1 {0.051,0.075,0.094,1}
#define TOC_BG2 {0.078,0.11,0.137,1}
#define TOC_BG3 {0.11,0.149,0.188,1}
#define TOC_LINE {0.141,0.188,0.227,1}
#define TOC_LINESTRONG {0.227,0.29,0.341,1}
#define TOC_TEXT {0.91,0.933,0.949,1}
#define TOC_TEXT2 {0.667,0.718,0.757,1}
#define TOC_MUTED {0.518,0.58,0.627,1}
#define TOC_ACCENT {0.204,0.827,0.6,1}
#define TOC_ACCENTDIM {0.055,0.227,0.169,1}
#define TOC_AMBER {0.961,0.722,0.255,1}
#define TOC_RED {1.0,0.373,0.337,1}

// PC du poste : trois états lisibles pour chaque bouton (normal BG2, survol BG3, désactivé : fond du panneau et texte
// atténué), bouton principal en accent (texte foncé), onglet plat (texte secondaire, choisi : fond BG2, texte accent et
// filet accent posés par fn_tocLaptop « tab »). Champs et listes sur le fond de l'écran (BG0), filet LINE.
class COMSPEC_RscTocButton: COMSPEC_RscButton {
    colorText[]=TOC_TEXT; colorBackground[]=TOC_BG2; colorBackground2[]=TOC_BG2; colorFocused[]=TOC_BG2; colorBackgroundActive[]=TOC_BG3;
    colorDisabled[]={0.518,0.58,0.627,0.6}; colorBackgroundDisabled[]=TOC_BG1; colorBorder[]=TOC_LINE;
};
class COMSPEC_RscTocButtonPrimary: COMSPEC_RscTocButton {
    colorText[]=TOC_BG0; colorBackground[]=TOC_ACCENT; colorBackground2[]=TOC_ACCENT; colorFocused[]=TOC_ACCENT; colorBackgroundActive[]={0.43,0.9,0.72,1};
};
class COMSPEC_RscTocTab: COMSPEC_RscTocButton {
    colorText[]=TOC_TEXT2; colorBackground[]=TOC_BG1; colorBackground2[]=TOC_BG1; colorFocused[]=TOC_BG1; colorBackgroundActive[]=TOC_BG2;
};
class COMSPEC_RscTocEdit: COMSPEC_RscEdit { colorText[]=TOC_TEXT; colorBackground[]=TOC_BG0; colorSelection[]=TOC_ACCENTDIM; colorDisabled[]=TOC_MUTED; };
class COMSPEC_RscTocEditMulti: COMSPEC_RscTocEdit { style=16; lineSpacing=1; };
class COMSPEC_RscTocCombo: COMSPEC_RscCombo { colorText[]=TOC_TEXT; colorBackground[]=TOC_BG0; colorSelect[]=TOC_TEXT; colorSelectBackground[]=TOC_ACCENTDIM; };
class COMSPEC_RscTocList: COMSPEC_RscListBoxDense {
    colorText[]=TOC_TEXT; colorSelect[]=TOC_TEXT; colorSelect2[]=TOC_TEXT; colorSelectBackground[]=TOC_ACCENTDIM; colorSelectBackground2[]=TOC_ACCENTDIM; colorBackground[]=TOC_BG0;
};
class COMSPEC_RscTocLbl: COMSPEC_RscLabel { colorText[]=TOC_MUTED; };
