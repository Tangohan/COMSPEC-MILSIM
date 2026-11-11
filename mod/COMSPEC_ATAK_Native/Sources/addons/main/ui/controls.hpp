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
class RscListNBox;
class RscCheckBox;
class RscProgress;
class RscPicture;
class RscActivePicture;
// Sous-classes redéclarées pour le fond ARDOISE (COMSPEC_RscMapAtakSlate) : légende et symboles de la carte recolorés.
class RscMapControl {
    class Legend; class Bush; class Tree; class SmallTree; class Rock; class Cross; class Chapel; class Shipwreck;
    class Bunker; class Fortress; class Fountain; class Ruin; class Stack; class Tourism; class ViewTower;
};
class RscXSliderH;
class RscControlsGroup {
    class VScrollbar;
    class HScrollbar;
};

// shadow=0 : l'ombre portée des RscText / RscStructuredText du jeu (shadow=1) doublait chaque lettre d'un liseré noir
// décalé, illisible sur les libellés gris (OUTILS, RECHERCHER) et le texte foncé ; les classes qui en ont besoin la remettent.
class COMSPEC_RscText: RscText { shadow=0; font=ATAK_FONT; colorText[]=ATAK_TEXT; colorBackground[]={0,0,0,0}; sizeEx="0.026 * safeZoneH"; };
class COMSPEC_RscTextRight: COMSPEC_RscText { style=1; };
class COMSPEC_RscTextCenter: COMSPEC_RscText { style=2; };
// Texte centré sur plusieurs lignes (libellé long d'une tuile du lanceur).
class COMSPEC_RscTextCenterMulti: COMSPEC_RscText { style=18; lineSpacing=1; };
class COMSPEC_RscStructuredText: RscStructuredText { shadow=0; colorText[]=ATAK_TEXT; colorBackground[]={0,0,0,0}; size="0.024 * safeZoneH";
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
    colorFocused[]=ATAK_BG2; colorBackground2[]=ATAK_BG2; colorBorder[]=ATAK_BORDER; colorShadow[]={0,0,0,0}; offsetPressedX=0; offsetPressedY=0;
    sizeEx="0.024 * safeZoneH";
};
class COMSPEC_RscButtonPrimary: COMSPEC_RscButton { colorText[]=ATAK_BG0; colorBackground[]=ATAK_GREEN; colorBackground2[]=ATAK_GREEN; colorFocused[]=ATAK_GREEN; colorBackgroundActive[]={0.46,0.88,0.52,1}; };
// Bouton invisible posé au-dessus d'une tuile (fond + icône + libellé) : seul le survol l'éclaire.
// Bouton transparent posé sur une ligne (sous-menu repliable, interrupteur, tuile) : toutes les couleurs de fond à 0,
// focus compris (colorBackground2, avec laquelle le moteur fait clignoter un bouton qui a le focus). Retour de jeu :
// PERSONNALISER LE GROUPE et les lignes de réglage apparaissaient assombries (tout le dessous à 40 %).
class COMSPEC_RscButtonOverlay: COMSPEC_RscButton { colorBackground[]={0,0,0,0}; colorBackground2[]={0,0,0,0}; colorFocused[]={0,0,0,0}; colorBackgroundDisabled[]={0,0,0,0}; colorDisabled[]={0,0,0,0}; colorBackgroundActive[]={0.36,0.78,0.42,0.18}; colorBorder[]={0,0,0,0}; text=""; };
// Bouton plat : texte seul, aucun fond ni clignotement de focus. Son fond est un contrôle posé dessous (« face », fn_formRender
// _flat) : ctrlSetBackgroundColor ne change que colorBackground, et le moteur fait clignoter un bouton qui a le focus entre
// cette couleur et colorBackground2 (constante de classe, sombre). Un bouton choisi (texte foncé sur l'accent) devenait
// du noir sur fond sombre : « MES ROUTES », « MA FICHE ». Retour de jeu 4.0.0.
class COMSPEC_RscButtonFlat: COMSPEC_RscButtonOverlay { colorDisabled[]={0.42,0.46,0.44,0.7}; colorBackgroundActive[]={1,1,1,0.10}; };
// Touches physiques de la coque : aucune surbrillance (la texture du téléphone suffit).
class COMSPEC_RscButtonInvisible: COMSPEC_RscButtonOverlay { colorBackgroundActive[]={0,0,0,0}; colorBackgroundDisabled[]={0,0,0,0}; colorDisabled[]={0,0,0,0}; colorShadow[]={0,0,0,0}; };
// Ligne et bouton de la Configuration ATHENA (fn_athenaConfig, menu Échap) : transparent, survol et focus clavier en vert Athena.
class COMSPEC_ATAK_AthenaCfgRow: COMSPEC_RscButtonOverlay { colorBackgroundActive[]={0.204,0.827,0.6,0.14}; colorFocused[]={0.204,0.827,0.6,0.10}; colorBackground2[]={0.204,0.827,0.6,0.10}; };
class COMSPEC_RscEdit: RscEdit { font=ATAK_FONT; colorText[]=ATAK_TEXT; colorBackground[]=ATAK_BG0; colorSelection[]=ATAK_GREEN_DIM; sizeEx="0.024 * safeZoneH"; };
class COMSPEC_RscEditMulti: COMSPEC_RscEdit { style=16; lineSpacing=1; };
class COMSPEC_RscCombo: RscCombo { font=ATAK_FONT; colorText[]=ATAK_TEXT; colorBackground[]=ATAK_BG0; colorSelect[]=ATAK_TEXT; colorSelectBackground[]=ATAK_GREEN_DIM; sizeEx="0.024 * safeZoneH"; };
class COMSPEC_RscListBox: RscListBox { font=ATAK_FONT; colorText[]=ATAK_TEXT; colorSelect[]=ATAK_TEXT; colorSelect2[]=ATAK_TEXT; colorSelectBackground[]=ATAK_GREEN_DIM; colorSelectBackground2[]=ATAK_GREEN_DIM; colorBackground[]={0,0,0,0}; sizeEx="0.024 * safeZoneH"; rowHeight="0.040 * safeZoneH"; };
// Tableau du BFT : niveaux de l'arbre (3 colonnes), indicatif, état, distance, azimut, âge. lnbSetColumnsPos les place selon
// l'orientation. La hauteur de ligne ne se règle pas par script : même valeur que COMSPEC_RscListBox.
class COMSPEC_RscListNBox: RscListNBox { font=ATAK_FONT; colorText[]=ATAK_TEXT; colorSelect[]=ATAK_TEXT; colorSelect2[]=ATAK_TEXT; colorSelectBackground[]=ATAK_GREEN_DIM; colorSelectBackground2[]=ATAK_GREEN_DIM; colorBackground[]={0,0,0,0}; sizeEx="0.024 * safeZoneH"; rowHeight="0.040 * safeZoneH"; columns[]={0,0.04,0.08,0.12,0.56,0.64,0.80,0.90}; drawSideArrows=0; idcLeft=-1; idcRight=-1; };
class COMSPEC_RscListBoxDense: COMSPEC_RscListBox { sizeEx="0.021 * safeZoneH"; rowHeight="0.027 * safeZoneH"; };
class COMSPEC_RscCheckbox: RscCheckBox {};
class COMSPEC_RscProgress: RscProgress { colorBar[]=ATAK_GREEN; colorFrame[]=ATAK_BORDER; };
class COMSPEC_RscControlsGroup: RscControlsGroup {
    class VScrollbar: VScrollbar { color[]=ATAK_GREEN; width=0.012; autoScrollEnabled=0; };
    class HScrollbar: HScrollbar { color[]=ATAK_GREEN; height=0; };
};
class COMSPEC_RscPicture: RscPicture { colorText[]=ATAK_GREEN; colorBackground[]={0,0,0,0}; style=2096; };
// Montre au poignet : blanc, étiré sur un rectangle déjà carré en pixels (fn_watch).
// La classe verte ci-dessus, sans nouveau commit, laisse l'écran en disque vert et le boîtier invisible.
class COMSPEC_RscWatchPic: RscPicture { colorText[]={1,1,1,1}; colorBackground[]={0,0,0,0}; style=48; };
class COMSPEC_RscMap: RscMapControl { colorBackground[]={0.045,0.055,0.048,1}; colorOutside[]={0.025,0.030,0.027,1}; showCountourInterval=1;
    // Textes de la carte Arma plus discrets (marqueurs, lieux, grille) : lisibles aussi sur le petit écran.
    sizeExLabel="0.018 * safeZoneH"; sizeExGrid="0.016 * safeZoneH"; sizeExUnits="0.024 * safeZoneH"; sizeExNames="0.026 * safeZoneH"; sizeExInfo="0.018 * safeZoneH"; sizeExLevel="0.016 * safeZoneH"; sizeEx="0.018 * safeZoneH"; };
