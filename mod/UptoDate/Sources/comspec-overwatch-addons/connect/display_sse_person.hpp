// Terminal biométrique SEEK II — enrôlement personne (idd 9991)
//
// L'illustration de l'appareil est la vraie surface : les contrôles sont posés
// DANS l'écran du SEEK, pas sur toute la hauteur du châssis. L'écran ne tenant
// qu'une dizaine de lignes, le terminal fonctionne en PAGES, avec un accueil à
// tuiles — comme l'appareil réel.
//
// Habillage : celui du logiciel d'enrôlement militaire (BAT/SEEK) sous Windows
// embarqué — fond gris clair, texte sombre, barre de titre bleue, boutons
// biseautés, petites polices très contrastées (lisibles en plein soleil),
// onglets du flux d'enrôlement et barre de touches logicielles calquée sur les
// touches physiques du châssis (A1, A2, QUERY, SIGN).
//
// Géométrie mesurée sur seek_chassis.paa (2048 × 2048) :
//   appareil        x 0.117 – 0.883   y 0.031 – 0.969
//   écran (verre)   x 0.292 – 0.708   y 0.108 – 0.328   (rentré dans le verre, sous le cadre)
//   touches physiques sous l'écran (bloc gauche) :
//     objectif      x 0.355 – 0.395   y 0.395 – 0.432   → A2 iris
//     carrée        x 0.422 – 0.476   y 0.395 – 0.437   → QUERY
//     bas gauche    x 0.352 – 0.405   y 0.446 – 0.477   → A1 empreintes
//     bas droite    x 0.420 – 0.476   y 0.446 – 0.477   → SIGN
//   platine verte   x 0.398 – 0.594   y 0.703 – 0.867
//   clavier         x 0.211 – 0.781   y 0.516 – 0.609
// Si la texture change, ce sont les seules valeurs à reprendre.
//
// Disposition de l'écran (de haut en bas) :
//   barre de titre bleue  [Home] SEEK II · <PAGE>             BAT 87%  14:32
//   onglets du flux       SUJET · EMPR. · IRIS · VISAGE · REVUE · ENVOI
//   page courante
//   barre d'état          Hors liaison · Local DB · sync 14:02 | dossier | REC | éch.
//   touches logicielles   <<  A1 EMPR.  A2 IRIS  QUERY  SIGN  >>
//
// Pages (fn_sseTerminalPage) : 0 accueil · 1 sujet · 2 contexte · 3 empreintes
//   4 constat · 5 visage · 6 envoi (dossier) · 7 terrain · 8 iris · 9 revue (watchlist)
//   Les numéros 0 à 7 sont appelés de l'extérieur (ATAK, @COMSPEC_SSE) : ne pas les décaler.
//
// IDC :
//   9500 bandeau d'aide (accueil)      9513 état transmission
//   9501 nom      9502 prénom          9514 capture empreintes  9523 iris  9524 ADN
//   9503 alias    9504 âge             9515 cliché visage
//   9505 statut   9506 circonstances   9516 transmettre 9517 annuler
//   9507 nationalité  9508 langue      9518 code dossier
//   9509 signes distinctifs            9519 signature ATAK   9520 bloc signature
//   9510 affiliation                   9521 constat de terrain
//   9511 armement détecté              9522 synthèse des modalités (page revue)
//   9512 déclarations                  9525 lecture qualité visage   9526 batterie + heure
//   9527 requête d'identité (QUERY)
//   9530-9535 tuiles d'accueil   9540 titre de page   9541 <<   9542 >>   9543 Home
//   9544 rendre le dossier actif
//   9551-9556 libellés page sujet   9560-9565 libellés autres pages
//   9566 jauge de complétude (revue)   9567 cadre lecture visage   9568 carte signature
//   9545-9550 dessins des tuiles d'accueil
//   9574 silhouette  9575 viseur r2t  9576 gabarit  9577 infos cliché
//   9578 angle  9579 zoom -  9557 zoom +  (page visage)
//   9570-9573 touches A1 / A2 (objectif) / QUERY / SIGN (sous l’écran)
//   9580-9582 page terrain (record / listes)  9590-9594 DIG SITE GRAPH PREV MISS
//   9600 barre d'état (liaison, synchro, dossier, compteur)   9601 fond barre d'état
//   9610-9619 bouts de doigts (ordre FBI : 9610 pouce D … 9614 auriculaire D,
//             9615 pouce G … 9619 auriculaire G)   9620 dessin des mains
//   9621 consigne empreintes   9622 panneau empreintes (slap / roulés, NFIQ)
//   9623 cadre du viseur visage
//   9626 note iris   9627/9628 qualité OD/OG   9629 consigne iris
//   9630/9631 cadres OD/OG   9632/9633 images OD/OG   9634/9635 titres OD/OG
//   9636/9637 fonds jauges OD/OG   9638/9639 jauges OD/OG
//   9640 bandeau watchlist (fond)  9641 bandeau watchlist (texte)  9642 score / source
//   9650-9655 onglets SUJET EMPR. IRIS VISAGE REVUE ENVOI   9656 fond des onglets
//   9657 fond touches logicielles   9660-9663 touches logicielles A1 A2 QUERY SIGN
//   Tout libellé doit porter un IDC : sans cela il ne peut pas être masqué et se
//   superpose d'une page à l'autre.

// Cadrage : l'illustration (carrée) est affichée sans déformation — en unités
// d'interface, un carré fait w = h × 3/4 — et agrandie pour que l'écran et le
// clavier occupent toute la hauteur. Le bas de l'appareil (platine d'empreintes,
// sangles) sort sous le bord de l'écran du jeu : l'écran du SEEK est ~1,7 fois
// plus grand qu'avec l'appareil entier (764 × 404 px en 1920 × 1080).
#define SEEK_H      (1.70 * safezoneH)
#define SEEK_W      (SEEK_H * 3 / 4)
#define SEEK_X      (safezoneX + (safezoneW - SEEK_W) / 2)
#define SEEK_Y      (safezoneY - 0.035 * SEEK_H)

// Échelle des tailles internes (lignes, polices) par rapport à l'ancien cadrage
// (appareil entier sur 0.92 × safezoneH).
#define SEEK_K      1.65
#define SEEK_U(v)        ((v) * SEEK_K * safezoneH)
#define SEEK_TXT    (0.040 * SEEK_K)

// Illustration de l'appareil. Vide = châssis dessiné seul : le terminal reste
// utilisable sans aucun asset.
#define SEEK_CHASSIS_TEXTURE "\z\comspec_overwatch\addons\connect\img\device\seek_chassis.paa"

// --- Écran, en fractions de l'illustration (rentré dans le verre LCD) ---
#define SCR_X       (SEEK_X + 0.292 * SEEK_W)
#define SCR_Y       (SEEK_Y + 0.108 * SEEK_H)
#define SCR_W       (0.416 * SEEK_W)
#define SCR_H       (0.220 * SEEK_H)

