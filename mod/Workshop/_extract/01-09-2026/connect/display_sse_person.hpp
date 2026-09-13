// Terminal biométrique SEEK — enrôlement personne (idd 9991)
//
// L'illustration de l'appareil est la vraie surface : les contrôles sont posés
// DANS l'écran du SEEK, pas sur toute la hauteur du châssis. L'écran ne tenant
// qu'une dizaine de lignes, le terminal fonctionne en PAGES, avec un accueil à
// tuiles — comme l'appareil réel.
//
// Géométrie mesurée sur seek_chassis.paa (2048 × 2048) :
//   appareil        x 0.117 – 0.883   y 0.031 – 0.969
//   écran (verre)   x 0.305 – 0.695   y 0.078 – 0.323   (rentré vs cadre plastique)
//   softkeys        x 0.305 – 0.695   y 0.342 – 0.372   (sous l’écran, hors clavier)
//   platine verte   x 0.398 – 0.594   y 0.703 – 0.867
//   clavier         x 0.211 – 0.781   y 0.516 – 0.609
// Si la texture change, ce sont les seules valeurs à reprendre.
//
// IDC :
//   9500 bandeau d'état bas            9513 état transmission
//   9501 nom      9502 prénom          9514 empreintes  9523 iris  9524 ADN
//   9503 alias    9504 âge             9515 photo visage
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
//   9570-9573 softkeys A1 / A2 / QUERY / SIGN (sous l’écran)
//   9580-9582 page terrain (record / listes)  9590-9594 DIG SITE GRAPH PREV MISS
//   Tout libellé doit porter un IDC : sans cela il ne peut pas être masqué et se
//   superpose d'une page à l'autre.

#define SEEK_X      (0.2125 * safezoneW + safezoneX)
#define SEEK_W      (0.5750 * safezoneW)
#define SEEK_Y      (0.0400 * safezoneH + safezoneY)
#define SEEK_H      (0.9200 * safezoneH)

// Illustration de l'appareil. Vide = châssis dessiné seul : le terminal reste
// utilisable sans aucun asset.
#define SEEK_CHASSIS_TEXTURE "\z\comspec_overwatch\addons\connect\img\device\seek_chassis.paa"

// --- Écran, en fractions de l'illustration (rentré dans le verre LCD) ---
#define SCR_X       (SEEK_X + 0.305 * SEEK_W)
#define SCR_Y       (SEEK_Y + 0.078 * SEEK_H)
#define SCR_W       (0.390 * SEEK_W)
#define SCR_H       (0.245 * SEEK_H)

// Grille interne : barre d'état en haut, navigation en bas, lignes utiles entre.
#define SP          (0.004 * SEEK_W)
#define IN_X        (SCR_X + SP)
#define IN_W        (SCR_W - 2 * SP)
#define HALF_W      ((IN_W - SP) / 2)
#define IN_X2       (IN_X + HALF_W + SP)

#define BAR_H       (0.0210 * safezoneH)
#define ROW_H       (0.0315 * safezoneH)
#define LBL_H       (0.0158 * safezoneH)
#define FIELD_H     (ROW_H - LBL_H)
#define ROW(n)      (SCR_Y + BAR_H + (0.003 * safezoneH) + (n) * ROW_H)
#define NAV_Y       (SCR_Y + SCR_H - BAR_H - (0.002 * safezoneH))

// Typo LCD : assez grande pour se lire à bout de bras, sans déborder du verre.
#define SEEK_FONT   0.024
#define SEEK_EDIT_EX 0.028
#define BTN_H       (0.022 * safezoneH)
#define FOOT_Y      (NAV_Y - BTN_H - (0.003 * safezoneH))
#define SEC_Y       (FOOT_Y - BTN_H - (0.004 * safezoneH))
#define MSG_H       (0.022 * safezoneH)
#define MSG_Y       (SEC_Y - MSG_H - (0.003 * safezoneH))
#define CASE_TOP    (SCR_Y + BAR_H + (0.004 * safezoneH))
#define CASE_EDIT_H (0.030 * safezoneH)
#define SIG_Y       (CASE_TOP + LBL_H + CASE_EDIT_H + (0.004 * safezoneH))
#define SIG_H       (0.038 * safezoneH)
#define TX_W        (IN_W * 0.62)
#define CANCEL_W    (IN_W - TX_W - SP)

