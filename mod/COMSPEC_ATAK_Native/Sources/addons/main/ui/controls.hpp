#include "colors.hpp"
// Polices du téléphone : Barlow Condensed embarquée (ui/fonts.hpp, tools/gen_fonts.py) pour tout le texte, une monospace
// du jeu pour les valeurs alignées (grilles, heures, fréquences, compteurs). Réglages > Police en choisit une autre :
// fn_fontSet remplace ces familles à l'affichage (contrôles créés par fn_fontApply, balises <t font=…> par fn_tr).
// Tailles : échelle de fn_layoutGet (font, fontSmall, fontCaption, fontTitle, fontDisplay) avec plancher en pixels.
#define ATAK_FONT "COMSPEC_Barlow"
#define ATAK_FONT_BOLD "COMSPEC_BarlowBold"
#define ATAK_FONT_LIGHT "COMSPEC_BarlowLight"
#define ATAK_FONT_MONO "EtelkaMonospacePro"
#define ATAK_FONT_MONO_BOLD "EtelkaMonospaceProBold"
class RscText;
class RscStructuredText { class Attributes; };
class RscButton;
class RscEdit;
class RscCombo;
class RscListBox;
class RscCheckBox;
class RscProgress;
class RscPicture;
class RscActivePicture;
class RscMapControl;
class RscXSliderH;
class RscControlsGroup {
    class VScrollbar;
    class HScrollbar;
};

class COMSPEC_RscText: RscText { font=ATAK_FONT; colorText[]=ATAK_TEXT; colorBackground[]={0,0,0,0}; sizeEx="0.026 * safeZoneH"; };
class COMSPEC_RscTextRight: COMSPEC_RscText { style=1; };
class COMSPEC_RscTextCenter: COMSPEC_RscText { style=2; };
// Texte centré sur plusieurs lignes (libellé long d'une tuile du lanceur).
class COMSPEC_RscTextCenterMulti: COMSPEC_RscText { style=18; lineSpacing=1; };
class COMSPEC_RscStructuredText: RscStructuredText { colorText[]=ATAK_TEXT; colorBackground[]={0,0,0,0}; size="0.024 * safeZoneH";
    // Police des balises <t> sans font=… (Roboto Condensed dans la classe du jeu).
    class Attributes: Attributes { font=ATAK_FONT; };
};
class COMSPEC_RscButton: RscButton {
    // Désactivé (ctrlEnable false) : texte nettement atténué et fond quasi transparent, bien distinct d'un bouton
    // disponible non choisi (fond BG2, texte clair).
    // shadow=0 : le RscButton du jeu trace un contour noir autour du texte (shadow=2), qui empâtait le texte foncé
    // des boutons verts (RÉDIGER, option choisie d'un sélecteur) jusqu'à le rendre illisible.
    shadow=0; font=ATAK_FONT_BOLD; colorText[]=ATAK_TEXT; colorDisabled[]={0.42,0.46,0.44,0.7};
    colorBackground[]=ATAK_BG2; colorBackgroundActive[]=ATAK_GREEN_DIM; colorBackgroundDisabled[]={0.04,0.045,0.042,0.55};
    colorFocused[]=ATAK_BG2; colorBorder[]=ATAK_BORDER; colorShadow[]={0,0,0,0}; offsetPressedX=0; offsetPressedY=0;
    sizeEx="0.024 * safeZoneH";
};
class COMSPEC_RscButtonPrimary: COMSPEC_RscButton { colorText[]=ATAK_BG0; colorBackground[]=ATAK_GREEN; colorFocused[]=ATAK_GREEN; colorBackgroundActive[]={0.46,0.88,0.52,1}; };
// Bouton invisible posé au-dessus d'une tuile (fond + icône + libellé) : seul le survol l'éclaire.
class COMSPEC_RscButtonOverlay: COMSPEC_RscButton { colorBackground[]={0,0,0,0}; colorFocused[]={0,0,0,0}; colorBackgroundDisabled[]={0,0,0,0}; colorDisabled[]={0,0,0,0}; colorBackgroundActive[]={0.36,0.78,0.42,0.18}; colorBorder[]={0,0,0,0}; text=""; };
// Touches physiques de la coque : aucune surbrillance (la texture du téléphone suffit).
class COMSPEC_RscButtonInvisible: COMSPEC_RscButtonOverlay { colorBackgroundActive[]={0,0,0,0}; colorBackgroundDisabled[]={0,0,0,0}; colorDisabled[]={0,0,0,0}; colorShadow[]={0,0,0,0}; };
class COMSPEC_RscEdit: RscEdit { font=ATAK_FONT; colorText[]=ATAK_TEXT; colorBackground[]=ATAK_BG0; colorSelection[]=ATAK_GREEN_DIM; sizeEx="0.024 * safeZoneH"; };
class COMSPEC_RscEditMulti: COMSPEC_RscEdit { style=16; lineSpacing=1; };
class COMSPEC_RscCombo: RscCombo { font=ATAK_FONT; colorText[]=ATAK_TEXT; colorBackground[]=ATAK_BG0; colorSelect[]=ATAK_TEXT; colorSelectBackground[]=ATAK_GREEN_DIM; sizeEx="0.024 * safeZoneH"; };
class COMSPEC_RscListBox: RscListBox { font=ATAK_FONT; colorText[]=ATAK_TEXT; colorSelect[]=ATAK_TEXT; colorSelect2[]=ATAK_TEXT; colorSelectBackground[]=ATAK_GREEN_DIM; colorSelectBackground2[]=ATAK_GREEN_DIM; colorBackground[]={0,0,0,0}; sizeEx="0.024 * safeZoneH"; rowHeight="0.040 * safeZoneH"; };
class COMSPEC_RscCheckbox: RscCheckBox {};
class COMSPEC_RscProgress: RscProgress { colorBar[]=ATAK_GREEN; colorFrame[]=ATAK_BORDER; };
class COMSPEC_RscControlsGroup: RscControlsGroup {
    class VScrollbar: VScrollbar { color[]=ATAK_GREEN; width=0.012; autoScrollEnabled=0; };
    class HScrollbar: HScrollbar { color[]=ATAK_GREEN; height=0; };
};
class COMSPEC_RscPicture: RscPicture { colorText[]=ATAK_GREEN; colorBackground[]={0,0,0,0}; style=2096; };
class COMSPEC_RscMap: RscMapControl { colorBackground[]={0.045,0.055,0.048,1}; colorOutside[]={0.025,0.030,0.027,1}; showCountourInterval=1;
    // Textes de la carte Arma plus discrets (marqueurs, lieux, grille) : lisibles aussi sur le petit écran.
    sizeExLabel="0.018 * safeZoneH"; sizeExGrid="0.016 * safeZoneH"; sizeExUnits="0.024 * safeZoneH"; sizeExNames="0.026 * safeZoneH"; sizeExInfo="0.018 * safeZoneH"; sizeExLevel="0.016 * safeZoneH"; sizeEx="0.018 * safeZoneH"; };
