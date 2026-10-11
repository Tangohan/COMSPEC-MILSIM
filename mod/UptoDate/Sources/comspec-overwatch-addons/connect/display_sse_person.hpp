// Terminal biométrique SEEK — enrôlement personne (idd 9991)
//
// L'illustration de l'appareil est la vraie surface : les contrôles sont posés
// DANS l'écran du SEEK, pas sur toute la hauteur du châssis. L'écran ne tenant
// qu'une dizaine de lignes, le terminal fonctionne en PAGES, avec un accueil à
// tuiles — comme l'appareil réel.
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
// IDC :
//   9500 bandeau d'état bas            9513 état transmission
//   9501 nom      9502 prénom          9514 empreintes  9523 iris  9524 ADN
//   9503 alias    9504 âge             9515 cliché visage
//   9505 statut   9506 circonstances   9516 transmettre 9517 annuler
//   9507 nationalité  9508 langue      9518 code dossier
//   9509 signes distinctifs            9519 signature ATAK   9520 bloc signature
//   9510 affiliation                   9521 constat de terrain
//   9511 armement détecté              9522 analyse biométrique
//   9512 déclarations                  9525 bandeau LCD      9526 barre d'état
//   9527 requête d'identité
//   9530-9535 tuiles d'accueil   9540 titre de page   9541 ◄   9542 ►   9543 accueil
//   9544 rendre le dossier actif
//   9551-9556 libellés page sujet   9560-9565 libellés autres pages
//   9566 platine   9567 cadre LCD   9568 carte signature (page dossier)
//   9545-9550 dessins des tuiles d'accueil
//   9574 silhouette  9575 viseur r2t  9576 gabarit  9577 infos cliché
//   9578 angle  9579 zoom -  9557 zoom +  (page photo)
//   9570-9573 touches A1 / A2 (objectif) / QUERY / SIGN (sous l’écran)
//   9580-9582 page terrain (record / listes)  9590-9594 DIG SITE GRAPH PREV MISS
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

// Grille interne : barre d'état en haut, navigation en bas, lignes utiles entre.
#define SP          (0.004 * SEEK_W)
#define IN_X        (SCR_X + SP)
#define IN_W        (SCR_W - 2 * SP)
#define HALF_W      ((IN_W - SP) / 2)
#define IN_X2       (IN_X + HALF_W + SP)

#define BAR_H       SEEK_U(0.0210)
#define ROW_H       SEEK_U(0.0315)
#define LBL_H       SEEK_U(0.0158)
#define FIELD_H     (ROW_H - LBL_H)
#define ROW(n)      (SCR_Y + BAR_H + SEEK_U(0.003) + (n) * ROW_H)
#define NAV_Y       (SCR_Y + SCR_H - BAR_H - SEEK_U(0.002))

// Typo LCD : assez grande pour se lire à bout de bras, sans déborder du verre.
#define SEEK_FONT   (0.024 * SEEK_K)
#define SEEK_EDIT_EX (0.028 * SEEK_K)
#define BTN_H       SEEK_U(0.022)
#define FOOT_Y      (NAV_Y - BTN_H - SEEK_U(0.003))
#define SEC_Y       (FOOT_Y - BTN_H - SEEK_U(0.004))
#define MSG_H       SEEK_U(0.022)
#define MSG_Y       (SEC_Y - MSG_H - SEEK_U(0.003))
#define CASE_TOP    (SCR_Y + BAR_H + SEEK_U(0.004))
#define CASE_EDIT_H SEEK_U(0.030)
#define SIG_Y       (CASE_TOP + LBL_H + CASE_EDIT_H + SEEK_U(0.004))
#define SIG_H       SEEK_U(0.038)
#define TX_W        (IN_W * 0.62)
#define CANCEL_W    (IN_W - TX_W - SP)

// CONTEXTE : signes distinctifs jusqu’à la barre de navigation (plus de trou noir).
#define CTX_MARKS_Y (ROW(3) + LBL_H)
#define CTX_MARKS_H ((NAV_Y - SEEK_U(0.004)) - CTX_MARKS_Y)

// Accueil : deux rangées de trois tuiles.
#define TILE_W      ((IN_W - 2 * SP) / 3)
#define TILE_H      (TILE_W * 2 / 3)
#define TILE_X(c)   (IN_X + (c) * (TILE_W + SP))
#define TILE_Y(r)   (SCR_Y + BAR_H + SEEK_U(0.008) + (r) * (TILE_H + SEEK_U(0.007)))

#define QW          ((IN_W - 3 * SP) / 4)