// Grille interne.
#define SP          (0.004 * SEEK_W)
#define IN_X        (SCR_X + SP)
#define IN_W        (SCR_W - 2 * SP)
#define HALF_W      ((IN_W - SP) / 2)
#define IN_X2       (IN_X + HALF_W + SP)

// Bandes horizontales : titre, onglets, (page), barre d'état, touches logicielles.
#define BAR_H       SEEK_U(0.0210)
#define TAB_H       SEEK_U(0.0170)
#define TAB_Y       (SCR_Y + BAR_H)
#define KEY_H       SEEK_U(0.0190)
#define NAV_Y       (SCR_Y + SCR_H - KEY_H)
#define STAT_H      SEEK_U(0.0130)
#define STAT_Y      (NAV_Y - STAT_H)
#define BODY_Y      (TAB_Y + TAB_H + SEEK_U(0.002))
#define BODY_B      (STAT_Y - SEEK_U(0.002))

#define ROW_H       SEEK_U(0.0315)
#define LBL_H       SEEK_U(0.0158)
#define FIELD_H     (ROW_H - LBL_H)
#define ROW(n)      (BODY_Y + (n) * ROW_H)

// Typo : Tahoma (titres, boutons) et Roboto condensé (corps), petites et sombres.
#define SEEK_FONT   (0.024 * SEEK_K)
#define SEEK_FONT_S (0.020 * SEEK_K)
#define SEEK_FONT_XS (0.017 * SEEK_K)
#define SEEK_EDIT_EX (0.028 * SEEK_K)
#define BTN_H       SEEK_U(0.022)
#define FOOT_Y      (BODY_B - BTN_H)
#define SEC_Y       (FOOT_Y - BTN_H - SEEK_U(0.003))
#define MSG_H       SEEK_U(0.022)
#define MSG_Y       (SEC_Y - MSG_H - SEEK_U(0.002))
#define CASE_TOP    BODY_Y
#define CASE_EDIT_H SEEK_U(0.026)
#define SIG_Y       (CASE_TOP + LBL_H + CASE_EDIT_H + SEEK_U(0.003))
#define SIG_H       SEEK_U(0.032)
#define TX_W        (IN_W * 0.62)
#define CANCEL_W    (IN_W - TX_W - SP)

// CONTEXTE : signes distinctifs jusqu’à la barre d'état (plus de trou).
#define CTX_MARKS_Y (ROW(3) + LBL_H)
#define CTX_MARKS_H (BODY_B - CTX_MARKS_Y)

// Accueil : deux rangées de trois tuiles (pas d'onglets sur l'accueil).
#define TILE_W      ((IN_W - 2 * SP) / 3)
#define TILE_H      (TILE_W * 2 / 3)
#define TILE_X(c)   (IN_X + (c) * (TILE_W + SP))
#define TILE_Y(r)   (SCR_Y + BAR_H + SEEK_U(0.005) + (r) * (TILE_H + SEEK_U(0.005)))

// Onglets du flux d'enrôlement et touches logicielles.
#define TABW        ((IN_W - 5 * SP) / 6)
#define TAB_X(i)    (IN_X + (i) * (TABW + SP))
#define SK_W        ((IN_W - 5 * SP) / 6)
#define SK_X(i)     (IN_X + (i) * (SK_W + SP))

// Page VISAGE : viseur portrait 3:4 à gauche (w = h × 9/16 en unités d'interface),
// réglages et cliché à droite.
#define VF_Y        (BODY_Y + SEEK_U(0.002))
#define VF_H        ((BODY_B - SEEK_U(0.002)) - VF_Y)
#define VF_W        (VF_H * 9 / 16)
#define PH_X        (IN_X + VF_W + 3 * SP)
#define PH_W        (IN_W - VF_W - 3 * SP)
#define PH_AW       ((PH_W - 2 * SP) / 2)
#define PH_ZW       ((PH_W - PH_AW - 2 * SP) / 2)

// Page EMPREINTES : consigne en tête, deux mains (image 2:1) à gauche, panneau à droite.
#define PROMPT_H    SEEK_U(0.0160)
#define HANDS_X     IN_X
#define HANDS_Y     (BODY_Y + PROMPT_H + SEEK_U(0.003))
#define HANDS_H     SEEK_U(0.1240)
#define HANDS_W     (HANDS_H * 1.5)
#define FP_X        (HANDS_X + HANDS_W + 2 * SP)
#define FP_W        (IN_X + IN_W - FP_X)
#define FBOX_H      SEEK_U(0.0150)
#define FBOX_W      (FBOX_H * 3 / 4)
// Bout de doigt : centre en fractions du dessin des mains (seek_hands_ca, 1024 × 512).
#define FTIP(fx,fy) x = (HANDS_X + (fx) * HANDS_W - FBOX_W / 2); y = (HANDS_Y + (fy) * HANDS_H - FBOX_H / 2); w = FBOX_W; h = FBOX_H

// Page IRIS : deux cadres œil droit (à gauche, face au sujet) / œil gauche.
#define EYE_Y       (BODY_Y + PROMPT_H + SEEK_U(0.002))
#define EYE_H       (BODY_B - BTN_H - SEEK_U(0.003) - EYE_Y)
#define EYE_W       ((IN_W - SP) / 2)
#define EYE_X(i)    (IN_X + (i) * (EYE_W + SP))
#define EYEP_H      SEEK_U(0.0620)
#define EYEP_W      (EYEP_H * 1.5)
#define EYEP_Y      (EYE_Y + SEEK_U(0.015))
#define EYEQ_Y      (EYEP_Y + EYEP_H + SEEK_U(0.002))
#define EYEB_Y      (EYEQ_Y + SEEK_U(0.0165))

// Page REVUE : bandeau watchlist, puis synthèse à gauche et actions à droite.
#define BEWL_H      SEEK_U(0.0400)
#define RV_W        (IN_W * 0.64)
#define RV_X2       (IN_X + RV_W + SP)
#define RV_W2       (IN_W - RV_W - SP)

// Touches physiques de l'appareil (A1 / A2 / QUERY / SIGN), posées sur les vraies touches.
#define KEY_BOX(x0,y0,x1,y1) x = (SEEK_X + x0 * SEEK_W); y = (SEEK_Y + y0 * SEEK_H); w = ((x1 - x0) * SEEK_W); h = ((y1 - y0) * SEEK_H)

// Texte de l'écran SEEK : taille de base mise à l'échelle du cadrage, encre sombre.
class COMSPEC_SeekText: RscStructuredText {
    size = SEEK_TXT;
    shadow = 0;
    colorText[] = {0.07, 0.09, 0.11, 1};
    class Attributes {
        font = "RobotoCondensed";
        color = "#14181d";
        colorLink = "#0a246a";
        align = "left";
        shadow = 0;
    };
};