// Carte du téléphone : textes Arma des repères (sizeExLabel), noms de lieux (sizeExNames) et numéros de grille masqués
// (taille minuscule) car redessinés par l'ATAK : fn_mapMarkerText et fn_mapPlaceText (style et calques au choix),
// fn_mapOnDraw pour la grille. Marqueurs de la mission non dessinés (showMarkers=0) : Arma les dessine à taille fixe
// en pixels, énormes sur le petit écran ; fn_mapGameMarkers les redessine à l'échelle de l'écran de l'ATAK.
class COMSPEC_RscMapAtak: COMSPEC_RscMap { showMarkers=0; sizeExLabel="0.0005"; sizeExGrid="0.0005"; sizeExNames="0.0005"; sizeExInfo="0.021 * safeZoneH"; sizeEx="0.021 * safeZoneH"; };
// Carte de l'app JTAC (dessin à part, fn_jtacDraw) : textes Arma des repères et des lieux gardés.
class COMSPEC_RscMapJtac: COMSPEC_RscMapAtak { showMarkers=1; sizeExLabel="0.022 * safeZoneH"; sizeExNames="0.030 * safeZoneH"; };
class COMSPEC_RscPanel: COMSPEC_RscText { colorBackground[]=ATAK_BG1; };
class COMSPEC_RscTile: COMSPEC_RscText { colorBackground[]=ATAK_BG2; };
class COMSPEC_RscBadge: COMSPEC_RscTextCenter { font=ATAK_FONT_BOLD; colorBackground[]=ATAK_DANGER; colorText[]={1,1,1,1}; sizeEx="0.018 * safeZoneH"; };
// Libellé au-dessus d'un champ : condensé gras gris (la monospace, large, serrait les libellés longs).
class COMSPEC_RscLabel: COMSPEC_RscText { font=ATAK_FONT_BOLD; colorText[]=ATAK_TEXT_DIM; sizeEx="0.017 * safeZoneH"; };
// Valeur alignée (grille, heure, fréquence) : chiffres de largeur fixe.
class COMSPEC_RscMono: COMSPEC_RscText { font=ATAK_FONT_MONO; };
class COMSPEC_RscMonoRight: COMSPEC_RscMono { style=1; };
class COMSPEC_RscCard: COMSPEC_RscStructuredText { colorBackground[]=ATAK_BG2; };
class COMSPEC_RscDivider: COMSPEC_RscText { colorBackground[]=ATAK_BORDER; };
class COMSPEC_RscPhone: RscPicture { colorText[]={1,1,1,1}; colorBackground[]={0,0,0,0}; style=48; text="\z\comspec_atak_native\addons\main\data\phone_portrait.paa"; };
class COMSPEC_RscIcon: COMSPEC_RscPicture { colorText[]=ATAK_TEXT; };
// Diapositive de briefing : couleurs d'origine, proportions conservées.
class COMSPEC_RscSlide: RscPicture { colorText[]={1,1,1,1}; colorBackground[]={0,0,0,0}; style=2096; };
// Icône cliquable (barre d'app, outils carte) : blanche, verte au survol.
class COMSPEC_RscIconButton: RscActivePicture { color[]=ATAK_TEXT; colorActive[]=ATAK_GREEN; colorDisabled[]={0.4,0.45,0.42,0.6}; colorText[]=ATAK_TEXT; style=2096; tooltipColorText[]=ATAK_TEXT; tooltipColorBox[]=ATAK_BORDER; tooltipColorShade[]=ATAK_BG0; };
class COMSPEC_RscBubbleIn: COMSPEC_RscStructuredText { colorBackground[]=ATAK_BG2; };
class COMSPEC_RscBubbleOut: COMSPEC_RscStructuredText { colorBackground[]={0.10,0.20,0.12,1}; };
class COMSPEC_RscChip: COMSPEC_RscTextCenter { font=ATAK_FONT_BOLD; colorBackground[]={0.55,0.38,0.08,1}; colorText[]={1,1,1,1}; };
// Curseur du rejeu de mission (app AAR).
class COMSPEC_RscSlider: RscXSliderH { color[]={0.36,0.78,0.42,0.9}; colorActive[]={0.46,0.88,0.52,1}; colorDisabled[]={0.4,0.45,0.42,0.6}; };
class COMSPEC_RscMapPanel: COMSPEC_RscStructuredText { colorBackground[]={0.025,0.030,0.027,0.78}; };