// Carte du téléphone : textes Arma des repères (sizeExLabel), noms de lieux (sizeExNames) et numéros de grille masqués
// (taille minuscule) car redessinés par l'ATAK : fn_mapMarkerText et fn_mapPlaceText (style et calques au choix),
// fn_mapOnDraw pour la grille. Marqueurs de la mission non dessinés (showMarkers=0) : Arma les dessine à taille fixe
// en pixels, énormes sur le petit écran ; fn_mapGameMarkers les redessine à l'échelle de l'écran de l'ATAK.
// Noms de lieux : une seule source, les nôtres (fn_mapPlaceText, taille en pixels et anti-chevauchement de fn_mapLbl). La taille
// minuscule ne suffisait pas : le moteur écrivait encore certains noms à la taille de leur type de lieu (CfgLocationTypes),
// à côté des nôtres (« GRAVIA » doublé de « Gravia », « Athira » seul en orange). Densité de noms nulle (ptsPerSquareTxt) et
// couleur transparente, comme la carte de la montre.
class COMSPEC_RscMapAtak: COMSPEC_RscMap { showMarkers=0; sizeExLabel="0.0005"; sizeExGrid="0.0005"; sizeExNames="0.0005"; sizeExInfo="0.021 * safeZoneH"; sizeEx="0.021 * safeZoneH";
    ptsPerSquareTxt=100000; colorNames[]={0,0,0,0}; };