// Bouton biseauté Windows embarqué : face grise, ombre portée sombre en bas à
// droite, filet blanc à gauche ; enfoncé, il recouvre son ombre.
class COMSPEC_SeekBtn: COMSPEC_RscButton {
    font = "TahomaB";
    sizeEx = SEEK_FONT_S;
    shadow = 0;
    colorText[] = {0.06, 0.07, 0.08, 1};
    colorDisabled[] = {0.52, 0.52, 0.52, 1};
    colorBackground[] = {0.86, 0.85, 0.82, 1};
    colorBackgroundDisabled[] = {0.80, 0.79, 0.77, 1};
    colorBackgroundActive[] = {0.74, 0.82, 0.95, 1};
    colorFocused[] = {0.86, 0.85, 0.82, 1};
    colorShadow[] = {0.30, 0.31, 0.33, 1};
    colorBorder[] = {1, 1, 1, 1};
    borderSize = 0.002;
    offsetX = 0.0016;
    offsetY = 0.0022;
    offsetPressedX = 0.0016;
    offsetPressedY = 0.0022;
};
// Action principale : bleu marine, texte blanc.
class COMSPEC_SeekBtnPrimary: COMSPEC_SeekBtn {
    colorText[] = {1, 1, 1, 1};
    colorBackground[] = {0.06, 0.20, 0.52, 1};
    colorBackgroundActive[] = {0.14, 0.33, 0.72, 1};
    colorFocused[] = {0.06, 0.20, 0.52, 1};
    colorBorder[] = {0.45, 0.58, 0.85, 1};
};
// Abandon : rouge sombre.
class COMSPEC_SeekBtnDanger: COMSPEC_SeekBtn {
    colorText[] = {1, 1, 1, 1};
    colorBackground[] = {0.60, 0.12, 0.10, 1};
    colorBackgroundActive[] = {0.75, 0.20, 0.16, 1};
    colorFocused[] = {0.60, 0.12, 0.10, 1};
    colorBorder[] = {0.85, 0.50, 0.46, 1};
};
// Onglet : gris quand inactif, blanc quand actif (couleurs posées par fn_sseTerminalPage).
class COMSPEC_SeekTab: COMSPEC_SeekBtn {
    sizeEx = SEEK_FONT_XS;
    colorText[] = {0.28, 0.30, 0.33, 1};
    colorBackground[] = {0.74, 0.73, 0.70, 1};
    colorBackgroundActive[] = {0.92, 0.94, 0.98, 1};
    colorFocused[] = {1, 1, 1, 1};
    colorShadow[] = {0.45, 0.46, 0.48, 1};
    offsetX = 0;
    offsetY = 0.0012;
    offsetPressedX = 0;
    offsetPressedY = 0.0012;
};
// Bout de doigt (NFIQ) : pastille colorée par fn_ssePersonRefreshPanels.
class COMSPEC_SeekFinger: RscText {
    style = 2;
    shadow = 0;
    font = "TahomaB";
    sizeEx = SEEK_FONT_S;
    colorText[] = {0.10, 0.10, 0.10, 1};
    colorBackground[] = {0.70, 0.71, 0.72, 0.85};
    text = "-";
};
// Champ de saisie : blanc, encre noire, cadre sombre.
class COMSPEC_SeekEdit: RscEdit {
    colorBackground[] = {1, 1, 1, 1};
    colorText[] = {0.06, 0.07, 0.08, 1};
    colorSelection[] = {0.04, 0.14, 0.42, 0.55};
    colorDisabled[] = {0.45, 0.46, 0.48, 1};
    sizeEx = SEEK_EDIT_EX;
    font = "RobotoCondensed";
    shadow = 0;
    autocomplete = "";
};

class COMSPEC_SsePerson_Dialog {
    idd = 9991;
    movingEnable = 1;
    onLoad = "uiNamespace setVariable ['COMSPEC_SsePerson_Display', _this select 0]; [] call comspec_overwatch_connect_fnc_ssePersonDialogOnLoad;";
    onUnload = "uiNamespace setVariable ['COMSPEC_SsePerson_Display', displayNull]; ['stop'] call comspec_overwatch_connect_fnc_sseFaceViewfinder; if (uiNamespace getVariable ['COMSPEC_SsePerson_SuspendUnload', false]) then { uiNamespace setVariable ['COMSPEC_SsePerson_SuspendUnload', false]; } else { uiNamespace setVariable ['COMSPEC_SsePerson_Target', objNull]; uiNamespace setVariable ['COMSPEC_SsePerson_Samples', []]; uiNamespace setVariable ['COMSPEC_SsePerson_Signature', []]; uiNamespace setVariable ['COMSPEC_SsePerson_Query', []]; uiNamespace setVariable ['COMSPEC_SsePerson_IdentityCache', []]; ['reset'] call comspec_overwatch_connect_fnc_sseFaceViewfinder; };";

    class Controls {
        // ================= APPAREIL =================
        // Pas de fond plein-cadre : la texture a des bords transparents — un RscText
        // opaque dépassait l’appareil (bandeau noir hors châssis). Filet seulement
        // si la PAA manque (alpha très basse).
        class Chassis: RscText {
            idc = -1;
            x = SEEK_X; y = SEEK_Y; w = SEEK_W; h = SEEK_H;
            colorBackground[] = {0.043, 0.047, 0.051, 0};
        };
        class ChassisTexture: RscPicture {
            idc = -1;
            text = SEEK_CHASSIS_TEXTURE;
            x = SEEK_X; y = SEEK_Y; w = SEEK_W; h = SEEK_H;
            colorText[] = {1, 1, 1, 1};
        };

        // ================= ÉCRAN =================
        // Fond gris clair (Windows embarqué) ; la PAA ajoute un reflet discret.
        class Screen: RscText {
            idc = -1;
            x = SCR_X; y = SCR_Y; w = SCR_W; h = SCR_H;
            colorBackground[] = {0.84, 0.83, 0.81, 1};
        };
        class ScreenGlass: RscPicture {
            idc = -1;
            text = "\z\comspec_overwatch\addons\connect\img\device\seek_screen_bg_co.paa";
            x = SCR_X; y = SCR_Y; w = SCR_W; h = SCR_H;
            colorText[] = {1, 1, 1, 1};
        };
        // Barre de titre bleue.
        class ScreenBar: RscText {
            idc = -1;
            x = SCR_X; y = SCR_Y; w = SCR_W; h = BAR_H;
            colorBackground[] = {0.039, 0.141, 0.416, 1};
        };
        class ScreenBarShine: RscText {
            idc = -1;
            x = SCR_X; y = SCR_Y; w = SCR_W; h = (BAR_H * 0.45);
            colorBackground[] = {0.35, 0.50, 0.85, 0.22};
        };
        class BtnHome: RscButton {
            idc = 9543;
            text = "Home";
            x = (SCR_X + SP); y = (SCR_Y + SEEK_U(0.002)); w = (0.12 * SCR_W); h = (BAR_H - SEEK_U(0.004));
            shadow = 0;
            colorBackground[] = {0.18, 0.44, 0.20, 1};
            colorBackgroundActive[] = {0.26, 0.58, 0.28, 1};
            colorText[] = {1, 1, 1, 1};
            colorFocused[] = {0.18, 0.44, 0.20, 1};
            colorShadow[] = {0, 0, 0, 0};
            colorBorder[] = {0, 0, 0, 0};
            sizeEx = SEEK_FONT_S;
            font = "TahomaB";
            tooltip = "Écran d'accueil";
            action = "[0] call comspec_overwatch_connect_fnc_sseTerminalPage;";
        };
        class ScreenTitle: COMSPEC_SeekText {
            idc = 9540;
            text = "<t size='0.64' align='center' color='#f2fff8'>SEEK II</t>";
            x = (SCR_X + 0.14 * SCR_W); y = (SCR_Y + SEEK_U(0.001));
            w = (0.42 * SCR_W); h = (BAR_H - SEEK_U(0.002));
        };
        class StatusRight: COMSPEC_SeekText {
            idc = 9526;
            text = "<t size='0.5' align='right' color='#ffffff'>--:--</t>";
            x = (SCR_X + 0.56 * SCR_W); y = (SCR_Y + SEEK_U(0.0025));
            w = (0.43 * SCR_W); h = (BAR_H - SEEK_U(0.003));
        };