// CONTEXTE : signes distinctifs jusqu’à la barre de navigation (plus de trou noir).
#define CTX_MARKS_Y (ROW(3) + LBL_H)
#define CTX_MARKS_H ((NAV_Y - (0.004 * safezoneH)) - CTX_MARKS_Y)

// Accueil : deux rangées de trois tuiles.
#define TILE_W      ((IN_W - 2 * SP) / 3)
#define TILE_H      (0.0540 * safezoneH)
#define TILE_X(c)   (IN_X + (c) * (TILE_W + SP))
#define TILE_Y(r)   (SCR_Y + BAR_H + (0.008 * safezoneH) + (r) * (TILE_H + (0.007 * safezoneH)))

#define QW          ((IN_W - 3 * SP) / 4)

// Softkeys physiques sous l’écran (A1 / A2 / QUERY / SIGN) — hors clavier QWERTY.
#define SOFT_Y      (SEEK_Y + 0.348 * SEEK_H)
#define SOFT_H      (0.026 * SEEK_H)
#define SOFT_W      (0.088 * SEEK_W)
#define SOFT_GAP    (0.014 * SEEK_W)
#define SOFT_X(i)   (SEEK_X + 0.305 * SEEK_W + (i) * (SOFT_W + SOFT_GAP))

class COMSPEC_SsePerson_Dialog {
    idd = 9991;
    movingEnable = 1;
    onLoad = "uiNamespace setVariable ['COMSPEC_SsePerson_Display', _this select 0]; [] call comspec_overwatch_connect_fnc_ssePersonDialogOnLoad;";
    onUnload = "uiNamespace setVariable ['COMSPEC_SsePerson_Display', displayNull]; if (uiNamespace getVariable ['COMSPEC_SsePerson_SuspendUnload', false]) then { uiNamespace setVariable ['COMSPEC_SsePerson_SuspendUnload', false]; } else { uiNamespace setVariable ['COMSPEC_SsePerson_Target', objNull]; uiNamespace setVariable ['COMSPEC_SsePerson_Samples', []]; uiNamespace setVariable ['COMSPEC_SsePerson_Signature', []]; uiNamespace setVariable ['COMSPEC_SsePerson_Query', []]; uiNamespace setVariable ['COMSPEC_SsePerson_IdentityCache', []]; };";

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
            sizeEx = 0.026;
            font = "PuristaMedium";
            action = "[0] call comspec_overwatch_connect_fnc_sseTerminalPage;";
        };
        class ScreenTitle: RscStructuredText {
            idc = 9540;
            text = "<t size='0.64' align='center' color='#f2fff8'>SEEK</t>";
            x = (SCR_X + 0.15 * SCR_W); y = (SCR_Y + 0.001 * safezoneH);
            w = (0.28 * SCR_W); h = (BAR_H - 0.002 * safezoneH);
        };
        class StatusRight: RscStructuredText {
            idc = 9526;
            text = "<t size='0.56' align='right' color='#d8f0e6'>--:--</t>";
            x = (SCR_X + 0.43 * SCR_W); y = (SCR_Y + 0.001 * safezoneH);
            w = (0.56 * SCR_W); h = (BAR_H - 0.002 * safezoneH);
        };

        // ================= PAGE 0 — ACCUEIL =================
        class Tile0: RscButton {
            idc = 9530;
            text = "SUJET";
            x = TILE_X(0); y = TILE_Y(0); w = TILE_W; h = TILE_H;
            colorBackground[] = {0.08, 0.20, 0.19, 0.96};
            colorBackgroundActive[] = {0.12, 0.42, 0.36, 0.95};
            colorText[] = {0.94, 0.99, 0.95, 1};
            sizeEx = SEEK_FONT;
            font = "PuristaMedium";
            tooltip = "Identité de la personne";
            action = "[1] call comspec_overwatch_connect_fnc_sseTerminalPage;";
        };
        class Tile1: Tile0 { idc = 9531; text = "CONTEXTE"; x = TILE_X(1); tooltip = "Statut, circonstances, affiliation"; action = "[2] call comspec_overwatch_connect_fnc_sseTerminalPage;"; };
        class Tile2: Tile0 { idc = 9532; text = "BIOMETRIE"; x = TILE_X(2); tooltip = "Empreintes, iris, ADN, requête d’identité"; action = "[3] call comspec_overwatch_connect_fnc_sseTerminalPage;"; };
        class Tile3: Tile0 { idc = 9533; text = "TERRAIN"; y = TILE_Y(1); tooltip = "Record lié, digital, site, graphe, preuves, mission"; action = "missionNamespace setVariable ['comspec_sse_uiScreen', 'terminal']; [7] call comspec_overwatch_connect_fnc_sseTerminalPage; ['terminal'] call comspec_sse_fnc_uiRefresh;"; };
        class Tile4: Tile0 { idc = 9534; text = "PHOTO"; x = TILE_X(1); y = TILE_Y(1); tooltip = "Photographie du visage"; action = "[5] call comspec_overwatch_connect_fnc_sseTerminalPage;"; };
        class Tile5: Tile0 { idc = 9535; text = "DOSSIER"; x = TILE_X(2); y = TILE_Y(1); tooltip = "Classement, signature, transmission"; action = "[6] call comspec_overwatch_connect_fnc_sseTerminalPage;"; };

        class Hint: RscStructuredText {
            idc = 9500;
            text = "<t size='0.58' align='center' color='#b8ddd0'>Prêt.</t>";
            x = IN_X; y = (NAV_Y - 0.020 * safezoneH); w = IN_W; h = (0.019 * safezoneH);
        };

        // ================= PAGE 1 — SUJET =================
        class LabelLast: RscStructuredText {
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
        class TextWeapons: RscStructuredText {
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
            wholeHeight = 0.24;
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
            x = IN_X; y = ROW(0); w = IN_W; h = (0.013 * safezoneH);
            colorBackground[] = {0.176, 0.784, 0.290, 0.8};
        };
        class BtnBio: COMSPEC_RscButton {
            idc = 9514;
            text = "EMPR.";
            x = IN_X; y = (ROW(1) - 0.003 * safezoneH); w = QW; h = (0.024 * safezoneH);
            sizeEx = SEEK_FONT;
            action = "['empreintes'] call comspec_overwatch_connect_fnc_sseBiometricSample;";
        };
        class BtnIris: BtnBio { idc = 9523; text = "IRIS"; x = (IN_X + QW + SP); action = "['iris'] call comspec_overwatch_connect_fnc_sseBiometricSample;"; };
        class BtnDna: BtnBio { idc = 9524; text = "ADN"; x = (IN_X + 2 * (QW + SP)); action = "['adn'] call comspec_overwatch_connect_fnc_sseBiometricSample;"; };
        class BtnQuery: COMSPEC_RscButtonAccent {
            idc = 9527;
            text = "QUERY";
            x = (IN_X + 3 * (QW + SP)); y = (ROW(1) - 0.003 * safezoneH);
            w = QW; h = (0.024 * safezoneH);
            sizeEx = SEEK_FONT;
            action = "[] call comspec_overwatch_connect_fnc_sseIdentityQuery;";
        };
        class TextBiometrics: RscStructuredText {
            idc = 9522;
            text = "<t size='0.62' color='#c8eadc'>Aucun prélèvement.</t>";
            x = IN_X; y = ROW(2); w = IN_W; h = (3.2 * ROW_H);
        };

        // ================= PAGE 4 — CONSTAT =================
        class TextMedical: RscStructuredText {
            idc = 9521;
            text = "<t size='0.62' color='#c8eadc'>Aucun constat.</t>";
            x = IN_X; y = ROW(0); w = IN_W; h = (2.4 * ROW_H);
        };
        class LabelStmt: LabelLast { idc = 9564; text = "<t size='0.68' color='#c8eadc'>DECLARATIONS</t>"; y = ROW(3); };
        class EditStmt: EditLast { idc = 9512; y = (ROW(3) + LBL_H); w = IN_W; h = (1.4 * ROW_H); };

        // ================= PAGE 5 — PHOTO =================
        class BtnPhoto: COMSPEC_RscButton {
            idc = 9515;
            text = "PHOTO DU VISAGE";
            x = IN_X; y = ROW(1); w = IN_W; h = (0.028 * safezoneH);
            sizeEx = SEEK_FONT;
            action = "[] call comspec_overwatch_connect_fnc_sseCaptureFacePhoto;";
        };
        class LcdFrame: RscText {
            idc = 9567;
            x = IN_X; y = ROW(3); w = IN_W; h = (0.018 * safezoneH);
            colorBackground[] = {0.08, 0.20, 0.18, 1};
        };
        class TextLcd: RscStructuredText {
            idc = 9525;
            text = "<t size='0.52' color='#b8e8c8' align='center'>PRET</t>";
            x = IN_X; y = (ROW(3) + 0.002 * safezoneH); w = IN_W; h = (0.015 * safezoneH);
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
            sizeEx = 0.030;
        };
        class SigCard: RscText {
            idc = 9568;
            x = IN_X; y = SIG_Y; w = IN_W; h = SIG_H;
            colorBackground[] = {0.08, 0.18, 0.17, 1};
        };
        class TextSignature: RscStructuredText {
            idc = 9520;
            text = "<t size='0.64' color='#f0c070'>Non signé</t>";
            x = (IN_X + 0.006 * SEEK_W); y = (SIG_Y + 0.003 * safezoneH);
            w = (IN_W - 0.012 * SEEK_W); h = (SIG_H - 0.005 * safezoneH);
        };
        class StatusText: RscStructuredText {
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
        class TerrainBody: RscStructuredText {
            idc = 9580;
            text = "<t size='0.55' color='#b8ddd0'>Record terrain.</t>";
            x = IN_X; y = ROW(0); w = IN_W; h = (1.85 * ROW_H);
        };
        class TerrainList: RscListBox {
            idc = 9581;
            x = IN_X; y = ROW(2); w = IN_W; h = (1.7 * ROW_H);
            colorBackground[] = {0.06, 0.14, 0.13, 0.92};
            colorText[] = {0.96, 0.99, 0.94, 1};
            sizeEx = 0.024;
        };
        class TerrainDetail: RscStructuredText {
            idc = 9582;
            text = "";
            x = IN_X; y = (NAV_Y - 0.001 * safezoneH); w = IN_W; h = 0.001;
        };
        class TerrainDig: COMSPEC_RscButton {
            idc = 9590;
            text = "DIG";
            x = IN_X; y = ROW(4); w = ((IN_W - 4 * SP) / 5); h = (0.022 * safezoneH);
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
            sizeEx = 0.026;
            font = "PuristaMedium";
            action = "[-1, true] call comspec_overwatch_connect_fnc_sseTerminalPage;";
        };
        class BtnNext: BtnPrev {
            idc = 9542;
            text = ">>";
            x = (SCR_X + SCR_W - SP - (0.20 * SCR_W));
            action = "[1, true] call comspec_overwatch_connect_fnc_sseTerminalPage;";
        };

        // ================= SOFTKEYS (sous l’écran, hors clavier) =================
        // Alignées sous l’LCD — plus sur la rangée QWERTY (y 0.516–0.609).
        class KeyA1: COMSPEC_RscButton {
            idc = 9570;
            text = "A1";
            x = SOFT_X(0); y = SOFT_Y; w = SOFT_W; h = SOFT_H;
            sizeEx = SEEK_FONT;
            colorBackground[] = {0.04, 0.09, 0.12, 0.72};
            colorBackgroundActive[] = {0.10, 0.28, 0.30, 0.95};
            colorFocused[] = {0.10, 0.28, 0.30, 0.95};
            tooltip = "Relevé d’empreintes";
            action = "['empreintes'] call comspec_overwatch_connect_fnc_sseBiometricSample;";
        };
        class KeyA2: KeyA1 {
            idc = 9571;
            text = "A2";
            x = SOFT_X(1);
            tooltip = "Relevé iris";
            action = "['iris'] call comspec_overwatch_connect_fnc_sseBiometricSample;";
        };
        class KeyQuery: KeyA1 {
            idc = 9572;
            text = "QUERY";
            x = SOFT_X(2);
            tooltip = "Requête d’identité";
            action = "[] call comspec_overwatch_connect_fnc_sseIdentityQuery;";
        };
        class KeySign: KeyA1 {
            idc = 9573;
            text = "SIGN";
            x = SOFT_X(3);
            tooltip = "Signer par l’ATAK";
            action = "[] call comspec_overwatch_connect_fnc_sseSignAtak;";
        };
    };
};