// Fond CLASSIQUE du téléphone (fn_mapStyle, fn_mapCtrl) : la carte d'origine d'Arma 3, celle de la touche M. La carte M
// (RscDisplayMainMap >> controlsBackground >> CA_Map) est un RscMapControl sans autre réglage de rendu : cette classe en
// hérite telle quelle, AUCUN attribut redéfini (couleurs, courbes et altitudes, texture satellite, noms de lieux, numéros
// de grille, marqueurs de la mission et leurs textes, tailles). Les mods qui retouchent RscMapControl (ACE...) s'y
// appliquent comme sur la carte M. fn_mapOnDraw ne redessine sur ce contrôle ni les noms de lieux, ni les numéros de
// grille, ni les marqueurs de la mission et leurs noms (le moteur les écrit déjà) : seulement les couches de l'ATAK.
// Avant 4.4.x : textes et marqueurs du moteur masqués puis redessinés au style tactique, la carte ne ressemblait pas à M.
class COMSPEC_RscMapAtakClassic: RscMapControl {};
// Carte de l'app JTAC (dessin à part, fn_jtacDraw) : textes Arma des repères et des lieux gardés.
// Fond HYBRIDE du téléphone (fn_mapStyle, fn_mapCtrl) : la texture satellite du terrain à toutes les
// échelles (maxSatelliteAlpha 1, jamais estompée), sans les aplats de forêt, rochers, courbes de niveau ni mer.
// HYBRIDE garde les routes et pistes par-dessus, comme la vue « Satellite + routes » d'une appli de cartes.
// Même principe que cTab (carte SAT / TOPO = deux contrôles carte, un seul affiché). COMSPEC_RscMapAtakSat n'est plus
// que la base d'HYBRIDE (son contrôle sert désormais au fond CLASSIQUE).
#define ATAK_NOCOL {0,0,0,0}
class COMSPEC_RscMapAtakSat: COMSPEC_RscMapAtak { maxSatelliteAlpha=1; alphaFadeStartScale=100; alphaFadeEndScale=100; showCountourInterval=0;
    colorSea[]=ATAK_NOCOL; colorForest[]=ATAK_NOCOL; colorForestBorder[]=ATAK_NOCOL; colorRocks[]=ATAK_NOCOL; colorRocksBorder[]=ATAK_NOCOL;
    colorLevels[]=ATAK_NOCOL; colorMainCountlines[]=ATAK_NOCOL; colorCountlines[]=ATAK_NOCOL; colorMainCountlinesWater[]=ATAK_NOCOL; colorCountlinesWater[]=ATAK_NOCOL;
    colorPowerLines[]=ATAK_NOCOL; colorRailWay[]=ATAK_NOCOL; colorTracks[]=ATAK_NOCOL; colorTracksFill[]=ATAK_NOCOL;
    colorRoads[]=ATAK_NOCOL; colorRoadsFill[]=ATAK_NOCOL; colorMainRoads[]=ATAK_NOCOL; colorMainRoadsFill[]=ATAK_NOCOL; };