        // ================= ONGLETS DU FLUX D'ENRÔLEMENT =================
        class TabStrip: RscText {
            idc = 9656;
            x = SCR_X; y = TAB_Y; w = SCR_W; h = TAB_H;
            colorBackground[] = {0.62, 0.61, 0.59, 1};
        };
        class Tab0: COMSPEC_SeekTab {
            idc = 9650;
            text = "SUJET";
            x = TAB_X(0); y = (TAB_Y + SEEK_U(0.002)); w = TABW; h = (TAB_H - SEEK_U(0.002));
            tooltip = "Demographics : identité, contexte, constat";
            action = "[1] call comspec_overwatch_connect_fnc_sseTerminalPage;";
        };
        class Tab1: Tab0 { idc = 9651; text = "EMPR."; x = TAB_X(1); tooltip = "Fingers : dix doigts, NFIQ"; action = "[3] call comspec_overwatch_connect_fnc_sseTerminalPage;"; };
        class Tab2: Tab0 { idc = 9652; text = "IRIS"; x = TAB_X(2); tooltip = "Irises : œil droit, œil gauche"; action = "[8] call comspec_overwatch_connect_fnc_sseTerminalPage;"; };
        class Tab3: Tab0 { idc = 9653; text = "VISAGE"; x = TAB_X(3); tooltip = "Face : photographie du visage"; action = "[5] call comspec_overwatch_connect_fnc_sseTerminalPage;"; };
        class Tab4: Tab0 { idc = 9654; text = "REVUE"; x = TAB_X(4); tooltip = "Review : modalités, ADN, interrogation watchlist"; action = "[9] call comspec_overwatch_connect_fnc_sseTerminalPage;"; };
        class Tab5: Tab0 { idc = 9655; text = "ENVOI"; x = TAB_X(5); tooltip = "Submit : dossier, signature, transmission"; action = "[6] call comspec_overwatch_connect_fnc_sseTerminalPage;"; };

        // ================= PAGE 0 — ACCUEIL =================
        // Tuiles dessinées (icône + libellé gravés dans la PAA), bouton transparent par-dessus.
        class TileArt0: RscPicture {
            idc = 9545;
            text = "\z\comspec_overwatch\addons\connect\img\device\seek_tile_subject_ca.paa";
            x = TILE_X(0); y = TILE_Y(0); w = TILE_W; h = TILE_H;
            colorText[] = {1, 1, 1, 1};
        };
        class TileArt1: TileArt0 { idc = 9546; text = "\z\comspec_overwatch\addons\connect\img\device\seek_tile_context_ca.paa"; x = TILE_X(1); };
        class TileArt2: TileArt0 { idc = 9547; text = "\z\comspec_overwatch\addons\connect\img\device\seek_tile_bio_ca.paa"; x = TILE_X(2); };
        class TileArt3: TileArt0 { idc = 9548; text = "\z\comspec_overwatch\addons\connect\img\device\seek_tile_terrain_ca.paa"; y = TILE_Y(1); };
        class TileArt4: TileArt0 { idc = 9549; text = "\z\comspec_overwatch\addons\connect\img\device\seek_tile_photo_ca.paa"; x = TILE_X(1); y = TILE_Y(1); };
        class TileArt5: TileArt0 { idc = 9550; text = "\z\comspec_overwatch\addons\connect\img\device\seek_tile_dossier_ca.paa"; x = TILE_X(2); y = TILE_Y(1); };
        class Tile0: RscButton {
            idc = 9530;
            text = "";
            x = TILE_X(0); y = TILE_Y(0); w = TILE_W; h = TILE_H;
            colorBackground[] = {0, 0, 0, 0};
            colorBackgroundActive[] = {0.04, 0.14, 0.42, 0.14};
            colorBackgroundDisabled[] = {0, 0, 0, 0};
            colorFocused[] = {0, 0, 0, 0};
            colorShadow[] = {0, 0, 0, 0};
            colorBorder[] = {0, 0, 0, 0};
            colorText[] = {0.06, 0.07, 0.08, 1};
            offsetPressedX = 0;
            offsetPressedY = 0;
            sizeEx = SEEK_FONT;
            font = "TahomaB";
            tooltip = "Identité de la personne";
            action = "[1] call comspec_overwatch_connect_fnc_sseTerminalPage;";
        };
        class Tile1: Tile0 { idc = 9531; x = TILE_X(1); tooltip = "Statut, circonstances, affiliation"; action = "[2] call comspec_overwatch_connect_fnc_sseTerminalPage;"; };
        class Tile2: Tile0 { idc = 9532; x = TILE_X(2); tooltip = "Empreintes, iris, ADN, requête d’identité"; action = "[3] call comspec_overwatch_connect_fnc_sseTerminalPage;"; };
        class Tile3: Tile0 { idc = 9533; y = TILE_Y(1); tooltip = "Record lié, digital, site, graphe, preuves, mission"; action = "missionNamespace setVariable ['comspec_sse_uiScreen', 'terminal']; [7] call comspec_overwatch_connect_fnc_sseTerminalPage; ['terminal'] call comspec_sse_fnc_uiRefresh;"; };
        class Tile4: Tile0 { idc = 9534; x = TILE_X(1); y = TILE_Y(1); tooltip = "Photographie du visage"; action = "[5] call comspec_overwatch_connect_fnc_sseTerminalPage;"; };
        class Tile5: Tile0 { idc = 9535; x = TILE_X(2); y = TILE_Y(1); tooltip = "Classement, signature, transmission"; action = "[6] call comspec_overwatch_connect_fnc_sseTerminalPage;"; };

        class Hint: COMSPEC_SeekText {
            idc = 9500;
            text = "<t size='0.5' align='center' color='#33414c'>Prêt.</t>";
            x = IN_X; y = (STAT_Y - SEEK_U(0.019)); w = IN_W; h = SEEK_U(0.018);
        };