// Page PHOTO : viseur portrait 3:4 à gauche (w = h × 9/16 en unités d'interface),
// réglages et cliché à droite.
#define VF_Y        ROW(0)
#define VF_H        ((NAV_Y - SEEK_U(0.004)) - VF_Y)
#define VF_W        (VF_H * 9 / 16)
#define PH_X        (IN_X + VF_W + 2 * SP)
#define PH_W        (IN_W - VF_W - 2 * SP)
#define PH_AW       ((PH_W - 2 * SP) / 2)
#define PH_ZW       ((PH_W - PH_AW - 2 * SP) / 2)

// Touches physiques de l'appareil (A1 / A2 / QUERY / SIGN), posées sur les vraies touches.
#define KEY_BOX(x0,y0,x1,y1) x = (SEEK_X + x0 * SEEK_W); y = (SEEK_Y + y0 * SEEK_H); w = ((x1 - x0) * SEEK_W); h = ((y1 - y0) * SEEK_H)

// Texte de l'écran SEEK : taille de base mise à l'échelle du cadrage.
class COMSPEC_SeekText: RscStructuredText {
    size = SEEK_TXT;
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
        // Légèrement rentré dans le cadre LCD pour ne plus déborder du verre.
        class Screen: RscText {
            idc = -1;
            x = SCR_X; y = SCR_Y; w = SCR_W; h = SCR_H;
            colorBackground[] = {0.055, 0.102, 0.098, 0.98};
        };
        // Verre LCD (dégradé, reflet, balayage) : la couleur unie ci-dessus reste si la PAA manque.
        class ScreenGlass: RscPicture {
            idc = -1;
            text = "\z\comspec_overwatch\addons\connect\img\device\seek_screen_bg_co.paa";
            x = SCR_X; y = SCR_Y; w = SCR_W; h = SCR_H;
            colorText[] = {1, 1, 1, 1};
        };
        class ScreenBar: RscText {
            idc = -1;
            x = SCR_X; y = SCR_Y; w = SCR_W; h = BAR_H;
            colorBackground[] = {0.09, 0.22, 0.21, 1};
        };
        class BtnHome: RscButton {
            idc = 9543;
            text = "Home";
            x = SCR_X; y = SCR_Y; w = (0.15 * SCR_W); h = BAR_H;
            colorBackground[] = {0, 0, 0, 0};
            colorBackgroundActive[] = {0.24, 0.87, 0.55, 0.35};
            colorText[] = {0.94, 0.99, 0.95, 1};
            colorFocused[] = {0, 0, 0, 0};
            sizeEx = (0.026 * SEEK_K);
            font = "PuristaMedium";
            action = "[0] call comspec_overwatch_connect_fnc_sseTerminalPage;";
        };
        class ScreenTitle: COMSPEC_SeekText {
            idc = 9540;
            text = "<t size='0.64' align='center' color='#f2fff8'>SEEK</t>";
            x = (SCR_X + 0.15 * SCR_W); y = (SCR_Y + SEEK_U(0.001));
            w = (0.28 * SCR_W); h = (BAR_H - SEEK_U(0.002));
        };
        class StatusRight: COMSPEC_SeekText {
            idc = 9526;
            text = "<t size='0.56' align='right' color='#d8f0e6'>--:--</t>";
            x = (SCR_X + 0.43 * SCR_W); y = (SCR_Y + SEEK_U(0.001));
            w = (0.56 * SCR_W); h = (BAR_H - SEEK_U(0.002));
        };

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
            colorBackgroundActive[] = {0.55, 1, 0.82, 0.14};
            colorBackgroundDisabled[] = {0, 0, 0, 0};
            colorFocused[] = {0, 0, 0, 0};
            colorShadow[] = {0, 0, 0, 0};
            colorBorder[] = {0, 0, 0, 0};
            colorText[] = {0.94, 0.99, 0.95, 1};
            offsetPressedX = 0;
            offsetPressedY = 0;
            sizeEx = SEEK_FONT;
            font = "PuristaMedium";
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
            text = "<t size='0.58' align='center' color='#b8ddd0'>Prêt.</t>";
            x = IN_X; y = (NAV_Y - SEEK_U(0.020)); w = IN_W; h = SEEK_U(0.019);
        };

        // ================= PAGE 1 — SUJET =================
        class LabelLast: COMSPEC_SeekText {
            idc = 9551;
            text = "<t size='0.68' color='#c8eadc'>NOM</t>";
            x = IN_X; y = ROW(0); w = HALF_W; h = LBL_H;
        };
        class EditLast: RscEdit {
            idc = 9501;
            x = IN_X; y = (ROW(0) + LBL_H); w = HALF_W; h = FIELD_H;
            colorBackground[] = {0.09, 0.20, 0.188, 1};
            colorText[] = {0.96, 0.99, 0.94, 1};
            colorSelection[] = {0.18, 0.48, 0.42, 0.7};
            colorDisabled[] = {0.62, 0.72, 0.68, 1};
            sizeEx = SEEK_EDIT_EX;
            font = "PuristaMedium";
            autocomplete = "";
        };
        class LabelFirst: LabelLast { idc = 9552; text = "<t size='0.68' color='#c8eadc'>PRENOM</t>"; x = IN_X2; };
        class EditFirst: EditLast { idc = 9502; x = IN_X2; };
        class LabelAlias: LabelLast { idc = 9553; text = "<t size='0.68' color='#c8eadc'>ALIAS</t>"; y = ROW(1); };
        class EditAlias: EditLast { idc = 9503; y = (ROW(1) + LBL_H); };
        class LabelAge: LabelLast { idc = 9554; text = "<t size='0.68' color='#c8eadc'>AGE</t>"; x = IN_X2; y = ROW(1); };
        class EditAge: EditLast { idc = 9504; x = IN_X2; y = (ROW(1) + LBL_H); };
        class LabelNat: LabelLast { idc = 9555; text = "<t size='0.68' color='#c8eadc'>NATIONALITE</t>"; y = ROW(2); };
        class EditNat: EditLast { idc = 9507; y = (ROW(2) + LBL_H); };
        class LabelLang: LabelLast { idc = 9556; text = "<t size='0.68' color='#c8eadc'>LANGUE</t>"; x = IN_X2; y = ROW(2); };
        class EditLang: EditLast { idc = 9508; x = IN_X2; y = (ROW(2) + LBL_H); };
        class TextWeapons: COMSPEC_SeekText {
            idc = 9511;
            text = "<t size='0.62' color='#c8eadc'>Aucun inventaire détecté.</t>";
            x = IN_X; y = ROW(3); w = IN_W; h = (2.2 * ROW_H);
        };

        // ================= PAGE 2 — CONTEXTE =================
        // Une ligne par champ, pleine largeur : listes et saisie lisibles, plus de trou sous le formulaire.
        class LabelStatus: LabelLast {
            idc = 9560;
            text = "<t size='0.68' color='#c8eadc'>STATUT</t>";
            w = IN_W;
        };
        class ComboStatus: RscCombo {
            idc = 9505;
            x = IN_X; y = (ROW(0) + LBL_H); w = IN_W; h = FIELD_H;
            colorBackground[] = {0.09, 0.20, 0.188, 1};
            colorText[] = {0.96, 0.99, 0.94, 1};
            colorSelect[] = {1, 1, 1, 1};
            colorSelectBackground[] = {0.14, 0.42, 0.36, 1};
            colorDisabled[] = {0.62, 0.72, 0.68, 1};
            sizeEx = SEEK_EDIT_EX;
            font = "PuristaMedium";
            wholeHeight = (0.24 * SEEK_K);
        };
        class LabelCirc: LabelLast {
            idc = 9561;
            text = "<t size='0.68' color='#c8eadc'>CIRCONSTANCES</t>";
            y = ROW(1);
            w = IN_W;
        };
        class ComboCirc: ComboStatus { idc = 9506; y = (ROW(1) + LBL_H); };
        class LabelAffil: LabelLast {
            idc = 9562;
            text = "<t size='0.68' color='#c8eadc'>AFFILIATION ESTIMEE</t>";
            y = ROW(2);
            w = IN_W;
        };
        class EditAffil: EditLast { idc = 9510; y = (ROW(2) + LBL_H); w = IN_W; };
        class LabelMarks: LabelLast {
            idc = 9563;
            text = "<t size='0.68' color='#c8eadc'>SIGNES DISTINCTIFS</t>";
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

        // ================= PAGE 3 — BIOMETRIE =================
        class Platen: RscText {
            idc = 9566;
            x = IN_X; y = ROW(0); w = IN_W; h = SEEK_U(0.013);
            colorBackground[] = {0.176, 0.784, 0.290, 0.8};
        };
        class BtnBio: COMSPEC_RscButton {
            idc = 9514;
            text = "EMPR.";
            x = IN_X; y = (ROW(1) - SEEK_U(0.003)); w = QW; h = SEEK_U(0.024);
            sizeEx = SEEK_FONT;
            action = "['empreintes'] call comspec_overwatch_connect_fnc_sseBiometricSample;";
        };
        class BtnIris: BtnBio { idc = 9523; text = "IRIS"; x = (IN_X + QW + SP); action = "['iris'] call comspec_overwatch_connect_fnc_sseBiometricSample;"; };
        class BtnDna: BtnBio { idc = 9524; text = "ADN"; x = (IN_X + 2 * (QW + SP)); action = "['adn'] call comspec_overwatch_connect_fnc_sseBiometricSample;"; };
        class BtnQuery: COMSPEC_RscButtonAccent {
            idc = 9527;
            text = "QUERY";
            x = (IN_X + 3 * (QW + SP)); y = (ROW(1) - SEEK_U(0.003));
            w = QW; h = SEEK_U(0.024);
            sizeEx = SEEK_FONT;
            action = "[] call comspec_overwatch_connect_fnc_sseIdentityQuery;";
        };
        class TextBiometrics: COMSPEC_SeekText {
            idc = 9522;
            text = "<t size='0.62' color='#c8eadc'>Aucun prélèvement.</t>";
            x = IN_X; y = ROW(2); w = IN_W; h = (3.2 * ROW_H);
        };

        // ================= PAGE 4 — CONSTAT =================
        class TextMedical: COMSPEC_SeekText {
            idc = 9521;
            text = "<t size='0.62' color='#c8eadc'>Aucun constat.</t>";
            x = IN_X; y = ROW(0); w = IN_W; h = (2.4 * ROW_H);
        };
        class LabelStmt: LabelLast { idc = 9564; text = "<t size='0.68' color='#c8eadc'>DECLARATIONS</t>"; y = ROW(3); };
        class EditStmt: EditLast { idc = 9512; y = (ROW(3) + LBL_H); w = IN_W; h = (1.4 * ROW_H); };

        // ================= PAGE 5 — PHOTO =================
        // Viseur : caméra rendue en direct (r2t) sur la tête du sujet, gabarit de visage
        // par-dessus. Sans sujet ou caméra : silhouette. Après le cliché, l'image reste figée.
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
            text = "<t size='0.56' color='#c8eadc'>Aucun sujet.</t>";
            x = PH_X; y = VF_Y; w = PH_W; h = SEEK_U(0.082);
        };
        class BtnFaceAngle: COMSPEC_RscButton {
            idc = 9578;
            text = "FACE";
            x = PH_X; y = (VF_Y + SEEK_U(0.086)); w = PH_AW; h = BTN_H;
            sizeEx = SEEK_FONT;
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
        class BtnPhoto: COMSPEC_RscButtonAccent {
            idc = 9515;
            text = "PRENDRE LE CLICHÉ";
            x = PH_X; y = (VF_Y + SEEK_U(0.112)); w = PH_W; h = SEEK_U(0.030);
            sizeEx = SEEK_FONT;
            action = "[] call comspec_overwatch_connect_fnc_sseCaptureFacePhoto;";
        };
        class LcdFrame: RscText {
            idc = 9567;
            x = PH_X; y = (NAV_Y - SEEK_U(0.026)); w = PH_W; h = SEEK_U(0.020);
            colorBackground[] = {0.08, 0.20, 0.18, 1};
        };
        class TextLcd: COMSPEC_SeekText {
            idc = 9525;
            text = "<t size='0.52' color='#b8e8c8' align='center'>PRET</t>";
            x = PH_X; y = (NAV_Y - SEEK_U(0.025)); w = PH_W; h = SEEK_U(0.018);
        };

        // ================= PAGE 6 — DOSSIER =================
        // Haut : code + état de signature. Bas : actions collées à la navigation.
        class LabelCase: LabelLast {
            idc = 9565;
            text = "<t size='0.68' color='#c8eadc'>CODE DOSSIER</t>";
            x = IN_X; y = CASE_TOP; w = IN_W; h = LBL_H;
        };
        class EditCase: EditLast {
            idc = 9518;
            x = IN_X; y = (CASE_TOP + LBL_H); w = IN_W; h = CASE_EDIT_H;
            colorBackground[] = {0.09, 0.20, 0.188, 1};
            colorText[] = {0.86, 0.98, 0.88, 1};
            sizeEx = (0.030 * SEEK_K);
        };
        class SigCard: RscText {
            idc = 9568;
            x = IN_X; y = SIG_Y; w = IN_W; h = SIG_H;
            colorBackground[] = {0.08, 0.18, 0.17, 1};
        };
        class TextSignature: COMSPEC_SeekText {
            idc = 9520;
            text = "<t size='0.64' color='#f0c070'>Non signé</t>";
            x = (IN_X + 0.006 * SEEK_W); y = (SIG_Y + SEEK_U(0.003));
            w = (IN_W - 0.012 * SEEK_W); h = (SIG_H - SEEK_U(0.005));
        };
        class StatusText: COMSPEC_SeekText {
            idc = 9513;
            text = "";
            x = IN_X; y = MSG_Y; w = IN_W; h = MSG_H;
        };
        class BtnCaseActive: COMSPEC_RscButton {
            idc = 9544;
            text = "DOSSIER ACTIF";
            x = IN_X; y = SEC_Y; w = HALF_W; h = BTN_H;
            sizeEx = SEEK_FONT;
            tooltip = "Rend ce dossier actif pour l’élément — les fiches suivantes y seront classées.";
            action = "private _d = uiNamespace getVariable ['COMSPEC_SsePerson_Display', displayNull]; ['set', ctrlText (_d displayCtrl 9518)] call comspec_overwatch_connect_fnc_sseActiveCase; [] call comspec_overwatch_connect_fnc_ssePersonRefreshPanels;";
        };
        class BtnSign: COMSPEC_RscButton {
            idc = 9519;
            text = "SIGNER";
            x = IN_X2; y = SEC_Y; w = HALF_W; h = BTN_H;
            sizeEx = SEEK_FONT;
            tooltip = "Signer la fiche avec l’indicatif ATAK de l’opérateur.";
            action = "[] call comspec_overwatch_connect_fnc_sseSignAtak;";
        };
        class BtnSave: COMSPEC_RscButtonAccent {
            idc = 9516;
            text = "TRANSMETTRE";
            x = IN_X; y = FOOT_Y; w = TX_W; h = BTN_H;
            sizeEx = SEEK_FONT;
            action = "[] call comspec_overwatch_connect_fnc_ssePersonDialogSubmit;";
        };
        class BtnClose: COMSPEC_RscButtonDanger {
            idc = 9517;
            text = "ANNULER";
            x = (IN_X + TX_W + SP); y = FOOT_Y; w = CANCEL_W; h = BTN_H;
            sizeEx = SEEK_FONT;
            action = "private _d = uiNamespace getVariable ['COMSPEC_SsePerson_Display', displayNull]; if (!isNull _d) then { _d closeDisplay 2; } else { closeDialog 0; };";
        };

        // ================= PAGE 7 — TERRAIN (ancien hub vert) =================
        class TerrainBody: COMSPEC_SeekText {
            idc = 9580;
            text = "<t size='0.55' color='#b8ddd0'>Record terrain.</t>";
            x = IN_X; y = ROW(0); w = IN_W; h = (1.85 * ROW_H);
        };
        class TerrainList: RscListBox {
            idc = 9581;
            x = IN_X; y = ROW(2); w = IN_W; h = (1.7 * ROW_H);
            colorBackground[] = {0.06, 0.14, 0.13, 0.92};
            colorText[] = {0.96, 0.99, 0.94, 1};
            sizeEx = (0.024 * SEEK_K);
        };
        class TerrainDetail: COMSPEC_SeekText {
            idc = 9582;
            text = "";
            x = IN_X; y = (NAV_Y - SEEK_U(0.001)); w = IN_W; h = 0.001;
        };
        class TerrainDig: COMSPEC_RscButton {
            idc = 9590;
            text = "DIG";
            x = IN_X; y = ROW(4); w = ((IN_W - 4 * SP) / 5); h = SEEK_U(0.022);
            sizeEx = SEEK_FONT;
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

        // ================= NAVIGATION =================
        class BtnPrev: RscButton {
            idc = 9541;
            text = "<<";
            x = IN_X; y = NAV_Y; w = (0.20 * SCR_W); h = BAR_H;
            colorBackground[] = {0.08, 0.20, 0.19, 0.95};
            colorBackgroundActive[] = {0.12, 0.42, 0.36, 0.95};
            colorText[] = {0.94, 0.99, 0.95, 1};
            colorFocused[] = {0.08, 0.20, 0.19, 0.95};
            sizeEx = (0.026 * SEEK_K);
            font = "PuristaMedium";
            action = "[-1, true] call comspec_overwatch_connect_fnc_sseTerminalPage;";
        };
        class BtnNext: BtnPrev {
            idc = 9542;
            text = ">>";
            x = (SCR_X + SCR_W - SP - (0.20 * SCR_W));
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
            tooltip = "Requête d’identité";
            action = "[] call comspec_overwatch_connect_fnc_sseIdentityQuery;";
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