class COMSPEC_RscMapAtakHyb: COMSPEC_RscMapAtakSat {
    colorTracks[]={0.84,0.80,0.66,0.55}; colorTracksFill[]={0.95,0.91,0.78,0.75};
    colorRoads[]={0.80,0.76,0.62,0.75}; colorRoadsFill[]={1,0.96,0.80,0.9};
    colorMainRoads[]={0.85,0.62,0.25,0.85}; colorMainRoadsFill[]={1,0.82,0.42,0.95}; };
// Fond ARDOISE du téléphone (fn_mapStyle, fn_mapCtrl) : bleu-gris sombre, mer très sombre, routes turquoise fines, courbes et
// altitudes sourdes, carroyage fin. Les couleurs d'une carte Arma ne se règlent que dans la classe du contrôle, d'où un
// quatrième contrôle. Le moteur n'expose aucune couleur pour les bâtiments ni pour le relief ombré : ils restent ceux d'Arma.
#define ATAK_SLATE_ICON(NAME,R,G,B,A) class NAME: NAME { color[]={R,G,B,A}; }
class COMSPEC_RscMapAtakSlate: COMSPEC_RscMapAtak {
    colorBackground[]={0.122,0.149,0.165,1}; colorOutside[]={0.035,0.045,0.055,1}; colorSea[]={0.020,0.047,0.074,1};
    colorForest[]={0.105,0.150,0.155,0.55}; colorForestBorder[]=ATAK_NOCOL; colorRocks[]={0.085,0.110,0.125,0.5}; colorRocksBorder[]=ATAK_NOCOL;
    colorLevels[]={0.17,0.31,0.30,0.9}; colorMainCountlines[]={0.21,0.40,0.38,0.55}; colorCountlines[]={0.21,0.40,0.38,0.32};
    colorMainCountlinesWater[]={0.14,0.25,0.31,0.45}; colorCountlinesWater[]={0.14,0.25,0.31,0.25};
    colorPowerLines[]={0.25,0.36,0.38,0.5}; colorRailWay[]={0.32,0.46,0.46,0.8};
    colorTracks[]={0.20,0.38,0.36,0.45}; colorTracksFill[]={0.20,0.38,0.36,0.85};
    colorRoads[]={0.16,0.32,0.30,0.9}; colorRoadsFill[]={0.24,0.48,0.45,1};
    colorMainRoads[]={0.18,0.36,0.34,1}; colorMainRoadsFill[]={0.30,0.61,0.57,1};
    colorGrid[]={0.20,0.42,0.40,0.45}; colorGridMap[]={0.20,0.42,0.40,0.45};
    colorTrails[]={0.20,0.38,0.36,0.3}; colorTrailsFill[]={0.20,0.38,0.36,0.5}; colorNames[]={0,0,0,0};
    class Legend: Legend { colorBackground[]={0.035,0.05,0.055,0.8}; color[]={0.72,0.88,0.84,1}; };
    ATAK_SLATE_ICON(Bush,0.20,0.33,0.32,0.4); ATAK_SLATE_ICON(Tree,0.20,0.33,0.32,0.4); ATAK_SLATE_ICON(SmallTree,0.20,0.33,0.32,0.4); ATAK_SLATE_ICON(Rock,0.36,0.44,0.46,0.7);
    ATAK_SLATE_ICON(Cross,0.55,0.68,0.66,0.8); ATAK_SLATE_ICON(Chapel,0.55,0.68,0.66,0.8); ATAK_SLATE_ICON(Shipwreck,0.55,0.68,0.66,0.8); ATAK_SLATE_ICON(Bunker,0.55,0.68,0.66,0.8);
    ATAK_SLATE_ICON(Fortress,0.55,0.68,0.66,0.8); ATAK_SLATE_ICON(Fountain,0.55,0.68,0.66,0.8); ATAK_SLATE_ICON(Ruin,0.55,0.68,0.66,0.8); ATAK_SLATE_ICON(Stack,0.55,0.68,0.66,0.8);
    ATAK_SLATE_ICON(Tourism,0.55,0.68,0.66,0.8); ATAK_SLATE_ICON(ViewTower,0.55,0.68,0.66,0.8);
};
// Carte de la montre Garmin (fn_watch, écran MAP) : zoom très rapproché, où Arma écrit les lieux-dits (« armée »,
// « military »…) à la taille prévue pour la grande carte, énormes sur le petit cadran. Aucun texte d'Arma ici :
// densité de textes nulle (ptsPerSquareTxt), noms transparents, tailles minuscules. Mon point et les alliés sont
// dessinés par fn_watchMapDraw.
class COMSPEC_RscMapWatch: COMSPEC_RscMapAtak { ptsPerSquareTxt=100000; colorNames[]={0,0,0,0}; colorLevels[]={0,0,0,0};
    sizeExUnits="0.0005"; sizeExInfo="0.0005"; sizeExLevel="0.0005"; sizeEx="0.0005"; };