        // ================= PAGE 1 — SUJET (Demographics) =================
        class LabelLast: COMSPEC_SeekText {
            idc = 9551;
            text = "<t size='0.6' font='TahomaB' color='#33414c'>NOM</t>";
            x = IN_X; y = ROW(0); w = HALF_W; h = LBL_H;
        };
        class EditLast: COMSPEC_SeekEdit {
            idc = 9501;
            x = IN_X; y = (ROW(0) + LBL_H); w = HALF_W; h = FIELD_H;
        };
        class LabelFirst: LabelLast { idc = 9552; text = "<t size='0.6' font='TahomaB' color='#33414c'>PRÉNOM</t>"; x = IN_X2; };
        class EditFirst: EditLast { idc = 9502; x = IN_X2; };
        class LabelAlias: LabelLast { idc = 9553; text = "<t size='0.6' font='TahomaB' color='#33414c'>ALIAS</t>"; y = ROW(1); };
        class EditAlias: EditLast { idc = 9503; y = (ROW(1) + LBL_H); };
        class LabelAge: LabelLast { idc = 9554; text = "<t size='0.6' font='TahomaB' color='#33414c'>ÂGE</t>"; x = IN_X2; y = ROW(1); };
        class EditAge: EditLast { idc = 9504; x = IN_X2; y = (ROW(1) + LBL_H); };
        class LabelNat: LabelLast { idc = 9555; text = "<t size='0.6' font='TahomaB' color='#33414c'>NATIONALITÉ</t>"; y = ROW(2); };
        class EditNat: EditLast { idc = 9507; y = (ROW(2) + LBL_H); };
        class LabelLang: LabelLast { idc = 9556; text = "<t size='0.6' font='TahomaB' color='#33414c'>LANGUE</t>"; x = IN_X2; y = ROW(2); };
        class EditLang: EditLast { idc = 9508; x = IN_X2; y = (ROW(2) + LBL_H); };
        class TextWeapons: COMSPEC_SeekText {
            idc = 9511;
            text = "<t size='0.56' color='#33414c'>Aucun inventaire détecté.</t>";
            x = IN_X; y = (ROW(3) + SEEK_U(0.002)); w = IN_W; h = (BODY_B - ROW(3) - SEEK_U(0.002));
            colorBackground[] = {0.97, 0.97, 0.96, 1};
        };

        // ================= PAGE 2 — CONTEXTE (Encounter) =================
        // Une ligne par champ, pleine largeur : listes et saisie lisibles, plus de trou sous le formulaire.
        class LabelStatus: LabelLast {
            idc = 9560;
            text = "<t size='0.6' font='TahomaB' color='#33414c'>STATUT</t>";
            w = IN_W;
        };
        class ComboStatus: RscCombo {
            idc = 9505;
            x = IN_X; y = (ROW(0) + LBL_H); w = IN_W; h = FIELD_H;
            colorBackground[] = {1, 1, 1, 1};
            colorText[] = {0.06, 0.07, 0.08, 1};
            colorSelect[] = {1, 1, 1, 1};
            colorSelectBackground[] = {0.04, 0.14, 0.42, 1};
            colorDisabled[] = {0.45, 0.46, 0.48, 1};
            sizeEx = SEEK_EDIT_EX;
            font = "RobotoCondensed";
            wholeHeight = (0.24 * SEEK_K);
        };
        class LabelCirc: LabelLast {
            idc = 9561;
            text = "<t size='0.6' font='TahomaB' color='#33414c'>CIRCONSTANCES</t>";
            y = ROW(1);
            w = IN_W;
        };
        class ComboCirc: ComboStatus { idc = 9506; y = (ROW(1) + LBL_H); };
        class LabelAffil: LabelLast {
            idc = 9562;
            text = "<t size='0.6' font='TahomaB' color='#33414c'>AFFILIATION ESTIMÉE</t>";
            y = ROW(2);
            w = IN_W;
        };
        class EditAffil: EditLast { idc = 9510; y = (ROW(2) + LBL_H); w = IN_W; };
        class LabelMarks: LabelLast {
            idc = 9563;
            text = "<t size='0.6' font='TahomaB' color='#33414c'>SIGNES DISTINCTIFS</t>";
            y = ROW(3);
            w = IN_W;
        };
        class EditMarks: EditLast {
            idc = 9509;
            style = 16;
            y = CTX_MARKS_Y;
            w = IN_W;
            h = CTX_MARKS_H;
        };

        // ================= PAGE 3 — EMPREINTES (Fingers) =================
        // Deux mains vues de dessus (posées sur la platine), un bout de doigt par
        // doigt : gris = non capturé, bleu = doigt attendu, sinon NFIQ 1–5 coloré.
        class FingerPrompt: COMSPEC_SeekText {
            idc = 9621;
            text = "";
            x = IN_X; y = BODY_Y; w = IN_W; h = PROMPT_H;
            colorBackground[] = {1, 0.97, 0.78, 1};
        };
        class Hands: RscPicture {
            idc = 9620;
            text = "\z\comspec_overwatch\addons\connect\img\device\seek_hands_ca.paa";
            x = HANDS_X; y = HANDS_Y; w = HANDS_W; h = HANDS_H;
            colorText[] = {1, 1, 1, 1};
        };
        // Ordre FBI : 1 pouce D, 2 index D, 3 majeur D, 4 annulaire D, 5 auriculaire D, 6–10 main gauche.
        class FingerR1: COMSPEC_SeekFinger { idc = 9610; FTIP(0.544,0.470); };
        class FingerR2: COMSPEC_SeekFinger { idc = 9611; FTIP(0.660,0.242); };
        class FingerR3: COMSPEC_SeekFinger { idc = 9612; FTIP(0.725,0.182); };
        class FingerR4: COMSPEC_SeekFinger { idc = 9613; FTIP(0.791,0.230); };
        class FingerR5: COMSPEC_SeekFinger { idc = 9614; FTIP(0.854,0.351); };
        class FingerL1: COMSPEC_SeekFinger { idc = 9615; FTIP(0.456,0.470); };
        class FingerL2: COMSPEC_SeekFinger { idc = 9616; FTIP(0.340,0.242); };
        class FingerL3: COMSPEC_SeekFinger { idc = 9617; FTIP(0.275,0.182); };
        class FingerL4: COMSPEC_SeekFinger { idc = 9618; FTIP(0.209,0.230); };
        class FingerL5: COMSPEC_SeekFinger { idc = 9619; FTIP(0.146,0.351); };
        class FingerPanel: COMSPEC_SeekText {
            idc = 9622;
            text = "";
            x = FP_X; y = HANDS_Y; w = FP_W; h = (BODY_B - BTN_H - SEEK_U(0.004) - HANDS_Y);
            colorBackground[] = {0.97, 0.97, 0.96, 1};
        };
        class BtnBio: COMSPEC_SeekBtnPrimary {
            idc = 9514;
            text = "CAPTURER  (A1)";
            x = FP_X; y = (BODY_B - BTN_H); w = FP_W; h = BTN_H;
            tooltip = "Slap 4-4-2 puis roulés — capteur d'empreintes du SEEK";
            action = "['empreintes'] call comspec_overwatch_connect_fnc_sseBiometricSample;";
        };

        // ================= PAGE 8 — IRIS (Irises) =================
        class IrisPrompt: FingerPrompt { idc = 9629; };
        class EyeBoxR: RscText {
            idc = 9630;
            x = EYE_X(0); y = EYE_Y; w = EYE_W; h = EYE_H;
            colorBackground[] = {0.97, 0.97, 0.96, 1};
        };
        class EyeBoxL: EyeBoxR { idc = 9631; x = EYE_X(1); };
        class EyePicR: RscPicture {
            idc = 9632;
            text = "\z\comspec_overwatch\addons\connect\img\device\seek_eye_ca.paa";
            x = (EYE_X(0) + (EYE_W - EYEP_W) / 2); y = EYEP_Y; w = EYEP_W; h = EYEP_H;
            colorText[] = {1, 1, 1, 0.35};
        };
        class EyePicL: EyePicR { idc = 9633; x = (EYE_X(1) + (EYE_W - EYEP_W) / 2); };
        class EyeTitleR: COMSPEC_SeekText {
            idc = 9634;
            text = "<t size='0.44' font='TahomaB' color='#14181d'>ŒIL DROIT (OD)</t>";
            x = (EYE_X(0) + SP); y = (EYE_Y + SEEK_U(0.001)); w = (EYE_W - 2 * SP); h = SEEK_U(0.014);
        };
        class EyeTitleL: EyeTitleR { idc = 9635; text = "<t size='0.44' font='TahomaB' color='#14181d'>ŒIL GAUCHE (OG)</t>"; x = (EYE_X(1) + SP); };
        class EyeQualR: COMSPEC_SeekText {
            idc = 9627;
            text = "";
            x = (EYE_X(0) + SP); y = EYEQ_Y; w = (EYE_W - 2 * SP); h = SEEK_U(0.015);
        };
        class EyeQualL: EyeQualR { idc = 9628; x = (EYE_X(1) + SP); };
        class EyeBarBgR: RscText {
            idc = 9636;
            x = (EYE_X(0) + 2 * SP); y = EYEB_Y; w = (EYE_W - 4 * SP); h = SEEK_U(0.008);
            colorBackground[] = {0.70, 0.70, 0.68, 1};
        };
        class EyeBarBgL: EyeBarBgR { idc = 9637; x = (EYE_X(1) + 2 * SP); };
        class EyeBarR: EyeBarBgR {
            idc = 9638;
            w = 0.001;
            colorBackground[] = {0.12, 0.55, 0.22, 1};
        };
        class EyeBarL: EyeBarR { idc = 9639; x = (EYE_X(1) + 2 * SP); };
        class IrisNote: COMSPEC_SeekText {
            idc = 9626;
            text = "";
            x = IN_X; y = (BODY_B - BTN_H); w = (IN_W * 0.58); h = BTN_H;
        };
        class BtnIris: COMSPEC_SeekBtnPrimary {
            idc = 9523;
            text = "CAPTURER  (A2)";
            x = (IN_X + IN_W * 0.58 + SP); y = (BODY_B - BTN_H); w = (IN_W * 0.42 - SP); h = BTN_H;
            tooltip = "Capture des deux yeux par l'objectif du SEEK";
            action = "['iris'] call comspec_overwatch_connect_fnc_sseBiometricSample;";
        };

        // ================= PAGE 9 — REVUE (Review / watchlist) =================
        // Bandeau type BEWL : NO MATCH vert, MATCH rouge (tier + conduite à tenir).
        class BewlBanner: RscText {
            idc = 9640;
            x = IN_X; y = BODY_Y; w = IN_W; h = BEWL_H;
            colorBackground[] = {0.55, 0.56, 0.58, 1};
        };
        class BewlText: COMSPEC_SeekText {
            idc = 9641;
            text = "";
            x = (IN_X + SP); y = (BODY_Y + SEEK_U(0.001)); w = (IN_W - 2 * SP); h = (BEWL_H - SEEK_U(0.002));
        };
        class BewlDetail: COMSPEC_SeekText {
            idc = 9642;
            text = "";
            x = IN_X; y = (BODY_Y + BEWL_H + SEEK_U(0.001)); w = IN_W; h = SEEK_U(0.015);
        };
        class Platen: RscText {
            idc = 9566;
            x = IN_X; y = (BODY_Y + BEWL_H + SEEK_U(0.0175)); w = RV_W; h = SEEK_U(0.006);
            colorBackground[] = {0.12, 0.55, 0.22, 1};
        };
        class TextBiometrics: COMSPEC_SeekText {
            idc = 9522;
            text = "<t size='0.5' color='#33414c'>Aucun relevé.</t>";
            x = IN_X; y = (BODY_Y + BEWL_H + SEEK_U(0.025)); w = RV_W; h = (BODY_B - (BODY_Y + BEWL_H + SEEK_U(0.025)));
            colorBackground[] = {0.97, 0.97, 0.96, 1};
        };
        class BtnQuery: COMSPEC_SeekBtnPrimary {
            idc = 9527;
            text = "QUERY";
            x = RV_X2; y = (BODY_Y + BEWL_H + SEEK_U(0.0175)); w = RV_W2; h = SEEK_U(0.030);
            sizeEx = SEEK_FONT;
            tooltip = "Interroger la watchlist (locale hors liaison)";
            action = "[] call comspec_overwatch_connect_fnc_sseIdentityQuery;";
        };
        class BtnDna: COMSPEC_SeekBtn {
            idc = 9524;
            text = "ADN (kit)";
            x = RV_X2; y = (BODY_Y + BEWL_H + SEEK_U(0.052)); w = RV_W2; h = BTN_H;
            tooltip = "Prélèvement ADN (écouvillon du coffret)";
            action = "['adn'] call comspec_overwatch_connect_fnc_sseBiometricSample;";
        };

        // ================= PAGE 4 — CONSTAT =================
        class TextMedical: COMSPEC_SeekText {
            idc = 9521;
            text = "<t size='0.56' color='#33414c'>Aucun constat.</t>";
            x = IN_X; y = ROW(0); w = IN_W; h = (2.6 * ROW_H);
            colorBackground[] = {0.97, 0.97, 0.96, 1};
        };
        class LabelStmt: LabelLast { idc = 9564; text = "<t size='0.6' font='TahomaB' color='#33414c'>DÉCLARATIONS</t>"; y = ROW(2.75); w = IN_W; };
        class EditStmt: EditLast { idc = 9512; y = (ROW(2.75) + LBL_H); w = IN_W; h = (BODY_B - (ROW(2.75) + LBL_H)); };