// Carte JTAC : noms de lieux du moteur gardés (densité et couleur d'Arma remises, COMSPEC_RscMapAtak les retire).
class COMSPEC_RscMapJtac: COMSPEC_RscMapAtak { showMarkers=1; sizeExLabel="0.022 * safeZoneH"; sizeExNames="0.030 * safeZoneH"; ptsPerSquareTxt=20; colorNames[]={0.1,0.1,0.1,0.9}; };
// Carte du PC portable du poste TOC (fn_tocLaptop) : vraie carte d'Arma en grand, marqueurs de la mission compris,
// textes à la taille d'un écran d'ordinateur (le PC n'est pas un téléphone).
// PC du poste TOC : noms de lieux et textes des marqueurs plus petits (un gros libellé orange barrait la carte).
class COMSPEC_RscMapToc: COMSPEC_RscMap { showMarkers=1; sizeExLabel="0.016 * safeZoneH"; sizeExNames="0.019 * safeZoneH"; sizeExGrid="0.016 * safeZoneH"; sizeExInfo="0.020 * safeZoneH"; };
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
// Navigateur intégré d'Arma (CT_WEBBROWSER, Arma 3 2.14+) : diapositives Athena qu'Arma refuse en texture (image
// téléchargée hors d'un PBO). Contenu local seulement (data\web\slide.html, fn_slideWebShow), aucune adresse externe.
// Classe sans parent (comme la tablette Overwatch) : rien ne dépend d'une classe du jeu absente selon la version.
class COMSPEC_RscWebBrowser { type=106; idc=-1; style=0; x=0; y=0; w=0.3; h=0.3; colorBackground[]={0,0,0,1}; colorText[]={1,1,1,1}; allowExternalURL=0; url=""; };
// Même contrôle avec adresses externes permises : réservé à l'app Vidéo YouTube (fn_pageYoutube), lecteur
// youtube-nocookie.com/embed/ID lancé par le joueur. NON VÉRIFIÉ en jeu : le sens exact de allowExternalURL.
class COMSPEC_RscWebBrowserExt: COMSPEC_RscWebBrowser { allowExternalURL=1; };
class COMSPEC_RscSlider: RscXSliderH { color[]={0.36,0.78,0.42,0.9}; colorActive[]={0.46,0.88,0.52,1}; colorDisabled[]={0.4,0.45,0.42,0.6}; };
class COMSPEC_RscMapPanel: COMSPEC_RscStructuredText { colorBackground[]={0.025,0.030,0.027,0.78}; };