        // ================= PAGE 5 — VISAGE (Face) =================
        // Fenêtre de capture : viseur (caméra r2t sur la tête du sujet) dans un cadre
        // sombre, gabarit par-dessus. Sans sujet ou caméra : silhouette. Après le
        // cliché, l'image reste figée.
        class FaceFrame: RscText {
            idc = 9623;
            x = (IN_X - 0.5 * SP); y = (VF_Y - SEEK_U(0.002)); w = (VF_W + SP); h = (VF_H + SEEK_U(0.004));
            colorBackground[] = {0.16, 0.17, 0.19, 1};
        };
        class FaceEmpty: RscPicture {
            idc = 9574;
            text = "\z\comspec_overwatch\addons\connect\img\device\seek_face_empty_ca.paa";
            x = IN_X; y = VF_Y; w = VF_W; h = VF_H;
            colorText[] = {1, 1, 1, 1};
        };
        class FaceFeed: FaceEmpty {
            idc = 9575;
            text = "#(argb,512,512,1)r2t(comspecseekface,0.75)";
        };
        class FaceGuide: FaceEmpty {
            idc = 9576;
            text = "\z\comspec_overwatch\addons\connect\img\device\seek_face_guide_ca.paa";
            colorText[] = {1, 1, 1, 0.85};
        };
        class FaceInfo: COMSPEC_SeekText {
            idc = 9577;
            text = "<t size='0.5' color='#33414c'>Aucun sujet.</t>";
            x = PH_X; y = VF_Y; w = PH_W; h = SEEK_U(0.070);
            colorBackground[] = {0.97, 0.97, 0.96, 1};
        };
        class BtnFaceAngle: COMSPEC_SeekBtn {
            idc = 9578;
            text = "FACE";
            x = PH_X; y = (VF_Y + SEEK_U(0.073)); w = PH_AW; h = BTN_H;
            tooltip = "Angle : face, trois quarts, profil";
            action = "['angle'] call comspec_overwatch_connect_fnc_sseFaceViewfinder;";
        };
        class BtnFaceZoomOut: BtnFaceAngle {
            idc = 9579;
            text = "-";
            x = (PH_X + PH_AW + SP); w = PH_ZW;
            tooltip = "Élargir le cadre";
            action = "['zoom', -1] call comspec_overwatch_connect_fnc_sseFaceViewfinder;";
        };
        class BtnFaceZoomIn: BtnFaceAngle {
            idc = 9557;
            text = "+";
            x = (PH_X + PH_AW + PH_ZW + 2 * SP); w = PH_ZW;
            tooltip = "Resserrer sur le visage";
            action = "['zoom', 1] call comspec_overwatch_connect_fnc_sseFaceViewfinder;";
        };
        class BtnPhoto: COMSPEC_SeekBtnPrimary {
            idc = 9515;
            text = "PRENDRE LE CLICHÉ";
            x = PH_X; y = (VF_Y + SEEK_U(0.099)); w = PH_W; h = SEEK_U(0.026);
            sizeEx = SEEK_FONT;
            action = "[] call comspec_overwatch_connect_fnc_sseCaptureFacePhoto;";
        };
        class LcdFrame: RscText {
            idc = 9567;
            x = PH_X; y = (BODY_B - SEEK_U(0.019)); w = PH_W; h = SEEK_U(0.018);
            colorBackground[] = {0.16, 0.17, 0.19, 1};
        };
        class TextLcd: COMSPEC_SeekText {
            idc = 9525;
            text = "<t size='0.46' color='#ffffff' align='center'>PRÊT</t>";
            x = PH_X; y = (BODY_B - SEEK_U(0.0185)); w = PH_W; h = SEEK_U(0.017);
        };

        // ================= PAGE 6 — ENVOI (Submit / dossier) =================
        // Haut : code + état de signature. Bas : actions collées à la barre d'état.
        class LabelCase: LabelLast {
            idc = 9565;
            text = "<t size='0.6' font='TahomaB' color='#33414c'>CODE DOSSIER</t>";
            x = IN_X; y = CASE_TOP; w = IN_W; h = LBL_H;
        };
        class EditCase: EditLast {
            idc = 9518;
            x = IN_X; y = (CASE_TOP + LBL_H); w = IN_W; h = CASE_EDIT_H;
            font = "TahomaB";
            sizeEx = (0.026 * SEEK_K);
        };
        class SigCard: RscText {
            idc = 9568;
            x = IN_X; y = SIG_Y; w = IN_W; h = SIG_H;
            colorBackground[] = {0.97, 0.97, 0.96, 1};
        };
        class TextSignature: COMSPEC_SeekText {
            idc = 9520;
            text = "<t size='0.56' color='#9a5b00'>Non signé</t>";
            x = (IN_X + 0.006 * SEEK_W); y = (SIG_Y + SEEK_U(0.002));
            w = (IN_W - 0.012 * SEEK_W); h = (SIG_H - SEEK_U(0.003));
        };
        class StatusText: COMSPEC_SeekText {
            idc = 9513;
            text = "";
            x = IN_X; y = MSG_Y; w = IN_W; h = MSG_H;
        };
        class BtnCaseActive: COMSPEC_SeekBtn {
            idc = 9544;
            text = "DOSSIER ACTIF";
            x = IN_X; y = SEC_Y; w = HALF_W; h = BTN_H;
            tooltip = "Rend ce dossier actif pour l’élément — les fiches suivantes y seront classées.";
            action = "private _d = uiNamespace getVariable ['COMSPEC_SsePerson_Display', displayNull]; ['set', ctrlText (_d displayCtrl 9518)] call comspec_overwatch_connect_fnc_sseActiveCase; [] call comspec_overwatch_connect_fnc_ssePersonRefreshPanels;";
        };
        class BtnSign: COMSPEC_SeekBtn {
            idc = 9519;
            text = "SIGNER";
            x = IN_X2; y = SEC_Y; w = HALF_W; h = BTN_H;
            tooltip = "Signer la fiche avec l’indicatif ATAK de l’opérateur.";
            action = "[] call comspec_overwatch_connect_fnc_sseSignAtak;";
        };
        class BtnSave: COMSPEC_SeekBtnPrimary {
            idc = 9516;
            text = "TRANSMETTRE";
            x = IN_X; y = FOOT_Y; w = TX_W; h = BTN_H;
            action = "[] call comspec_overwatch_connect_fnc_ssePersonDialogSubmit;";
        };
        class BtnClose: COMSPEC_SeekBtnDanger {
            idc = 9517;
            text = "ANNULER";
            x = (IN_X + TX_W + SP); y = FOOT_Y; w = CANCEL_W; h = BTN_H;
            action = "private _d = uiNamespace getVariable ['COMSPEC_SsePerson_Display', displayNull]; if (!isNull _d) then { _d closeDisplay 2; } else { closeDialog 0; };";
        };

        // ================= PAGE 7 — TERRAIN (ancien hub vert) =================
        class TerrainBody: COMSPEC_SeekText {
            idc = 9580;
            text = "<t size='0.5' color='#33414c'>Record terrain.</t>";
            x = IN_X; y = ROW(0); w = IN_W; h = (1.85 * ROW_H);
            colorBackground[] = {0.97, 0.97, 0.96, 1};
        };
        class TerrainList: RscListBox {
            idc = 9581;
            x = IN_X; y = (ROW(2) - SEEK_U(0.002)); w = IN_W; h = (BODY_B - BTN_H - SEEK_U(0.004) - (ROW(2) - SEEK_U(0.002)));
            colorBackground[] = {1, 1, 1, 1};
            colorText[] = {0.06, 0.07, 0.08, 1};
            colorSelect[] = {1, 1, 1, 1};
            colorSelect2[] = {1, 1, 1, 1};
            colorSelectBackground[] = {0.04, 0.14, 0.42, 1};
            colorSelectBackground2[] = {0.04, 0.14, 0.42, 1};
            colorShadow[] = {0, 0, 0, 0};
            shadow = 0;
            font = "RobotoCondensed";
            sizeEx = (0.022 * SEEK_K);
        };
        class TerrainDetail: COMSPEC_SeekText {
            idc = 9582;
            text = "";
            x = IN_X; y = (BODY_B - SEEK_U(0.001)); w = IN_W; h = 0.001;
        };
        class TerrainDig: COMSPEC_SeekBtn {
            idc = 9590;
            text = "DIG";
            x = IN_X; y = (BODY_B - BTN_H); w = ((IN_W - 4 * SP) / 5); h = BTN_H;
            tooltip = "Exploitation numérique";
            action = "['digital'] call comspec_sse_fnc_uiOpenScreen;";
        };
        class TerrainSite: TerrainDig {
            idc = 9591; text = "SITE";
            x = (IN_X + 1 * (((IN_W - 4 * SP) / 5) + SP));
            tooltip = "Exploitation de site";
            action = "['site'] call comspec_sse_fnc_uiOpenScreen;";
        };
        class TerrainGraph: TerrainDig {
            idc = 9592; text = "GRAPH";
            x = (IN_X + 2 * (((IN_W - 4 * SP) / 5) + SP));
            tooltip = "Graphe de renseignement";
            action = "['graph'] call comspec_sse_fnc_uiOpenScreen;";
        };
        class TerrainEvid: TerrainDig {
            idc = 9593; text = "PREV";
            x = (IN_X + 3 * (((IN_W - 4 * SP) / 5) + SP));
            tooltip = "Preuves et scellés";
            action = "['evidence'] call comspec_sse_fnc_uiOpenScreen;";
        };
        class TerrainMiss: TerrainDig {
            idc = 9594; text = "MISS";
            x = (IN_X + 4 * (((IN_W - 4 * SP) / 5) + SP));
            tooltip = "Fusion mission";
            action = "['mission'] call comspec_sse_fnc_uiOpenScreen;";
        };

        // ================= BARRE D'ÉTAT =================
        class StatusStrip: RscText {
            idc = 9601;
            x = SCR_X; y = STAT_Y; w = SCR_W; h = STAT_H;
            colorBackground[] = {0.93, 0.92, 0.90, 1};
        };
        class StatusLine: COMSPEC_SeekText {
            idc = 9600;
            text = "";
            x = IN_X; y = (STAT_Y + SEEK_U(0.0005)); w = IN_W; h = (STAT_H - SEEK_U(0.001));
        };

        // ================= TOUCHES LOGICIELLES =================
        // Même ordre que les touches physiques : A1 empreintes, A2 iris, QUERY, SIGN.
        class SoftKeyBar: RscText {
            idc = 9657;
            x = SCR_X; y = NAV_Y; w = SCR_W; h = KEY_H;
            colorBackground[] = {0.20, 0.21, 0.23, 1};
        };
        class BtnPrev: COMSPEC_SeekBtn {
            idc = 9541;
            text = "<<";
            x = SK_X(0); y = (NAV_Y + SEEK_U(0.002)); w = SK_W; h = (KEY_H - SEEK_U(0.0045));
            sizeEx = SEEK_FONT_XS;
            tooltip = "Étape précédente";
            action = "[-1, true] call comspec_overwatch_connect_fnc_sseTerminalPage;";
        };
        class SoftA1: BtnPrev {
            idc = 9660;
            text = "A1 EMPR.";
            x = SK_X(1);
            tooltip = "A1 : relevé d’empreintes";
            action = "['empreintes'] call comspec_overwatch_connect_fnc_sseBiometricSample;";
        };
        class SoftA2: BtnPrev {
            idc = 9661;
            text = "A2 IRIS";
            x = SK_X(2);
            tooltip = "A2 : relevé iris";
            action = "['iris'] call comspec_overwatch_connect_fnc_sseBiometricSample;";
        };
        class SoftQuery: BtnPrev {
            idc = 9662;
            text = "QUERY";
            x = SK_X(3);
            tooltip = "Interroger la watchlist (affiche la page Revue)";
            action = "[9] call comspec_overwatch_connect_fnc_sseTerminalPage; [] call comspec_overwatch_connect_fnc_sseIdentityQuery;";
        };
        class SoftSign: BtnPrev {
            idc = 9663;
            text = "SIGN";
            x = SK_X(4);
            tooltip = "Signer par l’ATAK";
            action = "[] call comspec_overwatch_connect_fnc_sseSignAtak;";
        };
        class BtnNext: BtnPrev {
            idc = 9542;
            text = ">>";
            x = SK_X(5);
            tooltip = "Étape suivante";
            action = "[1, true] call comspec_overwatch_connect_fnc_sseTerminalPage;";
        };

        // ================= TOUCHES PHYSIQUES (bloc gauche sous l'écran) =================
        // Boutons transparents sur les touches dessinées : survol éclairé, libellé discret.
        class KeyA1: COMSPEC_RscButton {
            idc = 9570;
            text = "A1";
            KEY_BOX(0.352,0.446,0.405,0.477);
            sizeEx = (0.018 * SEEK_K);
            colorText[] = {0.80, 0.92, 0.88, 0.55};
            colorBackground[] = {0, 0, 0, 0};
            colorBackgroundActive[] = {0.55, 1, 0.82, 0.20};
            colorFocused[] = {0, 0, 0, 0};
            colorShadow[] = {0, 0, 0, 0};
            colorBorder[] = {0, 0, 0, 0};
            tooltip = "A1 : relevé d’empreintes";
            action = "['empreintes'] call comspec_overwatch_connect_fnc_sseBiometricSample;";
        };
        class KeyA2: KeyA1 {
            idc = 9571;
            text = "";
            KEY_BOX(0.355,0.395,0.395,0.432);
            tooltip = "Objectif — A2 : relevé iris";
            action = "['iris'] call comspec_overwatch_connect_fnc_sseBiometricSample;";
        };
        class KeyQuery: KeyA1 {
            idc = 9572;
            text = "QUERY";
            KEY_BOX(0.422,0.395,0.476,0.437);
            tooltip = "Requête d’identité (watchlist)";
            action = "[9] call comspec_overwatch_connect_fnc_sseTerminalPage; [] call comspec_overwatch_connect_fnc_sseIdentityQuery;";
        };
        class KeySign: KeyA1 {
            idc = 9573;
            text = "SIGN";
            KEY_BOX(0.420,0.446,0.476,0.477);
            tooltip = "Signer par l’ATAK";
            action = "[] call comspec_overwatch_connect_fnc_sseSignAtak;";
        };
    };
};
